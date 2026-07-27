<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonGLPI;
use DBmysql;
use Dropdown;
use Html;
use Session;
use Ticket;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Internal process phase for a Ticket configured as a project request.
 * The native Ticket status remains untouched so the process is isolated from
 * service-desk queues and can evolve independently.
 */
class TicketPhase extends CommonDBTM
{
   protected static $notable = true;

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketphases';
   }

   /** @return array<string, string> */
   public static function getPhases(): array
   {
      return [
         'classification' => __('ClassificaÃ§Ã£o da necessidade', 'projectplus'),
         'portfolio_analysis' => __('AnÃ¡lise do portfÃ³lio', 'projectplus'),
         'awaiting_authorization' => __('Aguardando autorizaÃ§Ã£o de abertura', 'projectplus'),
         'authorized_opening' => __('Autorizado para abertura', 'projectplus'),
         'awaiting_tap_acceptance' => __('Aguardando aceite do TAP', 'projectplus'),
         'project_opened' => __('Projeto aberto', 'projectplus'),
         'rejected_cancelled' => __('Recusado/Cancelado', 'projectplus'),
      ];
   }

   public static function getPhaseLabel(string $phase): string
   {
      $phases = self::getPhases();
      return $phases[$phase] ?? $phase;
   }

   public static function postTicketAdd(Ticket $ticket): void
   {
      self::ensureForTicket($ticket);
   }

   public static function preTicketUpdate(Ticket $ticket): void
   {
      if (array_key_exists('itilcategories_id', $ticket->input ?? [])) {
         $ticket->fields['itilcategories_id'] = (int) $ticket->input['itilcategories_id'];
      }
      self::ensureForTicket($ticket);
   }

   /** @return array<string, mixed>|null */
   public static function getForTicket(int $ticketId): ?array
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($ticketId <= 0 || !$DB->tableExists(self::getTable())) {
         return null;
      }

      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['tickets_id' => $ticketId],
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function ensureForTicket(Ticket $ticket): void
   {
      /** @var DBmysql $DB */
      global $DB;

      if ((int) $ticket->getID() <= 0 || !TicketConfig::isEnabledForTicket($ticket)) {
         return;
      }
      if (self::getForTicket((int) $ticket->getID()) !== null) {
         return;
      }

      $now = date('Y-m-d H:i:s');
      $DB->insert(self::getTable(), [
         'tickets_id' => (int) $ticket->getID(),
         'entities_id' => (int) $ticket->getEntityID(),
         'phase' => 'classification',
         'users_id' => (int) Session::getLoginUserID(),
         'date_creation' => $now,
         'date_mod' => $now,
      ]);
      $DB->insert(TicketPhaseHistory::getTable(), [
         'tickets_id' => (int) $ticket->getID(),
         'entities_id' => (int) $ticket->getEntityID(),
         'old_phase' => '',
         'new_phase' => 'classification',
         'users_id' => (int) Session::getLoginUserID(),
         'justification' => __('Fase inicial definida automaticamente pela categoria do Ticket.', 'projectplus'),
         'date_creation' => $now,
      ]);
   }

   public static function updateForTicket(Ticket $ticket, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!TicketConfig::isEnabledForTicket($ticket)) {
         return false;
      }

      self::ensureForTicket($ticket);
      $current = self::getForTicket((int) $ticket->getID());
      $phase = (string) ($input['phase'] ?? '');
      $justification = trim((string) ($input['justification'] ?? ''));
      if (!array_key_exists($phase, self::getPhases())) {
         return false;
      }
      if ($phase === 'rejected_cancelled' && $justification === '') {
         return false;
      }
      if ($current === null || $phase === (string) $current['phase']) {
         return true;
      }

      $now = date('Y-m-d H:i:s');
      $userId = (int) Session::getLoginUserID();
      $DB->update(self::getTable(), [
         'phase' => $phase,
         'users_id' => $userId,
         'justification' => $justification,
         'date_mod' => $now,
      ], ['id' => (int) $current['id']]);
      $DB->insert(TicketPhaseHistory::getTable(), [
         'tickets_id' => (int) $ticket->getID(),
         'entities_id' => (int) $ticket->getEntityID(),
         'old_phase' => (string) $current['phase'],
         'new_phase' => $phase,
         'users_id' => $userId,
         'justification' => $justification,
         'date_creation' => $now,
      ]);
      return true;
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if (!$item instanceof Ticket || $withtemplate || !TicketConfig::isEnabledForTicket($item)) {
         return '';
      }
      return self::createTabEntry(__('SolicitaÃ§Ã£o de projeto', 'projectplus'), 0, $item::getType(), 'ti ti-folder');
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item instanceof Ticket && TicketConfig::isEnabledForTicket($item)) {
         self::showForTicket($item);
      }
      return true;
   }

   public static function showForTicket(Ticket $ticket): void
   {
      self::ensureForTicket($ticket);
      $phase = self::getForTicket((int) $ticket->getID());
      if ($phase === null) {
         return;
      }

      echo "<div class='center'><table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('Fluxo interno da solicitaÃ§Ã£o de projeto', 'projectplus')) . '</th></tr>';
      echo "<tr class='tab_bg_1'><td>" . self::escape(__('Fase atual', 'projectplus')) . '</td><td><strong>' . self::escape(self::getPhaseLabel((string) $phase['phase'])) . '</strong></td></tr>';
      echo "<tr class='tab_bg_1'><td>" . self::escape(__('Ãšltima atualizaÃ§Ã£o', 'projectplus')) . '</td><td>' . self::escape((string) $phase['date_mod']) . '</td></tr>';
      echo '</table></div>';

      if (Session::haveRight('ticket', UPDATE)) {
         self::showUpdateForm($ticket, $phase);
      }
      self::showHistory((int) $ticket->getID());
   }

   /** @param array<string, mixed> $phase */
   private static function showUpdateForm(Ticket $ticket, array $phase): void
   {
      echo "<div class='center'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('Atualizar fase', 'projectplus')) . '</th></tr>';
      echo "<tr class='tab_bg_1'><td>" . self::escape(__('Nova fase', 'projectplus')) . '</td><td>';
      Dropdown::showFromArray('phase', self::getPhases(), ['value' => (string) $phase['phase']]);
      echo '</td></tr>';
      echo "<tr class='tab_bg_1'><td>" . self::escape(__('Justificativa', 'projectplus')) . '</td><td>';
      echo "<textarea class='form-control' name='justification' rows='4'></textarea>";
      echo '<br><em>' . self::escape(__('ObrigatÃ³ria em caso de recusa ou cancelamento.', 'projectplus')) . '</em>';
      echo '</td></tr>';
      echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='update_phase' value='1'>" . self::escape(__('Salvar', 'projectplus')) . '</button></td></tr>';
      echo '</table></form></div>';
   }

   private static function showHistory(int $ticketId): void
   {
      /** @var DBmysql $DB */
      global $DB;

      echo "<div class='center'><table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='4'>" . self::escape(__('HistÃ³rico das fases', 'projectplus')) . '</th></tr>';
      echo '<tr><th>' . self::escape(__('Data', 'projectplus')) . '</th><th>' . self::escape(__('De', 'projectplus')) . '</th><th>' . self::escape(__('Para', 'projectplus')) . '</th><th>' . self::escape(__('ResponsÃ¡vel / justificativa', 'projectplus')) . '</th></tr>';
      foreach ($DB->request([
         'FROM' => TicketPhaseHistory::getTable(),
         'WHERE' => ['tickets_id' => $ticketId],
         'ORDER' => ['date_creation DESC', 'id DESC'],
      ]) as $entry) {
         $user = (int) $entry['users_id'] > 0 ? User::getFriendlyNameById((int) $entry['users_id']) : '';
         echo "<tr class='tab_bg_1'><td>" . self::escape((string) $entry['date_creation']) . '</td>';
         echo '<td>' . self::escape(self::getPhaseLabel((string) $entry['old_phase'])) . '</td>';
         echo '<td>' . self::escape(self::getPhaseLabel((string) $entry['new_phase'])) . '</td>';
         echo '<td>' . self::escape($user . ($entry['justification'] !== '' ? ' - ' . $entry['justification'] : '')) . '</td></tr>';
      }
      echo '</table></div>';
   }

   private static function getActionUrl(): string
   {
      global $CFG_GLPI;
      return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketphase.form.php';
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
