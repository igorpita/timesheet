<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Integration\Deck;

use OCA\OABTimesheet\Service\SecurityService;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Http\Client\IClientService;

class DeckService {
    public function __construct(
        private IDBConnection $db,
        private IAppManager $appManager,
        private IClientService $clientService,
        private SecurityService $securityService
    ) {}

    /**
     * Verifica se o aplicativo Nextcloud Deck está instalado e ativo.
     */
    public function isDeckAvailable(): bool {
        return $this->appManager->isEnabledForUser('deck');
    }

    /**
     * Consulta os quadros (boards) acessíveis pelo usuário atual, respeitando ACLs e compartilhamentos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserBoards(): array {
        $userUid = $this->securityService->getCurrentUserUid();

        $qb = $this->db->getQueryBuilder();
        $qb->select(
            'b.id',
            'b.title',
            'b.color',
            'b.owner',
            'b.archived'
        )
        ->from('deck_boards', 'b')
        ->leftJoin('b', 'deck_board_acl', 'a', $qb->expr()->eq('a.board_id', 'b.id'))
        ->where($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
        ->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
        ->andWhere(
            $qb->expr()->orX(
                $qb->expr()->eq('b.owner', $qb->createNamedParameter($userUid)),
                $qb->expr()->eq('a.participant', $qb->createNamedParameter($userUid))
            )
        )
        ->groupBy('b.id', 'b.title', 'b.color', 'b.owner', 'b.archived')
        ->orderBy('b.title', 'ASC');

        try {
            $result = $qb->executeQuery();
            $boards = [];
            while ($row = $result->fetch()) {
                $boards[] = [
                    'id' => (string)$row['id'],
                    'title' => $row['title'],
                    'color' => !empty($row['color']) ? ltrim($row['color'], '#') : '0082c9',
                    'owner' => $row['owner'],
                    'isOwner' => $row['owner'] === $userUid,
                ];
            }
            return $boards;
        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error('Erro ao consultar quadros do Deck: ' . $e->getMessage(), ['app' => 'oab_timesheet']);
            return [];
        }
    }

    /**
     * Consulta os cartões de um determinado quadro.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBoardCards(string $boardId): array {
        $userUid = $this->securityService->getCurrentUserUid();

        // Valida se o usuário tem permissão para visualizar o quadro
        if (!$this->canUserAccessBoard($boardId, $userUid)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select(
            'c.id',
            'c.title',
            'c.description',
            'c.duedate',
            'c.startdate',
            'c.done',
            'c.owner',
            'c.archived',
            'c.created_at',
            'c.last_modified',
            's.id as stack_id',
            's.title as stack_title',
            'b.id as board_id',
            'b.title as board_title'
        )
        ->from('deck_cards', 'c')
        ->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
        ->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('s.board_id', 'b.id'))
        ->where($qb->expr()->eq('b.id', $qb->createNamedParameter((int)$boardId, IQueryBuilder::PARAM_INT)))
        ->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
        ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
        ->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
        ->orderBy('s.order', 'ASC')
        ->addOrderBy('c.order', 'ASC');

        try {
            $result = $qb->executeQuery();
            $cards = [];
            while ($row = $result->fetch()) {
                $cardId = (string)$row['id'];
                $assigned = $this->isUserAssignedToCard($cardId, $userUid);
                $totalMinutes = $this->getTotalMinutesForCard($cardId);

                $cards[] = [
                    'id' => $cardId,
                    'boardId' => (string)$row['board_id'],
                    'boardTitle' => $row['board_title'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'stackId' => (string)$row['stack_id'],
                    'stack' => $row['stack_title'],
                    'startDate' => $row['startdate'],
                    'dueDate' => $row['duedate'],
                    'isDone' => !empty($row['done']),
                    'owner' => $row['owner'],
                    'assigned' => $assigned,
                    'totalMinutes' => $totalMinutes,
                    'totalHoursFormatted' => sprintf('%02dh%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['last_modified'],
                ];
            }
            return $cards;
        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error('Erro ao consultar cartões do Deck: ' . $e->getMessage(), ['app' => 'oab_timesheet']);
            return [];
        }
    }

    /**
     * Pesquisa cartões por termo no título ou descrição.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchCards(string $term): array {
        $userUid = $this->securityService->getCurrentUserUid();

        $qb = $this->db->getQueryBuilder();
        $qb->select(
            'c.id',
            'c.title',
            'c.description',
            's.title as stack_title',
            'b.id as board_id',
            'b.title as board_title'
        )
        ->from('deck_cards', 'c')
        ->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
        ->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('s.board_id', 'b.id'))
        ->leftJoin('b', 'deck_board_acl', 'a', $qb->expr()->eq('a.board_id', 'b.id'))
        ->where($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
        ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
        ->andWhere(
            $qb->expr()->orX(
                $qb->expr()->ilike('c.title', $qb->createNamedParameter('%' . $term . '%')),
                $qb->expr()->ilike('c.description', $qb->createNamedParameter('%' . $term . '%'))
            )
        )
        ->andWhere(
            $qb->expr()->orX(
                $qb->expr()->eq('b.owner', $qb->createNamedParameter($userUid)),
                $qb->expr()->eq('a.participant', $qb->createNamedParameter($userUid))
            )
        )
        ->setMaxResults(30);

        try {
            $result = $qb->executeQuery();
            $cards = [];
            while ($row = $result->fetch()) {
                $cardId = (string)$row['id'];
                $assigned = $this->isUserAssignedToCard($cardId, $userUid);
                $totalMinutes = $this->getTotalMinutesForCard($cardId);
                $cards[] = [
                    'id' => $cardId,
                    'boardId' => (string)$row['board_id'],
                    'boardTitle' => $row['board_title'],
                    'title' => $row['title'],
                    'description' => $row['description'] ?? '',
                    'stack' => $row['stack_title'],
                    'assigned' => $assigned,
                    'totalMinutes' => $totalMinutes,
                    'totalHoursFormatted' => sprintf('%02dh%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                ];
            }
            return $cards;
        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error('Erro ao pesquisar cartões do Deck: ' . $e->getMessage(), ['app' => 'oab_timesheet']);
            return [];
        }
    }

    /**
     * Valida se o usuário tem acesso ao quadro do Deck (seja proprietário ou membro no ACL).
     */
    public function canUserAccessBoard(string $boardId, string $userUid): bool {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('b.id')
               ->from('deck_boards', 'b')
               ->leftJoin('b', 'deck_board_acl', 'a', $qb->expr()->eq('a.board_id', 'b.id'))
               ->where($qb->expr()->eq('b.id', $qb->createNamedParameter((int)$boardId, IQueryBuilder::PARAM_INT)))
               ->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
               ->andWhere(
                   $qb->expr()->orX(
                       $qb->expr()->eq('b.owner', $qb->createNamedParameter($userUid)),
                       $qb->expr()->eq('a.participant', $qb->createNamedParameter($userUid))
                   )
               )
               ->setMaxResults(1);

            $res = $qb->executeQuery();
            return (bool)$res->fetch();
        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error('Erro ao verificar permissão do quadro Deck: ' . $e->getMessage(), ['app' => 'oab_timesheet']);
            return false;
        }
    }

    /**
     * Valida se o usuário tem acesso a um cartão específico no Deck.
     */
    public function canUserAccessCard(string $cardId, string $userUid): bool {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('c.id')
               ->from('deck_cards', 'c')
               ->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
               ->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('s.board_id', 'b.id'))
               ->leftJoin('b', 'deck_board_acl', 'a', $qb->expr()->eq('a.board_id', 'b.id'))
               ->where($qb->expr()->eq('c.id', $qb->createNamedParameter((int)$cardId, IQueryBuilder::PARAM_INT)))
               ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
               ->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
               ->andWhere(
                   $qb->expr()->orX(
                       $qb->expr()->eq('b.owner', $qb->createNamedParameter($userUid)),
                       $qb->expr()->eq('a.participant', $qb->createNamedParameter($userUid))
                   )
               )
               ->setMaxResults(1);

            $res = $qb->executeQuery();
            return (bool)$res->fetch();
        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error('Erro ao verificar permissão do cartão Deck: ' . $e->getMessage(), ['app' => 'oab_timesheet']);
            return false;
        }
    }

    /**
     * Retorna os IDs dos quadros (projetos) em que o usuário é Dono (owner)
     * ou possui permissão de "Pode gerenciar" (permission_manage = true).
     *
     * @return int[]
     */
    public function getManageableBoardIds(string $userUid): array {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->selectDistinct('b.id')
               ->from('deck_boards', 'b')
               ->leftJoin('b', 'deck_board_acl', 'a', $qb->expr()->eq('a.board_id', 'b.id'))
               ->where($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
               ->andWhere(
                   $qb->expr()->orX(
                       $qb->expr()->eq('b.owner', $qb->createNamedParameter($userUid)),
                       $qb->expr()->andX(
                           $qb->expr()->eq('a.permission_manage', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)),
                           $qb->expr()->eq('a.participant', $qb->createNamedParameter($userUid))
                       )
                   )
               );

            $res = $qb->executeQuery();
            $ids = [];
            while ($row = $res->fetch()) {
                $ids[] = (int)$row['id'];
            }
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Retorna todos os IDs dos cartões que pertencem a uma lista de quadros (projetos).
     *
     * @param int[] $boardIds
     * @return string[]
     */
    public function getCardIdsForBoards(array $boardIds): array {
        if (empty($boardIds)) {
            return [];
        }

        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('c.id')
               ->from('deck_cards', 'c')
               ->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
               ->where($qb->expr()->in('s.board_id', $qb->createNamedParameter($boardIds, IQueryBuilder::PARAM_INT_ARRAY)))
               ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

            $res = $qb->executeQuery();
            $cardIds = [];
            while ($row = $res->fetch()) {
                $cardIds[] = (string)$row['id'];
            }
            return $cardIds;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verifica se o usuário é dono ou pode gerenciar o cartão do Deck.
     */
    public function canUserManageCard(string $cardId, string $userUid): bool {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('b.id')
               ->from('deck_cards', 'c')
               ->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
               ->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('s.board_id', 'b.id'))
               ->leftJoin('b', 'deck_board_acl', 'a', $qb->expr()->eq('a.board_id', 'b.id'))
               ->where($qb->expr()->eq('c.id', $qb->createNamedParameter((int)$cardId, IQueryBuilder::PARAM_INT)))
               ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
               ->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
               ->andWhere(
                   $qb->expr()->orX(
                       $qb->expr()->eq('b.owner', $qb->createNamedParameter($userUid)),
                       $qb->expr()->andX(
                           $qb->expr()->eq('a.permission_manage', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)),
                           $qb->expr()->eq('a.participant', $qb->createNamedParameter($userUid))
                       )
                   )
               )
               ->setMaxResults(1);

            $res = $qb->executeQuery();
            return (bool)$res->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Retorna detalhes de um cartão do Deck pelo ID.
     */
    public function getCardById(string $cardId): ?array {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select(
                'c.id',
                'c.title',
                's.title as stack_title',
                'b.id as board_id',
                'b.title as board_title'
            )
            ->from('deck_cards', 'c')
            ->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('c.stack_id', 's.id'))
            ->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('s.board_id', 'b.id'))
            ->where($qb->expr()->eq('c.id', $qb->createNamedParameter((int)$cardId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);

            $res = $qb->executeQuery();
            $row = $res->fetch();
            if (!$row) {
                return null;
            }

            return [
                'id' => (string)$row['id'],
                'title' => $row['title'],
                'stack' => $row['stack_title'],
                'boardId' => (string)$row['board_id'],
                'boardTitle' => $row['board_title'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Verifica se o usuário atual está atribuído ao cartão.
     */
    private function isUserAssignedToCard(string $cardId, string $userUid): bool {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('participant')
               ->from('deck_assigned_users')
               ->where($qb->expr()->eq('card_id', $qb->createNamedParameter((int)$cardId, IQueryBuilder::PARAM_INT)))
               ->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($userUid)));

            $res = $qb->executeQuery();
            return (bool)$res->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getTotalMinutesForCard(string $cardId): int {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select($qb->createFunction('COALESCE(SUM(duration_minutes), 0) AS total'))
               ->from('timesheet_entries')
               ->where($qb->expr()->eq('source_type', $qb->createNamedParameter('DECK')))
               ->andWhere($qb->expr()->eq('external_id', $qb->createNamedParameter($cardId)));
            $res = $qb->executeQuery();
            $row = $res->fetch();
            return (int)($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
