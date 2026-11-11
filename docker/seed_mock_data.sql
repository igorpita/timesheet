-- ====================================================================
-- SEED DE DADOS DE HOMOLOGAÇÃO — TIMESHEET
-- ====================================================================

-- 1. Limpeza de registros anteriores de teste (se houver)
DELETE FROM oc_timesheet_audit_logs;
DELETE FROM oc_timesheet_sync_queue;
DELETE FROM oc_timesheet_entries;

-- 2. Inserção de Apontamentos Realistas de TI
-- Usuários: igor.pita, alisson.costa, maria.silva, carlos.eduardo

-- Semana Atual & Recente
INSERT INTO oc_timesheet_entries 
(id, user_uid, source_type, external_id, external_task_id, external_title, work_date, duration_minutes, description, status, created_by, version, created_at, updated_at)
VALUES
-- Igor Pita (Gestão / Infraestrutura / Projetos)
('e1000001-0000-4000-8000-000000000001', 'igor.pita', 'DECK', '1001', NULL, 'Configuração do proxy reverso Traefik', CURRENT_DATE - INTERVAL '1 day', 180, 'Ajuste de certificados TLS LetsEncrypt e rotas de roteamento do cluster', 'approved', 'igor.pita', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
('e1000001-0000-4000-8000-000000000002', 'igor.pita', 'DECK', '1002', NULL, 'Validação de permissões OAuth2 GLPI', CURRENT_DATE - INTERVAL '1 day', 120, 'Configuração de client ID, secret e testes de token Bearer REST API v2', 'approved', 'igor.pita', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
('e1000001-0000-4000-8000-000000000003', 'igor.pita', 'GLPI', '12362', 'task-501', 'Chamado #12362: Criação de fila de atendimento no n8n', CURRENT_DATE - INTERVAL '1 day', 180, 'Modelagem do workflow de webhook e triagem automática de chamados', 'approved', 'igor.pita', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),

('e1000001-0000-4000-8000-000000000004', 'igor.pita', 'DECK', '1003', NULL, 'Criação das migrações do banco de dados', CURRENT_DATE, 240, 'Definição de índices, tabelas de auditoria e fila de sincronização', 'submitted', 'igor.pita', 1, NOW(), NOW()),
('e1000001-0000-4000-8000-000000000005', 'igor.pita', 'GLPI', '12377', 'task-502', 'Chamado #12377: Migração de banco de homologação PostgreSQL', CURRENT_DATE, 150, 'Dump e restore da base do GLPI e ajuste de encoding UTF8', 'submitted', 'igor.pita', 1, NOW(), NOW()),

-- Alisson Costa (Infraestrutura / Redes / GLPI)
('e2000001-0000-4000-8000-000000000001', 'alisson.costa', 'GLPI', '12345', 'task-503', 'Chamado #12345: Falha de comunicação com servidor de arquivos', CURRENT_DATE - INTERVAL '2 days', 210, 'Substituição de switch de borda no 3º andar e testes de latência', 'approved', 'alisson.costa', 1, NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),
('e2000001-0000-4000-8000-000000000002', 'alisson.costa', 'GLPI', '12350', 'task-504', 'Chamado #12350: Configuração de certificado digital token A3', CURRENT_DATE - INTERVAL '2 days', 120, 'Instalação de drivers Safenet e homologação no PJe para advogados', 'approved', 'alisson.costa', 1, NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),
('e2000001-0000-4000-8000-000000000003', 'alisson.costa', 'DECK', '1001', NULL, 'Configuração do proxy reverso Traefik', CURRENT_DATE - INTERVAL '2 days', 150, 'Configuração de VIP no Keepalived para alta disponibilidade', 'approved', 'alisson.costa', 1, NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),

('e2000001-0000-4000-8000-000000000004', 'alisson.costa', 'GLPI', '12345', 'task-505', 'Chamado #12345: Falha de comunicação com servidor de arquivos', CURRENT_DATE - INTERVAL '1 day', 240, 'Monitoramento de tráfego de rede e análise de logs do Samba', 'approved', 'alisson.costa', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
('e2000001-0000-4000-8000-000000000005', 'alisson.costa', 'DECK', '1004', NULL, 'Documentação de homologação da equipe', CURRENT_DATE - INTERVAL '1 day', 180, 'Elaboração do guia de troubleshooting para os técnicos de campo', 'approved', 'alisson.costa', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),

-- Maria Silva (Desenvolvimento & Sistemas)
('e3000001-0000-4000-8000-000000000001', 'maria.silva', 'DECK', '1002', NULL, 'Validação de permissões OAuth2 GLPI', CURRENT_DATE - INTERVAL '3 days', 300, 'Desenvolvimento do client PHP e interceptor de sessão', 'approved', 'maria.silva', 1, NOW() - INTERVAL '3 days', NOW() - INTERVAL '3 days'),
('e3000001-0000-4000-8000-000000000002', 'maria.silva', 'DECK', '1003', NULL, 'Criação das migrações do banco de dados', CURRENT_DATE - INTERVAL '3 days', 180, 'Criação das entidades Doctrine e validação de chaves estrangeiras', 'approved', 'maria.silva', 1, NOW() - INTERVAL '3 days', NOW() - INTERVAL '3 days'),

('e3000001-0000-4000-8000-000000000003', 'maria.silva', 'DECK', '1003', NULL, 'Criação das migrações do banco de dados', CURRENT_DATE - INTERVAL '2 days', 240, 'Criação das views analíticas no PostgreSQL para relatórios', 'approved', 'maria.silva', 1, NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),
('e3000001-0000-4000-8000-000000000004', 'maria.silva', 'GLPI', '12362', 'task-506', 'Chamado #12362: Criação de fila de atendimento no n8n', CURRENT_DATE - INTERVAL '2 days', 240, 'Testes de integração entre API do GLPI e Telegram Bot', 'approved', 'maria.silva', 1, NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),

-- Carlos Eduardo (Suporte Técnico N1 / N2)
('e4000001-0000-4000-8000-000000000001', 'carlos.eduardo', 'GLPI', '12350', 'task-507', 'Chamado #12350: Configuração de certificado digital token A3', CURRENT_DATE - INTERVAL '1 day', 270, 'Atendimento presencial no gabinete da presidência', 'approved', 'carlos.eduardo', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
('e4000001-0000-4000-8000-000000000002', 'carlos.eduardo', 'GLPI', '12345', 'task-508', 'Chamado #12345: Falha de comunicação com servidor de arquivos', CURRENT_DATE - INTERVAL '1 day', 210, 'Verificação física de cabos e mapeamento de unidades de rede', 'approved', 'carlos.eduardo', 1, NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day');

-- 3. Inserção de Trilha de Auditoria
INSERT INTO oc_timesheet_audit_logs (entry_id, user_uid, action, changes, created_at)
VALUES
('e1000001-0000-4000-8000-000000000001', 'igor.pita', 'CREATE', '{"after": {"durationMinutes": 180, "description": "Ajuste de certificados TLS LetsEncrypt"}}', NOW() - INTERVAL '1 day'),
('e1000001-0000-4000-8000-000000000001', 'igor.pita', 'APPROVE', '{"status": "approved"}', NOW() - INTERVAL '20 hours'),
('e2000001-0000-4000-8000-000000000001', 'alisson.costa', 'CREATE', '{"after": {"durationMinutes": 210, "description": "Substituição de switch de borda"}}', NOW() - INTERVAL '2 days');

-- 4. Inserção de Itens na Fila de Sincronização GLPI
INSERT INTO oc_timesheet_sync_queue (entry_id, target_system, sync_status, attempts, idempotency_key, created_at, synced_at)
VALUES
('e1000001-0000-4000-8000-000000000003', 'GLPI', 'synced', 1, 'glpi-idemp-key-001', NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
('e2000001-0000-4000-8000-000000000001', 'GLPI', 'synced', 1, 'glpi-idemp-key-002', NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),
('e1000001-0000-4000-8000-000000000005', 'GLPI', 'pending', 0, 'glpi-idemp-key-003', NOW(), NULL);

