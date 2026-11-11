<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Service;

use OCA\OABTimesheet\Db\TimeEntry;
use OCA\OABTimesheet\Db\TimeEntryMapper;
use OCA\OABTimesheet\Db\AuditLog;
use OCA\OABTimesheet\Db\AuditLogMapper;
use OCA\OABTimesheet\Db\SyncQueue;
use OCA\OABTimesheet\Db\SyncQueueMapper;
use OCA\OABTimesheet\Service\SecurityService;
use OCA\OABTimesheet\Integration\Deck\DeckService;

class TimeEntryService {
    public function __construct(
        private TimeEntryMapper $entryMapper,
        private AuditLogMapper $auditMapper,
        private SyncQueueMapper $syncMapper,
        private SecurityService $securityService,
        private ?DeckService $deckService = null
    ) {}

    public function getUserEntries(?string $startDate = null, ?string $endDate = null): array {
        $uid = $this->securityService->getCurrentUserUid();
        return $this->entryMapper->findByUser($uid, $startDate, $endDate);
    }

    public function getTeamEntries(?string $startDate = null, ?string $endDate = null, ?string $sourceType = null): array {
        if (!$this->securityService->isManager()) {
            throw new \RuntimeException('Acesso negado: apenas gestores podem consultar horas da equipe.');
        }
        return $this->entryMapper->findAllTeamEntries($startDate, $endDate, $sourceType);
    }

