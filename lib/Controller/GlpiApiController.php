<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Controller;

use OCA\OABTimesheet\AppInfo\Application;
use OCA\OABTimesheet\Db\SyncQueueMapper;
use OCA\OABTimesheet\Integration\GLPI\GlpiClient;
use OCA\OABTimesheet\Service\SecurityService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class GlpiApiController extends ApiController {
    public function __construct(
        IRequest $request,
        private SecurityService $securityService,
        private SyncQueueMapper $syncMapper,
        private GlpiClient $glpiClient
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function searchTickets(): DataResponse {
        $q = trim($this->request->getParam('q', ''));
        $userUid = $this->securityService->getCurrentUserUid();

        try {
            $tickets = $this->glpiClient->searchTickets($q, $userUid);
            return new DataResponse([
                'success' => true,
                'query' => $q,
                'data' => $tickets
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function ticketDetails(string $ticketId): DataResponse {
        return new DataResponse([
            'success' => true,
            'data' => [
                'id' => $ticketId,
                'title' => "Chamado #$ticketId - Operação TI",
                'status' => 'Em atendimento',
            ]
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function syncStatus(): DataResponse {
        $pending = $this->syncMapper->findPending(20);
        return new DataResponse([
            'success' => true,
            'pendingCount' => count($pending),
            'items' => $pending,
        ]);
    }

    /**
     * Retorna o estado atual da configuração do GLPI (apenas para gestores / admins).
     */
    public function getConfig(): DataResponse {
        if (!$this->securityService->isManager()) {
            return new DataResponse(['success' => false, 'error' => 'Acesso negado: apenas gestores podem ver configurações.'], 403);
        }

        return new DataResponse([
            'success' => true,
            'data' => $this->glpiClient->getConfig()
        ]);
    }

    /**
     * Salva as configurações de conexão com a API REST do GLPI 12.
     */
    public function saveConfig(): DataResponse {
        if (!$this->securityService->isManager()) {
            return new DataResponse(['success' => false, 'error' => 'Acesso negado: apenas gestores podem salvar configurações.'], 403);
        }

        $url = trim($this->request->getParam('baseUrl', ''));
        $appToken = $this->request->getParam('appToken', null);
        $userToken = $this->request->getParam('userToken', null);

        if (empty($url)) {
            return new DataResponse(['success' => false, 'error' => 'A URL base da API do GLPI é obrigatória.'], 400);
        }

        $this->glpiClient->saveConfig($url, $appToken, $userToken);

        return new DataResponse([
            'success' => true,
            'message' => 'Configurações do GLPI salvas com sucesso.',
            'data' => $this->glpiClient->getConfig()
        ]);
    }

    /**
     * Testa a conexão em tempo real com a API REST do GLPI 12.
     */
    public function testConnection(): DataResponse {
        if (!$this->securityService->isManager()) {
            return new DataResponse(['success' => false, 'error' => 'Acesso negado.'], 403);
        }

        $url = $this->request->getParam('baseUrl', null);
        $appToken = $this->request->getParam('appToken', null);
        $userToken = $this->request->getParam('userToken', null);

        $result = $this->glpiClient->testConnection($url, $appToken, $userToken);
        return new DataResponse($result);
    }
}
