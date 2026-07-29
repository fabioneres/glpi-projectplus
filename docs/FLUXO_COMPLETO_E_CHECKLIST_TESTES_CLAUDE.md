# Project+ — fluxo operacional e roteiro de validação ponta a ponta

## Finalidade deste documento

Este documento descreve o fluxo atualmente implementado pelo plugin **Project+** no GLPI de homologação e orienta uma rodada de testes funcionais pelo Claude.

O objetivo do teste é validar o uso completo, identificar falhas reproduzíveis e registrar evidências. O Claude **não deve implementar, corrigir ou alterar código**.

> A priorização institucional já foi validada funcionalmente. Nesta rodada, ela deve ser apenas conferida quanto à exibição do ranking na lista de Projetos e à atualização da prioridade nativa.

---

## 1. Regras de atuação do Claude

### Pode

- Navegar no GLPI de homologação pelo navegador.
- Consultar configurações, projetos, chamados, documentos, listas e histórico.
- Executar ações funcionais estritamente necessárias em registros de teste autorizados.
- Criar registros de teste somente quando necessário, sempre com o prefixo `TESTE_CLAUDE_`.
- Criar um relatório Markdown de resultados e anexar capturas de tela ou URLs como evidência.

### Não pode

- Alterar arquivos, código-fonte, banco de dados, permissões, perfis, entidades, categorias, configurações gerais ou listas suspensas.
- Executar comandos de terminal, migrations, instalação, atualização, limpeza de cache ou operações Git.
- Excluir, purgar ou alterar registros reais/de produção.
- Corrigir comportamentos identificados; deve apenas descrevê-los.
- Criar uma importação real ou em massa. A confirmação de importação só é permitida com autorização explícita e usando um arquivo com dados `TESTE_CLAUDE_`.

Se uma pré-condição estiver ausente, o caso deve ser marcado como **Bloqueado**, sem tentar contornar a falta de permissão por meio de alterações administrativas.

---

## 2. Perfis envolvidos no fluxo

| Papel | Atribuição no processo |
|---|---|
| Solicitante | Abre o chamado, preenche o TIP quando solicitado, consulta seus projetos, publica interações permitidas e registra aceites documentais. |
| Ponto focal | Pode acompanhar o projeto vinculado, publicar interações permitidas e registrar aceites quando possuir vínculo/visibilidade. |
| Gerente de projetos | Conduz o chamado de projeto, solicita e avalia o TIP, classifica a demanda, prepara TAP, abre o projeto, atualiza o projeto, prepara TEP/TA e conduz a solução documental. |
| Gerente de portfólio | Diretor ou assessor com o mesmo acesso técnico: avalia a classificação/ranking, autoriza ou recusa a abertura e consulta a visão de portfólio. |
| Administrador do Project+ | Configura o fluxo por entidade, modelos de campos TIP/TAP/TEP/TA, listas do plugin e direitos de perfil. |

Os direitos são configuráveis em **Administração → Perfis → Project+**. Os principais são: Solicitações de projeto, Priorização de portfólio, Administração do Project+ e os direitos de Interações (responder, tarefa, solução com TEP, documento, validação e escalar).

---

## 3. Configuração inicial obrigatória

### 3.1 Fluxo por entidade

Em **Configurar → Project+ → Fluxo de solicitação de projeto**, o administrador/gestor autorizado seleciona a entidade, habilita o fluxo e escolhe a **Categoria ITIL** que caracteriza uma solicitação de projeto.

Quando um chamado daquela entidade recebe a categoria configurada, a aba **Solicitação de projeto** passa a ser exibida. O status nativo do chamado não é alterado pelo plugin; o controle interno ocorre por fases próprias do Project+.

### 3.2 Campos dos documentos

Em **Configurar → Project+**, devem existir áreas distintas para:

- Modelo TIP;
- Modelo TAP;
- Modelo TEP;
- Modelo TA.

