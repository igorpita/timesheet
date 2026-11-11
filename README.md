# Timesheet — Apontamento de Horas Integrado ao Nextcloud Deck

Aplicação de apontamento, acompanhamento e consolidação de esforço integrada ao **Nextcloud Deck**.
**Autor:** Igor Pita

---

## 🎯 Princípios Fundamentais

1. **Fonte Única da Verdade:**
   - **Nextcloud Deck:** Projetos e tarefas oficiais.
   - O **Timesheet** não cria nem duplica tarefas; apenas registra e consolida o esforço (`time_entries`) referenciando os cartões de projeto.
2. **Sem Senhas Paralelas:** Autenticação centralizada e nativa através da sessão do Nextcloud.
3. **Agilidade no Fluxo de Trabalho:** Apontamento direto dentro do card do Deck ou na interface centralizada do app.
4. **Inteligência Gerencial:** Base PostgreSQL pronta para relatórios analíticos nativos e visualizações no **Metabase**.

---

## 🗂️ Estrutura do Projeto

```
timesheet/
├── appinfo/
│   ├── info.xml            # Metadados e dependências do App Nextcloud
│   └── routes.php          # Rotas de navegação e APIs REST v1
├── lib/
│   ├── AppInfo/
│   │   └── Application.php # Container de injeção de dependência e bootstrap
│   ├── Controller/
│   │   ├── PageController.php          # Renderização da SPA
│   │   ├── TimeEntryApiController.php  # CRUD de apontamentos com auditoria
│   │   ├── DeckApiController.php       # Consulta de quadros e cartões Deck
│   │   ├── GlpiApiController.php       # Pesquisa de chamados e fila GLPI
│   │   └── TeamApiController.php       # Visão consolidada para gestores
│   ├── Db/
│   │   ├── TimeEntry.php & Mapper.php  # Entidade de registro de horas
│   │   ├── AuditLog.php & Mapper.php   # Trilha de auditoria completa
│   │   └── SyncQueue.php & Mapper.php  # Fila de sincronização GLPI
│   ├── Migration/
│   │   └── Version000001Date...php     # Schema do banco de dados (Doctrine)
│   └── Service/
│       ├── TimeEntryService.php        # Regras de negócio e idempotência
│       └── SecurityService.php         # Controle de perfis e permissões ACL
├── templates/
│   └── main.php            # Ponto de montagem da aplicação no Nextcloud
├── src/                    # Frontend SPA (Vue 3 + Axios)
│   ├── components/         # Modais de apontamento manual e histórico
│   ├── views/              # Meu Timesheet, Projetos Deck, Chamados GLPI, Equipe, Relatórios
│   ├── services/api.js     # Cliente API com CSRF token do Nextcloud
│   ├── App.vue             # Componente raiz com navegação por abas
│   └── main.js             # Ponto de entrada do bundle
├── css/
│   └── app.css             # Estilização moderna alinhada ao Nextcloud
├── js/
│   └── oab-timesheet-main.js # Bundle compilado pronto para execução
├── docker/
│   ├── docker-compose.yml  # Ambiente local: Nextcloud 30 + PostgreSQL 16 + Metabase
│   └── init-db.sql         # Script de inicialização do banco
└── especificacoes_iniciais.md # Documento de referência original
```

---

## 🚀 Como Executar em Desenvolvimento

### 1. Compilar o Frontend (Vue 3)
```bash
# Instalar dependências
npm install

# Compilar bundle de produção
npm run build

# Ou modo watch durante desenvolvimento da interface
npm run dev
```

### 2. Subir o Ambiente Docker Local
```bash
cd docker
docker compose up -d
```

O ambiente disponibiliza:
- **Nextcloud:** [http://localhost:8080](http://localhost:8080) (usuário `admin` / senha `admin_secret`)
- **PostgreSQL 16:** Porta `5432`
- **Metabase BI:** [http://localhost:3000](http://localhost:3000)

### 3. Habilitar o Aplicativo no Nextcloud
Dentro do container Nextcloud (ou pelo painel de Apps):
```bash
docker exec -u www-data oab_timesheet_nextcloud php occ app:enable oab_timesheet
```

---

## 📊 Status das Entregas (Roadmap de 6 Sprints)

- [x] **Sprint 1 — Fundação, Arquitetura e Estrutura do App Nextcloud** (Concluído)
- [x] **Sprint 2 — Integração Avançada com Deck API & ACLs** (Concluído)
- [x] **Sprint 3 — Motor de Apontamento Avançado, Visão Semanal em Grade e Auditoria** (Concluído)
- [x] **Sprint 4 — Integração GLPI 12 (Cliente REST v2, Idempotência e Worker de Sincronização)** (Concluído)
- [x] **Sprint 5 — PoC de Integração Visual no Deck e Views Analíticas Metabase** (Concluído)
- [x] **Sprint 6 — Homologação, Guia de Operação e Deploy de Produção (Docker Swarm + Portainer)** (Concluído)

