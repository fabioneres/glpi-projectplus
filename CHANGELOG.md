# Changelog

## 1.0.0-alpha.1 - Unreleased (linha GLPI 11)

Primeira versão da linha **GLPI 11**, equivalente funcional à `0.9.14` (linha GLPI 10). Sem funcionalidade nova.
Candidata interna: ainda não validada em navegação nem em fluxo completo.

### Alterado

- Compatível com **GLPI 11.0.0 a 11.0.99** e **PHP 8.2** ou superior. Não instala em GLPI 10.
- `css/`, `js/` e `pics/` passam a ficar em `public/`: o GLPI 11 só serve essa pasta em `/plugins/<chave>/`. Os caminhos nos hooks não mudam.
- As páginas de configuração passam a montar a própria URL (`FrontUrl`) em vez de `$_SERVER['PHP_SELF']`, que no GLPI 11 vale sempre `/index.php`.
- Consultas SQL em texto deixam de passar por `$DB->request()` (proibido no GLPI 11): `doQuery()` para `SHOW INDEX` e critérios em array para a contagem.
- `Html::clean()` (removido no GLPI 11) substituído por `htmlescape()`.
- `Html::displayNotFoundError()` e `Html::displayRightError()` (depreciados) substituídos pelas exceções `NotFoundHttpException` e `AccessDeniedHttpException`.
- Mensagens de aviso que carregam texto dinâmico (exceções, justificativas, nomes de documento) passam por `htmlescape()`: no GLPI 11 elas são exibidas sem codificação.
- Deixa de aplicar `addslashes`, `Toolbox::addslashes_deep` e `Sanitizer::sanitize` na criação de projeto, de anexo e de solução: o GLPI 11 espera valores crus em `add()`, e o escape antigo gravaria barras literais ou texto codificado.
- A ação em massa nativa passa a reconhecer a configuração de categoria (`glpiitemtypetables`).
- Removido o hook `CSRF_COMPLIANT`, depreciado e ignorado pelo GLPI 11.
- **Negativa da abertura não trava mais o fluxo.** Quando o solicitante reprova a solução que negou a abertura, o chamado reaberto volta para *Aguardando autorização de abertura* e o Gestor de Portfólio pode autorizar ou negar de novo. O acompanhamento publicado é editável em *Configuração das etapas do fluxo* (evento *Fluxo retomado após reprovação da negativa*). Cancelamento comum continua sem retomada. O texto padrão do aviso de consequência da negativa foi atualizado; instalações que o editaram mantêm o texto salvo.
- Depois de **Gerar**, **Salvar rascunho** ou **Enviar** um termo, a página permanece onde estava e o passo da jornada continua aberto, em vez de voltar ao topo (TAP no chamado; TEP e TA no projeto).
- A coluna **Ação** das listas de campos (TIP, TIP-DT, TAP, TEP, TA) e da configuração de categoria voltou a mostrar o botão *Editar*: o GLPI 11 prefixa o texto dos cabeçalhos com `- ` e os scripts comparavam o texto exato. O link agora também é gerado no servidor (`giveItem`), o que elimina um aviso de chave indefinida por linha no log.

## 0.9.14 — 2026-09-25

### Alterado

- A negativa do Gerente de Projetos no **TIP** deixa de encerrar a solicitação e passa a **devolvê-la ao solicitante para ajuste**. Antes, recusar solucionava o chamado e levava a solicitação para *Recusado/Cancelado*, que é um estado sem volta — o solicitante só podia recomeçar do zero em outro chamado. Agora o chamado continua aberto, a fase volta para *Aguardando preenchimento do TIP* e o formulário reabre **com tudo o que já havia sido respondido**, inclusive os anexos, que reaparecem marcados para serem mantidos. O solicitante corrige o que foi apontado e reenvia; o ciclo pode se repetir. O encerramento continua possível como cancelamento, a partir de qualquer fase.
- Ao devolver, o solicitante recebe a justificativa como **acompanhamento público** no chamado. Esse texto é editável em *Configuração das etapas do fluxo*, num bloco novo de **acompanhamento de eventos**: alguns acontecimentos precisam de texto próprio porque a fase de destino, sozinha, não explica o que houve — a devolução leva à mesma fase da primeira solicitação de preenchimento, mas só ela tem justificativa.
- Devolver o TIP passa a exigir a mesma verificação de responsável que aceitar já exigia. Antes, a ação que encerrava a solicitação era a menos controlada das duas.

