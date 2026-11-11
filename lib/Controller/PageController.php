<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Controller;

use OCA\OABTimesheet\AppInfo\Application;
use OCA\OABTimesheet\Service\SecurityService;
use OCA\OABTimesheet\Integration\Deck\DeckService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private SecurityService $securityService,
        private ?DeckService $deckService = null
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

        if (!$isManager && $user !== null && $this->deckService !== null) {
            $managed = $this->deckService->getManageableBoardIds($user->getUID());
            $isManager = !empty($managed);
        }

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

