<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Dropdown;
use Html;
use Project;
use Session;
use Ticket;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Institutional ranking captured before a project is authorized for opening. */
class TicketPriority
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketpriorities';
   }

   /** @return array<string, mixed> */
   public static function getForTicket(int $ticketId): array
   {
      global $DB;
      $criteria = ProjectPriority::getCriteria();
      $defaults = array_fill_keys(array_keys($criteria), 0);
      $defaults += ['tickets_id' => $ticketId, 'entities_id' => 0, 'ranking' => 0, 'date_mod' => null];
      if ($ticketId <= 0 || !$DB->tableExists(self::getTable())) {
         return $defaults;
      }
      $row = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['tickets_id' => $ticketId], 'LIMIT' => 1])->current();
      return $row ? array_merge($defaults, $row) : $defaults;
   }

   public static function isComplete(int $ticketId): bool
   {
      $data = self::getForTicket($ticketId);
      foreach (ProjectPriority::getCriteria() as $field => $label) {
         if ((int) ($data[$field] ?? 0) < 1) {
            return false;
         }
      }
      return true;
   }

   public static function saveForTicket(Ticket $ticket, array $input): void
   {
      if (!Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, UPDATE)
         || !Session::haveRight('ticket', UPDATE)
         || !Session::haveAccessToEntity((int) $ticket->getEntityID(), true)) {
         throw new \RuntimeException(__('Sem permissão para priorizar esta solicitação.', 'projectplus'));
      }

      $values = [];
      $complete = true;
      foreach (ProjectPriority::getCriteria() as $field => $label) {
         $value = (int) ($input[$field] ?? 0);
         if ($value < 0 || $value > 5) {
            throw new \RuntimeException(__('Valor de priorização inválido.', 'projectplus'));
         }
         $values[$field] = $value;
         $complete = $complete && $value > 0;
      }
      $values['ranking'] = $complete ? array_sum($values) : 0;
      $values['entities_id'] = (int) $ticket->getEntityID();
      $values['date_mod'] = date('Y-m-d H:i:s');
      /** @var DBmysql $DB */
      global $DB;
      $DB->updateOrInsert(
         self::getTable(),
         $values + ['tickets_id' => (int) $ticket->getID(), 'date_creation' => date('Y-m-d H:i:s')],
         ['tickets_id' => (int) $ticket->getID()]
      );
      if ($complete) {
         $ticket->update([
            'id' => (int) $ticket->getID(),
            'priority' => ProjectPriority::getNativePriorityForRanking((int) $values['ranking']),
         ]);
      }
   }

   /** Transfers the approved ticket ranking to the newly created Project. */
   public static function copyToProject(Ticket $ticket, Project $project): void
   {
      $data = self::getForTicket((int) $ticket->getID());
      if (!self::isComplete((int) $ticket->getID())) {
         return;
      }
      $values = [];
      foreach (ProjectPriority::getCriteria() as $field => $label) {
         $values[$field] = (int) $data[$field];
      }
      $values['ranking'] = (int) $data['ranking'];
      $values['entities_id'] = (int) $project->getEntityID();
      $values['date_mod'] = date('Y-m-d H:i:s');
      global $DB;
      $DB->updateOrInsert(
         ProjectPriority::getTable(),
         $values + ['projects_id' => (int) $project->getID(), 'date_creation' => date('Y-m-d H:i:s')],
         ['projects_id' => (int) $project->getID()]
      );
      $project->update([
         'id' => (int) $project->getID(),
         'priority' => ProjectPriority::getNativePriorityForRanking((int) $data['ranking']),
      ]);
   }

   public static function showForTicket(Ticket $ticket): void
   {
      if (!Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)) {
         return;
      }
      $data = self::getForTicket((int) $ticket->getID());
      $ranking = (int) $data['ranking'];
      $nativePriority = ProjectPriority::getNativePriorityForRanking($ranking);
      $canEdit = Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, UPDATE)
         && Session::haveRight('ticket', UPDATE)
         && Session::haveAccessToEntity((int) $ticket->getEntityID(), true);
      global $CFG_GLPI;
      $url = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketpriority.form.php';
      $priorityLabel = $ranking
         ? __('Prioridade', 'projectplus') . ' – ' . \CommonITILObject::getPriorityName($nativePriority)
         : __('Não classificada', 'projectplus');
      $style = ProjectPriority::getPriorityStyle($nativePriority);

      echo "<section class='projectplus-priority projectplus-priority--{$nativePriority}' {$style}><header class='projectplus-priority__header projectplus-priority__header--{$nativePriority}'><div><h3>" . self::escape(__('Priorização para abertura do projeto', 'projectplus')) . '</h3><p>' . self::escape(__('Preencha os critérios para subsidiar a decisão de transformar esta solicitação em projeto.', 'projectplus')) . "</p></div><div class='projectplus-priority__score projectplus-priority__score--{$nativePriority}'><span>" . self::escape(__('Ranking', 'projectplus')) . '</span><strong>' . ($ranking ?: '—') . "</strong><em class='projectplus-priority__native projectplus-priority__native--{$nativePriority}'>" . self::escape($priorityLabel) . '</em></div></header>';
      if ($canEdit) {
         echo "<form method='post' action='" . self::escape($url) . "' data-track-changes='true'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      }
      echo "<div class='projectplus-priority__grid'>";
      foreach (ProjectPriority::getCriteria() as $field => $label) {
         echo "<div class='projectplus-priority__field'><label>" . self::escape(__($label, 'projectplus')) . '</label>';
         Dropdown::showFromArray($field, ProjectPriority::getLevelOptions(), ['value' => (int) $data[$field], 'disabled' => !$canEdit]);
         echo '</div>';
      }
      echo '</div><footer class="projectplus-priority__footer"><small>' . self::escape(__('O ranking é calculado pela soma dos sete critérios preenchidos. A autorização de abertura requer o preenchimento completo.', 'projectplus')) . '</small>';
      if ($canEdit) {
         echo "<button class='btn btn-primary' type='submit' name='save_ticket_priority' value='1'><i class='ti ti-device-floppy'></i> " . self::escape(__('Salvar priorização', 'projectplus')) . '</button></form>';
      }
      echo '</footer></section>';
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
