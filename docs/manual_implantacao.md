# Manual de Implantação e Operação — OAB Timesheet

Este documento contém os procedimentos operacionais para implantação, migração, rotinas de backup e restauração do **OAB Timesheet** no ambiente de produção da **OAB-BA** (Docker Swarm + Portainer + Traefik).

---

## 1. Pré-Requisitos no Docker Swarm

1. **Rede Traefik Externa:**
   ```bash
   docker network create --driver=overlay --attachable traefik-public
   ```
2. **Secret com a Senha do Banco:**
   ```bash
   openssl rand -base64 24 | docker secret create pg_password -
   ```
3. **DNS Configurado:**
   - `portal.oab-ba.org.br` -> IP do cluster (Nextcloud)
   - `bi-timesheet.oab-ba.org.br` -> IP do cluster (Metabase)

---

## 2. Deploy da Stack pelo Portainer ou CLI

Pelo terminal do nó manager do Swarm:
```bash
cd docker
docker stack deploy -c docker-stack.yml oab_timesheet
```

Ou crie uma nova Stack no **Portainer** colando o conteúdo de [`docker/docker-stack.yml`](file:///Users/igorpita/dev/timesheet/docker/docker-stack.yml).

---

## 3. Habilitação e Migrações do Banco

Após os containers iniciarem:
```bash
# 1. Obter o ID do container do Nextcloud
CONTAINER_ID=$(docker ps -qf "name=oab_timesheet_nextcloud")

# 2. Habilitar o App OAB Timesheet e rodar as migrações automáticas
docker exec -u www-data $CONTAINER_ID php occ app:enable oab_timesheet

# 3. Aplicar as views analíticas no PostgreSQL para o Metabase
docker exec -i $(docker ps -qf "name=oab_timesheet_postgres") psql -U nextcloud -d nextcloud < docker/metabase_views.sql
```

---

## 4. Configuração da Integração com o GLPI 12

Para conectar com a API REST v2 do GLPI:
```bash
# Definir URL base e tokens da API
docker exec -u www-data $CONTAINER_ID php occ config:app:set oab_timesheet glpi_url --value="https://glpi.oab-ba.org.br/apirest.php"
docker exec -u www-data $CONTAINER_ID php occ config:app:set oab_timesheet glpi_app_token --value="SEU_APP_TOKEN_AQUI"
docker exec -u www-data $CONTAINER_ID php occ config:app:set oab_timesheet glpi_user_token --value="SEU_USER_TOKEN_AQUI"
```

O Background Job (`SyncGlpiJob`) roda automaticamente a cada 5 minutos pelo cron do Nextcloud.

---

## 5. Rotina de Backup e Recuperação

### Backup Diário do PostgreSQL
```bash
docker exec $(docker ps -qf "name=oab_timesheet_postgres") pg_dump -U nextcloud nextcloud | gzip > /backup/timesheet_backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Procedimento de Restauração (Disaster Recovery)
```bash
gunzip -c /backup/timesheet_backup_YYYYMMDD_HHMMSS.sql.gz | docker exec -i $(docker ps -qf "name=oab_timesheet_postgres") psql -U nextcloud -d nextcloud
```

