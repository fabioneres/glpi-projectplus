# Project+ — Portfólio de Projetos

<p align="center">
  <img src="public/pics/logo.png" alt="Logo do plugin Project+" width="180">
</p>

[![Licença](https://img.shields.io/badge/Licen%C3%A7a-GPLv3%2B-orange)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Versão](https://img.shields.io/badge/Versão-1.0.0--alpha.1-green)](CHANGELOG.md)
[![GLPI](https://img.shields.io/badge/GLPI-11.0.x%20apenas-blue)](#compatibilidade)
[![Repositório](https://img.shields.io/badge/Reposit%C3%B3rio-GitLab-informational)](https://git.unifesp.br/dssc/sua-unifesp/glpi/plugins/project-plus)

Plugin para GLPI 11 que organiza a solicitação, avaliação, priorização,
autorização e abertura de projetos a partir de chamados. O Project+ preserva o
chamado como registro da demanda e cria o projeto nativo do GLPI somente depois
da conclusão sequencial das etapas exigidas.

> ### ⚠️ Compatibilidade: somente GLPI 11.0.x
>
> Esta linha funciona exclusivamente em **GLPI 11.0.0 a 11.0.99**, com
> **PHP 8.2 ou superior**. Não instale em GLPI 10: para ele existe a linha `0.x`.

## Funcionalidades

**Fluxo de solicitação de projeto**

- Etapas sequenciais: TIP, TIP - Detalhamento Técnico, priorizações técnica e
  estratégica, autorização de abertura, TAP e criação do projeto.
- Nenhuma etapa posterior pode ser executada antes da conclusão da anterior.
- Textos de orientação, pendência, encaminhamento e consequência configuráveis
  por etapa.
- Recusa do TIP devolve a solicitação ao requerente para ajuste, mantendo as
  respostas e os anexos já enviados.
- Autorização de abertura pelo Gestor de Portfólio, com registro de justificativa
  e possibilidade de negar a abertura pela solução nativa do GLPI.

**Documentos e termos**

- Modelos independentes de TIP, TIP - DT, TAP, TEP e TA, com campos
  configuráveis por entidade, obrigatoriedade, ordem e disponibilidade para
  entidades filhas.
- Preenchimento a partir de fontes explícitas — chamado e documentos anteriores
  — sem sobrescrever campos definidos como preenchimento manual.
- Geração, envio, aceite, recusa, histórico e PDF dos documentos com controle
  de acesso ao item de origem.

**Roteamento, priorização e projeto**

- Roteamento por entidade, categoria ITIL, Gerente de Projetos e Gestor de
  Portfólio, com busca hierárquica do responsável mais próximo.
- Perfis granulares para documentos, priorizações, fluxo, projetos e interações.
- Ranking institucional e filtros na pesquisa nativa de chamados e projetos.
- Abertura de projeto nativo vinculada ao chamado, com caixa de solução nativa,
  modelos, anexos e tipo de solução.
- Configuração da origem dos campos nativos do projeto e bloqueio de conclusão
  de projetos do fluxo até o aceite do TA.

**Acompanhamento e portal**

- Interações, tarefas, soluções, documentos, validações e transferência de
  projetos entre entidades autorizadas.
- Notificações nativas para interações de projeto, configuráveis pelo GLPI.
- Portal de consulta para solicitantes e pontos focais, com projetos vinculados,
  termos e interações permitidas.

## Como funciona

O fluxo respeita o perfil ativo, a visibilidade do chamado e o acesso efetivo à
entidade. Configurações recursivas nunca substituem a autorização ao objeto
específico.

## Demonstração

Em uma solicitação de projeto habilitada, o solicitante preenche o TIP; o
Gerente de Projetos analisa e libera o Detalhamento Técnico; as priorizações e
a autorização de abertura são concluídas; por fim, o TAP é aceito antes de o
Project+ criar o projeto nativo vinculado e solucionar o chamado.

## Telas

| Configuração do fluxo | TIP no chamado | Visão do portfólio |
|---|---|---|
| ![Configuração do fluxo por categoria](docs/screenshots/01-configuracao.png) | ![Formulário do TIP](docs/screenshots/02-tip.png) | ![Visão do portfólio](docs/screenshots/03-portfolio.png) |

Capturas feitas em ambiente de teste; os logins de usuários foram substituídos por rótulos genéricos.

## Compatibilidade

- GLPI: **11.0.0 a 11.0.99**.
- PHP: **8.2 ou superior**.
- Banco de dados: MySQL ou MariaDB compatíveis com o GLPI 11.

## Instalação

1. Copie o conteúdo do pacote para `GLPI_ROOT/plugins/projectplus/` (o
   diretório do plugin deve se chamar exatamente `projectplus`).
2. Em **Configurar > Plugins**, instale e ative **Project+**.
3. Em **Administração > Perfis**, conceda os direitos do Project+ aos perfis
   que atuarão no fluxo.
4. Em **Configurar > Project+**, habilite as categorias ITIL, configure os
   responsáveis, os modelos documentais e as regras aplicáveis às entidades.

Para atualizar, faça backup da pasta do plugin e use a pasta completa da versão
nova. Não use cópia parcial por sobrescrita: arquivos removidos em versões novas
podem permanecer na instalação anterior.

## Permissões

As permissões ficam em **Administração > Perfis > Project+** e são separadas em
Documentos, Priorização, Fluxo, Projetos, Interações e Configurar. A posse de
uma permissão não dispensa o acesso ao chamado, projeto ou entidade envolvidos.

Quem pode atuar em cada etapa é definido **pelos direitos do perfil**, e não por
uma pessoa específica: qualquer usuário cujo perfil tenha o direito da etapa, com
acesso ao chamado e à entidade, pode executá-la. O Gerente de Projetos e o Gestor
de Portfólio configurados por categoria servem apenas para **direcionar o chamado
entre as entidades** (a do gerente e a do gestor) e para as notificações; eles não
restringem quem pode atuar.

## Versão e histórico

- Versão atual: **1.0.0-alpha.1** (linha para GLPI 11, em validação; ainda sem
  tag nem release publicada).
- Para GLPI 10 existe a linha `0.x`, última versão `0.9.14`.
- Histórico completo: [CHANGELOG.md](CHANGELOG.md).

## Documentação

- [Histórico de alterações](CHANGELOG.md)

## Suporte e contato

- Dúvidas, problemas e sugestões: [GitLab Issues](https://git.unifesp.br/dssc/sua-unifesp/glpi/plugins/project-plus/-/issues)
- Autor: Fabio Neres
- Equipe responsável: SUA UNIFESP
- Licença: [GPLv3+](https://www.gnu.org/licenses/gpl-3.0.html)