    public function getReportsData(?string $startDate = null, ?string $endDate = null, ?string $userUid = null): array {
        $isManager = $this->securityService->isManager();
        $currentUser = $this->securityService->getCurrentUserUid();

        if (!$isManager) {
            // Colaboradores visualizam apenas seus próprios registros
            $rawEntries = $this->entryMapper->findByUser($currentUser, $startDate, $endDate);
        } else {
            if (!empty($userUid)) {
                $rawEntries = $this->entryMapper->findByUser($userUid, $startDate, $endDate);
            } else {
                $rawEntries = $this->entryMapper->findAllTeamEntries($startDate, $endDate, 'DECK');
            }
        }

        $entries = [];
        $collaboratorsSet = [];
        $projectsSet = [];
        $totalMinutes = 0;
        $projectAggregates = [];
        $userAggregates = [];

        foreach ($rawEntries as $entry) {
            $extTitle = $entry->getExternalTitle() ?? '';
            $projectName = 'Projeto Geral (Deck)';
            $cardTitle = $extTitle;

            if (preg_match('/^\[(.*?)\]\s*(.*)$/', $extTitle, $matches)) {
                $projectName = trim($matches[1]);
                $cardTitle = trim($matches[2]);
            }

            $u = $entry->getUserUid();
            $collaboratorsSet[$u] = true;
            $projectsSet[$projectName] = true;

            $duration = $entry->getDurationMinutes() ?? 0;
            $totalMinutes += $duration;

            // Agregação por Projeto
            if (!isset($projectAggregates[$projectName])) {
                $projectAggregates[$projectName] = [
                    'projectName' => $projectName,
                    'totalMinutes' => 0,
                    'count' => 0,
                    'uniqueCards' => [],
                ];
            }
            $projectAggregates[$projectName]['totalMinutes'] += $duration;
            $projectAggregates[$projectName]['count'] += 1;
            $projectAggregates[$projectName]['uniqueCards'][$entry->getExternalId()] = true;

            // Agregação por Colaborador
            if (!isset($userAggregates[$u])) {
                $userAggregates[$u] = [
                    'userUid' => $u,
                    'totalMinutes' => 0,
                    'count' => 0,
                    'workDates' => [],
                ];
            }
            $userAggregates[$u]['totalMinutes'] += $duration;
            $userAggregates[$u]['count'] += 1;
            $userAggregates[$u]['workDates'][$entry->getWorkDate()] = true;

            $entryArray = $entry->jsonSerialize();
            $entryArray['projectName'] = $projectName;
            $entryArray['cardTitle'] = $cardTitle;
            $entries[] = $entryArray;
        }

        // Formatação dos Agregados por Projeto
        $byProject = [];
        foreach ($projectAggregates as $pName => $pData) {
            $mins = $pData['totalMinutes'];
            $pct = $totalMinutes > 0 ? round(($mins / $totalMinutes) * 100, 1) : 0;
            $byProject[] = [
                'projectName' => $pName,
                'uniqueCardsCount' => count($pData['uniqueCards']),
                'count' => $pData['count'],
                'totalMinutes' => $mins,
                'totalHoursFormatted' => sprintf('%02dh%02d', intdiv($mins, 60), $mins % 60),
                'percentage' => $pct,
            ];
        }
        usort($byProject, fn($a, $b) => $b['totalMinutes'] <=> $a['totalMinutes']);

        // Formatação dos Agregados por Colaborador
        $byCollaborator = [];
        foreach ($userAggregates as $uName => $uData) {
            $mins = $uData['totalMinutes'];
            $daysCount = max(1, count($uData['workDates']));
            $avgMins = intdiv($mins, $daysCount);
            $pct = $totalMinutes > 0 ? round(($mins / $totalMinutes) * 100, 1) : 0;
            $byCollaborator[] = [
                'userUid' => $uName,
                'daysWorked' => count($uData['workDates']),
                'count' => $uData['count'],
                'totalMinutes' => $mins,
                'totalHoursFormatted' => sprintf('%02dh%02d', intdiv($mins, 60), $mins % 60),
                'dailyAvgFormatted' => sprintf('%02dh%02d', intdiv($avgMins, 60), $avgMins % 60),
                'percentage' => $pct,
            ];
        }
        usort($byCollaborator, fn($a, $b) => $b['totalMinutes'] <=> $a['totalMinutes']);

        return [
            'entries' => $entries,
            'totalMinutes' => $totalMinutes,
            'totalHoursFormatted' => sprintf('%02dh%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
            'collaborators' => array_keys($collaboratorsSet),
            'projects' => array_keys($projectsSet),
            'byProject' => $byProject,
            'byCollaborator' => $byCollaborator,
            'isManager' => $isManager,
        ];
    }

    public function getEntry(string $id): TimeEntry {
        $entry = $this->entryMapper->findById($id);
        if ($entry === null) {
            throw new \RuntimeException('Apontamento não localizado.');
        }
        if (!$this->securityService->canEditEntry($entry)) {
            throw new \RuntimeException('Acesso negado para consultar este apontamento.');
        }
        return $entry;
    }

    public function createEntry(array $data): TimeEntry {
        $currentUserUid = $this->securityService->getCurrentUserUid();

        $sourceType = strtoupper(trim($data['sourceType'] ?? ''));
        if (!in_array($sourceType, ['DECK', 'GLPI'], true)) {
            throw new \InvalidArgumentException('Origem inválida. Deve ser DECK ou GLPI.');
        }

        $durationMinutes = (int)($data['durationMinutes'] ?? 0);
        if ($durationMinutes <= 0) {
            throw new \InvalidArgumentException('A duração do apontamento deve ser maior que zero.');
        }

        if ($durationMinutes > 1440) { // 24 horas em minutos
            throw new \InvalidArgumentException('A duração não pode exceder 24 horas em um único apontamento.');
        }

        $workDate = trim($data['workDate'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            throw new \InvalidArgumentException('Formato de data inválido. Utilize AAAA-MM-DD.');
        }

        $today = date('Y-m-d');
        if ($workDate > $today) {
            throw new \InvalidArgumentException('Não é permitido registrar apontamentos com data futura.');
        }

        // Validação de limite diário acumulado (alerta/trava de 24h)
        $dailyExistingMinutes = $this->getDailyTotalMinutes($currentUserUid, $workDate);
        if (($dailyExistingMinutes + $durationMinutes) > 1440) {
            throw new \InvalidArgumentException(sprintf(
                'O total apontado no dia %s (%s) excede 24 horas.',
                $workDate,
                sprintf('%02dh%02d', intdiv($dailyExistingMinutes + $durationMinutes, 60), ($dailyExistingMinutes + $durationMinutes) % 60)
            ));
        }

        $description = trim($data['description'] ?? '');
        if (empty($description)) {
            throw new \InvalidArgumentException('A descrição da atividade realizada é obrigatória.');
        }

        $externalId = trim($data['externalId'] ?? '');
        if (empty($externalId)) {
            throw new \InvalidArgumentException('O identificador externo (cartão ou chamado) é obrigatório.');
        }

        // Validação de Permissões para cartões do Nextcloud Deck
        $externalTitle = $data['externalTitle'] ?? null;
        if ($sourceType === 'DECK' && $this->deckService !== null) {
            if (is_numeric($externalId)) {
                if (!$this->deckService->canUserAccessCard($externalId, $currentUserUid)) {
                    throw new \RuntimeException('Permissão negada: você não possui acesso a este cartão ou projeto no Nextcloud Deck.');
                }
                // Preenche o título do cartão automaticamente caso venha vazio
                if (empty($externalTitle)) {
                    $card = $this->deckService->getCardById($externalId);
                    if ($card !== null) {
                        $externalTitle = sprintf('[%s] %s', $card['boardTitle'], $card['title']);
                    }
                }
            }
        }

        $entry = new TimeEntry();
        // Identificador UUID
        $uuid = $this->generateUuidV4();
        $entry->setId($uuid);
        $entry->setUserUid($currentUserUid);
        $entry->setSourceType($sourceType);
        $entry->setExternalId($externalId);
        $entry->setExternalTaskId($data['externalTaskId'] ?? null);
        $entry->setExternalTitle($externalTitle);
        $entry->setWorkDate($workDate);
        $entry->setDurationMinutes($durationMinutes);
        $entry->setDescription($description);
        $entry->setStatus('submitted');
        $entry->setCreatedBy($currentUserUid);
        $entry->setVersion(1);
        $entry->setCreatedAt(date('Y-m-d H:i:s'));
        $entry->setUpdatedAt(date('Y-m-d H:i:s'));

        $saved = $this->entryMapper->insert($entry);

        // Registro de Auditoria
        $this->logAudit((string)$saved->getId(), $currentUserUid, 'CREATE', [
            'after' => $saved->jsonSerialize()
        ]);

        // Se a origem for GLPI, enfileira para sincronização
        if ($sourceType === 'GLPI') {
            $this->enqueueGlpiSync($saved);
        }

        return $saved;
    }

    public function getDailyTotalMinutes(string $userUid, string $workDate): int {
        $entries = $this->entryMapper->findByUser($userUid, $workDate, $workDate);
        return array_reduce($entries, fn($sum, $e) => $sum + ($e->getDurationMinutes() ?? 0), 0);
    }

    public function updateEntry(string $id, array $data): TimeEntry {
        $entry = $this->getEntry($id);

        if (!$this->securityService->canEditEntry($entry)) {
            throw new \RuntimeException('Permissão insuficiente para alterar este apontamento.');
        }

        $oldData = $entry->jsonSerialize();
        $currentUserUid = $this->securityService->getCurrentUserUid();

        if (isset($data['durationMinutes'])) {
            $durationMinutes = (int)$data['durationMinutes'];
            if ($durationMinutes <= 0) {
                throw new \InvalidArgumentException('Duração inválida.');
            }
            $entry->setDurationMinutes($durationMinutes);
        }

        if (isset($data['workDate'])) {
            $workDate = trim($data['workDate']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
                throw new \InvalidArgumentException('Formato de data inválido. Utilize AAAA-MM-DD.');
            }
            $today = date('Y-m-d');
            if ($workDate > $today) {
                throw new \InvalidArgumentException('Não é permitido registrar apontamentos com data futura.');
            }
            $entry->setWorkDate($workDate);
        }

        if (isset($data['description'])) {
            $description = trim($data['description']);
            if (empty($description)) {
                throw new \InvalidArgumentException('A descrição é obrigatória.');
            }
            $entry->setDescription($description);
        }

        $entry->setUpdatedBy($currentUserUid);
        $entry->setUpdatedAt(date('Y-m-d H:i:s'));
        $entry->setVersion($entry->getVersion() + 1);

        $updated = $this->entryMapper->update($entry);

        $this->logAudit((string)$updated->getId(), $currentUserUid, 'UPDATE', [
            'before' => $oldData,
            'after' => $updated->jsonSerialize()
        ]);

        return $updated;
    }

    public function deleteEntry(string $id): void {
        $entry = $this->getEntry($id);

        if (!$this->securityService->canDeleteEntry($entry)) {
            throw new \RuntimeException('Permissão insuficiente para remover este apontamento.');
        }

        $currentUserUid = $this->securityService->getCurrentUserUid();
        $oldData = $entry->jsonSerialize();

        $this->entryMapper->delete($entry);

        $this->logAudit($id, $currentUserUid, 'DELETE', [
            'before' => $oldData
        ]);
    }

    public function getAuditLogs(string $entryId): array {
        $entry = $this->getEntry($entryId);
        return $this->auditMapper->findByEntryId($entry->getId());
    }

    private function logAudit(string $entryId, string $userUid, string $action, array $changes): void {
        $log = new AuditLog();
        $log->setEntryId($entryId);
        $log->setUserUid($userUid);
        $log->setAction($action);
        $log->setChanges(json_encode($changes, JSON_UNESCAPED_UNICODE));
        $log->setCreatedAt(date('Y-m-d H:i:s'));
        $this->auditMapper->insert($log);
    }

    private function enqueueGlpiSync(TimeEntry $entry): void {
        $idempotencyKey = hash('sha256', sprintf('glpi-%s-%s-%s', $entry->getId(), $entry->getExternalId(), $entry->getVersion()));

        // Checar se já existe na fila
        if ($this->syncMapper->findByIdempotencyKey($idempotencyKey) !== null) {
            return;
        }

        $sync = new SyncQueue();
        $sync->setEntryId($entry->getId());
        $sync->setTargetSystem('GLPI');
        $sync->setSyncStatus('pending');
        $sync->setAttempts(0);
        $sync->setIdempotencyKey($idempotencyKey);
        $sync->setCreatedAt(date('Y-m-d H:i:s'));

        $this->syncMapper->insert($sync);
    }

    private function generateUuidV4(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

