<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20261110000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $params): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // 1. Tabela Principal de Registros de Horas
        if (!$schema->hasTable('timesheet_entries')) {
            $table = $schema->createTable('timesheet_entries');
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('user_uid', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('source_type', 'string', ['length' => 16, 'notnull' => true]);
            $table->addColumn('external_id', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('external_task_id', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('external_title', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('work_date', 'date', ['notnull' => true]);
            $table->addColumn('duration_minutes', 'integer', ['notnull' => true]);
            $table->addColumn('description', 'text', ['notnull' => true]);
            $table->addColumn('status', 'string', ['length' => 32, 'default' => 'submitted', 'notnull' => true]);
            $table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('updated_by', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('approved_by', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('approved_at', 'datetime', ['notnull' => false]);
            $table->addColumn('origin_entry_id', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('version', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_uid', 'work_date'], 'ts_user_date_idx');
            $table->addIndex(['source_type', 'external_id'], 'ts_source_ext_idx');
        }

        // 2. Tabela de Auditoria e Rastreabilidade
        if (!$schema->hasTable('timesheet_audit_logs')) {
            $table = $schema->createTable('timesheet_audit_logs');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('entry_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('user_uid', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('action', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('changes', 'text', ['notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['entry_id'], 'ts_audit_entry_idx');
            $table->addIndex(['user_uid'], 'ts_audit_user_idx');
        }

        // 3. Fila de Sincronização Assíncrona com GLPI
        if (!$schema->hasTable('timesheet_sync_queue')) {
            $table = $schema->createTable('timesheet_sync_queue');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('entry_id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('target_system', 'string', ['length' => 16, 'notnull' => true]);
            $table->addColumn('sync_status', 'string', ['length' => 32, 'default' => 'pending', 'notnull' => true]);
            $table->addColumn('attempts', 'integer', ['default' => 0, 'notnull' => true]);
            $table->addColumn('last_error', 'text', ['notnull' => false]);
            $table->addColumn('idempotency_key', 'string', ['length' => 128, 'notnull' => true]);
            $table->addColumn('created_at', 'datetime', ['notnull' => true]);
            $table->addColumn('synced_at', 'datetime', ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['idempotency_key'], 'ts_sync_idemp_idx');
            $table->addIndex(['sync_status'], 'ts_sync_status_idx');
        }

        return $schema;
    }
}

