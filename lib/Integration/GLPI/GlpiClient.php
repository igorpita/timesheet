<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Integration\GLPI;

use OCP\Http\Client\IClientService;
use OCP\IConfig;

class GlpiClient {
    private string $baseUrl;
    private string $appToken;
    private string $userToken;

    public function __construct(
        private IClientService $clientService,
        private IConfig $config
    ) {
        $this->baseUrl = rtrim($this->config->getAppValue('oab_timesheet', 'glpi_url', 'http://glpi.local/apirest.php'), '/');
        $this->appToken = $this->config->getAppValue('oab_timesheet', 'glpi_app_token', '');
        $this->userToken = $this->config->getAppValue('oab_timesheet', 'glpi_user_token', '');
    }

    public function getConfig(): array {
        return [
            'baseUrl' => $this->baseUrl,
            'hasAppToken' => !empty($this->appToken),
            'hasUserToken' => !empty($this->userToken),
            'isConfigured' => !empty($this->appToken) && !empty($this->userToken),
        ];
    }

    public function saveConfig(string $baseUrl, ?string $appToken, ?string $userToken): void {
        $cleanUrl = rtrim(trim($baseUrl), '/');
        $this->config->setAppValue('oab_timesheet', 'glpi_url', $cleanUrl);
        $this->baseUrl = $cleanUrl;

        if ($appToken !== null && trim($appToken) !== '' && !str_contains($appToken, '••••')) {
            $this->config->setAppValue('oab_timesheet', 'glpi_app_token', trim($appToken));
            $this->appToken = trim($appToken);
        }

        if ($userToken !== null && trim($userToken) !== '' && !str_contains($userToken, '••••')) {
            $this->config->setAppValue('oab_timesheet', 'glpi_user_token', trim($userToken));
            $this->userToken = trim($userToken);
        }
    }

