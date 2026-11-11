<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TimeEntryMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'timesheet_entries', TimeEntry::class);
    }

    public function findById(string $id): ?TimeEntry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        $entities = $this->findEntities($qb);
        return $entities[0] ?? null;
    }

    /**
     * Insere a entidade persistindo o ID UUID de forma explícita.
     */
    public function insert(Entity $entity): Entity {
        /** @var TimeEntry $entity */
        $qb = $this->db->getQueryBuilder();
        $qb->insert($this->getTableName())
           ->values([
               'id' => $qb->createNamedParameter($entity->getId()),
               'user_uid' => $qb->createNamedParameter($entity->getUserUid()),
               'source_type' => $qb->createNamedParameter($entity->getSourceType()),
               'external_id' => $qb->createNamedParameter($entity->getExternalId()),
               'external_task_id' => $qb->createNamedParameter($entity->getExternalTaskId()),
               'external_title' => $qb->createNamedParameter($entity->getExternalTitle()),
               'work_date' => $qb->createNamedParameter($entity->getWorkDate()),
               'duration_minutes' => $qb->createNamedParameter($entity->getDurationMinutes(), IQueryBuilder::PARAM_INT),
               'description' => $qb->createNamedParameter($entity->getDescription()),
               'status' => $qb->createNamedParameter($entity->getStatus()),
               'created_by' => $qb->createNamedParameter($entity->getCreatedBy()),
               'version' => $qb->createNamedParameter($entity->getVersion(), IQueryBuilder::PARAM_INT),
               'created_at' => $qb->createNamedParameter($entity->getCreatedAt()),
               'updated_at' => $qb->createNamedParameter($entity->getUpdatedAt()),
           ]);
        $qb->executeStatement();
        return $entity;
    }

    /**
     * Atualiza a entidade pelo UUID.
     */
    public function update(Entity $entity): Entity {
        /** @var TimeEntry $entity */
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
           ->set('duration_minutes', $qb->createNamedParameter($entity->getDurationMinutes(), IQueryBuilder::PARAM_INT))
           ->set('work_date', $qb->createNamedParameter($entity->getWorkDate()))
           ->set('description', $qb->createNamedParameter($entity->getDescription()))
           ->set('status', $qb->createNamedParameter($entity->getStatus()))
           ->set('updated_by', $qb->createNamedParameter($entity->getUpdatedBy()))
           ->set('version', $qb->createNamedParameter($entity->getVersion(), IQueryBuilder::PARAM_INT))
           ->set('updated_at', $qb->createNamedParameter($entity->getUpdatedAt()))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($entity->getId())));
        $qb->executeStatement();
        return $entity;
    }

    /**
     * Remove a entidade pelo UUID.
     */
    public function delete(Entity $entity): Entity {
        /** @var TimeEntry $entity */
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($entity->getId())));
        $qb->executeStatement();
        return $entity;
    }

    /**
     * @return TimeEntry[]
     */
    public function findByUser(string $userUid, ?string $startDate = null, ?string $endDate = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)));

        if ($startDate !== null) {
            $qb->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($startDate)));
        }
        if ($endDate !== null) {
            $qb->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($endDate)));
        }

        $qb->orderBy('work_date', 'DESC')
           ->addOrderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Consulta registros visíveis para o usuário:
     * - Seus próprios registros OU
     * - Registros em cartões de projetos/quadros em que possui gestão (allowedCardIds)
     *
     * @param string[] $allowedCardIds
     * @return TimeEntry[]
     */
    public function findVisibleEntries(
        string $userUid,
        array $allowedCardIds = [],
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $filterUserUid = null
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName());

        if (!empty($allowedCardIds)) {
            $scopeCondition = $qb->expr()->orX(
                $qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)),
                $qb->expr()->andX(
                    $qb->expr()->eq('source_type', $qb->createNamedParameter('DECK')),
                    $qb->expr()->in('external_id', $qb->createNamedParameter($allowedCardIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY))
                )
            );
        } else {
            $scopeCondition = $qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid));
        }

        $qb->where($scopeCondition);

        if (!empty($filterUserUid)) {
            $qb->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($filterUserUid)));
        }

        if ($startDate !== null) {
            $qb->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($startDate)));
        }
        if ($endDate !== null) {
            $qb->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($endDate)));
        }

        $qb->orderBy('work_date', 'DESC')
           ->addOrderBy('created_at', 'DESC')
           ->setMaxResults(1000);

        return $this->findEntities($qb);
    }

    /**
     * @return TimeEntry[]
     */
    public function findByExternal(string $sourceType, string $externalId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('source_type', $qb->createNamedParameter($sourceType)))
           ->andWhere($qb->expr()->eq('external_id', $qb->createNamedParameter($externalId)))
           ->orderBy('work_date', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * @return TimeEntry[]
     */
    public function findAllTeamEntries(?string $startDate = null, ?string $endDate = null, ?string $sourceType = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName());

        $hasCondition = false;
        if ($startDate !== null) {
            $qb->where($qb->expr()->gte('work_date', $qb->createNamedParameter($startDate)));
            $hasCondition = true;
        }
        if ($endDate !== null) {
            if ($hasCondition) {
                $qb->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($endDate)));
            } else {
                $qb->where($qb->expr()->lte('work_date', $qb->createNamedParameter($endDate)));
                $hasCondition = true;
            }
        }
        if ($sourceType !== null) {
            if ($hasCondition) {
                $qb->andWhere($qb->expr()->eq('source_type', $qb->createNamedParameter($sourceType)));
            } else {
                $qb->where($qb->expr()->eq('source_type', $qb->createNamedParameter($sourceType)));
            }
        }

        $qb->orderBy('work_date', 'DESC')
           ->setMaxResults(500);

        return $this->findEntities($qb);
    }

    public function getTotalMinutesForExternal(string $sourceType, string $externalId): int {
        $entries = $this->findByExternal($sourceType, $externalId);
        return array_reduce($entries, fn($sum, $e) => $sum + ($e->getDurationMinutes() ?? 0), 0);
    }
}
