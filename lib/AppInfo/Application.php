<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\AppInfo;

use OCA\OABTimesheet\Service\TimeEntryService;
use OCA\OABTimesheet\Service\SecurityService;
use OCA\OABTimesheet\Integration\Deck\DeckService;
use OCA\OABTimesheet\Integration\GLPI\GlpiClient;
use OCA\OABTimesheet\Cron\SyncGlpiJob;
use OCA\OABTimesheet\Db\TimeEntryMapper;
use OCA\OABTimesheet\Db\AuditLogMapper;
use OCA\OABTimesheet\Db\SyncQueueMapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;

class Application extends App implements IBootstrap {
    public const APP_ID = 'oab_timesheet';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerService(TimeEntryService::class, function ($c) {
            return new TimeEntryService(
                $c->get(TimeEntryMapper::class),
                $c->get(AuditLogMapper::class),
                $c->get(SyncQueueMapper::class),
                $c->get(SecurityService::class),
                $c->get(DeckService::class),
                $c->get(\OCP\IUserManager::class)
            );
        });

        $context->registerService(SecurityService::class, function ($c) {
            return new SecurityService(
                $c->get(\OCP\IUserSession::class),
                $c->get(\OCP\IGroupManager::class)
            );
        });

        $context->registerService(DeckService::class, function ($c) {
            return new DeckService(
                $c->get(\OCP\IDBConnection::class),
                $c->get(\OCP\App\IAppManager::class),
                $c->get(\OCP\Http\Client\IClientService::class),
                $c->get(SecurityService::class)
            );
        });

        $context->registerService(GlpiClient::class, function ($c) {
            return new GlpiClient(
                $c->get(\OCP\Http\Client\IClientService::class),
                $c->get(\OCP\IConfig::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        // PoC: Injeta o script de integração contextual com os cartões do Nextcloud Deck
        Util::addScript(self::APP_ID, 'deck-integration');
    }
}