Cada área permite montar uma grade de campos por entidade: ordem, rótulo, descrição, tipo (texto curto, texto longo, número, data, lista suspensa ou título de seção), obrigatório e ativo. O TIP é preenchido pelo solicitante. TAP, TEP e TA são preenchidos pelo responsável/gestor antes de gerar o respectivo PDF.

### 3.3 Listas e modelos auxiliares

- **Configurar → Listas suspensas → Project+**: Cestas Portfólio, Ações PDTIC, Modelos de acompanhamento de Projetos e Modelos de solução do projeto.
- Os modelos de acompanhamento ficam disponíveis apenas no compositor de **Responder**.
- Os modelos de solução ficam disponíveis apenas no compositor de **Solução**.
- **Cesta Portfólio** e **Ação PDTIC** são selecionadas nos dados complementares do projeto.

---

## 4. Fluxo completo: chamado até encerramento do projeto

### Etapa 1 — Entrada da solicitação

1. O solicitante cria um chamado pela categoria configurada para solicitação de projeto.
2. O Project+ cria/controla a fase interna **Classificação da necessidade** e registra o evento no histórico da aba Solicitação de projeto.
3. O gerente de projetos verifica se a demanda deve seguir como projeto.

**Resultado esperado:** a aba Solicitação de projeto aparece apenas nos chamados cuja entidade e categoria estejam configuradas.

### Etapa 2 — Solicitação e preenchimento do TIP

1. O gerente de projetos, na aba Solicitação de projeto, seleciona **Solicitar preenchimento do TIP**.
2. O Project+ altera a fase para **Aguardando preenchimento do TIP** e adiciona um acompanhamento automático ao chamado informando a solicitação.
3. O solicitante abre a mesma aba e preenche os campos ativos do modelo TIP.
4. Ao selecionar **Enviar TIP para análise**, todos os obrigatórios devem ser validados.
5. O plugin registra o envio, cria acompanhamento automático e altera a fase para **Aguardando validação do TIP**.

**Resultado esperado:** o solicitante não envia TIP sem solicitação prévia ou sem preencher os campos obrigatórios.

### Etapa 3 — Análise do TIP pelo gestor

1. O gerente abre a aba Solicitação de projeto depois do envio.
2. O TIP é exibido exclusivamente como documento/PDF incorporado, com opção de abrir o documento em outra aba.
3. O gestor pode escolher uma das decisões:
   - **Aceitar TIP**: registra a decisão, cria acompanhamento automático e devolve a fase para **Classificação da necessidade**;
   - **Recusar TIP e solucionar chamado**: exige justificativa, registra a recusa, soluciona o chamado com a justificativa e muda a fase para **Recusado/Cancelado**.

**Resultado esperado:** não há exibição paralela dos campos do TIP para o gestor; a análise ocorre pelo PDF. A recusa nunca pode ocorrer sem justificativa.

### Etapa 4 — Classificação e autorização de abertura

1. Após aceitar o TIP, o gerente de projetos conclui a classificação da necessidade na aba Solicitação de projeto.
2. A fase pode seguir para **Análise do portfólio**.
3. O gerente de portfólio analisa os dados e o ranking e, se aplicável, registra **Aguardando autorização de abertura**.
4. Uma decisão positiva leva a fase para **Autorizado para abertura**. Uma negativa usa **Recusado/Cancelado**, com justificativa.

**Resultado esperado:** toda alteração de fase fica registrada com data, responsável, fase anterior, nova fase e justificativa quando informada. A regra atual permite seleção de fases pelos perfis com direito de Solicitações de projeto; as transições ainda não são restringidas individualmente por papel.

### Etapa 5 — TAP e aceite de abertura

1. Com a abertura autorizada, o gerente prepara o **TAP — Termo de Abertura do Projeto** na aba **Documentos e aceites** do chamado.
2. O responsável preenche os campos ativos do modelo TAP e usa **Salvar rascunho** sempre que necessário.
3. Após preencher os obrigatórios, seleciona **Gerar TAP**. O PDF gerado é uma versão do documento disponível para visualização.
4. Só depois da geração deve aparecer **Enviar para aceite**.
5. O cliente visualiza somente o PDF gerado, marca **Li e aceito os termos deste documento** e seleciona **Aceitar e continuar**.
6. O aceite do TAP é auditado e a fase do chamado passa a **Autorizado para abertura**.

