# Changelog

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
