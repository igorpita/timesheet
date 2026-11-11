<?php

declare(strict_types=1);

return [
    'routes' => [
        // Frontend Web View
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Time Entries CRUD API
        ['name' => 'time_entry_api#index', 'url' => '/api/v1/entries', 'verb' => 'GET'],
        ['name' => 'time_entry_api#create', 'url' => '/api/v1/entries', 'verb' => 'POST'],
        ['name' => 'time_entry_api#show', 'url' => '/api/v1/entries/{id}', 'verb' => 'GET'],
        ['name' => 'time_entry_api#update', 'url' => '/api/v1/entries/{id}', 'verb' => 'PUT'],
        ['name' => 'time_entry_api#destroy', 'url' => '/api/v1/entries/{id}', 'verb' => 'DELETE'],
        ['name' => 'time_entry_api#auditLogs', 'url' => '/api/v1/entries/{id}/audit', 'verb' => 'GET'],

        // Nextcloud Deck Integration API
        ['name' => 'deck_api#listBoards', 'url' => '/api/v1/deck/boards', 'verb' => 'GET'],
        ['name' => 'deck_api#listCards', 'url' => '/api/v1/deck/boards/{boardId}/cards', 'verb' => 'GET'],
        ['name' => 'deck_api#searchCards', 'url' => '/api/v1/deck/search', 'verb' => 'GET'],
        ['name' => 'deck_api#cardHistory', 'url' => '/api/v1/deck/cards/{cardId}/history', 'verb' => 'GET'],

        // GLPI Integration API
        ['name' => 'glpi_api#searchTickets', 'url' => '/api/v1/glpi/tickets', 'verb' => 'GET'],
        ['name' => 'glpi_api#ticketDetails', 'url' => '/api/v1/glpi/tickets/{ticketId}', 'verb' => 'GET'],
        ['name' => 'glpi_api#syncStatus', 'url' => '/api/v1/glpi/sync-status', 'verb' => 'GET'],
        ['name' => 'glpi_api#getConfig', 'url' => '/api/v1/glpi/config', 'verb' => 'GET'],
        ['name' => 'glpi_api#saveConfig', 'url' => '/api/v1/glpi/config', 'verb' => 'POST'],
        ['name' => 'glpi_api#testConnection', 'url' => '/api/v1/glpi/test-connection', 'verb' => 'POST'],

        // Manager / Team API
        ['name' => 'team_api#summary', 'url' => '/api/v1/team/summary', 'verb' => 'GET'],
        ['name' => 'team_api#entries', 'url' => '/api/v1/team/entries', 'verb' => 'GET'],
        ['name' => 'team_api#reports', 'url' => '/api/v1/team/reports', 'verb' => 'GET'],
    ]
];