- A negativa da **autorização de abertura** passa a ser registrada pela caixa nativa de solução do GLPI — a mesma da abertura do projeto —, com editor, modelo, anexos e **tipo de solução**. Isso resolve um problema de indicador: uma demanda negada era contabilizada como chamado solucionado igual a qualquer outro. Com o tipo de solução, basta cadastrar em *Tipos de solução* do GLPI algo como *Projeto negado* e *Projeto adiado* para separar, em relatórios e pesquisas nativas, o que foi atendido do que foi recusado. O chamado continua indo para **Solucionado**, e não *Fechado*, preservando a ciência do solicitante.
- Antes de confirmar a negativa, o Gestor de Portfólio vê um **aviso da consequência** — que a negativa encerra a solicitação e o fluxo não poderá ser retomado. O texto é editável em *Configuração das etapas do fluxo*, na coluna **Aviso de consequência**, que aparece apenas nas etapas cuja ação é irreversível.

- A tela de **Interações** do projeto ganhou três correções de uso. Ao clicar em *Responder*, a barra de ações (*Responder*, *Tarefa*, *Solução*, *Documento*, *Validação*, *Transferir projeto*) **some**, como já acontece na linha do tempo nativa do chamado — antes ela ficava sob o formulário aberto, competindo com ele. A página também **rola até o formulário**: ele abre no fim de uma lista longa e, fora da área visível, o clique parecia não ter feito nada. E o **seletor de modelo de acompanhamento parou de vazar para fora do painel** quando o modelo escolhido tem nome longo; agora o nome é truncado com reticências.
- Nova configuração **Transferência de projetos**, em *Configurar → Project+*: define, para cada entidade de origem, quais entidades podem receber os projetos dela. A origem é escolhida num seletor e os destinos numa tabela com flag de **Ativo**, com filtro por nome, paginação e marcação em lote. **Atenção ao atualizar: a regra é fechada por padrão — entidade sem nenhum destino ativo não transfere.** Como nenhuma origem vem configurada, a transferência fica indisponível até alguém cadastrar as regras; a tela avisa isso em destaque. A regra vale para a **entidade exata**: entidades filhas não herdam. O bloqueio é verificado no servidor, não só escondendo a opção na tela, e a lista de destinos também respeita as entidades que o usuário alcança.
- **O seletor de entidade da transferência foi refeito.** Ele trazia só o nome da folha: numa estrutura como a da UNIFESP, "São Paulo" aparecia duas vezes sem nada que distinguisse uma da outra — e transferir para a entidade errada não tem desfazer. Agora segue o arranjo do plugin **tickettransfer**: o caminho completo do pai vira o cabeçalho do grupo e os filhos aparecem com o nome curto, sem repetir o nome do pai em cada linha. A busca casa tanto o cabeçalho quanto os filhos, então procurar por *STI* encontra as entidades abaixo dela. A lista mostra apenas os destinos que a regra permite e que o usuário alcança. O registro gravado na linha do tempo passou a trazer o caminho completo, pelo mesmo motivo de ambiguidade.
- O botão **Escalar** passou a se chamar **Transferir projeto**, junto com as demais menções na interface do plugin — o rótulo do direito em *Perfis*, o nome do evento em *Notificações* e as mensagens da ação. Os identificadores internos não mudaram, então direitos já atribuídos continuam valendo. Notificações criadas por uma instalação anterior mantêm o nome antigo até serem renomeadas à mão.
- Um projeto do fluxo **só pode ser finalizado depois do aceite do TA**. Antes, qualquer pessoa com permissão de editar o projeto podia colocá-lo em estado finalizado ou em 100% sem que o cliente tivesse assinado o Termo de Aceite — o projeto aparecia concluído nos indicadores enquanto o documento que comprova a entrega não existia. Agora a tentativa é barrada com um aviso que diz o que fazer. A regra vale **apenas para projetos abertos pelo fluxo**, isto é, os que têm vínculo com um chamado: projeto criado direto no GLPI ou trazido por importação nunca terá um TA e continua fechando normalmente. O aceite do TA continua encerrando o projeto sozinho. Quando o percentual é calculado pelo GLPI a partir das tarefas (*auto_percent_done*), o número passa — ali ele é medida de andamento, não declaração de encerramento; o estado finalizado continua barrado nos dois casos. O texto da recusa é **editável** em *Configuração das etapas do fluxo*, na etapa **Encerramento do projeto**, e aceita a variável `{acao}` para citar o que foi tentado. Esvaziá-lo não desliga a trava: o sistema continua recusando, com uma explicação mínima, para a gravação nunca falhar em silêncio.
- Na tela **Configuração das etapas do fluxo**, os campos de cada etapa passam a sair da própria definição da etapa, em vez de um conjunto fixo de colunas. Na prática: cada etapa mostra só os textos que fazem sentido para ela — *Autorização de abertura* continua com os três, *Encerramento do projeto* aparece só com o aviso de recusa.
- As **interações de projeto passam a enviar e-mail**. O botão *Notificar participantes* gravava a intenção e nada mais: nenhuma linha de código lia aquela coluna, e nada era enviado. Agora o plugin acrescenta quatro eventos à notificação nativa do **Projeto** — *Nova interação*, *Solução registrada*, *Projeto escalado para outra entidade* e *Aviso sobre um termo* —, cada um com sua notificação e seu modelo, configuráveis em *Configurar → Notificações* como qualquer outra do GLPI. O caminho é o mesmo dos chamados: assim como o acompanhamento não é um tipo de notificação próprio e quem notifica é o chamado, aqui quem notifica é o projeto — o que traz de graça os destinatários que o GLPI já conhece (gerente, grupo do gerente, usuários e grupos da equipe, contatos e fornecedores). O envio em si continua sendo feito pela ação automática nativa *queuednotification*; o plugin apenas enfileira. **As quatro notificações nascem desativadas**, com o gerente do projeto como destinatário: quem instala decide quando começar a enviar e para quem. Interações marcadas como **internas nunca notificam**, mesmo com o botão ligado, porque a lista de destinatários do projeto inclui gente de fora da equipe.
- A tela **Configuração das etapas do fluxo** foi redesenhada. Ela usava três tabelas de até quatro colunas: em tela larga o nome da fase ficava espremido, o campo de acompanhamento esticava por mais de mil pixels e a coluna *Aviso de consequência* aparecia quase toda preenchida com travessão, já que só uma etapa tem aviso. Agora segue o mesmo visual das demais telas de configuração, com cada etapa em um cartão — ordem, nome e **identificador interno**, que é o que os gatilhos referenciam e antes não aparecia em lugar nenhum. As variáveis que podem ser usadas nos textos viraram uma legenda com o significado de cada uma, o campo de aviso de consequência recebeu destaque de alerta e o botão *Salvar* passou a acompanhar a rolagem.