**Resultado esperado:** sem PDF gerado, o envio para aceite deve ser bloqueado. O aceite deve ser impossível sem marcar a confirmação.

### Etapa 6 — Abertura do projeto e solução do chamado de origem

1. Com o TAP aceito e a fase autorizada, o gerente seleciona **Abrir projeto e solucionar chamado**.
2. O Project+ cria o projeto nativo do GLPI, vincula-o ao chamado e soluciona o chamado de origem.
3. O projeto passa a ser o objeto de trabalho do ciclo de execução.

**Resultado esperado:** a ação cria apenas um projeto para a solicitação e o chamado fica solucionado com vínculo/rastreabilidade para o projeto.

### Etapa 7 — Execução e dados do projeto

No projeto nativo, o Project+ acrescenta:

- Dados do Projeto: Cesta Portfólio e Ação PDTIC;
- Gerente: usuário e grupo nativos;
- Dados do solicitante: área, solicitante, e-mail derivado, ponto focal e e-mail derivado;
- Planejamento: datas, objetivo e produto/resultado esperado;
- Aba **Priorização**;
- Aba **Interações**;
- Aba **Documentos e aceites**.

Solicitante e ponto focal devem ser usuários existentes do GLPI. O sistema exibe seu nome de apresentação e deriva os e-mails do cadastro desses usuários.

### Etapa 8 — Priorização institucional e visão de portfólio

1. O gestor abre a aba **Priorização** do projeto e atribui nível de 1 a 5 aos sete critérios temporários:
   - Risco residual do PDTIC;
   - Abrangência dos resultados;
   - Urgência do projeto;
   - Tempo estimado do projeto;
   - Familiaridade da equipe com a proposta;
   - Prioridade institucional;
   - Tempo aguardando a execução.
2. O ranking é a soma dos sete critérios (1 a 35).
3. O ranking atualiza automaticamente a prioridade nativa do GLPI:

| Ranking | Prioridade nativa |
|---:|---|
| 1–5 | Muito baixa |
| 6–10 | Baixa |
| 11–15 | Média |
| 16–20 | Alta |
| 21–25 | Muito alta |
| 26–35 | Crítica |

4. As cores do cartão são obtidas da configuração nativa **Configurar → Geral → Cores das prioridades**.
5. Gestores de portfólio podem adicionar **Ranking** à lista nativa de Projetos em listas pessoais ou globais e consultar a **Visão do portfólio**.

### Etapa 9 — Interações durante a execução

1. Na aba **Interações**, a linha do tempo mostra interações e eventos automáticos do projeto.
2. O compositor abre somente após selecionar um botão do rodapé, como no módulo de chamados.
3. **Responder** permite publicar acompanhamento, anexar arquivo, escolher um modelo de acompanhamento, definir Privado e habilitar/desabilitar Notificar participantes.
4. **Tarefa**, **Documento**, **Validação** e **Escalar** dependem dos direitos do perfil. Escalar transfere o projeto para outra entidade e registra o evento.
5. Interação marcada como privada é destinada à gestão e não deve ser visível ao solicitante/ponto focal.

**Resultado esperado:** os botões visíveis dependem dos direitos Project+ e a aparência segue o padrão do acompanhamento do GLPI.

### Etapa 10 — Entrega: TEP, solução e aceite

1. Quando o projeto estiver entregue, o gerente configura/preenche os campos do **TEP — Relatório de Entrega do Projeto** na aba Documentos e aceites do projeto.
2. O responsável salva rascunho, valida os obrigatórios e seleciona **Gerar TEP**.
3. Na aba Interações, o botão **Solução** só deve funcionar se houver um TEP gerado.
4. O gestor escreve o texto da solução e seleciona **Solucionar e enviar o TEP**.
5. A interação é registrada e o PDF TEP gerado é publicado para aceite do cliente.
6. O cliente abre o PDF, marca o aceite e confirma.

