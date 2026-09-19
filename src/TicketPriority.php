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

   /** Checks completion only for one of the two independent priority blocks. */
   public static function isCompleteForType(int $ticketId, string $type): bool
   {
      $data = self::getForTicket($ticketId);
      foreach (ProjectPriority::getCriteriaForPriorityType($type) as $field => $label) {
         if ((int) ($data[$field] ?? 0) < 1) {
            return false;
         }
      }
      return true;
   }

   /** The technical assessment starts only after the technical-detail TIP is generated. */
   public static function isTechnicalPriorityAvailable(Ticket $ticket): bool
   {
      $intent = TicketIntent::getForTicket((int) $ticket->getID(), TipDocumentType::DETAIL);
      return (string) ($intent['status'] ?? '') === 'generated';
   }

   /** The strategic assessment follows the submission of the technical block. */
   public static function isStrategicPriorityAvailable(Ticket $ticket): bool
   {
      return self::isTechnicalPriorityAvailable($ticket)
         && self::isCompleteForType((int) $ticket->getID(), 'technical')
         && self::isTechnicalSent((int) $ticket->getID());
   }

   /** Tells whether the columns added for the explicit submission are in place. */
   private static function hasSubmissionColumns(): bool
   {
      global $DB;
      return $DB->tableExists(self::getTable())
         && $DB->fieldExists(self::getTable(), 'date_technical_sent');
   }

   /** The technical block was handed over to the Portfolio Manager. */
   public static function isTechnicalSent(int $ticketId): bool
   {
      if (!self::hasSubmissionColumns()) {
         // Before the upgrade runs, completion is the only available evidence
         // and it matches the previous behaviour, where completing routed.
         return self::isCompleteForType($ticketId, 'technical');
      }
      $data = self::getForTicket($ticketId);
      return trim((string) ($data['date_technical_sent'] ?? '')) !== '';
   }

   public static function saveForTicket(Ticket $ticket, array $input): void
   {
      $type = (string) ($input['priority_type'] ?? '');
      if (!in_array($type, ['technical', 'institutional'], true)) {
         throw new \RuntimeException(__('Tipo de priorização inválido.', 'projectplus'));
      }
      if ($type === 'technical' && !self::isTechnicalPriorityAvailable($ticket)) {
         throw new \RuntimeException(__('A priorização técnica será liberada após a geração do TIP - Detalhamento Técnico.', 'projectplus'));
      }
      if ($type === 'institutional' && !self::isStrategicPriorityAvailable($ticket)) {
         throw new \RuntimeException(__('A priorização estratégica será liberada após a conclusão da priorização técnica.', 'projectplus'));
      }
      TicketPhase::assertCurrentPhase(
         $ticket,
         'portfolio_analysis',
         __('A priorização só pode ser preenchida após a geração do TIP - Detalhamento Técnico e na etapa de análise do portfólio.', 'projectplus')
      );
      TicketRouting::assertActor(
         $ticket,
         $type === 'institutional' ? 'portfolio' : 'project',
         $type === 'institutional' ? ProjectProfile::RIGHT_PRIORITY_STRATEGIC : ProjectProfile::RIGHT_PRIORITY_TECHNICAL
      );
      $editable = ProjectPriority::getEditableCriteria($type);
      if ($editable === [] || !Session::haveAccessToEntity((int) $ticket->getEntityID())) {
         throw new \RuntimeException(__('Sem permissão para priorizar esta solicitação.', 'projectplus'));
      }
      // Submitting is an explicit decision of the technical block: saving keeps
      // the request with the Project Manager, sending hands it over.
      $send = $type === 'technical' && !empty($input['send_to_portfolio']);
      if ($type === 'technical' && self::isTechnicalSent((int) $ticket->getID())) {
         throw new \RuntimeException(__('A priorização técnica já foi enviada ao Gestor de Portfólio e não pode mais ser alterada.', 'projectplus'));
      }

      $current = self::getForTicket((int) $ticket->getID());
      $values = [];
      $complete = true;
      foreach (ProjectPriority::getCriteria() as $field => $label) {
         if (!isset($editable[$field]) && array_key_exists($field, $input)) {
            throw new \RuntimeException(__('Sem permissão para alterar este critério.', 'projectplus'));
         }
         $value = isset($editable[$field]) ? (int) ($input[$field] ?? $current[$field] ?? 0) : (int) ($current[$field] ?? 0);
         if (!in_array($value, [0, 1, 3, 5], true)) {
            throw new \RuntimeException(__('Valor de priorização inválido.', 'projectplus'));
         }
         $values[$field] = $value;
         $complete = $complete && $value > 0;
      }
      $typeComplete = true;
      foreach (ProjectPriority::getCriteriaForPriorityType($type) as $field => $label) {
         $typeComplete = $typeComplete && (int) ($values[$field] ?? 0) > 0;
      }
      if ($send) {
         if (!$typeComplete) {
            throw new \RuntimeException(__('Preencha todos os critérios da priorização técnica antes de enviar ao Gestor de Portfólio.', 'projectplus'));
         }
         // Validate the next owner before committing the submission, avoiding a
         // block closed for editing with no valid route forward.
         TicketRouting::assertRoleCanBeRouted(
            $ticket,
            'portfolio',
            ProjectProfile::RIGHT_PRIORITY_STRATEGIC
         );
      }
      // Keep the accumulated score visible after each block. The native GLPI
      // priority remains unchanged until all technical and strategic criteria
      // have been completed.
      $values['ranking'] = array_sum($values);
      $values['entities_id'] = (int) $ticket->getEntityID();
      $values['date_mod'] = date('Y-m-d H:i:s');
      if ($send && self::hasSubmissionColumns()) {
         $values['date_technical_sent'] = date('Y-m-d H:i:s');
         $values['technical_sent_users_id'] = (int) Session::getLoginUserID();
      }
      /** @var DBmysql $DB */
      global $DB;
      $DB->updateOrInsert(
         self::getTable(),
         $values + ['tickets_id' => (int) $ticket->getID(), 'date_creation' => date('Y-m-d H:i:s')],
         ['tickets_id' => (int) $ticket->getID()]
      );
      if ($send) {
         // The technical block belongs to the previously recorded Project
         // Manager. On submission the request moves to the closest eligible
         // Portfolio Manager in the ancestor chain for strategic priority.
         TicketRouting::routeToRole(
            $ticket,
            'portfolio',
            ProjectProfile::RIGHT_PRIORITY_STRATEGIC
         );
      }
      if ($type === 'institutional' && $complete) {
         TicketPhase::setPhase(
            $ticket,
            'awaiting_authorization',
            __('Priorização estratégica concluída. Aguardando autorização de abertura.', 'projectplus')
         );
      }
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

   public static function showForTicket(Ticket $ticket, string $type = 'technical'): void
   {
      if (!in_array($type, ['technical', 'institutional'], true) || !ProjectPriority::canViewPriorityType($type)) {
         return;
      }
      if ($type === 'technical' && !self::isTechnicalPriorityAvailable($ticket)) {
         echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>" . self::escape(__('Priorização técnica ainda não liberada.', 'projectplus')) . '</strong><br>' . self::escape(FlowStageDescription::get('technical_priority', 'pending')) . '</div>';
         return;
      }
      if ($type === 'institutional' && !self::isStrategicPriorityAvailable($ticket)) {
         echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>" . self::escape(__('Priorização estratégica ainda não liberada.', 'projectplus')) . '</strong><br>' . self::escape(FlowStageDescription::get('strategic_priority', 'pending')) . '</div>';
         return;
      }
      $data = self::getForTicket((int) $ticket->getID());
      $ranking = (int) $data['ranking'];
      $fullyClassified = self::isComplete((int) $ticket->getID());
      $nativePriority = $fullyClassified ? ProjectPriority::getNativePriorityForRanking($ranking) : 0;
      $editable = ProjectPriority::getEditableCriteria($type);
      $technicalSent = self::isTechnicalSent((int) $ticket->getID());
      // Once handed over to the Portfolio Manager, the technical block becomes
      // the record of what was submitted.
      $canEdit = $editable !== [] && Session::haveAccessToEntity((int) $ticket->getEntityID())
         && !($type === 'technical' && $technicalSent);
      global $CFG_GLPI;
      $url = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketpriority.form.php';
      $priorityLabel = $fullyClassified
         ? __('Prioridade', 'projectplus') . ' – ' . \CommonITILObject::getPriorityName($nativePriority)
         : ($ranking ? __('Ranking parcial', 'projectplus') : __('Não classificada', 'projectplus'));
      $style = ProjectPriority::getPriorityStyle($nativePriority);

      $title = $type === 'technical' ? __('Priorização técnica', 'projectplus') : __('Priorização estratégica', 'projectplus');
      $description = FlowStageDescription::get($type === 'technical' ? 'technical_priority' : 'strategic_priority');
      echo "<section class='projectplus-priority projectplus-priority--{$nativePriority}' {$style}><header class='projectplus-priority__header projectplus-priority__header--{$nativePriority}'><div><h3>" . self::escape($title) . '</h3><p>' . self::escape($description) . "</p></div><div class='projectplus-priority__score projectplus-priority__score--{$nativePriority}'><span>" . self::escape(__('Ranking', 'projectplus')) . '</span><strong>' . ($ranking ?: '—') . "</strong><em class='projectplus-priority__native projectplus-priority__native--{$nativePriority}'>" . self::escape($priorityLabel) . '</em></div></header>';
      if ($canEdit) {
         echo "<form method='post' action='" . self::escape($url) . "' data-track-changes='true'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]) . Html::hidden('priority_type', ['value' => $type]);
      }
      echo "<div class='projectplus-priority__grid'>";
      foreach (ProjectPriority::getCriteriaForPriorityType($type) as $field => $label) {
         echo "<div class='projectplus-priority__field'><label>" . self::escape(__($label, 'projectplus')) . '</label>';
         Dropdown::showFromArray($field, ProjectPriority::getLevelOptionsForCriterion($field), ['value' => (int) $data[$field], 'disabled' => !$canEdit || !isset($editable[$field])]);
         echo '</div>';
      }
      echo '</div><footer class="projectplus-priority__footer"><small>' . self::escape(__('O ranking é calculado pela soma dos seis critérios preenchidos. A autorização de abertura requer o preenchimento completo.', 'projectplus')) . '</small>';
      if ($canEdit) {
         echo "<div class='projectplus-priority__actions'><button class='btn btn-primary' type='submit' name='save_ticket_priority' value='1'><i class='ti ti-device-floppy'></i> " . self::escape(__('Salvar priorização', 'projectplus')) . '</button>';
         if ($type === 'technical') {
            // Saving keeps the request with the Project Manager; sending is the
            // explicit handover that also closes this block for editing. O envio
            // usa a mesma classe do aceite do TIP para as duas conclusoes de
            // etapa terem a mesma cor em todo o fluxo.
            echo " <button class='btn btn-success' type='submit' name='send_to_portfolio' value='1'><i class='ti ti-send'></i> " . self::escape(__('Enviar para Gestor de Portfólio', 'projectplus')) . '</button>';
         }
         echo '</div></form>';
      }
      if ($type === 'technical' && $technicalSent) {
         echo "<div class='projectplus-acceptance__accepted'><i class='ti ti-circle-check'></i> " . self::escape(__('Priorização técnica enviada ao Gestor de Portfólio. Este bloco ficou somente leitura.', 'projectplus')) . '</div>';
      }
      echo '</footer></section>';
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
