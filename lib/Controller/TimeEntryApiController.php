<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Controller;

use OCA\OABTimesheet\AppInfo\Application;
use OCA\OABTimesheet\Service\TimeEntryService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

class TimeEntryApiController extends ApiController {
    public function __construct(
        IRequest $request,
        private TimeEntryService $entryService
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function index(?string $startDate = null, ?string $endDate = null): DataResponse {
        try {
            $entries = $this->entryService->getUserEntries($startDate, $endDate);
            return new DataResponse([
                'success' => true,
                'data' => $entries,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(): DataResponse {
        try {
            $data = $this->request->getParams();
            $entry = $this->entryService->createEntry($data);
            return new DataResponse([
                'success' => true,
                'data' => $entry,
            ], Http::STATUS_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function show(string $id): DataResponse {
        try {
            $entry = $this->entryService->getEntry($id);
            return new DataResponse([
                'success' => true,
                'data' => $entry,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(string $id): DataResponse {
        try {
            $data = $this->request->getParams();
            $entry = $this->entryService->updateEntry($id, $data);
            return new DataResponse([
                'success' => true,
                'data' => $entry,
            ]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(string $id): DataResponse {
        try {
            $this->entryService->deleteEntry($id);
            return new DataResponse([
                'success' => true,
                'message' => 'Apontamento removido com sucesso.',
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function auditLogs(string $id): DataResponse {
        try {
            $logs = $this->entryService->getAuditLogs($id);
            return new DataResponse([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }
}
