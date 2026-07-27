# Roteiro de testes — Project+

## Finalidade

Validar, em ambiente de homologação, as funcionalidades atualmente entregues pelo plugin **Project+** para GLPI 10.

Este roteiro é destinado ao Claude como agente de **testes בלבד**. O objetivo é registrar o comportamento observado, falhas e evidências para posterior correção pela equipe de desenvolvimento.

## Regra absoluta: não alterar nada

O Claude **não deve**:

- editar arquivos do plugin ou do GLPI;
- executar comandos de instalação, atualização, cache, banco de dados ou migração;
- alterar código, permissões, perfis, entidades, categorias, listas suspensas ou configurações;
- criar, excluir ou editar projetos, chamados ou usuários além dos registros de teste já autorizados;
- apagar dados, tabelas, logs ou sessões;
- fazer `git commit`, `git push`, `git reset`, `git checkout` ou qualquer operação de versionamento que modifique o repositório.

O Claude pode apenas navegar na interface, consultar dados já existentes e, quando necessário para um teste manual autorizado, criar registros de teste claramente identificados com o prefixo `TESTE_CLAUDE_`. Ao final, deve informar todos os registros criados, sem removê-los.

## Ambiente e versão sob teste

- Produto: Project+
- Plugin instalado: `projectplus`
- Versão esperada: `0.5.1-beta`
- Plataforma: GLPI 10.0.x / PHP 7.4+
- Ambiente: VM de homologação

## Formato obrigatório do relatório

Para cada caso de teste, registrar:

| Campo | Conteúdo esperado |
|---|---|
| ID | Identificador do caso, por exemplo `PP-PROJ-01` |
| Resultado | Aprovado, Reprovado, Bloqueado ou Não executado |
| Evidência | Captura de tela, URL e descrição objetiva do que ocorreu |
| Dados usados | Usuário, entidade, projeto ou chamado utilizado; não incluir senha |
| Observações | Mensagem exibida, comportamento inesperado e passos para reproduzir |

No final, consolidar os resultados em: aprovados, reprovados, bloqueados, riscos e recomendações. Não propor alteração de código; apenas descrever o problema de forma reproduzível.

## Pré-condições de teste

1. Confirmar que o plugin está instalado e ativo em **Configurar > Plugins**.
2. Usar, quando possível, uma entidade de homologação e registros de teste existentes.
3. Identificar pelo menos:
   - um perfil administrativo com acesso a Projetos e Configurar;
   - um usuário comum vinculado como solicitante de um projeto;
   - um usuário comum vinculado como ponto focal de outro projeto;
   - uma categoria ITIL disponível para ativar o fluxo de solicitação de projeto.
4. Se uma pré-condição não existir, marcar o teste como **Bloqueado** e explicar o motivo. Não criar configurações sem autorização explícita.

## Casos de teste

### 1. Dados complementares do Projeto

| ID | Procedimento | Resultado esperado |
|---|---|---|
| PP-PROJ-01 | Abrir um projeto já existente no módulo nativo de Projetos. | Os blocos **Dados do Projeto**, **Gerente**, **Dados do solicitante (Cliente)** e **Planejamento** são exibidos sem quebrar o formulário nativo. |
| PP-PROJ-02 | Verificar Cesta Portfólio e Ação PDTIC. | Ambos aparecem como listas suspensas e respeitam a entidade do projeto. |
| PP-PROJ-03 | Verificar Objetivo e Produto/Resultado esperado. | Ambos aparecem como áreas de texto na coluna direita, alinhados a Descrição e Comentários, no padrão de duas colunas. |
| PP-PROJ-04 | Verificar solicitante e ponto focal de um projeto preenchido. | São exibidos como usuários do GLPI; os e-mails correspondem ao cadastro dos usuários. |
| PP-PROJ-05 | Abrir um projeto sem dados Project+. | O formulário nativo permanece utilizável e não apresenta erro PHP, tela em branco ou aviso técnico. |

### 2. Listas suspensas Project+

