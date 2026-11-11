<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AuditLogMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timesheet_audit_logs', AuditLog::class);
    }

    /**
     * @return AuditLog[]
     */
    public function findByEntryId(string $entryId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId)))
           ->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }
}

