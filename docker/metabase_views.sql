-- ====================================================================
-- VIEWS ANALÍTICAS PARA METABASE — TIMESHEET
-- ====================================================================

-- 1. Visão Enriquecida de Apontamentos
CREATE OR REPLACE VIEW vw_timesheet_overview AS
SELECT 
    te.id,
    te.user_uid,
    te.source_type,
    te.external_id,
    te.external_title,
    te.work_date,
    EXTRACT(YEAR FROM te.work_date)::INTEGER AS work_year,
    EXTRACT(MONTH FROM te.work_date)::INTEGER AS work_month,
    TO_CHAR(te.work_date, 'YYYY-MM') AS year_month,
    EXTRACT(WEEK FROM te.work_date)::INTEGER AS work_week,
    EXTRACT(DOW FROM te.work_date)::INTEGER AS day_of_week,
    te.duration_minutes,
    ROUND(te.duration_minutes / 60.0, 2) AS duration_hours,
    te.description,
    te.status,
    te.created_at,
    te.updated_at
FROM oc_timesheet_entries te;

-- 2. Horas por Colaborador por Mês
CREATE OR REPLACE VIEW vw_timesheet_hours_by_collaborator AS
SELECT 
    user_uid,
    year_month,
    COUNT(DISTINCT work_date) AS dias_com_apontamento,
    SUM(duration_minutes) AS total_minutos,
    ROUND(SUM(duration_minutes) / 60.0, 1) AS total_horas,
    ROUND(AVG(duration_minutes) / 60.0, 1) AS media_horas_por_registro
FROM vw_timesheet_overview
GROUP BY user_uid, year_month
ORDER BY year_month DESC, total_horas DESC;

-- 3. Comparativo: Projetos (Deck) vs. Operacional (GLPI)
CREATE OR REPLACE VIEW vw_timesheet_projects_vs_operational AS
SELECT 
    year_month,
    source_type,
    CASE 
        WHEN source_type = 'DECK' THEN 'Projetos (Deck)'
        WHEN source_type = 'GLPI' THEN 'Operacional / Chamados (GLPI)'
        ELSE source_type
    END AS categoria_macro,
    COUNT(id) AS total_registros,
    ROUND(SUM(duration_minutes) / 60.0, 1) AS total_horas,
    ROUND(
        (SUM(duration_minutes)::NUMERIC / NULLIF(SUM(SUM(duration_minutes)) OVER (PARTITION BY year_month), 0)) * 100, 
        1
    ) AS percentual_esforco
FROM vw_timesheet_overview
GROUP BY year_month, source_type
ORDER BY year_month DESC, source_type ASC;

-- 4. Detalhamento de Horas por Projeto (Deck)
CREATE OR REPLACE VIEW vw_timesheet_deck_breakdown AS
SELECT 
    external_id AS card_id,
    COALESCE(external_title, CONCAT('Cartão #', external_id)) AS card_title,
    COUNT(DISTINCT user_uid) AS colaboradores_envolvidos,
    COUNT(id) AS total_apontamentos,
    ROUND(SUM(duration_minutes) / 60.0, 1) AS total_horas,
    MIN(work_date) AS primeiro_apontamento,
    MAX(work_date) AS ultimo_apontamento
FROM vw_timesheet_overview
WHERE source_type = 'DECK'
GROUP BY external_id, external_title
ORDER BY total_horas DESC;

-- 5. Detalhamento de Horas por Chamado (GLPI)
CREATE OR REPLACE VIEW vw_timesheet_glpi_breakdown AS
SELECT 
    external_id AS ticket_id,
    COALESCE(external_title, CONCAT('Chamado #', external_id)) AS ticket_title,
    user_uid AS tecnico_responsavel,
    COUNT(id) AS total_atendimentos,
    ROUND(SUM(duration_minutes) / 60.0, 1) AS total_horas,
    MIN(work_date) AS data_inicio_atendimento,
    MAX(work_date) AS data_ultimo_atendimento
FROM vw_timesheet_overview
WHERE source_type = 'GLPI'
GROUP BY external_id, external_title, user_uid
ORDER BY total_horas DESC;

-- 6. Taxa de Cobertura de Preenchimento (Compliance de Dias Úteis)
CREATE OR REPLACE VIEW vw_timesheet_coverage_compliance AS
SELECT 
    user_uid,
    year_month,
    COUNT(DISTINCT work_date) AS dias_apontados,
    -- Estimativa de 22 dias úteis médios por mês
    ROUND((COUNT(DISTINCT work_date)::NUMERIC / 22.0) * 100, 1) AS taxa_cobertura_percentual,
    ROUND(SUM(duration_minutes) / 60.0, 1) AS total_horas_mes
FROM vw_timesheet_overview
WHERE day_of_week BETWEEN 1 AND 5 -- Segunda a Sexta
GROUP BY user_uid, year_month
ORDER BY year_month DESC, taxa_cobertura_percentual DESC;