| ID | Procedimento | Resultado esperado |
|---|---|---|
| PP-LIST-01 | Acessar **Configurar > Listas suspensas > Project+**. | As opções Cestas Portfólio e Ações PDTIC aparecem na navegação. |
| PP-LIST-02 | Abrir cada lista. | A tela usa o padrão nativo, com pesquisa, listas e botão Adicionar para perfis autorizados. |
| PP-LIST-03 | Conferir uma lista com registros existentes. | Os registros aparecem e podem ser selecionados no projeto da mesma entidade. |
| PP-LIST-04 | Acessar com perfil sem direito de configurar listas, se disponível. | Não deve haver possibilidade indevida de inclusão, edição ou exclusão. |

### 3. Importação de projetos existentes

| ID | Procedimento | Resultado esperado |
|---|---|---|
| PP-IMP-01 | Abrir **Configurar > Project+ > Importação de projetos**. | A tela de importação abre e oferece o download do modelo CSV. |
| PP-IMP-02 | Baixar o modelo sem alterá-lo e reenviá-lo com uma linha de teste válida previamente aprovada. | A pré-validação aceita os cabeçalhos do modelo. Nenhum projeto é criado antes da confirmação. |
| PP-IMP-03 | Validar uma linha com entidade, ID da entidade, código e nome válidos. | A pré-validação informa que a linha está pronta para importar. |
| PP-IMP-04 | Validar uma linha com ID e nome de entidade incompatíveis. | A linha é recusada com mensagem clara indicando a divergência. |
| PP-IMP-05 | Validar logins de solicitante e ponto focal existentes. | Os logins são associados aos usuários GLPI; após importação autorizada, nome de exibição e e-mail são derivados do cadastro. |
| PP-IMP-06 | Validar datas nos formatos `AAAA-MM-DD HH:MM`, `DD/MM/AAAA HH:MM` e `AAAA/MM/DD HH:MM`. | Os formatos são aceitos e apresentados corretamente na pré-validação/importação. |
| PP-IMP-07 | Validar percentual como `50` e `50%`. | Ambos são aceitos quando válidos; valores acima de 100 são rejeitados. |
| PP-IMP-08 | Validar código de projeto já existente na mesma entidade. | A importação bloqueia a duplicidade e informa a linha afetada. |
| PP-IMP-09 | Se houver autorização para confirmar uma linha de teste, confirmar a importação. | O projeto é criado uma única vez, campos nativos e Project+ são preenchidos e o resultado informa a quantidade criada. |

> Não confirmar importações de produção. Caso não haja autorização para criar o projeto de teste, encerrar em pré-validação e marcar `PP-IMP-09` como Bloqueado.

### 4. Portal do solicitante — Meus projetos

| ID | Procedimento | Resultado esperado |
|---|---|---|
| PP-PORT-01 | Entrar como usuário que seja solicitante ou ponto focal de pelo menos um projeto. | O atalho único **Meus projetos** aparece abaixo de **Meus chamados**. Não há submenu redundante. |
| PP-PORT-02 | Abrir Meus projetos. | A lista contém somente os projetos nos quais o usuário é solicitante ou ponto focal, respeitando a entidade acessível. |
| PP-PORT-03 | Abrir um projeto da lista. | É exibida somente a aba **Projeto**, em modo leitura, com os blocos e dados do projeto. |
| PP-PORT-04 | Tentar identificar controles de alteração no detalhe. | Não devem existir Salvar, exclusão, edição, outras abas ou ações que alterem o projeto. |
| PP-PORT-05 | Entrar como usuário sem vínculo a qualquer projeto. | O atalho não deve aparecer; se aparecer por configuração do portal, a lista deve ficar vazia sem revelar projetos de terceiros. |
| PP-PORT-06 | Tentar abrir diretamente a URL de um projeto sem vínculo. | O acesso deve ser negado ou redirecionado, sem expor os dados. |

### 5. Configuração do fluxo de solicitação de projeto

