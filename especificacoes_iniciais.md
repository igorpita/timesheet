# Especificação técnica — Timesheet integrado ao Nextcloud Deck e GLPI



Arquitetura proposta

Projeto de desenvolvimento



Igor, com as decisões tomadas, eu seguiria com o desenvolvimento de uma aplicação própria, chamada provisoriamente de OAB Timesheet.

A proposta é criar uma aplicação complementar ao Nextcloud, e não uma nova ferramenta de gestão de projetos.

O princípio fundamental será:

O Deck e o GLPI continuam sendo os sistemas oficiais de origem das atividades. O OAB Timesheet será responsável por registrar, consolidar e disponibilizar o esforço realizado pelos colaboradores.

Isso resolve os três problemas centrais:

- Não haverá duplicidade de projetos e tarefas.
- O colaborador terá uma experiência de apontamento dentro do Deck e também em um portal independente.
- As horas de projetos e chamados serão consolidadas em uma única base gerencial.

Vou estruturar a especificação como um documento de projeto que poderá ser utilizado como referência para desenvolvimento, contratação ou implementação com auxílio de IA.

# 1. Visão geral da solução

Nome: OAB Timesheet

Objetivo: permitir que os colaboradores do Departamento de Informática da OAB-BA registrem manualmente o tempo dedicado às atividades de projetos e às demandas operacionais, com integração ao Nextcloud Deck e GLPI 12.

## Arquitetura lógica

Nextcloud Deck

Projetos e tarefas

GLPI 12

Chamados e tarefas

OAB Timesheet

Portal independente + integração nos cartões Deck

PostgreSQL

Registros, auditoria, configurações e consolidação

Metabase

Painéis e indicadores de gestão

# 2. Requisitos funcionais

## RF01 — Autenticação e identificação

A aplicação deverá utilizar a identidade do usuário do Nextcloud.

Requisitos:

- Login pelo usuário autenticado no Nextcloud.
- Não criar uma segunda senha.
- Identificar o usuário pelo UID do Nextcloud.
- Recuperar nome e e-mail do perfil.
- Controlar permissões por perfil.
- Registrar quem criou, editou ou aprovou cada apontamento.

Minha recomendação é utilizar autenticação federada por OAuth 2.0/OpenID Connect, caso a configuração disponível permita, ou integração por mecanismo de autenticação próprio de aplicativo Nextcloud.

Não armazenar senhas dos colaboradores.

## RF02 — Integração com Nextcloud Deck

O sistema deverá consultar os quadros e cartões existentes, sem reproduzir seus cadastros.

Informações necessárias:

| Campo               | Origem |
| ------------------- | ------ |
| ID do quadro        | Deck   |
| Nome do quadro      | Deck   |
| ID do cartão        | Deck   |
| Título do cartão    | Deck   |
| Descrição           | Deck   |
| Responsáveis        | Deck   |
| Lista/coluna        | Deck   |
| Situação            | Deck   |
| Data de criação     | Deck   |
| Data de atualização | Deck   |

O sistema deverá permitir:

- Consultar os quadros disponíveis.
- Consultar os cartões dos quadros.
- Identificar os responsáveis pelos cartões.
- Respeitar os compartilhamentos e permissões.
- Pesquisar cartões por título.
- Registrar horas em cartões existentes.
- Consultar o histórico de apontamentos por cartão.

Regra fundamental: o Timesheet não poderá criar projetos ou tarefas paralelas para representar os cartões do Deck.

O relacionamento será feito por identificadores externos, por exemplo:

`deck_board_id + deck_card_id`

Também recomendo guardar a identificação do servidor Nextcloud, para evitar colisões se futuramente houver mais de uma instância.

# 3. Integração visual com o Deck

Encontrei um antecedente técnico muito relevante para esse projeto.

Existe um projeto chamado Deck Time Tracking, desenvolvido especificamente como complemento do Nextcloud Deck. Ele implementava:

- Botão de iniciar/parar na própria tarefa.
- Aba de Timesheet dentro dos detalhes do cartão.
- Associação do registro ao usuário.
- Permissão de apontamento para responsáveis e usuários com permissão de edição/gestão do quadro.
- Histórico de horas relacionado ao cartão.