    public function testConnection(?string $baseUrl = null, ?string $appToken = null, ?string $userToken = null): array {
        $url = rtrim($baseUrl ?: $this->baseUrl, '/');
        $aToken = ($appToken !== null && !str_contains($appToken, '••••') && trim($appToken) !== '') ? trim($appToken) : $this->appToken;
        $uToken = ($userToken !== null && !str_contains($userToken, '••••') && trim($userToken) !== '') ? trim($userToken) : $this->userToken;

        if (empty($url) || empty($aToken) || empty($uToken)) {
            return [
                'success' => false,
                'error' => 'URL, App-Token e User-Token são obrigatórios para validar a conexão.'
            ];
        }

        try {
            $client = $this->clientService->newClient();
            $response = $client->get($url . '/initSession', [
                'headers' => [
                    'App-Token' => $aToken,
                    'Authorization' => 'user_token ' . $uToken,
                ],
                'timeout' => 8,
            ]);

            $body = json_decode($response->getBody(), true);
            if (isset($body['session_token'])) {
                try {
                    $client->get($url . '/killSession', [
                        'headers' => [
                            'App-Token' => $aToken,
                            'Session-Token' => $body['session_token']
                        ],
                        'timeout' => 5
                    ]);
                } catch (\Throwable) {}

                return [
                    'success' => true,
                    'message' => 'Conexão com o GLPI 12 realizada com sucesso! Token de sessão autenticado.'
                ];
            }

            return [
                'success' => false,
                'error' => 'Resposta do GLPI sem token de sessão válido: ' . $response->getBody()
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Falha na conexão com o GLPI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pesquisa chamados abertos no GLPI 12.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchTickets(string $query = '', ?string $technicianUid = null): array {
        if (empty($this->appToken)) {
            // Em ambiente sem credenciais configuradas, retorna dados simulados/homologação
            return $this->getMockTickets($query, $technicianUid);
        }

        try {
            $client = $this->clientService->newClient();
            $sessionToken = $this->initSession();

            $url = $this->baseUrl . '/Ticket';
            $params = [
                'is_deleted' => 0,
                'range' => '0-30',
                'sort' => 'date_mod',
                'order' => 'DESC'
            ];

            if (!empty($query)) {
                $params['searchText[name]'] = $query;
            }

            $response = $client->get($url, [
                'headers' => [
                    'App-Token' => $this->appToken,
                    'Session-Token' => $sessionToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => $params,
                'timeout' => 10,
            ]);

            $data = json_decode($response->getBody(), true) ?? [];
            return array_map(function ($ticket) {
                return [
                    'id' => (string)($ticket['id'] ?? ''),
                    'title' => $ticket['name'] ?? 'Sem título',
                    'requester' => 'Solicitante GLPI',
                    'status' => $this->mapGlpiStatus((int)($ticket['status'] ?? 1)),
                    'category' => 'Suporte / TI',
                    'date' => substr($ticket['date'] ?? date('Y-m-d'), 0, 10),
                ];
            }, $data);
        } catch (\Throwable $e) {
            return $this->getMockTickets($query, $technicianUid);
        }
    }

    /**
     * Cria uma tarefa (TicketTask) associada a um chamado no GLPI, com a duração em segundos.
     */
    public function createTicketTask(string $ticketId, int $durationMinutes, string $content, string $workDate, ?string $userUid = null): array {
        if (empty($this->appToken)) {
            // Mock de sucesso em ambiente sem servidor GLPI ativo
            return [
                'success' => true,
                'glpi_task_id' => 'mock-task-' . time(),
                'message' => 'Simulação de tarefa criada com sucesso no GLPI'
            ];
        }

        $client = $this->clientService->newClient();
        $sessionToken = $this->initSession();

        $url = $this->baseUrl . '/Ticket/' . $ticketId . '/TicketTask';
        $payload = [
            'input' => [
                'tickets_id' => (int)$ticketId,
                'actiontime' => $durationMinutes * 60, // GLPI armazena duração em segundos
                'content' => $content,
                'date' => $workDate . ' 12:00:00',
                'state' => 2, // Concluída
            ]
        ];

        $response = $client->post($url, [
            'headers' => [
                'App-Token' => $this->appToken,
                'Session-Token' => $sessionToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 15,
        ]);

        $body = json_decode($response->getBody(), true);
        return [
            'success' => true,
            'glpi_task_id' => (string)($body['id'] ?? ''),
            'data' => $body
        ];
    }

    private function initSession(): string {
        $client = $this->clientService->newClient();
        $response = $client->get($this->baseUrl . '/initSession', [
            'headers' => [
                'App-Token' => $this->appToken,
                'Authorization' => 'user_token ' . $this->userToken,
            ],
            'timeout' => 10,
        ]);

        $body = json_decode($response->getBody(), true);
        return $body['session_token'] ?? '';
    }

    private function mapGlpiStatus(int $status): string {
        return match ($status) {
            1 => 'Novo',
            2 => 'Em atendimento (atribuído)',
            3 => 'Em atendimento (planejado)',
            4 => 'Pendente',
            5 => 'Solucionado',
            6 => 'Fechado',
            default => 'Status ' . $status,
        };
    }

    private function getMockTickets(string $query, ?string $technicianUid): array {
        $all = [
            [
                'id' => '12345',
                'title' => 'Falha de comunicação com servidor de arquivos',
                'requester' => 'Maria Silva (Protocolo)',
                'technician' => $technicianUid ?: 'igor.pita',
                'status' => 'Em atendimento',
                'category' => 'Infraestrutura / Redes',
                'date' => date('Y-m-d')
            ],
            [
                'id' => '12350',
                'title' => 'Configuração de certificado digital token A3',
                'requester' => 'Carlos Souza (Presidência)',
                'technician' => $technicianUid ?: 'igor.pita',
                'status' => 'Pendente',
                'category' => 'Suporte / Certificação',
                'date' => date('Y-m-d')
            ],
            [
                'id' => '12362',
                'title' => 'Criação de fila de atendimento no n8n',
                'requester' => 'Coordenação TI',
                'technician' => $technicianUid ?: 'igor.pita',
                'status' => 'Atribuído',
                'category' => 'Desenvolvimento / Automação',
                'date' => date('Y-m-d')
            ],
            [
                'id' => '12377',
                'title' => 'Migração de banco de homologação PostgreSQL',
                'requester' => 'Igor Pita (TI)',
                'technician' => $technicianUid ?: 'igor.pita',
                'status' => 'Em atendimento',
                'category' => 'Infraestrutura / Banco de Dados',
                'date' => date('Y-m-d')
            ]
        ];

        if (!empty($query)) {
            return array_values(array_filter($all, function ($t) use ($query) {
                return stripos($t['title'], $query) !== false ||
                       stripos($t['id'], $query) !== false ||
                       stripos($t['requester'], $query) !== false;
            }));
        }

        return $all;
    }
}

