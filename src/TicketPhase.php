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
         'classification' => self::label('Classifica&ccedil;&atilde;o da necessidade'),
         'awaiting_tip' => __('Aguardando preenchimento do TIP', 'projectplus'),
         'awaiting_tip_review' => __('Aguardando validação do TIP', 'projectplus'),
         'portfolio_analysis' => self::label('An&aacute;lise do portf&oacute;lio'),
         'awaiting_authorization' => self::label('Aguardando autoriza&ccedil;&atilde;o de abertura'),
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

      return self::setPhase($ticket, $phase, $justification);
   }

   /** Changes a phase and writes its audit history. Intended for controlled plugin actions. */
   public static function setPhase(Ticket $ticket, string $phase, string $justification = ''): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!TicketConfig::isEnabledForTicket($ticket) || !array_key_exists($phase, self::getPhases())) {
         return false;
      }
      self::ensureForTicket($ticket);
      $current = self::getForTicket((int) $ticket->getID());
      if ($current === null || $phase === (string) $current['phase']) {
         return $current !== null;
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
         return self::createTabEntry(__('Termos', 'projectplus'), 0, $item::getType(), 'ti ti-file-check');
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

      echo "<section class='projectplus-phase-shell'>";
      echo "<header class='projectplus-phase-header'><div class='projectplus-phase-header__title'><span class='projectplus-phase-header__icon'><i class='ti ti-git-branch'></i></span><div><span>" . self::escape(__('Solicitação de projeto', 'projectplus')) . '</span><h3>' . self::escape(self::label('Fluxo interno da solicita&ccedil;&atilde;o de projeto')) . '</h3></div></div>';
      echo "<div class='projectplus-phase-header__details'><div><span>" . self::escape(__('Fase atual', 'projectplus')) . '</span>' . self::renderPhaseBadge((string) $phase['phase']) . '</div><div><span>' . self::escape(self::label('&Uacute;ltima atualiza&ccedil;&atilde;o')) . '</span><strong>' . self::escape((string) $phase['date_mod']) . '</strong></div></div></header>';
      echo "<div class='projectplus-document-legend' aria-label='" . self::escape(__('Legenda dos documentos', 'projectplus')) . "'><span class='projectplus-document-legend__title'><i class='ti ti-info-circle'></i> " . self::escape(__('Legenda', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--accepted'><i></i>" . self::escape(__('Aceito pelo gestor', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--released'><i></i>" . self::escape(__('Liberado para preenchimento', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--rejected'><i></i>" . self::escape(__('Recusado', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--disabled'><i></i>" . self::escape(__('Não enviado / desabilitado', 'projectplus')) . '</span></div>';

      echo "<div class='projectplus-document-journey'>";
      $intent = TicketIntent::getForTicket((int) $ticket->getID());
      $tipState = self::getIntentVisualState($intent);
      $tapState = DocumentAcceptance::getVisualState(DocumentAcceptance::getForItem($ticket, 'tap'));
      if ($tapState === 'disabled' && (string) $phase['phase'] === 'authorized_opening') {
         $tapState = 'released';
      }
      self::showDocumentStep('TIP - Termo de Intenção do Projeto', 'ti ti-file-text', $tipState, false, static function () use ($ticket): void {
         TicketIntent::showForTicket($ticket);
      });
      self::showDocumentStep('TAP - Termo de Abertura do Projeto', 'ti ti-file-description', $tapState, false, static function () use ($ticket): void {
         DocumentAcceptance::showTicketDocument($ticket);
      });
      echo '</div>';
      DocumentAcceptance::showJourneyAccordionScript();

      TicketProject::showForTicket($ticket);

      if (Session::haveRight('ticket', UPDATE)) {
         self::showUpdateForm($ticket, $phase);
      }
      self::showHistory((int) $ticket->getID());
      self::showPhaseSectionsAccordionScript();
      echo '</section>';
   }

   /** @param array<string, mixed> $phase */
   private static function showUpdateForm(Ticket $ticket, array $phase): void
   {
      echo "<section class='projectplus-phase-card projectplus-phase-form'><div class='projectplus-phase-card__heading'><i class='ti ti-pencil'></i><div><h3>" . self::escape(__('Atualizar fase', 'projectplus')) . '</h3><p>' . self::escape(__('Registre a próxima etapa ou uma decisão do fluxo.', 'projectplus')) . "</p></div></div><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo "<div class='projectplus-phase-form__grid'><div class='projectplus-phase-form__field'><label>" . self::escape(__('Nova fase', 'projectplus')) . "</label><div class='projectplus-phase-select'>";
      Dropdown::showFromArray('phase', self::getPhases(), ['value' => (string) $phase['phase']]);
      echo '</div>';
      self::showPhaseSelectScript();
      echo "</div><div class='projectplus-phase-form__field projectplus-phase-form__field--wide'><label>" . self::escape(__('Justificativa', 'projectplus')) . '</label>';
      echo "<textarea class='form-control' name='justification' rows='4'></textarea><small><i class='ti ti-info-circle'></i>" . self::escape(self::label('Obrigat&oacute;ria em caso de recusa ou cancelamento.')) . '</small></div></div>';
      echo "<footer class='projectplus-phase-form__footer'><span>" . self::escape(__('A alteração ficará registrada no histórico do fluxo.', 'projectplus')) . "</span><button class='btn btn-primary' type='submit' name='update_phase' value='1'><i class='ti ti-device-floppy'></i> " . self::escape(__('Salvar', 'projectplus')) . '</button></footer></form></section>';
   }

   private static function showHistory(int $ticketId): void
   {
      /** @var DBmysql $DB */
      global $DB;

      echo "<section class='projectplus-phase-card projectplus-phase-history'><div class='projectplus-phase-card__heading'><i class='ti ti-history'></i><div><h3>" . self::escape(self::label('Hist&oacute;rico das fases')) . '</h3><p>' . self::escape(__('Rastreabilidade das mudanças realizadas na solicitação.', 'projectplus')) . "</p></div></div><div class='projectplus-phase-history__table'><table><thead><tr><th>" . self::escape(__('Data', 'projectplus')) . '</th><th>' . self::escape(__('De', 'projectplus')) . '</th><th>' . self::escape(__('Para', 'projectplus')) . '</th><th>' . self::escape(self::label('Respons&aacute;vel / justificativa')) . '</th></tr></thead><tbody>';
      foreach ($DB->request([
         'FROM' => TicketPhaseHistory::getTable(),
         'WHERE' => ['tickets_id' => $ticketId],
         'ORDER' => ['date_creation DESC', 'id DESC'],
      ]) as $entry) {
         $user = (int) $entry['users_id'] > 0 ? User::getFriendlyNameById((int) $entry['users_id']) : '';
         echo '<tr><td>' . self::escape((string) $entry['date_creation']) . '</td>';
         echo '<td>' . self::renderPhaseBadge((string) $entry['old_phase']) . '</td>';
         echo '<td>' . self::renderPhaseBadge((string) $entry['new_phase']) . '</td>';
         echo '<td>' . self::escape($user . ($entry['justification'] !== '' ? ' - ' . $entry['justification'] : '')) . '</td></tr>';
      }
      echo '</tbody></table></div></section>';
   }

   private static function showPhaseSectionsAccordionScript(): void
   {
      echo "<script>(function () { document.querySelectorAll('.projectplus-phase-shell').forEach(function (shell) { if (shell.dataset.projectplusPhaseAccordionReady) { return; } shell.dataset.projectplusPhaseAccordionReady = '1'; var cards = shell.querySelectorAll('.projectplus-phase-form, .projectplus-phase-history'); cards.forEach(function (card) { var heading = card.querySelector('.projectplus-phase-card__heading'); if (!heading) { return; } var content = document.createElement('div'); content.className = 'projectplus-phase-collapsible__content'; while (heading.nextSibling) { content.appendChild(heading.nextSibling); } card.appendChild(content); content.hidden = true; card.classList.add('projectplus-phase-collapsible'); heading.setAttribute('role', 'button'); heading.setAttribute('tabindex', '0'); heading.setAttribute('aria-expanded', 'false'); var chevron = document.createElement('i'); chevron.className = 'ti ti-chevron-down projectplus-phase-collapsible__chevron'; heading.appendChild(chevron); function toggle() { var open = !card.classList.contains('is-open'); cards.forEach(function (other) { other.classList.remove('is-open'); var otherContent = other.querySelector('.projectplus-phase-collapsible__content'); var otherHeading = other.querySelector('.projectplus-phase-card__heading'); if (otherContent) { otherContent.hidden = true; } if (otherHeading) { otherHeading.setAttribute('aria-expanded', 'false'); } }); if (open) { card.classList.add('is-open'); content.hidden = false; heading.setAttribute('aria-expanded', 'true'); } } heading.addEventListener('click', toggle); heading.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggle(); } }); }); }); })();</script>";
   }

   private static function showPhaseSelectScript(): void
   {
      echo "<script>(function () { document.querySelectorAll('.projectplus-phase-select').forEach(function (container) { if (container.dataset.projectplusPhaseSelectReady) { return; } var select = container.querySelector('select[name=phase]'); if (!select) { return; } container.dataset.projectplusPhaseSelectReady = '1'; function updateColor() { container.dataset.phase = select.value || 'classification'; } select.addEventListener('change', updateColor); updateColor(); }); })();</script>";
   }

   /** @param callable():void $content */
   private static function showDocumentStep(string $label, string $icon, string $state, bool $open, callable $content): void
   {
      echo "<details class='projectplus-document-step projectplus-document-step--" . self::escape($state) . "'" . ($open ? ' open' : '') . "><summary><span><i class='" . self::escape($icon) . "'></i> " . self::escape($label) . "</span><i class='ti ti-chevron-down'></i></summary><div class='projectplus-document-step__content'>";
      $content();
      echo '</div></details>';
   }

   /** @param array<string,mixed>|null $intent */
   private static function getIntentVisualState(?array $intent): string
   {
      $status = (string) ($intent['status'] ?? '');
      if ($status === 'accepted') { return 'accepted'; }
      if ($status === 'rejected') { return 'rejected'; }
      if (in_array($status, ['requested', 'submitted'], true)) { return 'released'; }
      return 'disabled';
   }

   private static function renderPhaseBadge(string $phase): string
   {
      return "<strong class='projectplus-phase-badge projectplus-phase-badge--" . self::escape(self::getPhaseColorClass($phase)) . "'>" . self::escape(self::getPhaseLabel($phase)) . '</strong>';
   }

   private static function getPhaseColorClass(string $phase): string
   {
      $classes = [
         'classification'             => 'classification',
         'awaiting_tip'               => 'awaiting-tip',
         'awaiting_tip_review'        => 'tip-review',
         'portfolio_analysis'         => 'portfolio',
         'awaiting_authorization'     => 'authorization',
         'authorized_opening'         => 'authorized',
         'awaiting_tap_acceptance'    => 'tap-acceptance',
         'project_opened'             => 'opened',
         'rejected_cancelled'         => 'rejected',
      ];
      return $classes[$phase] ?? 'neutral';
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

   private static function label(string $value): string
   {
      return html_entity_decode(__($value, 'projectplus'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
   }
}
