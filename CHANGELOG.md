# Changelog

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
