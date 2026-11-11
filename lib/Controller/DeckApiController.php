<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Controller;

use OCA\OABTimesheet\AppInfo\Application;
use OCA\OABTimesheet\Db\TimeEntryMapper;
use OCA\OABTimesheet\Integration\Deck\DeckService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class DeckApiController extends ApiController {
    public function __construct(
        IRequest $request,
        private DeckService $deckService,
        private TimeEntryMapper $entryMapper
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function listBoards(): DataResponse {
        try {
            $boards = $this->deckService->getUserBoards();
            return new DataResponse([
                'success' => true,
                'data' => $boards
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function listCards(string $boardId): DataResponse {
        try {
            $cards = $this->deckService->getBoardCards($boardId);
            return new DataResponse([
                'success' => true,
                'boardId' => $boardId,
                'data' => $cards
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function searchCards(): DataResponse {
        $term = trim($this->request->getParam('q', ''));
        try {
            $cards = $this->deckService->searchCards($term);
            return new DataResponse([
                'success' => true,
                'term' => $term,
                'data' => $cards
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function cardHistory(string $cardId): DataResponse {
        $entries = $this->entryMapper->findByExternal('DECK', $cardId);
        $totalMinutes = $this->entryMapper->getTotalMinutesForExternal('DECK', $cardId);

        return new DataResponse([
            'success' => true,
            'cardId' => $cardId,
            'totalMinutes' => $totalMinutes,
            'totalFormatted' => sprintf('%02dh%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
            'entries' => $entries,
        ]);
    }
}
