<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Controller;

use OCA\OABTimesheet\AppInfo\Application;
use OCA\OABTimesheet\Service\SecurityService;
use OCA\OABTimesheet\Service\TimeEntryService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;

class TeamApiController extends ApiController {
    public function __construct(
        IRequest $request,
        private SecurityService $securityService,
        private TimeEntryService $entryService
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function entries(?string $startDate = null, ?string $endDate = null, ?string $sourceType = null): DataResponse {
        try {
            $entries = $this->entryService->getTeamEntries($startDate, $endDate, $sourceType);
            $serialized = array_map(function ($e) {
                $arr = $e->jsonSerialize();
                $arr['userDisplayName'] = $this->entryService->resolveUserDisplayName($e->getUserUid());
                return $arr;
            }, $entries);
            return new DataResponse([
                'success' => true,
                'data' => $serialized,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function summary(?string $startDate = null, ?string $endDate = null): DataResponse {
        try {
            $entries = $this->entryService->getTeamEntries($startDate, $endDate);
            $totalMinutes = 0;
            $byUser = [];
            $projects = [];

            foreach ($entries as $entry) {
                $mins = $entry->getDurationMinutes() ?? 0;
                $totalMinutes += $mins;

                $user = $entry->getUserUid();
                $displayName = $this->entryService->resolveUserDisplayName($user);
                $byUser[$displayName] = ($byUser[$displayName] ?? 0) + $mins;

                $proj = $entry->getExternalTitle() ?? $entry->getExternalId();
                if ($proj) {
                    $projects[$proj] = true;
                }
            }

            return new DataResponse([
                'success' => true,
                'totalMinutes' => $totalMinutes,
                'totalHoursFormatted' => sprintf('%02dh%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                'totalEntries' => count($entries),
                'activeProjectsCount' => count($projects),
                'byUser' => $byUser,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function reports(?string $startDate = null, ?string $endDate = null, ?string $userUid = null): DataResponse {
        try {
            $data = $this->entryService->getReportsData($startDate, $endDate, $userUid);
            return new DataResponse([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return new DataResponse(['success' => false, 'error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}