**Resultado esperado:** sem TEP configurado e gerado, a solução é bloqueada com mensagem clara. O TA permanece bloqueado até o aceite do TEP.

### Etapa 11 — TA, finalização documental e encerramento nativo

1. Depois do aceite do TEP, o gerente preenche o **TA — Termo de Aceite**.
2. Salva o rascunho, gera o PDF e envia para aceite.
3. O cliente confirma a leitura e o aceite do TA.
4. O aceite do TA é auditado e o Project+ atualiza o projeto para 100% concluído e preenche a data real de término.
5. O gestor encerra/arquiva o projeto pelo comportamento nativo disponível no GLPI, conforme a regra operacional da entidade.

> Observação importante: o aceite do TA finaliza documentalmente o projeto e atualiza percentual/data. A alteração automática do status nativo para arquivado/encerrado deve ser confirmada nesta rodada; se não ocorrer, registrar como falha ou melhoria de regra de negócio.

---

## 5. Recursos complementares

### Portal “Meus projetos”

- O atalho **Meus projetos** aparece abaixo de **Meus chamados** somente para usuário que seja solicitante ou ponto focal de pelo menos um projeto.
- A lista exibe apenas projetos em que o usuário está vinculado.
- O detalhe é somente leitura e expõe apenas a aba Projeto; não deve permitir salvar, excluir ou acessar abas administrativas.

### Importação de projetos existentes

- A tela disponibiliza um modelo CSV oficial.
- A rotina pré-valida cabeçalhos, entidade/ID da entidade, campos obrigatórios, usuários por login, datas, percentual e duplicidade de código na entidade.
- Datas devem aceitar `AAAA-MM-DD HH:MM`, `AAAA/MM/DD HH:MM` e `DD/MM/AAAA HH:MM`.
- Solicitante e ponto focal são recebidos como login e convertidos para nome/e-mail do usuário GLPI existente.
- A criação efetiva exige confirmação posterior à pré-validação.

---

## 6. Pré-condições para a execução do roteiro

1. Plugin Project+ instalado e ativo.
2. Uma entidade de homologação configurada com categoria ITIL ativadora do fluxo.
3. Usuário gestor com direitos de Projetos, Solicitações de projeto, Priorização de portfólio e direitos necessários de Interações.
4. Usuário solicitante e usuário ponto focal existentes, com acesso ao portal de autoatendimento.
5. Campos ativos configurados para TIP, TAP, TEP e TA na entidade de teste.
6. Um chamado de teste de categoria ativadora, ou autorização explícita para criar um chamado `TESTE_CLAUDE_`.

---

## 7. Checklist de testes ponta a ponta

Marcar cada caso como **Aprovado**, **Reprovado**, **Bloqueado** ou **Não executado**.

### 7.1 Segurança, menus e configuração

- [ ] **PP-E2E-01** — Confirmar plugin ativo e menu Project+ disponível apenas para perfil autorizado.
- [ ] **PP-E2E-02** — Confirmar que a configuração do fluxo permite selecionar entidade e carregar suas categorias sem botão intermediário desnecessário.
- [ ] **PP-E2E-03** — Confirmar que TIP, TAP, TEP e TA possuem áreas separadas de configuração de campos.
- [ ] **PP-E2E-04** — Confirmar que Cestas Portfólio, Ações PDTIC, modelos de acompanhamento e modelos de solução aparecem em Listas suspensas → Project+.
- [ ] **PP-E2E-05** — Confirmar a aba Project+ na configuração de perfis e a presença de todos os direitos de interação.

### 7.2 Solicitação, TIP e decisão

