# Relatório cronológico de alterações — Project+ (01 a 31/07/2026)

**Período:** 01/07/2026 a 31/07/2026
**Fonte principal:** histórico Git local, tags e `CHANGELOG.md` do plugin.
**Corte do relatório:** 31/07/2026.

> Este relatório distingue claramente o que foi **publicado em release** do que está apenas
> **em desenvolvimento local**. Alterações sem commit não devem ser consideradas
> distribuídas para GitHub, GitLab ou uma nova instalação do plugin.

## Resumo executivo

O mês marcou a evolução do Project+ de um complemento de campos para Projetos para uma
solução de fluxo de solicitação de projeto no GLPI. Foram entregues campos adicionais,
listas suspensas, importação CSV, portal de consulta, workflow a partir de chamados,
TIP configurável, documentos TAP/TEP/TA, aceite auditável, interações, priorização e
abertura de projeto vinculada ao ticket.

### Releases publicadas no período

| Data | Versão | Situação | Destaque |
|---|---|---|---|
| 21/07 | `0.1.0-beta` | Publicada | Base do plugin: campos extras e listas do Project+. |
| 23/07 | `0.3.10` | Publicada | Importação CSV, portal **Meus projetos** e listas nativas. |
| 29/07 | `0.7.1-beta` | Publicada | Documentos, interações, priorização e fluxo ampliado. |
| 30/07 | `0.7.2-beta` | Publicada | Área Project+, melhorias de telas e configuração. |
| 30/07 | `0.8.0-beta` | Publicada | Fluxo documental e priorização integrados. |

## Linha do tempo detalhada

### 01 a 20/07 — preparação funcional

Não há commits do Project+ no repositório para este intervalo. O código versionado do
plugin inicia em 21/07. O período anterior corresponde ao levantamento inicial do
produto e à definição do escopo, posteriormente registrado nas versões do PRD em
`docs/projectplus/`.

### 21/07 — criação da base do plugin (release `0.1.0-beta`)

**Publicação:** commit `0ac02a6`, tag `v0.1.0-beta`.

- Criado o plugin Project+ como extensão do módulo nativo de Projetos do GLPI 10.
- Adicionados blocos complementares no formulário de projeto:
  - Dados do Projeto;
  - Planejamento;
  - Dados do solicitante.
- Adicionados os campos **Cesta Portfólio**, **Ação PDTIC**, **Objetivo** e
  **Produto/Resultado esperado**.
- Criadas as listas suspensas multi-entidade para Cesta Portfólio e Ação PDTIC.
- Ajustado o alinhamento dos campos no formulário de Projetos.

### 22/07 — especificação e preparação da importação

Não há commit do plugin neste dia. Foram atualizados artefatos de validação do produto:

- evolução do PRD até a versão `1.6`;
- criação do modelo inicial de importação de projetos existentes;
- consolidação dos requisitos de solicitante, ponto focal, entidades e campos de projeto.

### 23/07 — importação, portal e release `0.3.10`

**Publicação:** commit `e4410cd`, tag `v0.3.10`.

- Implementada a importação assistida de projetos existentes por CSV.
- Adicionada pré-validação de entidades, usuários, datas, códigos e dropdowns antes da
  gravação.
- Criada geração de projetos nativos, dados adicionais Project+ e vínculo de projeto-pai.
- Disponibilizado download da planilha modelo de importação.
- Evoluída a compatibilidade do importador:
  - cabeçalhos normalizados por Excel/LibreOffice;
  - arquivos CSV UTF-8, ANSI/Windows-1252 e tabulados;
  - datas em múltiplos formatos, incluindo `AAAA/MM/DD HH:MM`;
  - percentuais com `%`;
  - busca tolerante a acentos e diferenças de maiúsculas/minúsculas;
  - suporte ao ID `0` da entidade raiz;
  - conferência entre nome e ID da entidade.
- Criado o atalho **Meus projetos** no portal do solicitante/ponto focal.
- Criada listagem e detalhe somente leitura, respeitando vínculo do usuário e entidade.
- Movidas as listas Cestas Portfólio e Ações PDTIC para **Configurar > Listas suspensas**,
  com inclusão, edição, exclusão e barra nativa do GLPI.

### 24 a 26/07 — validação funcional

Não há commits do Project+ no intervalo. O período foi usado para homologar importação,
portal do solicitante e listas suspensas, com os ajustes já incorporados na publicação
`0.3.10`.

### 27/07 — início do fluxo de solicitação e documentação

Principais commits: `8136274` a `4acc50b`.

- Criado o fluxo interno de solicitação de projeto dentro do Chamado.
- Criadas configuração por entidade/categoria ITIL, fases internas e histórico das fases.
- Adicionado Project+ ao menu nativo de Plugins e reorganizada a área de configuração.
- Implementada matriz inicial de direitos de perfil do Project+.
- Criado o TIP no chamado, com:
  - configuração por entidade;
  - campos, tipos, ordem, obrigatoriedade e ativação;
  - registro de respostas por campo;
  - validações de usuário, entidade, CSRF e obrigatoriedade;
  - estado de aguardando preenchimento e envio auditável.
