<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SyncQueueMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timesheet_sync_queue', SyncQueue::class);
    }

    /**
     * @return SyncQueue[]
     */
    public function findPending(int $limit = 50): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('sync_status', $qb->createNamedParameter('pending')))
           ->orderBy('created_at', 'ASC')
           ->setMaxResults($limit);

        return $this->findEntities($qb);
    }

    public function findByIdempotencyKey(string $key): ?SyncQueue {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('idempotency_key', $qb->createNamedParameter($key)));

        $entities = $this->findEntities($qb);
        return $entities[0] ?? null;
    }
}