### Corrigido

- **A recusa do TAP pelo cliente deixava o chamado travado.** O documento voltava corretamente para edição do Gerente de Projetos, mas a fase permanecia em *Aguardando aceite do TAP* — e o reenvio exige a fase *Autorizado para abertura*. Na prática: o cliente recusava, o gerente corrigia, e não conseguia mais enviar, sem que ninguém tivesse errado. A fase passa a acompanhar a devolução, e o ciclo recusar → corrigir → reenviar pode se repetir quantas vezes for necessário. TEP e TA não eram afetados, porque vivem no projeto, que não tem fases.
- **Avisos automáticos do fluxo reabriam chamados já solucionados.** O GLPI reabre um chamado solucionado quando quem comenta é o solicitante, e os acompanhamentos que o plugin publica a cada mudança de fase caíam nessa regra. Na prática, uma solicitação encerrada por negativa ou por abertura de projeto podia voltar a *Novo* no mesmo instante, com a data de solução apagada. Os avisos do plugin passam a ser marcados como mensagens de sistema, que informam sem reabrir.
- **A ordem dos termos só existia na tela.** O formulário do TA fica escondido até o TEP ser aceito, mas o endpoint de envio não verificava nada: era possível publicar o TA sem o TEP aceito e, pior, com a entrega recusada — pedindo ao cliente que assinasse o aceite de uma entrega que ele acabara de recusar. Como aceitar o TA encerra o projeto (100% concluído, estado finalizado), isso fechava como concluído um projeto cuja entrega não foi aceita. A regra passa a ser verificada no servidor.
- Um documento recusado **volta ao estado de rascunho**. Antes, a versão recusada continuava marcada como gerada — e, como o botão *Enviar para aceite* depende apenas de existir uma versão gerada, o responsável conseguia reenviar ao cliente exatamente o documento que acabara de ser recusado, sem corrigir nada e sem gerar nova versão. Agora *Enviar para aceite* só reaparece depois de o documento ser gerado de novo. O conteúdo é preservado: o responsável edita o que foi apontado, gera e envia.
- O aviso de recusa passa a ter **um texto por termo** — TAP, TEP e TA —, todos editáveis em *Configuração das etapas do fluxo*. O que o cliente recusa em cada um é diferente: no TEP a recusa é da **entrega**, não da redação do documento, e o texto agora diz isso.
- **Cancelar solicitação era oferecido onde não funcionava.** O bloco aparecia em todas as fases, inclusive depois do projeto aberto — mas ali não há para onde ir: o chamado já foi solucionado e a fase é final. Quem tentasse escrevia a justificativa e recebia o erro *"A etapa solicitada não pode ser executada antes da conclusão da etapa anterior"*, que não descreve o problema. Nessa fase o formulário dá lugar a um aviso explicando que a interrupção agora é do **projeto**, pelo estado nativo do GLPI, com link direto para ele. Nas demais fases o cancelamento continua igual.

