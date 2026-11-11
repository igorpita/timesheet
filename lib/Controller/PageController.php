<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Controller;

use OCA\OABTimesheet\AppInfo\Application;
use OCA\OABTimesheet\Service\SecurityService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private SecurityService $securityService
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->securityService->getCurrentUser();
        $isManager = $this->securityService->isManager();

        Util::addScript(Application::APP_ID, 'oab-timesheet-main');
        Util::addStyle(Application::APP_ID, 'oab-timesheet');
        Util::addStyle(Application::APP_ID, 'app');

        return new TemplateResponse(
            Application::APP_ID,
            'main',
            [
                'appId' => Application::APP_ID,
                'userUid' => $user ? $user->getUID() : '',
                'userName' => $user ? $user->getDisplayName() : '',
                'userEmail' => $user ? $user->getEMailAddress() : '',
                'isManager' => $isManager,
            ]
        );
    }
}

