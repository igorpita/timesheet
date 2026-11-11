<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Cron;

use OCA\OABTimesheet\Db\SyncQueueMapper;
use OCA\OABTimesheet\Db\TimeEntryMapper;
use OCA\OABTimesheet\Integration\GLPI\GlpiClient;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class SyncGlpiJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private SyncQueueMapper $syncMapper,
        private TimeEntryMapper $entryMapper,
        private GlpiClient $glpiClient
    ) {
        parent::__construct($time);
        // Executa a cada 5 minutos
        $this->setInterval(300);
    }

    protected function run($arguments): void {
        $pendingItems = $this->syncMapper->findPending(50);

        foreach ($pendingItems as $item) {
            $entryId = $item->getEntryId();
            if (!$entryId) {
                continue;
            }

            try {
                $entry = $this->entryMapper->find((int)$entryId);
            } catch (\Throwable $e) {
                // Tenta buscar por UUID
                $entries = $this->entryMapper->findByExternal('GLPI', $item->getEntryId());
                $entry = $entries[0] ?? null;
            }

            if ($entry === null) {
                $item->setSyncStatus('failed');
                $item->setLastError('Apontamento de origem não localizado no banco.');
                $this->syncMapper->update($item);
                continue;
            }

            try {
                $res = $this->glpiClient->createTicketTask(
                    $entry->getExternalId(),
                    $entry->getDurationMinutes() ?? 60,
                    $entry->getDescription() ?? '',
                    $entry->getWorkDate() ?? date('Y-m-d'),
                    $entry->getUserUid()
                );

                if (!empty($res['success'])) {
                    $item->setSyncStatus('synced');
                    $item->setSyncedAt(date('Y-m-d H:i:s'));
                    $item->setLastError(null);
                    $this->syncMapper->update($item);
                } else {
                    $item->setAttempts($item->getAttempts() + 1);
                    $item->setLastError($res['error'] ?? 'Falha desconhecida na API GLPI.');
                    $this->syncMapper->update($item);
                }
            } catch (\Throwable $err) {
                $item->setAttempts($item->getAttempts() + 1);
                $item->setLastError(substr($err->getMessage(), 0, 500));
                if ($item->getAttempts() >= 5) {
                    $item->setSyncStatus('failed');
                }
                $this->syncMapper->update($item);
            }
        }
    }
}