### Removido

- Sai o cadastro de **modelos PDF** (tela *Modelos de documentos*, seleção de modelo na publicação e o endpoint correspondente). Ele era a primeira forma de publicar TAP, TEP e TA para aceite e foi substituído pelo PDF que o próprio plugin gera a partir dos campos preenchidos — na base de homologação, 25 documentos publicados pelo caminho gerado contra 1 pelo modelo. A tela já estava sem link no menu e a interface que consumia os modelos não tinha mais chamador. Aceites publicados anteriormente a partir de um modelo continuam abrindo normalmente: eles apontam para o documento do GLPI, não para o cadastro. A tabela `glpi_plugin_projectplus_documenttemplates` **não é apagada** na atualização; ela deixa de ser criada e de ser lida, e só é removida quando o plugin é desinstalado.

### Novidades

- Os modelos de documento ganham o tipo de campo **Anexo**, disponível no TIP, no TIP - DT, no TAP, no TEP e no TA. O envio usa a área de upload nativa do GLPI — arrastar e soltar, limite de tamanho e lista de extensões permitidas são os mesmos do restante do produto. Cada arquivo vira um documento do GLPI vinculado ao chamado ou ao projeto, aparece na aba **Documentos** e só é baixado por quem já podia abrir aquele registro. Um campo aceita até 10 arquivos; ao reabrir o formulário, cada anexo já gravado aparece com a opção *Manter*, e desmarcá-la o remove do campo ao salvar. No PDF gerado, o campo lista os nomes dos arquivos.
- **Preencher com origem** passa a oferecer duas informações do chamado que faltavam: **Número do chamado** e **Data de abertura**.

### Segurança

- **Listagem de solicitações**: a lista da Central e a página *Solicitações de projeto* passam a mostrar apenas os chamados que o usuário pode abrir no GLPI. Antes, o direito de leitura do Project+ exibia título, solicitante e fase de todos os chamados da entidade.
- **Documentos do fluxo**: leitura, visualização e PDF de TIP, TIP-DT, TAP, TEP e TA passam a validar o chamado ou projeto informado na URL, e não apenas a entidade. Antes, trocar o identificador dava acesso ao termo de outra solicitação.
- **Configuração por entidade**: gravação de fluxo, campos, modelos e categorias passa a exigir acesso efetivo à entidade de destino. Antes, um perfil administrativo restrito a uma subentidade conseguia gravar na entidade raiz ou em qualquer entidade superior.
- **Documento publicado como termo**: passa a ser servido apenas com tipos seguros para exibição embutida; os demais são baixados como anexo. O caminho do arquivo é confirmado dentro do diretório de documentos do GLPI.
- **Importação de projetos**: o lote passa a ser atômico. Uma falha no meio da importação desfaz tudo e indica a linha, em vez de deixar projetos criados pela metade.

- A lista **Meus projetos**, na visão do solicitante, passa a ser renderizada pelo mesmo componente de tabela da tela nativa de Projetos, com as mesmas colunas e na mesma ordem — Nome, Entidade, ID, Prioridade, Ranking, Código, Estado, Percentual finalizado, Data de criação e Descrição. Crachá de entidade com o caminho da árvore, bloco de prioridade colorido, célula de estado pintada com a cor do status e descrição resumida seguem o formato nativo, e os rótulos vêm do próprio GLPI. Projeto sem estado definido aparece em branco, como no GLPI, em vez de *Sem status*. A regra de quais projetos aparecem continua a mesma — apenas aqueles em que o usuário é solicitante ou ponto focal.
- Os campos que existem nas duas telas do portal passam a usar o mesmo nome da tela nativa (Nome, Código, Prioridade, Estado, Percentual finalizado, Data de criação, Última atualização e Tipo), em vez de traduções próprias do plugin.

