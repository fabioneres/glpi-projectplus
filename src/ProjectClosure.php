<?php

namespace GlpiPlugin\Projectplus;

use Project;
use ProjectState;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Trava de encerramento do projeto.
 *
 * Um projeto do fluxo so pode chegar a estado finalizado ou a 100% depois que
 * o cliente aceitar o TA. Sem isso, o encerramento documental e o encerramento
 * real do projeto podem divergir: o projeto aparece concluido nos indicadores
 * enquanto o termo que comprova a entrega nunca foi assinado.
 *
 * Vale **somente para projetos abertos pelo fluxo** - os que tem vinculo com um
 * chamado. Projeto criado direto no GLPI ou trazido por importacao nunca tera
 * um TA, e exigir um deles tornaria esses projetos impossiveis de fechar.
 *
 * A checagem roda no hook `pre_item_update`. Nao e preciso nenhuma excecao para
 * a acao do proprio plugin: `DocumentAcceptance::accept()` grava o aceite do TA
 * antes de atualizar o projeto, entao quando esta trava roda o TA ja esta
 * aceito e a atualizacao passa.
 */
class ProjectClosure
{
   /**
    * Deixa a atualizacao seguir, ou a barra com um aviso.
    *
    * @return bool false quando barrou - o chamador nao deve gravar mais nada.
    */
   public static function allowUpdate(Project $project): bool
   {
      $input = $project->input ?? [];
      if (!is_array($input)) {
         return false;
      }

      $motivo = self::blockingReason($project, $input);
      if ($motivo === '') {
         return true;
      }

      Session::addMessageAfterRedirect($motivo, false, ERROR);
      // O GLPI aborta a atualizacao inteira quando o hook zera a entrada.
      $project->input = false;
      return false;
   }

   /** @param array<string, mixed> $input */
   private static function blockingReason(Project $project, array $input): string
   {
      $projectId = (int) $project->getID();
      if ($projectId <= 0 || TicketProject::getTicketIdForProject($projectId) === 0) {
         return '';
      }
      if (self::isTaAccepted($project)) {
         return '';
      }

      $encerra = [];
      if (self::wouldSetFinishedState($input)) {
         $encerra[] = __('o estado de finalizado', 'projectplus');
      }
      if (self::wouldSetFullPercent($project, $input)) {
         $encerra[] = __('o percentual de 100%', 'projectplus');
      }
      if ($encerra === []) {
         return '';
      }

      $acao = implode(__(' nem ', 'projectplus'), $encerra);
      $texto = trim(FlowStageDescription::get('project_closure', 'blocked'));
      // Esvaziar o texto na configuracao nao pode fazer a gravacao falhar em
      // silencio: sem explicacao, o usuario so ve o formulario nao salvar.
      if ($texto === '') {
         $texto = __('Não é possível aplicar {acao} antes do aceite do TA - Termo de Aceite.', 'projectplus');
      }

      return str_replace('{acao}', $acao, $texto);
   }

   private static function isTaAccepted(Project $project): bool
   {
      $ta = DocumentAcceptance::getForItem($project, 'ta');
      return (string) ($ta['status'] ?? '') === 'accepted';
   }

   /** @param array<string, mixed> $input */
   private static function wouldSetFinishedState(array $input): bool
   {
      if (!array_key_exists('projectstates_id', $input)) {
         return false;
      }
      return self::isFinishedState((int) $input['projectstates_id']);
   }

   /**
    * O percentual so conta como encerramento quando alguem o declara.
    *
    * Com `auto_percent_done` ligado quem calcula e o GLPI, a partir das
    * tarefas: barrar esse recalculo quebraria a edicao de tarefa sem que o
    * usuario tivesse pedido nada, e o numero ali e metrica, nao declaracao de
    * encerramento. O estado de finalizado continua barrado nos dois casos, e e
    * ele que encerra o projeto.
    *
    * @param array<string, mixed> $input
    */
   private static function wouldSetFullPercent(Project $project, array $input): bool
   {
      if (!array_key_exists('percent_done', $input)) {
         return false;
      }
      $automatico = array_key_exists('auto_percent_done', $input)
         ? (int) $input['auto_percent_done'] === 1
         : (int) ($project->fields['auto_percent_done'] ?? 0) === 1;
      if ($automatico) {
         return false;
      }
      return (int) $input['percent_done'] === 100
         && (int) ($project->fields['percent_done'] ?? 0) !== 100;
   }

   private static function isFinishedState(int $stateId): bool
   {
      if ($stateId <= 0) {
         return false;
      }
      /** @var \DBmysql $DB */
      global $DB;
      $state = $DB->request([
         'FROM' => ProjectState::getTable(),
         'WHERE' => ['id' => $stateId],
         'LIMIT' => 1,
      ])->current();
      return $state !== null && (int) $state['is_finished'] === 1;
   }
}