[All apps - App Store - Nextcloud](https://images.openai.com/static-rsc-4/ZRe9DZrmy2msibsEzKwEAGvuV8P1GVbVsyisNyPg_ypy1ZKnvFciE0Q0Po43t4sVljg5O7XaG-2LB2h7Tr4hBces0F8JOtTS9G9GyoNW1yyvt-2cJw6QSM0lqqPaP1s53RzlWfQUU_JYLeGSe4otChPPFJ7kZaijlN3k0CFKjb0?purpose=inline)

Deck Time Tracking

Projeto arquivado

Código-fonte e referência técnica:

[GitHub — Deck Time Tracking](https://github.com/rallisf1/nextcloud-deck-time-tracking?utm_source=chatgpt.com)

O projeto foi descontinuado pelo desenvolvedor. Portanto, não recomendo instalá-lo diretamente em produção. Entretanto, é uma excelente referência para a experiência que queremos construir.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://github.com\&sz=32)

GitHub



Isso também reforça minha recomendação de desenvolver uma aplicação complementar, aproveitando o conceito, mas com arquitetura atualizada, suporte ao Nextcloud 33 e integração com GLPI.

## 3.1. Duas interfaces, uma única base

Interface A — dentro do Deck

Adicionar uma aba ou seção de apontamento no cartão:

- Total de horas já registradas.
- Botão "Registrar horas".
- Formulário de lançamento manual.
- Histórico por colaborador.
- Total acumulado da tarefa.

Interface B — portal independente

Criar uma aplicação acessível pelo menu do Nextcloud, por exemplo:

`https://portal.oab-ba.org.br/apps/oab-timesheet/`

O portal terá cinco módulos:

| Módulo        | Funcionalidade                               |
| ------------- | -------------------------------------------- |
| Meu Timesheet | Meus registros diários e semanais            |
| Projetos      | Consulta de quadros e cartões do Deck        |
| Chamados      | Pesquisa e apontamento de atividades do GLPI |
| Equipe        | Consulta gerencial dos registros             |
| Relatórios    | Consolidação de horas e indicadores          |

A interface independente também será útil para quem precisa registrar uma sequência de atividades sem abrir cada cartão individualmente.

# 4. Especificação do módulo GLPI

Aqui existe uma decisão importante: o portal será uma segunda interface para apontamento dos chamados, mas não criará chamados nem substituirá o fluxo de atendimento.

## RF03 — Integração com GLPI 12

O colaborador poderá pesquisar chamados existentes diretamente pelo portal.

Campos disponíveis para pesquisa:

- Número do chamado.
- Título.
- Solicitante.
- Técnico responsável.
- Status.
- Data de abertura.

O lançamento deverá conter:

| Campo                  | Origem                    |
| ---------------------- | ------------------------- |
| Chamado                | GLPI                      |
| ID do chamado          | GLPI                      |
| Técnico                | Usuário autenticado       |
| Data da atividade      | Colaborador               |
| Duração                | Colaborador               |
| Descrição              | Colaborador               |
| Categoria da atividade | Configuração do Timesheet |

### Exemplo de utilização

Novo apontamento — Chamado GLPI

Exemplo ilustrativo

Chamado #12345

Falha de comunicação com servidor de arquivos

Técnico

Alisson

Data

10/11/2026

Duração

01h30

Descrição do trabalho\
Análise da conectividade e testes de acesso ao compartilhamento.

Registro de exemplo

### Regra de integração

Minha preferência é que o portal seja a interface de lançamento, mas que o registro também seja enviado ao GLPI, associado à tarefa do chamado.

Assim, o GLPI continuará contendo o histórico operacional completo e o Timesheet manterá uma base consolidada para os relatórios.

Há um ponto que exige atenção: precisamos validar a API REST v2 do GLPI 12 para criação e consulta de tarefas, identificação do técnico e tempo efetivamente realizado. A documentação oficial descreve a API de alto nível e sua autenticação OAuth 2.0.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://help.glpi-project.org\&sz=32)

Help Center GLPI+1



Se houver limitações na API para algum tipo de registro, teremos uma camada adaptadora para o mecanismo disponível, sem escrever diretamente nas tabelas internas do GLPI.

Regra contra duplicidade: um lançamento feito pelo portal não poderá ser contado novamente caso seja sincronizado ou importado do GLPI. Cada registro terá um identificador de origem e uma chave de idempotência.

# 5. Modelo de dados

A aplicação terá banco PostgreSQL próprio, separado do GLPI e do Nextcloud.

A regra é armazenar somente o que for necessário para apontamento, relacionamentos externos e auditoria.

Proponho as seguintes entidades:

Estrutura lógica do banco

users

Usuários do Nextcloud, identificados pelo UID.

time_entries

Registro principal: usuário, data, duração, descrição, origem e situação.

external_references

Referência ao quadro/cartão Deck ou chamado/tarefa GLPI.

project_memberships

Relacionamento entre usuário e projeto, derivado das permissões do Deck ou configurado para projetos administrativos.

audit_logs

Histórico de inclusões, alterações, aprovações e cancelamentos.

integration_sync

Controle de sincronização, erros, última atualização e identificadores externos.

### Estrutura principal de `time_entries`

| Campo            | Tipo      | Descrição                                |
| ---------------- | --------- | ---------------------------------------- |
| id               | UUID      | Identificador único                      |
| user_uid         | VARCHAR   | UID Nextcloud                            |
| source_type      | VARCHAR   | DECK ou GLPI                             |
| external_id      | VARCHAR   | ID do cartão ou chamado                  |
| external_task_id | VARCHAR   | ID da tarefa GLPI, quando aplicável      |
| work_date        | DATE      | Data da atividade                        |
| duration_minutes | INTEGER   | Duração em minutos                       |
| description      | TEXT      | Trabalho realizado                       |
| status           | VARCHAR   | Rascunho, enviado, aprovado ou rejeitado |
| created_at       | TIMESTAMP | Criação                                  |
| updated_at       | TIMESTAMP | Atualização                              |

Eu incluiria também:

- `created_by`
- `updated_by`
- `approved_by`
- `approved_at`
- `origin_entry_id`, para rastrear sincronizações
- `version`, para controle de concorrência

Importante: o identificador externo não poderá ser utilizado isoladamente. O relacionamento precisa considerar também a instância de origem e o tipo de objeto.

# 6. Controle de acesso e permissões

Esse é um dos pontos mais importantes do projeto.

O Timesheet não deve permitir que um colaborador visualize ou registre horas em projetos aos quais não possui acesso.

### Matriz de permissões proposta

| Ação                               | Colaborador   | Gestor TI | Administrador |
| ---------------------------------- | ------------- | --------- | ------------- |
| Registrar próprias horas           | Sim           | Sim       | Sim           |
| Consultar próprias horas           | Sim           | Sim       | Sim           |
| Editar próprias horas              | Sim           | Sim       | Sim           |
| Consultar horas da equipe          | Não           | Sim       | Sim           |
| Consultar todos os projetos        | Conforme Deck | Sim       | Sim           |
| Corrigir apontamentos de terceiros | Não           | Sim\*     | Sim           |
| Exportar relatórios                | Não           | Sim       | Sim           |
| Configurar integrações             | Não           | Não       | Sim           |

Com justificativa e trilha de auditoria.

### Regras de autorização

Para o Deck:

- O sistema consulta as permissões do quadro.
- Respeita as ACLs de usuários, grupos e compartilhamentos.
- Verifica a atribuição do cartão.
- Aplica a política de apontamento definida pela TI.

Minha sugestão é permitir apontamento aos responsáveis pelo cartão e aos membros autorizados do projeto, com possibilidade de configurar exceções.

Para o GLPI:

- O colaborador só registra em nome do próprio usuário autenticado.
- A integração verifica se ele é técnico associado ao chamado ou pertence a um grupo autorizado.
- O gestor possui acesso gerencial sem alterar a autoria original.

Não utilizaria uma conta administrativa irrestrita do GLPI para todos os lançamentos. O mecanismo de integração deverá preservar a identidade do técnico e validar suas permissões.

# 7. Arquitetura de desenvolvimento

Considerando sua infraestrutura atual, eu aproveitaria o ambiente Docker Swarm, Portainer, Traefik e PostgreSQL.

Minha sugestão:

| Componente               | Tecnologia                      |
| ------------------------ | ------------------------------- |
| Aplicação principal      | Nextcloud App Framework (PHP)   |
| Interface                | Vue.js                          |
| API interna              | OCS API / rotas da aplicação    |
| Banco                    | PostgreSQL                      |
| Integração Deck          | REST API do Deck                |
| Integração GLPI          | REST API v2                     |
| Autenticação             | Sessão autenticada do Nextcloud |
| Processamento assíncrono | Nextcloud Background Jobs       |
| Integrações adicionais   | n8n, quando necessário          |
| Relatórios               | Metabase                        |
| Implantação              | Docker Swarm + Portainer        |

### Por que uma aplicação nativa do Nextcloud?

Porque você já possui a infraestrutura e a autenticação centralizadas.

Uma aplicação nativa permite:

- Aparecer no menu de aplicativos.
- Utilizar a sessão do usuário.
- Reaproveitar o mecanismo de permissões.
- Ser instalada e atualizada pelo mecanismo de aplicativos do Nextcloud.
- Integrar a interface ao ambiente visual existente.

Não criaria outro serviço de autenticação.

### Uma ressalva técnica sobre o Deck

A API REST do Deck permite consultar quadros, cartões e permissões de compartilhamento. A documentação descreve inclusive a ACL de quadros e a associação de usuários e grupos.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://github.com\&sz=32)

GitHub+1



Entretanto, não devemos presumir que exista uma API pública e estável para adicionar uma nova aba à tela de detalhes dos cartões.

Por isso, proponho uma prova de conceito específica para a interface integrada antes de decidir como implementá-la:

1. Verificar os pontos de extensão disponíveis na versão do Deck instalada.
2. Testar a inclusão de uma ação contextual no cartão.
3. Evitar alterações diretas nos arquivos originais do Deck.
4. Se não houver extensão sustentável, desenvolver um módulo de interface complementar versionado, com testes de compatibilidade a cada atualização.

A aplicação independente continuará funcionando mesmo que a integração visual precise ser atualizada.

# 8. Fluxo técnico de funcionamento

\#chatgpt-mermaid-\_r_mm\_{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;fill:rgb(237, 237, 237);}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#chatgpt-mermaid-\_r_mm\_ .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_mm\_ .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_mm\_ .error-icon{fill:rgb(48, 48, 48);}#chatgpt-mermaid-\_r_mm\_ .error-text{fill:rgb(237, 237, 237);stroke:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ .edge-thickness-normal{stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .edge-thickness-thick{stroke-width:3.5px;}#chatgpt-mermaid-\_r_mm\_ .edge-pattern-solid{stroke-dasharray:0;}#chatgpt-mermaid-\_r_mm\_ .edge-thickness-invisible{stroke-width:0;fill:none;}#chatgpt-mermaid-\_r_mm\_ .edge-pattern-dashed{stroke-dasharray:3;}#chatgpt-mermaid-\_r_mm\_ .edge-pattern-dotted{stroke-dasharray:2;}#chatgpt-mermaid-\_r_mm\_ .marker{fill:rgb(175, 175, 175);stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_mm\_ .marker.cross{stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_mm\_ svg{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;}#chatgpt-mermaid-\_r_mm\_ p{margin:0;}#chatgpt-mermaid-\_r_mm\_ .label{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ .cluster-label text{fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ .cluster-label span{color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ .cluster-label span p{background-color:transparent;}#chatgpt-mermaid-\_r_mm\_ .label text,#chatgpt-mermaid-\_r_mm\_ span{fill:rgb(237, 237, 237);color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ .node rect,#chatgpt-mermaid-\_r_mm\_ .node circle,#chatgpt-mermaid-\_r_mm\_ .node ellipse,#chatgpt-mermaid-\_r_mm\_ .node polygon,#chatgpt-mermaid-\_r_mm\_ .node path{fill:rgb(9, 23, 44);stroke:rgb(31, 78, 148);stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .rough-node .label text,#chatgpt-mermaid-\_r_mm\_ .node .label text,#chatgpt-mermaid-\_r_mm\_ .image-shape .label,#chatgpt-mermaid-\_r_mm\_ .icon-shape .label{text-anchor:middle;}#chatgpt-mermaid-\_r_mm\_ .node .katex path{fill:#000;stroke:#000;stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .rough-node .label,#chatgpt-mermaid-\_r_mm\_ .node .label,#chatgpt-mermaid-\_r_mm\_ .image-shape .label,#chatgpt-mermaid-\_r_mm\_ .icon-shape .label{text-align:center;}#chatgpt-mermaid-\_r_mm\_ .node.clickable{cursor:pointer;}#chatgpt-mermaid-\_r_mm\_ .root .anchor path{fill:rgb(175, 175, 175)!important;stroke-width:0;stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_mm\_ .arrowheadPath{fill:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_mm\_ .edgePath .path{stroke:rgb(175, 175, 175);stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .flowchart-link{stroke:rgb(175, 175, 175);fill:none;}#chatgpt-mermaid-\_r_mm\_ .edgeLabel{background-color:rgb(0, 0, 0);text-align:center;}#chatgpt-mermaid-\_r_mm\_ .edgeLabel p{background-color:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_mm\_ .edgeLabel rect{opacity:0.5;background-color:rgb(0, 0, 0);fill:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_mm\_ .labelBkg{background-color:rgba(0, 0, 0, 0.5);}#chatgpt-mermaid-\_r_mm\_ .cluster rect{fill:rgb(48, 48, 48);stroke:rgba(255, 255, 255, 0.15);stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .cluster text{fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ .cluster span{color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ div.mermaidTooltip{position:absolute;text-align:center;max-width:200px;padding:2px;font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:12px;background:rgb(48, 48, 48);border:1px solid rgba(255, 255, 255, 0.15);border-radius:2px;pointer-events:none;z-index:100;}#chatgpt-mermaid-\_r_mm\_ .flowchartTitleText{text-anchor:middle;font-size:18px;fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_mm\_ rect.text{fill:none;stroke-width:0;}#chatgpt-mermaid-\_r_mm\_ .icon-shape,#chatgpt-mermaid-\_r_mm\_ .image-shape{background-color:rgb(0, 0, 0);text-align:center;}#chatgpt-mermaid-\_r_mm\_ .icon-shape p,#chatgpt-mermaid-\_r_mm\_ .image-shape p{background-color:rgb(0, 0, 0);padding:2px;}#chatgpt-mermaid-\_r_mm\_ .icon-shape .label rect,#chatgpt-mermaid-\_r_mm\_ .image-shape .label rect{opacity:0.5;background-color:rgb(0, 0, 0);fill:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_mm\_ .label-icon{display:inline-block;height:1em;overflow:visible;vertical-align:-0.125em;}#chatgpt-mermaid-\_r_mm\_ .node .label-icon path{fill:currentColor;stroke:revert;stroke-width:revert;}#chatgpt-mermaid-\_r_mm\_ .node .neo-node{stroke:rgb(31, 78, 148);}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node rect,#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].cluster rect,#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node polygon{stroke:url(#chatgpt-mermaid-\_r_mm\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].swimlane.cluster rect{filter:none;}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node path{stroke:url(#chatgpt-mermaid-\_r_mm\_-gradient);stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node .outer-path{filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node .neo-line path{stroke:rgb(31, 78, 148);filter:none;}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node circle{stroke:url(#chatgpt-mermaid-\_r_mm\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].node circle .state-start{fill:#000000;}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].icon-shape .icon{fill:url(#chatgpt-mermaid-\_r_mm\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_mm\_ [data-look="neo"].icon-shape .icon-neo path{stroke:url(#chatgpt-mermaid-\_r_mm\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_mm\_ .node text{font-size:14px;font-weight:600;letter-spacing:normal;fill:rgb(153, 206, 255);}#chatgpt-mermaid-\_r_mm\_ .edgeLabels text{font-size:13px;font-weight:600;letter-spacing:-0.08px;fill:rgb(153, 206, 255);}#chatgpt-mermaid-\_r_mm\_ .node tspan[font-weight="normal"],#chatgpt-mermaid-\_r_mm\_ .edgeLabels tspan[font-weight="normal"]{font-weight:600;}#chatgpt-mermaid-\_r_mm\_ .edgeLabel .label rect{opacity:1;rx:13px;ry:13px;fill:rgb(0, 14, 26);stroke:rgb(26, 62, 95);stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .node rect,#chatgpt-mermaid-\_r_mm\_ .node circle,#chatgpt-mermaid-\_r_mm\_ .node ellipse,#chatgpt-mermaid-\_r_mm\_ .node polygon,#chatgpt-mermaid-\_r_mm\_ .node path{fill:rgb(0, 40, 77);stroke:rgba(255, 255, 255, 0.1);stroke-width:1px;}#chatgpt-mermaid-\_r_mm\_ .node rect{rx:16px;ry:16px;}#chatgpt-mermaid-\_r_mm\_ .mermaid-decision .label-container{fill:rgb(0, 14, 26);stroke:rgb(26, 62, 95);stroke-dasharray:2,2;}#chatgpt-mermaid-\_r_mm\_ .edgePaths .flowchart-link{stroke:rgb(175, 175, 175);stroke-width:1px;stroke-linecap:round;stroke-linejoin:round;}#chatgpt-mermaid-\_r_mm\_ .marker{fill:rgb(175, 175, 175);stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_mm\_ :root{--mermaid-font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";}Colaborador autenticado noNextcloudOnde deseja apontar?Seleciona quadro e cartão doDeckPesquisa chamado no GLPIValida ACL e atribuiçãoValida técnico e permissõesFormulário de apontamentoGrava registro noPostgreSQLOrigemRelacionamento com cartãoSincronização da tarefa noGLPIConsolidação gerencialMetabaseProjetoChamadoDeckGLPI

O lançamento manual não terá cronômetro. O colaborador informará data e duração, por exemplo, 1h30.

Para os chamados, recomendo que o portal grave primeiro o registro em uma fila transacional, com estado de sincronização. Só depois de confirmação do GLPI o lançamento será marcado como sincronizado.

Isso evita que uma falha de comunicação produza registros aparentemente concluídos que não foram gravados no sistema operacional.

# 9. Plano de desenvolvimento

Eu dividiria o desenvolvimento em seis sprints, com entregas verificáveis.

1. Sprint 1 — Fundação e autenticação

   Semana 1
   - Criar repositório Git.
   - Estruturar aplicação Nextcloud.
   - Implementar autenticação.
   - Criar banco PostgreSQL e migrations.
   - Implementar perfis de acesso.
   - Preparar ambiente Docker de homologação.
   Entrega: aplicação instalada no Nextcloud, com login e banco funcionando.
2. Sprint 2 — Integração com Deck

   Semana 2
   - Implementar cliente REST do Deck.
   - Consultar quadros e cartões.
   - Consultar responsáveis e ACLs.
   - Implementar pesquisa de cartões.
   - Implementar relacionamento com os registros de horas.
   Entrega: colaborador consegue selecionar cartões reais do Deck.
3. Sprint 3 — Motor de apontamento

   Semana 3
   - Criar formulário de lançamento.
   - Implementar histórico.
   - Criar edição e cancelamento.
   - Implementar regras de duração.
   - Criar auditoria.
   - Implementar visão semanal.
   Entrega: Timesheet funcional para projetos.
4. Sprint 4 — Integração GLPI

   Semana 4
   - Implementar autenticação na API GLPI.
   - Pesquisar chamados.
   - Consultar técnicos e tarefas.
   - Implementar lançamento pelo portal.
   - Sincronizar o apontamento com o GLPI.
   - Tratar erros, repetição e duplicidade.
   Entrega: apontamento de projetos e chamados em uma única aplicação.
5. Sprint 5 — Integração visual e gestão

   Semana 5
   - Implementar acesso contextual pelo Deck.
   - Criar painel gerencial.
   - Criar filtros por período, projeto e colaborador.
   - Preparar consultas para Metabase.
   - Testar permissões e auditoria.
   Entrega: portal e interface integrada ao Deck.
6. Sprint 6 — Homologação e produção

   Semana 6
   - Testes com equipe piloto.
   - Ajustes de usabilidade.
   - Testes de carga e segurança.
   - Documentação técnica.
   - Backup e procedimento de recuperação.
   - Implantação geral.
   Entrega: OAB Timesheet em produção.

As seis semanas são uma estimativa de planejamento, condicionada à disponibilidade das APIs e à viabilidade da integração visual com o Deck.

# 10. Critérios de aceite

Eu não consideraria o projeto concluído sem validar estes cenários:

| Teste                          | Resultado esperado                      |
| ------------------------------ | --------------------------------------- |
| Usuário abre o portal          | Login automático pelo Nextcloud         |
| Usuário consulta projetos      | Somente quadros autorizados             |
| Usuário seleciona cartão       | Cartão original do Deck                 |
| Usuário registra horas         | Registro associado ao seu UID           |
| Usuário consulta histórico     | Somente seus registros                  |
| Gestor consulta equipe         | Visão consolidada                       |
| Usuário registra chamado       | Chamado original do GLPI                |
| GLPI indisponível              | Registro fica pendente de sincronização |
| Sincronização repetida         | Não gera duplicidade                    |
| Cartão arquivado               | Histórico de horas preservado           |
| Usuário perde acesso ao quadro | Não consegue fazer novos apontamentos   |
| Registro é alterado            | Auditoria preserva o histórico          |

# 11. Indicadores que serão disponibilizados

A base deverá permitir construir, no Metabase, pelo menos estes painéis:

- Horas por colaborador.
- Horas por projeto.
- Horas por cartão/tarefa.
- Horas por categoria de chamado.
- Horas por técnico e grupo GLPI.
- Horas operacionais versus projetos.
- Evolução mensal.
- Distribuição de capacidade por área técnica.
- Horas planejadas versus realizadas, caso futuramente sejam cadastradas estimativas nos projetos.

Também incluiria um indicador de cobertura de apontamento: percentual dos dias úteis com registros realizados, para ajudar a identificar dados incompletos.

# 12. Decisões de escopo para a primeira versão

Para evitar que o projeto cresça excessivamente, sugiro estabelecer estes limites:

| Funcionalidade                | Versão 1               |
| ----------------------------- | ---------------------- |
| Login Nextcloud               | Sim                    |
| Integração Deck               | Sim                    |
| Integração GLPI               | Sim                    |
| Apontamento manual            | Sim                    |
| Cronômetro                    | Não                    |
| Aprovação de horas            | Opcional, configurável |
| Relatórios gerenciais         | Sim                    |
| Metabase                      | Sim                    |
| Aplicativo mobile próprio     | Não                    |
| Controle de frequência/ponto  | Não                    |
| Gestão financeira de projetos | Não                    |
| Planejamento de capacidade    | Evolução futura        |

Importante: o OAB Timesheet não será um sistema de controle de jornada trabalhista. Será uma ferramenta de gestão de esforço e alocação de capacidade da TI.

# 13. Próxima etapa: transformar a especificação em projeto executável

Minha sugestão é iniciar pelo levantamento técnico do ambiente e pela prova de conceito, antes de escrever o código definitivo.

Para isso, eu organizaria o projeto em um repositório Git com esta estrutura:

```
oab-timesheet/
├── appinfo/
├── lib/
│   ├── Controller/
│   ├── Service/
│   ├── Integration/
│   │   ├── Deck/
│   │   └── GLPI/
│   ├── Db/
│   ├── Security/
│   └── Audit/
├── src/
│   ├── components/
│   ├── views/
│   └── services/
├── tests/
├── docs/
├── docker/
└── README.md
```

Minha recomendação é construir primeiro uma aplicação nativa do Nextcloud, usando PHP e Vue.js, sem adicionar outro backend desnecessariamente. O PostgreSQL será a base dos registros e o n8n ficará reservado para automações complementares.

A primeira entrega de desenvolvimento deve ser uma prova de conceito do Deck: autenticar o usuário, consultar os quadros e cartões autorizados e demonstrar o registro de horas em um cartão real. Só depois avançaria para o GLPI.

Isso reduz o risco de construir seis semanas de software e descobrir que a integração visual pretendida não é sustentável.

O próximo trabalho pode ser organizar a execução em tarefas técnicas, com critérios de aceite e entregáveis por sprint, acompanhando semanalmente o avanço do desenvolvimento.

⏱️Acompanhar implantação do OAB TimesheetToque para configurar