### Correções

- Corrige a abertura do projeto quando o nome não tem origem configurada, está como preenchimento manual ou o campo mapeado chega vazio: o projeto passa a ser criado com o nome **Projeto sem nome**, em vez de falhar.
- O card **Autorização de abertura do projeto** permanece verde depois de concluído, em vez de voltar ao estado neutro quando o fluxo avança para o TAP e para a abertura; uma autorização negada passa a aparecer em vermelho.
- A **Priorização técnica** ganha dois botões: *Salvar priorização*, que grava sem encaminhar, e *Enviar para Gestor de Portfólio*, que exige todos os critérios preenchidos, encaminha a solicitação e deixa o bloco técnico somente leitura. A priorização estratégica só é liberada após esse envio. O envio usa o mesmo verde do *Aceitar* do TIP, para que concluir uma etapa tenha sempre a mesma cor no fluxo; salvar fica em âmbar, como as demais gravações.
- Na validação do TIP, **Aceitar** e **Recusar** passam a ficar lado a lado, em um rodapé alinhado à direita do documento, com rótulos curtos — o título do cartão já diz qual termo está em validação. A justificativa deixa de ocupar a tela o tempo todo: aparece apenas depois que a recusa é escolhida, já com o cursor no campo, e o botão *Cancelar* volta ao estado anterior descartando o texto. O botão que confirma a recusa mantém a consequência por extenso.
- Reordena as sub-abas do Portfólio de Projetos para **Interações**, **Termos** e **Priorização**. A aba aberta ao entrar passa a ser a primeira exibida; links e redirecionamentos que já indicam a aba continuam abrindo a aba indicada.
- Remove a caixa **Li e aceito os termos deste documento**: o botão *Aceitar e continuar* passa a ser a própria declaração de aceite, em todas as fases e também no portal do solicitante.
- O aceite dos documentos (TAP, TEP e TA) passa a usar o mesmo rodapé do TIP, mantendo os textos: *Aceitar e continuar* e *Recusar documento* lado a lado, alinhados à direita, com a justificativa aparecendo somente depois que a recusa é escolhida. Vale também no portal do solicitante.
- Reúne todas as folhas de estilo do plugin em `css/projectplus.css`. O GLPI passa a carregar **1 folha por página no lugar de 30**, sem alteração visual, de regra de negócio, de permissão ou de dado.
- As folhas foram concatenadas na ordem exata em que eram carregadas, preservando especificidade, ordem de cascata e `!important`. Foram removidas as declarações que já eram inalcançáveis por serem redeclaradas adiante pelo mesmo seletor, e as regras de 19 classes que não existem mais no markup — sobras das telas de configuração e da lista de fluxo reescritas para a pesquisa nativa do GLPI.
- Nenhum arquivo do plugin carrega mais sufixo de versão no nome. O JavaScript passou a se chamar `js/projectplus-ranking-search.js`. O cache do navegador já é invalidado pelo `?v=` que o GLPI anexa com a versão do plugin, então uma correção visual passa a ser editada no próprio arquivo.

## 0.9.13-beta

- Permite que substitutos continuem o fluxo quando o perfil ativo possui a permissão específica da etapa e acesso à entidade do chamado, sem substituir o Gerente de Projetos ou o Gestor de Portfólio titular configurado.
- Mantém os titulares como referências oficiais de roteamento, responsabilidades, observação e notificações, registrando nas fases o usuário que efetivamente executou cada ação.

## 0.9.12-beta

- Mantém a coluna **Ação** fixa na configuração do fluxo, independentemente das preferências de visão global ou pessoal.
- Preserva o botão **Editar** após pesquisas e atualizações AJAX da lista, associando cada ação ao ID correto da configuração.
- Remove com segurança configurações órfãs quando a entidade ou a categoria ITIL correspondente foi excluída ou deixou de pertencer à entidade configurada.
- Impede que categorias excluídas sejam recriadas durante a sincronização da tela, sem alterar chamados, fases ou históricos existentes.

## 0.9.11-beta

