#!/bin/bash
# ====================================================================
# SCRIPT DE TESTE AUTOMATIZADO DAS APIS — TIMESHEET
# ====================================================================

BASE_URL="http://localhost:8080/index.php/apps/oab_timesheet/api/v1"
NC_USER="admin"
NC_PASS="admin_secret"

echo "🧪 Iniciando testes das APIs do Timesheet (por Igor Pita)..."
echo "Servidor: $BASE_URL"

# 1. Teste de Listagem de Apontamentos
echo -e "\n1. Testando GET /entries..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/entries" | grep -q "success" && echo "✅ OK: Listagem de apontamentos funcionando." || echo "❌ Erro ao listar apontamentos."

# 2. Teste de Criação de Apontamento (Deck)
echo -e "\n2. Testando POST /entries (Apontamento Deck)..."
CREATE_PAYLOAD='{"sourceType":"DECK","externalId":"1001","externalTitle":"Configuração do proxy reverso Traefik","workDate":"'$(date +%Y-%m-%d)'","durationMinutes":90,"description":"Teste automatizado de registro de esforço"}'
RES_CREATE=$(curl -s -u "$NC_USER:$NC_PASS" -X POST -H "Content-Type: application/json" -H "OCS-APIREQUEST: true" -d "$CREATE_PAYLOAD" "$BASE_URL/entries")
echo "$RES_CREATE" | grep -q "success" && echo "✅ OK: Apontamento criado com sucesso." || echo "❌ Erro ao criar apontamento: $RES_CREATE"

# 3. Teste de Validação de Limite de 24h
echo -e "\n3. Testando Validação de Limite Máximo de 24h..."
INVALID_PAYLOAD='{"sourceType":"DECK","externalId":"1001","workDate":"'$(date +%Y-%m-%d)'","durationMinutes":1500,"description":"Tentativa inválida de lançar 25 horas"}'
RES_INVALID=$(curl -s -u "$NC_USER:$NC_PASS" -X POST -H "Content-Type: application/json" -H "OCS-APIREQUEST: true" -d "$INVALID_PAYLOAD" "$BASE_URL/entries")
echo "$RES_INVALID" | grep -q "24 horas" && echo "✅ OK: Trava de 24 horas bloqueou corretamente o lançamento inválido." || echo "⚠️ Aviso: Resposta inesperada: $RES_INVALID"

# 4. Teste de Consulta de Quadros Deck
echo -e "\n4. Testando GET /deck/boards..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/deck/boards" | grep -q "success" && echo "✅ OK: Consulta de quadros do Deck funcionando." || echo "❌ Erro ao consultar quadros."

# 5. Teste de Pesquisa de Chamados GLPI
echo -e "\n5. Testando GET /glpi/tickets..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/glpi/tickets?q=servidor" | grep -q "success" && echo "✅ OK: Pesquisa de chamados do GLPI funcionando." || echo "❌ Erro ao pesquisar chamados GLPI."

# 6. Teste de Fila de Sincronização GLPI
echo -e "\n6. Testando GET /glpi/sync-status..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/glpi/sync-status" | grep -q "success" && echo "✅ OK: Fila de sincronização ativa." || echo "❌ Erro ao checar sincronização."

# 7. Teste de Indicadores de Gestão (Equipe)
echo -e "\n7. Testando GET /team/summary..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/team/summary" | grep -q "success" && echo "✅ OK: Resumo gerencial consolidado funcionando." || echo "❌ Erro ao consultar resumo da equipe."

# 8. Teste de Histórico de Cartão Deck
echo -e "\n8. Testando GET /deck/cards/7/history..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/deck/cards/7/history" | grep -q "success" && echo "✅ OK: Histórico do cartão do Deck funcionando." || echo "❌ Erro ao consultar histórico do cartão."

# 9. Teste de Relatórios Analíticos e Sumarizados
echo -e "\n9. Testando GET /team/reports..."
curl -s -u "$NC_USER:$NC_PASS" -H "OCS-APIREQUEST: true" "$BASE_URL/team/reports" | grep -q "byProject" && echo "✅ OK: Relatórios analíticos e sumarizados funcionando." || echo "❌ Erro ao consultar relatórios da equipe."

# 10. Teste de Bloqueio de Apontamento com Data Futura
echo -e "\n10. Testando Bloqueio de Apontamento em Data Futura..."
TOMORROW=$(date -v+1d +%Y-%m-%d 2>/dev/null || date -d "tomorrow" +%Y-%m-%d 2>/dev/null || echo "2099-01-01")
FUTURE_PAYLOAD='{"sourceType":"DECK","externalId":"1001","externalTitle":"Demanda Futura","workDate":"'"$TOMORROW"'","durationMinutes":60,"description":"Tentativa inválida de lançar horas no futuro"}'
RES_FUTURE=$(curl -s -u "$NC_USER:$NC_PASS" -X POST -H "Content-Type: application/json" -H "OCS-APIREQUEST: true" -d "$FUTURE_PAYLOAD" "$BASE_URL/entries")
echo "$RES_FUTURE" | grep -q "data futura" && echo "✅ OK: Bloqueio de data futura funcionando (rejeitado com sucesso)." || echo "❌ Erro: Apontamento futuro não foi bloqueado: $RES_FUTURE"

echo -e "\n🎉 Bateria de testes concluída!"