| ID | Procedimento | Resultado esperado |
|---|---|---|
| PP-FLOW-01 | Abrir **Configurar > Project+ > Fluxo de solicitação de projeto**. | A tela apresenta a entidade selecionável, opção de habilitar fluxo e categoria ITIL ativadora. |
| PP-FLOW-02 | Selecionar uma entidade e conferir a lista de categorias. | A entidade selecionada é respeitada e as categorias disponíveis pertencem ao contexto esperado. |
| PP-FLOW-03 | Com uma configuração já salva, abrir um chamado da entidade/categoria configuradas. | A aba **Solicitação de projeto** é exibida no chamado. |
| PP-FLOW-04 | Abrir chamado de categoria diferente ou de entidade não configurada. | A aba não deve aparecer. |
| PP-FLOW-05 | Na aba visível, conferir fase inicial. | A fase inicial é **Classificação da necessidade** e há histórico inicial com data e responsável. |
| PP-FLOW-06 | Alterar uma fase, se houver autorização e um chamado de teste. | Fase, responsável, data e justificativa são registrados no histórico. |
| PP-FLOW-07 | Selecionar Recusado/Cancelado sem justificativa. | A mudança deve ser recusada; não pode haver atualização silenciosa. |

### 6. Perfis e permissões Project+

| ID | Procedimento | Resultado esperado |
|---|---|---|
| PP-PERF-01 | Acessar **Administração > Perfis** com um perfil administrativo e abrir a aba **Project+**. | A página renderiza uma matriz de direitos; não fica em branco e não exibe erro técnico. |
| PP-PERF-02 | Conferir os itens de direito. | Devem existir, no mínimo: Solicitações de projeto, Priorização de portfólio e Administração do Project+. |
| PP-PERF-03 | Reabrir um perfil após salvar direitos previamente configurados. | Os direitos persistem e os campos marcados são refletidos corretamente. |
| PP-PERF-04 | Com um perfil limitado, verificar se os direitos Project+ restringem telas e alterações correspondentes. | O acesso deve obedecer ao direito específico, não somente ao direito genérico de chamado. |

## Situação conhecida e pontos de atenção

### Erro confirmado recentemente

Houve erro crítico ao abrir a aba de perfil Project+:

```text
Call to undefined method GlpiPlugin\Projectplus\TicketConfig::getRights()
```

Origem: a matriz de direitos apontava uma linha para `TicketConfig`, classe que não oferece o método esperado pelo componente nativo de perfis do GLPI.

Correção implantada: versão `0.5.1-beta`, substituindo a associação da linha pelo objeto compatível `TicketPhase`.

Status: **a correção foi instalada, mas a validação visual pós-instalação ainda é obrigatória**. Priorizar `PP-PERF-01` a `PP-PERF-04`. Se a tela permanecer em branco, registrar data/hora, perfil, URL e captura de tela. Não alterar nada para tentar corrigir.

### Limitações funcionais atuais

1. As permissões específicas Project+ ainda precisam de validação completa de persistência e aplicação nas telas. Há risco de algumas ações ainda dependerem do direito genérico `ticket`/Atualizar.
2. O fluxo de fases permite selecionar as fases existentes, mas ainda não possui:
   - transições restritas por papel;
   - regra formal de quais fases cabem a gerente de projetos ou gerente de portfólio;
   - cálculo de priorização/ranking;
   - criação automática de projeto após autorização;
   - TIP, TAP ou TEP;
   - aceite do cliente, PDF ou assinatura;
   - interações privadas/públicas e notificações próprias do projeto.
3. A importação é CSV assistida e deve ser validada com arquivo real antes de ser usada em lote. Não há rotina de desfazer uma importação concluída.
4. Os erros antigos de outros plugins exibidos no log da VM (por exemplo, Maintenance Costs) não devem ser atribuídos ao Project+ sem evidência direta de caminho ou classe `projectplus`.

## Critérios mínimos de aprovação desta rodada

Considerar a rodada aprovada somente se:

- não houver erro crítico novo referenciando `plugins/projectplus/`;
- os casos `PP-PROJ-01`, `PP-LIST-01`, `PP-IMP-01`, `PP-PORT-01`, `PP-FLOW-01` e `PP-PERF-01` forem aprovados;
- os controles de acesso do portal não revelarem projetos sem vínculo;
- a importação não criar registros antes da confirmação explícita;
- o relatório final listar todos os casos bloqueados e a causa de cada bloqueio.

## Entrega esperada do Claude

Criar somente um relatório Markdown de testes, por exemplo `RELATORIO_TESTES_PROJECTPLUS.md`, sem modificar nenhum arquivo existente. O relatório deve conter os resultados por ID, evidências, falhas encontradas e a lista de dados de teste eventualmente criados.