- [ ] **PP-E2E-06** — Abrir/criar chamado de teste com a entidade e categoria ativadoras; confirmar aba Solicitação de projeto e fase inicial Classificação da necessidade.
- [ ] **PP-E2E-07** — Como gestor, solicitar preenchimento do TIP; confirmar mudança para Aguardando preenchimento e acompanhamento automático no chamado.
- [ ] **PP-E2E-08** — Como solicitante, tentar enviar TIP sem obrigatório; confirmar bloqueio.
- [ ] **PP-E2E-09** — Preencher e enviar TIP; confirmar fase Aguardando validação e acompanhamento automático.
- [ ] **PP-E2E-10** — Como gestor, validar que o TIP é exibido em PDF incorporado, sem formulário de respostas paralelo.
- [ ] **PP-E2E-11** — Aceitar TIP; confirmar registro no histórico, acompanhamento e retorno à Classificação da necessidade.
- [ ] **PP-E2E-12** — Em chamado separado de teste, recusar TIP sem justificativa; confirmar bloqueio. Em seguida recusar com justificativa; confirmar solução do chamado e texto da solução.

### 7.3 Fases, TAP e abertura do projeto

- [ ] **PP-E2E-13** — Atualizar fases Classificação → Análise do portfólio → Aguardando autorização → Autorizado para abertura e conferir histórico auditável.
- [ ] **PP-E2E-14** — Preencher TAP sem obrigatórios; confirmar bloqueio de geração.
- [ ] **PP-E2E-15** — Salvar rascunho TAP, reabrir e confirmar persistência dos campos.
- [ ] **PP-E2E-16** — Gerar TAP; confirmar visualização do PDF gerado e aparecimento de Enviar para aceite.
- [ ] **PP-E2E-17** — Como cliente, tentar aceitar TAP sem marcar “Li e aceito”; confirmar bloqueio. Marcar e aceitar; confirmar auditoria e fase Autorizado para abertura.
- [ ] **PP-E2E-18** — Abrir projeto e solucionar chamado; confirmar um único projeto criado, vínculo/rastreabilidade e chamado solucionado sem erro técnico.

### 7.4 Projeto, dados e portal

- [ ] **PP-E2E-19** — Conferir dados Project+: Cesta Portfólio, Ação PDTIC, solicitante, e-mails derivados, ponto focal, objetivo e produto/resultado esperado.
- [ ] **PP-E2E-20** — Validar que solicitante/ponto focal são salvos ao editar o projeto e persistem após recarregar.
- [ ] **PP-E2E-21** — Como solicitante e como ponto focal, validar atalho Meus projetos, filtro estrito de vínculo e detalhe somente leitura.
- [ ] **PP-E2E-22** — Tentar abrir URL direta de projeto sem vínculo; confirmar negação/redirecionamento e ausência de vazamento de dados.

### 7.5 Priorização e portfólio

- [ ] **PP-E2E-23** — Conferir a aba Priorização e o mapeamento do ranking para prioridade nativa. Não é necessário repetir toda a validação de cálculo já aprovada.
- [ ] **PP-E2E-24** — Confirmar que o cartão usa a cor atual configurada em Configurar → Geral → Cores das prioridades e mantém contraste de texto.
- [ ] **PP-E2E-25** — Como gestor de portfólio, adicionar a coluna Ranking em uma lista pessoal e, se o perfil permitir, em uma lista global de Projetos.
- [ ] **PP-E2E-26** — Como perfil sem direito de Priorização de portfólio, confirmar que Ranking e Visão do portfólio não ficam disponíveis indevidamente.

### 7.6 Interações

- [ ] **PP-E2E-27** — Validar a linha do tempo e o rodapé de ações da aba Interações com aparência equivalente aos acompanhamentos do chamado.
- [ ] **PP-E2E-28** — Confirmar que o editor rico abre somente ao clicar em Responder/Solução, e não fica aberto inicialmente.
- [ ] **PP-E2E-29** — Confirmar que Responder mostra somente modelos de acompanhamento e Solução mostra somente modelos de solução.
- [ ] **PP-E2E-30** — Publicar resposta em projeto de teste; confirmar histórico, autor, data e visibilidade adequada.
- [ ] **PP-E2E-31** — Como gestor, publicar interação Privada e confirmar que ela não aparece ao solicitante/ponto focal.
- [ ] **PP-E2E-32** — Acionar Escalar com entidade de teste autorizada; confirmar transferência e registro do evento. Não escalar projetos reais.