- Torna o fluxo estritamente sequencial, abre automaticamente apenas a fase atual e apresenta somente a próxima pendência necessária.
- Adiciona descrições configuráveis para as etapas, inclusive mensagens de etapa pendente e de encaminhamento para outra entidade.
- Inclui a autorização de abertura pelo Gestor de Portfólio, com permissão própria, autorização ou recusa mediante justificativa.
- Evolui o roteamento hierárquico: busca o responsável mais próximo na árvore, retorna o chamado ao Gerente de Projetos e adiciona o Gestor de Portfólio como observador quando aplicável.
- Acrescenta filtros Project+ na pesquisa nativa de chamados para fase atual, ranking, Gerente de Projetos e Gestor de Portfólio.
- Padroniza as listas administrativas com paginação e ações em massa e melhora a edição, exclusão segura e apresentação dos campos documentais.
- Corrige os tipos TIP e TIP-DT, oculta o filtro técnico de tipo de documento e mantém o botão Editar nas pesquisas dos documentos.
- Move a opção de disponibilização para entidades filhas para cada campo de TIP, TIP-DT, TAP, TEP e TA, permitindo composições diferentes por entidade sem tornar o modelo inteiro recursivo.
- Integra a caixa nativa de solução como etapa final da abertura do projeto, incluindo modelo, tipo, anexos e opção de base de conhecimento.
- Faz “Preenchimento manual” deixar efetivamente vazios os campos configuráveis do projeto e converte HTML para texto legível quando uma origem textual é selecionada explicitamente.
- Corrige a criação do projeto quando a descrição ou um valor mapeado contém HTML/CSS com apóstrofos.
- Corrige inconsistências visuais, barras de rolagem duplicadas e mensagens de bloqueio/permissão durante as transferências entre entidades.

## 0.9.10-beta
- Adiciona configuração por entidade da origem dos campos da tela nativa de Projetos.
- Permite sugerir valores do chamado, TIP, TIP - DT e TAP para todos os campos disponíveis no formulário de Projeto; quando não houver configuração ou conversão possível, mantém o comportamento padrão do GLPI.

## 0.9.9-beta
- Adiciona roteamento automático de solicitações entre entidades conforme o Gerente de Projetos ou Gestor de Portfólio configurado na categoria ativa do Project+.
- A busca sobe pela árvore somente em configurações recursivas, preserva uma categoria ativa no destino e retorna à entidade do Gerente de Projetos que já atuou na solicitação.
- Valida o responsável configurado e a permissão mandatória nas ações de TIP, TIP-DT, TAP e priorizações.

## 0.9.8-beta

- Corrige a edição manual da configuração de entidade e categoria do fluxo Project+: o formulário volta a salvar Gerente de Projetos, Gestor de Portfólio, recursividade e ativação.
- Evita a dupla validação de CSRF no formulário e usa token exclusivo para impedir que componentes assíncronos da mesma página invalidem o salvamento.
- Valida os responsáveis selecionados diretamente pelas associações de perfil, direito Project+ e escopo/recursividade de entidade do GLPI.

## 0.9.7-beta

- Corrige a migração da configuração de categorias do fluxo: instalações atualizadas agora recebem as colunas **Gerente de projetos**, **Gestor de portfólio** e **Recursivo** na tabela correta (`ticketconfigs`), preservando os registros já existentes.
- Corrige a instalação limpa, que agora cria essas três colunas diretamente na tabela de configuração do fluxo e não mais em tabelas auxiliares de listas suspensas.

## 0.9.6-beta

- Separa os modelos, campos e configurações do **TIP - Termo de Intenção de Projeto** e do **TIP - Detalhamento Técnico**.
- Migra os campos existentes para o TIP e cria uma lista inicial independente para o TIP-DT, sem alterar respostas ou PDFs históricos.
- Remove o preenchimento automático por correspondência de nome entre documentos; cada campo de TIP, TIP-DT, TAP, TEP e TA agora pode ter uma origem explícita configurada (um campo do chamado ou de um documento anterior do fluxo), sugerida ao abrir o formulário e sempre revisável antes de salvar.
- Impede alterações diretas de TAP, TEP e TA depois de publicados ou aceitos; a revisão só é liberada após recusa formal.
- Mantém novos modelos documentais sem campos predefinidos, preservando os campos já configurados em instalações existentes.
- Inclui a visão central de solicitações de projeto ("Solicitações de projeto" na Central e em `front/requests.php`) e adequa sua apresentação ao padrão visual nativo do GLPI.
- Restringe a atualização manual de fase ao cancelamento formal da solicitação; as demais fases passam a avançar automaticamente pelas próprias ações do fluxo (solicitar, preencher, gerar, enviar, aceitar/recusar).
- Expande automaticamente, na jornada documental, a etapa correspondente à fase atual do chamado.
- Adiciona ícones de ajuda contextual junto ao rótulo dos campos configuráveis, exibindo a descrição/orientação cadastrada.
- Amplia a configuração do fluxo por entidade e categoria ITIL com campos para Gerente de Projetos, Gestor de Portfólio, recursividade e ativação, com edição por linha na própria lista. **Estes dois campos ainda são apenas informativos nesta versão**: a atribuição é salva e validada (usuário precisa ter a permissão Project+ correspondente), mas ainda não é consultada pelas regras de preenchimento/aceite de TAP, TEP e TA — isso fica para uma próxima versão.
- Moderniza a tela de edição da configuração de categorias do fluxo com componentes e tema nativos do GLPI.
- Corrige erro "Unknown column ... is_recursive ... in order clause" na lista de fluxo de solicitação de projeto quando as colunas de Gerente de Projetos/Gestor de Portfólio/Recursivo ainda não existem na base (upgrade que atualizou o código antes de rodar a migração do plugin).