- Aplicado layout documental ao TIP enviado e correções de contraste e codificação.
- Implementado catálogo de documentos e o aceite auditável de **TAP**, **TEP** e **TA**.
- Criadas proteções de CSRF nos modelos documentais e controle de acesso ao visualizador.
- Criada ação para abrir Projeto a partir de ticket autorizado.

### 28/07 — aperfeiçoamento do fluxo e modelos

Principais commits: `307259f` a `5e6351f`.

- Refinado o layout da configuração do fluxo de solicitação.
- Contornado cache de estilos da tela de configuração e do visualizador de aceite.
- Incluídas entradas de configuração para modelos documentais.
- Ao solicitar o TIP, passou a ser criado acompanhamento automático no chamado.

### 29/07 — interações, documentos configuráveis e release `0.7.1-beta`

**Publicação:** commit `5fa3e55`, tag `v0.7.1-beta`.

- Adicionada área unificada **Project+** ao Projeto.
- Implementadas abas internas para Termos, Interações e Priorização.
- Implementadas interações de projeto, com privacidade, notificações, anexos,
  acompanhamento e modelos próprios de acompanhamento/solução.
- Incluídos fluxo de solução com TEP e escalonamento de projeto.
- Tornados TAP, TEP e TA configuráveis por campos, com rascunho, geração e PDF.
- Criados documentos de aceite e visualização no portal do solicitante.
- Implementada priorização institucional e sincronização com a prioridade nativa do GLPI.
- Criado roteiro/checklist de testes ponta a ponta para validação independente.
- Integrado o histórico do repositório GitLab ao repositório local.

### 30/07 — consolidação, release `0.7.2-beta` e `0.8.0-beta`

**Publicações:** commits `890670b` e `cb738a7`; tags `v0.7.2-beta` e `v0.8.0-beta`.

#### Versão `0.7.2-beta`

- Consolidada a área Project+ com Termos, Interações e Priorização.
- Ampliado o portal para exibir Termos e Interações vinculados ao projeto.
- Evoluídos documentos TIP, TAP, TEP e TA com rastreabilidade de geração, aceite e
  recusa.
- Criada tela **Sobre**, com guia operacional do fluxo.
- Corrigidos rótulos, mensagens e caracteres com problemas de acentuação/codificação.

#### Versão `0.8.0-beta`

- Consolidado o fluxo documental na área Project+.
- Mantida a subaba atual após ações de documentos, interações e priorização.
- Evoluída a abertura de projeto a partir de ticket:
  - validações de pré-requisitos;
  - vínculo nativo com o chamado;
  - texto de solução informado ao solicitante.
- Ampliada a priorização com ranking e atualização da prioridade nativa do GLPI.
- Evoluídos TAP, TEP e TA para geração, envio, aceite e recusa auditáveis.

### 31/07 — alterações locais em desenvolvimento (ainda não publicadas)

No corte deste relatório há alterações locais sem commit posterior à `0.8.0-beta`.
Elas **não integram uma release** e devem ser tratadas como trabalho em andamento:

- reorganização dos dois TIPs: **TIP** e **TIP - DT**;
- separação da priorização em blocos Técnico e Estratégico, com permissões distintas;
- ampliação das permissões de perfil por função e por documento do fluxo;
- ajustes do portal para exibir Termos e Interações ao solicitante/ponto focal;
- evolução da configuração por categoria ITIL e entidade, incluindo paginação;
- melhoria do visual das telas de configuração, importação e modelos;
- ajustes em modelos de acompanhamento e solução de projetos;
- ajustes de navegação, layout e rótulos da área Project+.

## Situação ao final do período

| Componente | Última situação publicada | Observação |
|---|---|---|
| Campos adicionais de Projeto | Entregue | Base desde `0.1.0-beta`. |
| Listas Cesta Portfólio/Ação PDTIC | Entregue | Integradas às listas suspensas nativas. |
| Importação CSV | Entregue | Com modelo, pré-validação e tolerância a formatos. |
| Portal Meus projetos | Entregue | Consulta de projetos vinculados. |
| Fluxo de solicitação no chamado | Entregue | Configuração por categoria ITIL e fases internas. |
| TIP configurável | Entregue | Evoluções de dois TIPs estão locais, não publicadas. |
| TAP/TEP/TA | Entregue em beta | Geração, aceite e recusa; ajustes locais em andamento. |
| Interações | Entregue em beta | Evoluções de layout/perfil ainda locais. |
| Priorização/ranking | Entregue em beta | Reestruturação Técnico/Estratégico local. |
| Configurações por entidade/categoria | Em evolução | Alterações locais ainda sem release. |

## Evidências consultadas

- `git log --all` do repositório Project+ no período.
- Tags `v0.1.0-beta`, `v0.3.10`, `v0.7.1-beta`, `v0.7.2-beta` e `v0.8.0-beta`.
- `CHANGELOG.md` do plugin.
- Artefatos de PRD e modelo de importação em `docs/projectplus/`.
- Estado local não commitado existente no corte de 31/07/2026.