### 7.7 TEP, TA e encerramento

- [ ] **PP-E2E-33** — Preencher, salvar rascunho e gerar TEP; confirmar PDF gerado e persistência ao reabrir.
- [ ] **PP-E2E-34** — Tentar usar Solução sem TEP gerado; confirmar bloqueio claro.
- [ ] **PP-E2E-35** — Usar Solução com texto e TEP gerado; confirmar interação e envio do PDF TEP para aceite.
- [ ] **PP-E2E-36** — Como cliente, aceitar TEP e confirmar auditoria. Confirmar que TA é liberado somente após esse aceite.
- [ ] **PP-E2E-37** — Preencher, gerar e enviar TA; confirmar que o cliente visualiza apenas o PDF gerado.
- [ ] **PP-E2E-38** — Aceitar TA; confirmar auditoria, percentual concluído em 100% e data real de término preenchida.
- [ ] **PP-E2E-39** — Conferir o comportamento de status final/arquivamento nativo do projeto após TA. Registrar se houver necessidade de ação manual ou se ocorrer mudança automática.

### 7.8 Importação

- [ ] **PP-E2E-40** — Baixar o modelo CSV e confirmar que a pré-validação aceita os próprios cabeçalhos do modelo.
- [ ] **PP-E2E-41** — Pré-validar uma única linha `TESTE_CLAUDE_` com ID e nome de entidade consistentes, logins existentes e datas em formato aceito.
- [ ] **PP-E2E-42** — Pré-validar divergência entre nome e ID da entidade; confirmar mensagem compreensível.
- [ ] **PP-E2E-43** — Confirmar criação somente se houver autorização explícita; validar campos importados e registrar o ID do projeto criado.

---

## 8. Formato obrigatório para cada falha

Para cada caso reprovado, o Claude deve registrar **exatamente** estes campos:

```markdown
### Falha PP-E2E-XX — título curto

**Ação**

Descrever passo a passo o que foi clicado/preenchido, incluindo entidade, papel do usuário e registro de teste usado.

**Resultado esperado**

Descrever o comportamento previsto neste documento.

**Resultado com a falha**

Descrever o que ocorreu de fato, sem sugerir correção. Incluir mensagem exibida, URL, data/hora, usuário/perfil (sem senha) e evidência visual quando disponível.
```

Exemplo:

```markdown
### Falha PP-E2E-16 — geração do TAP não disponibiliza o PDF

**Ação**

Como gerente de projetos, no chamado TESTE_CLAUDE_001, preenchi todos os campos obrigatórios do TAP e cliquei em Gerar TAP.

**Resultado esperado**

O sistema deve armazenar a versão gerada, mostrar o link/visualização do PDF e habilitar Enviar para aceite.

**Resultado com a falha**

O botão retornou à mesma tela sem mensagem e não exibiu PDF nem Enviar para aceite. URL: `...`. Evidência: captura `pp-e2e-16.png`.
```

---

## 9. Entrega esperada do Claude

Criar apenas um relatório, por exemplo `RELATORIO_TESTES_PROJECTPLUS_E2E.md`, contendo:

1. Ambiente, data/hora e perfis utilizados (sem senhas);
2. Resultado de todos os casos `PP-E2E-01` a `PP-E2E-43`;
3. Evidências e todas as falhas no formato obrigatório;
4. Casos bloqueados e motivo;
5. Registros `TESTE_CLAUDE_` eventualmente criados;
6. Resumo quantitativo: aprovados, reprovados, bloqueados e não executados.

O relatório não deve conter mudanças de código, comandos de correção nem instruções de implementação.