## 0.9.5-beta

- Corrige a edição dos campos configuráveis de TAP, TEP e TA quando registros antigos possuem descrição ou valores nulos.
- Adiciona edição das opções de listas suspensas e passa a renderizá-las nos formulários documentais.
- Exibe retorno explícito de sucesso ou falha ao salvar um campo documental.

## 0.9.4-beta

- Substitui a lista manual de categorias do fluxo pela pesquisa nativa do GLPI, com regras, grupos, ordenação, visões pessoal/global, paginação e seleção de registros.
- Expõe as colunas ID, Categoria ITIL, Entidade, Grupo e Ativo para pesquisa e configuração de listas nativas.
- Inclui ações em massa nativas para ativar ou desativar categorias no fluxo de solicitações de projeto.

## 0.9.1-beta

- Reorganiza as permissões do Project+ em matrizes por domínio: Documentos, Priorização, Fluxo, Projetos, Interações e Configurar.
- Separa visualmente as permissões de Ler, Atualizar e Criar das Interações, preservando a configuração individual de cada ação.
- Alinha Abrir projeto e as ações de Interação à permissão Criar correspondente.

## 0.9.0-beta

- Inclui a matriz granular de permissões do Project+, separada nos blocos Assistência, Projetos e Configurar.
- Mantém as novas permissões desabilitadas por padrão em todos os perfis; a concessão passa a ser explícita pelo administrador.
- Ajusta a exibição de data e hora do fluxo, portal, aceite documental e PDF do TIP para obedecer ao formato configurado no GLPI.

## 0.8.0-beta

- Unifica o acesso documental do projeto na área **Project+**, com abas internas para Termos, Interações e Priorização.
- Exibe TIP, TAP, TEP e TA na sequência documental, com estados visuais, legenda e expansão individual.
- Amplia o portal do solicitante/ponto focal com Termos e Interações vinculados ao projeto.
- Mantém a aba interna atual após ações de interação, priorização, documentos e aceite.
- Evolui a abertura do projeto a partir do chamado, com vínculo nativo, validações do fluxo e solução informada ao solicitante.
- Inclui priorização institucional no chamado e no projeto, com ranking e sincronização da prioridade nativa do GLPI.
- Evolui os documentos TAP, TEP e TA para campos configuráveis, rascunho, geração, envio, aceite e recusa auditáveis.

## 0.7.2-beta

- Consolida a área **Project+** no projeto com Termos, Interações e Priorização.
- Amplia o fluxo documental TIP, TAP, TEP e TA, incluindo rascunhos, geração, aceite e rastreabilidade.
- Inclui a priorização institucional por ranking e sua integração com a prioridade nativa do GLPI.
- Evolui as Interações com modelos, privacidade, notificações, solução com TEP e escalação de projeto.
- Atualiza a configuração e os layouts do fluxo, dos termos e das telas administrativas.
- Corrige rótulos, mensagens e caracteres com acentuação/codificação incorreta.

## 0.7.0-beta

- Torna o TIP configurável por entidade: título, introdução, campos, tipos, ordem, obrigatoriedade e ativação.
- A área responsável agora deve usar a ação **Solicitar preenchimento do TIP** no chamado antes que o solicitante possa acessar o formulário.
- Registra as respostas por campo e mantém a fase interna em **Aguardando preenchimento do TIP** até o envio.

## 0.6.0-beta

- Inclui o TIP (Termo de Intenção do Projeto) dentro da aba Solicitação de projeto do chamado.
- Registra de forma auditável o formulário submetido pelo solicitante, incluindo usuário, e-mail, data/hora e entidade.
- Valida solicitante vinculado ao chamado, entidade, CSRF e campos obrigatórios antes do envio.
- Mantém o TIP enviado em modo de consulta para apoiar a análise do gerente de projetos.

## 0.3.10

- Inclui a coluna obrigatória **ID da entidade** no modelo de importação e confere sua correspondência com o nome da entidade informado.

## 0.3.9

- Corrige a importação para reconhecer a entidade raiz do GLPI, cujo identificador nativo é `0`.

## 0.3.8

- Torna a busca de entidade e dropdowns da importação tolerante a maiúsculas, minúsculas e acentos.
- Corrige a consulta aos dropdowns globais de Tipo e Status e aceita os aliases de status em português, incluindo `Executando`.

## 0.3.7

- Aceita datas de importação no formato `AAAA/MM/DD HH:MM`, convertendo-as para o formato interno do GLPI.

## 0.3.6

- Amplia a importação para aceitar arquivos separados por tabulação e codificados em Windows-1252/ANSI, além de datas no formato `DD/MM/AAAA HH:MM` e percentuais com `%`.

## 0.3.5

- Corrige a validação dos cabeçalhos da importação para aceitar a normalização de acentos e caracteres invisíveis feita por Excel e LibreOffice.

## 0.3.4

- Adiciona o download da planilha-modelo CSV diretamente na tela de importação de projetos existentes.

## 0.3.3

- Reestrutura o detalhe de **Meus projetos** para reproduzir, em modo leitura, os blocos e o layout de duas colunas do formulário nativo de Projetos.
- Exibe os dados nativos e os campos do Project+ como controles somente leitura e corrige a renderização de conteúdo HTML que estava duplamente codificado.

## 0.3.2

- Torna **Meus projetos** um atalho único no portal, sem submenu.
- Aproxima a listagem e o detalhe do padrão visual do módulo de Projetos, mantendo somente a aba **Projeto** em modo leitura.

## 0.3.1

- Corrige a gravação de solicitante e ponto focal quando somente os campos do Project+ são alterados no formulário do projeto.

## 0.3.0

- Adiciona o atalho **Meus projetos** ao portal do solicitante, logo após **Meus chamados**, apenas para usuários vinculados como solicitante ou ponto focal.
- Inclui lista e detalhe próprios do portal, em modo exclusivamente leitura e com validação de vínculo e entidade para cada projeto.

## 0.2.6

- Unifica o registro dos dropdowns com as chaves de menu do GLPI, restaurando a barra nativa com **Adicionar**, pesquisa e listas em ambas as telas.

## 0.2.5

- Usa adaptadores de classe compatíveis com o menu de listas suspensas do GLPI 10, fazendo Cestas Portfólio e Ações PDTIC exibirem a barra nativa com **Adicionar**, pesquisa e listas.

## 0.2.4

- Alinha as permissões de Cestas Portfólio e Ações PDTIC ao direito nativo de listas suspensas do GLPI, permitindo a inclusão para os mesmos perfis autorizados a gerenciar dropdowns.

## 0.2.3

- Usa os controladores nativos `CommonDropdown` do GLPI para Cestas Portfólio e Ações PDTIC, disponibilizando a barra padrão com **Adicionar**, pesquisa, listas e operações de edição/exclusão.

## 0.2.2

- Corrige o contexto das listas do Project+ para que o botão nativo **Adicionar** seja exibido em Cestas Portfólio e Ações PDTIC.

## 0.2.1

- Move os cadastros de Cestas Portfólio e Ações PDTIC para **Configurar > Listas suspensas > Projetos+**, com as telas nativas do GLPI para inclusão, edição e exclusão.

## 0.2.0

- importação assistida de Projetos existentes por CSV UTF-8;
- pré-validação de entidades, códigos, usuários, datas e dropdowns antes da gravação;
- criação de Projetos nativos, dados ProjectPlus e vínculos de projeto-pai;
- relatório de resultado após confirmação da importação.

## 0.1.1

- solicitante e ponto focal vinculados a usuÃ¡rios existentes do GLPI;
- preenchimento automÃ¡tico de nome de exibiÃ§Ã£o e e-mail a partir do cadastro do usuÃ¡rio;
- validaÃ§Ã£o de usuÃ¡rio ativo, e-mail e acesso Ã  entidade do Projeto.

## 0.1.0-beta

Versão embrionária para homologação no GLPI 10:

- campos complementares no cadastro nativo de Projetos;
- blocos de Dados do Projeto, Planejamento e Dados do solicitante;
- dropdowns multi-entidade para Cesta Portfólio e Ação PDTIC.
