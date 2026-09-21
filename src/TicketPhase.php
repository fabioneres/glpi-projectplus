<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonGLPI;
use DBmysql;
use Dropdown;
use Html;
use ITILFollowup;
use Session;
use Ticket;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TicketPhase extends CommonDBTM
{
   protected static $notable = true;

   /** Phases that only exist after the opening was authorized. */
   public const AUTHORIZED_PHASES = ['authorized_opening', 'awaiting_tap_acceptance', 'project_opened'];

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketphases';
   }

   /** @return array<string, string> */
   public static function getPhases(): array
   {
      return [
         'classification' => self::label('Classifica&ccedil;&atilde;o da necessidade'),
         'awaiting_pretip' => __('Aguardando preenchimento do TIP - Termo de Intenção de Projeto', 'projectplus'),
         'awaiting_pretip_review' => __('Aguardando validação do TIP - Termo de Intenção de Projeto', 'projectplus'),
         'awaiting_tip' => __('Aguardando preenchimento do TIP - Detalhamento Técnico', 'projectplus'),
         'awaiting_tip_review' => __('Aguardando validação do TIP - Detalhamento Técnico', 'projectplus'),
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
      return FlowStageDescription::getNameForTrigger($phase, self::getPhaseLabelRaw($phase));
   }

   public static function getPhaseLabelRaw(string $phase): string
   {
      $phases = self::getPhases();
      return $phases[$phase] ?? $phase;
   }

   /** @return array<string, string> Configured phase names keyed by workflow trigger. */
   public static function getSearchablePhases(): array
   {
      $phases = [];
      foreach (array_keys(self::getPhases()) as $phase) {
         $phases[$phase] = self::getPhaseLabel($phase);
      }
      return $phases;
   }

   public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
   {
      if ($field !== 'phase') {
         return parent::getSpecificValueToSelect($field, $name, $values, $options);
      }

      $value = is_array($values) ? (string) ($values[$field] ?? '') : (string) $values;
      return Dropdown::showFromArray($name, self::getSearchablePhases(), [
         'value' => $value,
         'width' => $options['width'] ?? '100%',
         'display' => false,
      ]);
   }

   public static function getSpecificValueToDisplay($field, $values, array $options = [])
   {
      if ($field !== 'phase') {
         return parent::getSpecificValueToDisplay($field, $values, $options);
      }

      $value = is_array($values) ? (string) ($values[$field] ?? '') : (string) $values;
      return self::getPhaseLabel($value);
   }

   /** Native Ticket search filters using the same user selector as Technician. */
   public static function getSearchOptionsForTicket(): array
   {
      $options = [['id' => 'projectplus', 'name' => __('Project+', 'projectplus')]];

      $options[] = [
         'id' => 9523,
         'table' => self::getTable(),
         'field' => 'phase',
         'datatype' => 'specific',
         'searchtype' => ['equals', 'notequals'],
         'name' => __('Fase atual', 'projectplus'),
         'forcegroupby' => true,
         'massiveaction' => false,
         'joinparams' => ['jointype' => 'child'],
      ];

      $options[] = [
         'id' => 9524,
         'table' => TicketPriority::getTable(),
         'field' => 'ranking',
         'datatype' => 'string',
         'searchtype' => ['equals', 'notequals', 'lessthan', 'morethan'],
         'name' => __('Ranking', 'projectplus'),
         'forcegroupby' => true,
         'massiveaction' => false,
         'joinparams' => ['jointype' => 'child'],
      ];

      foreach ([
         9521 => ['project_manager_users_id', __('Gerente de Projetos', 'projectplus')],
         9522 => ['portfolio_manager_users_id', __('Gestor de Portfólio', 'projectplus')],
      ] as $id => $definition) {
         $options[] = [
            'id' => $id,
            'table' => User::getTable(),
            'field' => 'name',
            'linkfield' => $definition[0],
            'datatype' => 'dropdown',
            'name' => $definition[1],
            'forcegroupby' => true,
            'massiveaction' => false,
            'use_subquery' => true,
            'joinparams' => [
               'beforejoin' => [
                  'table' => self::getTable(),
                  'joinparams' => ['jointype' => 'child'],
               ],
            ],
         ];
      }
      return $options;
   }

   /** @return array<string, string> Phases that may be changed manually in Project+. */
   private static function getManualPhases(): array
   {
      $phases = self::getPhases();
      // Normal phases advance only through their workflow actions. Manual
      // intervention is reserved for the auditable exception of cancellation.
      return ['rejected_cancelled' => $phases['rejected_cancelled']];
   }

   /** @return array<string, array<int, string>> */
   private static function getAllowedTransitions(): array
   {
      return [
         'classification' => ['awaiting_pretip', 'awaiting_tip'],
         'awaiting_pretip' => ['awaiting_pretip_review', 'rejected_cancelled'],
         // A negativa do Gerente de Projetos no TIP devolve para ajuste, e nao
         // encerra a solicitacao: o solicitante edita o que ja preencheu e
         // reenvia. O encerramento continua possivel como cancelamento.
         'awaiting_pretip_review' => ['classification', 'awaiting_pretip', 'rejected_cancelled'],
         'awaiting_tip' => ['portfolio_analysis', 'rejected_cancelled'],
         'portfolio_analysis' => ['awaiting_authorization', 'rejected_cancelled'],
         'awaiting_authorization' => ['authorized_opening', 'rejected_cancelled'],
         'authorized_opening' => ['awaiting_tap_acceptance', 'project_opened', 'rejected_cancelled'],
         'awaiting_tap_acceptance' => ['authorized_opening', 'rejected_cancelled'],
      ];
   }

   /** Ensures that a workflow action is executed in its single valid phase. */
   public static function assertCurrentPhase(Ticket $ticket, string $expectedPhase, string $message): void
   {
      self::ensureForTicket($ticket);
      $current = self::getForTicket((int) $ticket->getID());
      if ((string) ($current['phase'] ?? '') !== $expectedPhase) {
         throw new \RuntimeException($message);
      }
   }

   /** Registers the portfolio manager's explicit opening decision. */
   public static function decideOpening(Ticket $ticket, bool $authorized, string $justification = ''): void
   {
      self::assertCurrentPhase($ticket, 'awaiting_authorization', __('A autorização de abertura ainda não está disponível.', 'projectplus'));
      TicketRouting::assertActor($ticket, 'portfolio', ProjectProfile::RIGHT_AUTHORIZE_OPENING);
      if (!$authorized && trim($justification) === '') {
         throw new \RuntimeException(__('Informe a justificativa para negar a abertura do projeto.', 'projectplus'));
      }
      if ($authorized) {
         self::setPhase(
            $ticket,
            'authorized_opening',
            __('Abertura do projeto autorizada.', 'projectplus'),
            false
         );
         return;
      }
      $justification = trim($justification);
      $ticket->update(['id' => (int) $ticket->getID(), 'status' => Ticket::SOLVED, 'solution' => $justification]);
      self::setPhase($ticket, 'rejected_cancelled', $justification);
   }

   public static function postTicketAdd(Ticket $ticket): void
   {
      self::ensureForTicket($ticket);
      // A request created in a child entity is immediately placed with the
      // configured project manager, when the configuration is complete.
      TicketRouting::routeInitial($ticket);
   }

   public static function preTicketUpdate(Ticket $ticket): void
   {
      if (array_key_exists('itilcategories_id', $ticket->input ?? [])) {
         $ticket->fields['itilcategories_id'] = (int) $ticket->input['itilcategories_id'];
      }
      self::ensureForTicket($ticket);
   }

   /** Tells a cancellation that came from a denied authorization from any other. */
   public static function wasAuthorizationDenied(int $ticketId): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($ticketId <= 0 || !$DB->tableExists(TicketPhaseHistory::getTable())) {
         return false;
      }
      $row = $DB->request([
         'COUNT' => 'count',
         'FROM' => TicketPhaseHistory::getTable(),
         'WHERE' => [
            'tickets_id' => $ticketId,
            'old_phase' => 'awaiting_authorization',
            'new_phase' => 'rejected_cancelled',
         ],
      ])->current();
      return (int) ($row['count'] ?? 0) > 0;
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
         TicketRouting::syncResponsibilities($ticket);
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
      TicketRouting::syncResponsibilities($ticket);
      self::addAutomaticFollowup($ticket, 'classification', '', '');
   }

   public static function updateForTicket(Ticket $ticket, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!TicketConfig::isEnabledForTicket($ticket)) {
         return false;
      }
      if (!Session::haveRight(ProjectProfile::RIGHT_FLOW_UPDATE, UPDATE)) {
         throw new \RuntimeException(__('Você não possui permissão para atualizar as fases da solicitação.', 'projectplus'));
      }
      self::ensureForTicket($ticket);
      $current = self::getForTicket((int) $ticket->getID());
      $phase = (string) ($input['phase'] ?? '');
      $justification = trim((string) ($input['justification'] ?? ''));
      if (!array_key_exists($phase, self::getManualPhases())) {
         return false;
      }
      if ($phase !== 'rejected_cancelled' || $justification === '') {
         return false;
      }
      if ($phase === 'authorized_opening' && !TicketPriority::isComplete((int) $ticket->getID())) {
         throw new \RuntimeException(__('Conclua a priorização institucional antes de autorizar a abertura do projeto.', 'projectplus'));
      }
      if ($phase === 'authorized_opening' && !Session::haveRight(ProjectProfile::RIGHT_AUTHORIZE_OPENING, UPDATE)) {
         throw new \RuntimeException(__('Você não possui permissão para autorizar a abertura do projeto.', 'projectplus'));
      }
      if ($current === null || $phase === (string) $current['phase']) {
         return true;
      }

      return self::setPhase($ticket, $phase, $justification);
   }

   /** Changes a phase and writes its audit history. Intended for controlled plugin actions. */
   /**
    * @param string|null $followupEvent Evento do fluxo que descreve o que
    *        aconteceu. Quando informado, o acompanhamento publico usa o texto
    *        do evento em vez do texto da fase de destino - necessario quando a
    *        mesma fase e alcancada por caminhos com significados diferentes.
    *        `null` usa o texto da fase; string vazia nao publica acompanhamento
    *        nenhum, para quem ja publicou o seu antes de mudar a fase.
    */
   public static function setPhase(
      Ticket $ticket,
      string $phase,
      string $justification = '',
      bool $validateNextActorRight = true,
      ?string $followupEvent = null
   ): bool
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
      $currentPhase = (string) $current['phase'];
      $allowed = self::getAllowedTransitions()[$currentPhase] ?? [];
      if (!in_array($phase, $allowed, true)) {
         throw new \RuntimeException(__('A etapa solicitada não pode ser executada antes da conclusão da etapa anterior.', 'projectplus'));
      }
      // Resolve the configured manager before committing the phase. This keeps
      // the ticket in the entity that owns the next Project+ responsibility.
      TicketRouting::routeForPhase($ticket, $phase, $validateNextActorRight);
      $current = self::getForTicket((int) $ticket->getID());
      if ($current === null) {
         return false;
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
      self::addAutomaticFollowup($ticket, $phase, $currentPhase, $justification, $followupEvent);
      return true;
   }

   private static function addAutomaticFollowup(
      Ticket $ticket,
      string $phase,
      string $previousPhase,
      string $justification,
      ?string $followupEvent = null
   ): void {
      $content = $followupEvent !== null
         ? FlowStageDescription::getEventFollowup($followupEvent)
         : FlowStageDescription::getPhaseFollowup($phase);
      if ($content === '') {
         return;
      }
      $content = strtr($content, [
         '{fase}' => self::getPhaseLabel($phase),
         '{fase_anterior}' => $previousPhase === '' ? '' : self::getPhaseLabel($previousPhase),
         '{chamado_id}' => (string) $ticket->getID(),
         '{justificativa}' => $justification,
      ]);
      $followup = new ITILFollowup();
      $followup->add([
         'itemtype' => Ticket::class,
         'items_id' => (int) $ticket->getID(),
         'users_id' => (int) Session::getLoginUserID(),
         'content' => $content,
         'is_private' => 0,
      ]);
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if (
         !$item instanceof Ticket
         || $withtemplate
         || !TicketConfig::isEnabledForTicket($item)
         || !Session::haveRight(ProjectProfile::RIGHT_REQUESTS, READ)
      ) {
         return '';
      }
      return self::createTabEntry(__('Portfólio de Projetos', 'projectplus'), 0, $item::getType(), 'ti ti-file-check');
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if (
         $item instanceof Ticket
         && TicketConfig::isEnabledForTicket($item)
         && Session::haveRight(ProjectProfile::RIGHT_REQUESTS, READ)
      ) {
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
      echo "<div class='projectplus-phase-header__details'><div><span>" . self::escape(__('Fase atual', 'projectplus')) . '</span>' . self::renderPhaseBadge((string) $phase['phase']) . '</div><div><span>' . self::escape(self::label('&Uacute;ltima atualiza&ccedil;&atilde;o')) . '</span><strong>' . self::escape(self::formatDateTime((string) $phase['date_mod'])) . '</strong></div></div></header>';
      echo "<div class='projectplus-document-legend' aria-label='" . self::escape(__('Legenda dos documentos', 'projectplus')) . "'><span class='projectplus-document-legend__title'><i class='ti ti-info-circle'></i> " . self::escape(__('Legenda', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--accepted'><i></i>" . self::escape(__('Aceito pelo gestor', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--released'><i></i>" . self::escape(__('Liberado para preenchimento', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--rejected'><i></i>" . self::escape(__('Recusado', 'projectplus')) . "</span><span class='projectplus-document-legend__item projectplus-document-legend__item--disabled'><i></i>" . self::escape(__('Não enviado / desabilitado', 'projectplus')) . '</span></div>';

      echo "<div class='projectplus-document-journey'>";
      $preTip = TicketIntent::getForTicket((int) $ticket->getID(), TipDocumentType::TIP);
      $preTipState = self::getIntentVisualState($preTip);
      $intent = TicketIntent::getForTicket((int) $ticket->getID(), TipDocumentType::DETAIL);
      $tipState = self::getIntentVisualState($intent);
      $tap = DocumentAcceptance::getForItem($ticket, 'tap');
      $tapState = DocumentAcceptance::getVisualState($tap);
      $tapAccepted = (string) ($tap['status'] ?? '') === 'accepted';
      $currentPhase = (string) $phase['phase'];
      $technicalPriorityComplete = TicketPriority::isStrategicPriorityAvailable($ticket);
      if ($tapState === 'disabled' && $currentPhase === 'authorized_opening') {
         $tapState = 'released';
      }
      self::showDocumentStep('TIP - Termo de Intenção de Projeto', 'ti ti-file-search', $preTipState, in_array($currentPhase, ['awaiting_pretip', 'awaiting_pretip_review'], true), static function () use ($ticket): void {
         TicketIntent::showForTicket($ticket, TipDocumentType::TIP);
      });
      self::showDocumentStep('TIP - Detalhamento Técnico', 'ti ti-file-text', $tipState, $currentPhase === 'awaiting_tip', static function () use ($ticket): void {
         TicketIntent::showForTicket($ticket, TipDocumentType::DETAIL);
      });
      if (ProjectPriority::canViewPriorityType('technical')) {
         $tipDetailGenerated = (string) ($intent['status'] ?? '') === 'generated';
         // Green means handed over to the Portfolio Manager, not merely filled:
         // a complete block that was not submitted still expects an action.
         $technicalState = TicketPriority::isTechnicalSent((int) $ticket->getID())
            ? 'accepted'
            : ($tipDetailGenerated ? 'released' : 'disabled');
         self::showDocumentStep(__('Priorização técnica', 'projectplus'), 'ti ti-list-numbers', $technicalState, $currentPhase === 'portfolio_analysis' && !$technicalPriorityComplete, static function () use ($ticket, $tipDetailGenerated): void {
            if (!$tipDetailGenerated) {
               echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>" . self::escape(__('Priorização técnica ainda não liberada.', 'projectplus')) . '</strong><br>' . self::escape(FlowStageDescription::get('technical_priority', 'pending')) . '</div>';
               return;
            }
            TicketPriority::showForTicket($ticket, 'technical');
         });
      }
      if (ProjectPriority::canViewPriorityType('institutional')) {
         $institutionalState = TicketPriority::isCompleteForType((int) $ticket->getID(), 'institutional') ? 'accepted' : ($technicalPriorityComplete ? 'released' : 'disabled');
         self::showDocumentStep(__('Priorização estratégica', 'projectplus'), 'ti ti-list-numbers', $institutionalState, $currentPhase === 'portfolio_analysis' && $technicalPriorityComplete, static function () use ($ticket, $technicalPriorityComplete): void {
            if (!$technicalPriorityComplete) {
               echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>" . self::escape(__('Priorização estratégica ainda não liberada.', 'projectplus')) . '</strong><br>' . self::escape(FlowStageDescription::get('strategic_priority', 'pending')) . '</div>';
               return;
            }
            TicketPriority::showForTicket($ticket, 'institutional');
         });
      }
      // The authorization is a milestone, not a transient phase: once granted it
      // must keep signalling completion while the flow moves on to the TAP and
      // to the project opening.
      $authorizationGranted = in_array($currentPhase, self::AUTHORIZED_PHASES, true);
      $authorizationDenied = $currentPhase === 'rejected_cancelled'
         && self::wasAuthorizationDenied((int) $ticket->getID());
      $authorizationState = $authorizationGranted
         ? 'accepted'
         : ($authorizationDenied ? 'rejected' : ($currentPhase === 'awaiting_authorization' ? 'released' : 'disabled'));
      self::showDocumentStep(__('Autorização de abertura do projeto', 'projectplus'), 'ti ti-circle-check', $authorizationState, $currentPhase === 'awaiting_authorization', static function () use ($ticket, $currentPhase, $authorizationGranted, $authorizationDenied): void {
         if ($authorizationGranted) {
            echo "<div class='projectplus-acceptance__accepted'><i class='ti ti-circle-check'></i> " . self::escape(__('Abertura do projeto autorizada.', 'projectplus')) . '</div>';
            return;
         }
         if ($authorizationDenied) {
            echo "<div class='alert alert-danger'><i class='ti ti-circle-x'></i> " . self::escape(__('Abertura do projeto negada. A justificativa está no histórico das fases.', 'projectplus')) . '</div>';
            return;
         }
         if ($currentPhase !== 'awaiting_authorization') {
            echo "<div class='alert alert-info'>" . self::escape(FlowStageDescription::get('authorization_opening', 'pending')) . '</div>';
            return;
         }
         if (!Session::haveRight(ProjectProfile::RIGHT_AUTHORIZE_OPENING, UPDATE)) {
            echo "<div class='alert alert-info'>" . self::escape(__('Aguardando o Gestor de Portfólio autorizado.', 'projectplus')) . '</div>';
            return;
         }
         global $CFG_GLPI;
         $url = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketauthorization.form.php';
         echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>" . self::escape(__('Autorização de abertura pendente.', 'projectplus')) . '</strong><br>' . self::escape(FlowStageDescription::get('authorization_opening', 'active')) . '</div>';
         echo "<form method='post' action='" . self::escape($url) . "' class='projectplus-phase-form'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
         echo "<label>" . self::escape(__('Justificativa em caso de negativa', 'projectplus')) . "</label><textarea class='form-control' name='justification' rows='3'></textarea><div class='center mt-3'><button class='btn btn-primary' type='submit' name='authorize_opening' value='1'>" . self::escape(__('Autorizar abertura', 'projectplus')) . "</button> <button class='btn btn-danger' type='submit' name='deny_opening' value='1'>" . self::escape(__('Negar abertura', 'projectplus')) . '</button></div></form>';
      });
      $tapOpen = $currentPhase === 'awaiting_tap_acceptance'
         || ($currentPhase === 'authorized_opening' && !$tapAccepted);
      self::showDocumentStep('TAP - Termo de Abertura do Projeto', 'ti ti-file-description', $tapState, $tapOpen, static function () use ($ticket): void {
         DocumentAcceptance::showTicketDocument($ticket);
      });
      $projectId = TicketProject::getProjectId((int) $ticket->getID());
      $projectReady = $projectId === 0 && TicketProject::getMissingOpeningRequirements($ticket) === [];
      $projectState = $projectId > 0 ? 'accepted' : ($projectReady ? 'released' : 'disabled');
      self::showDocumentStep(
         __('Abrir projeto e solucionar chamado', 'projectplus'),
         'ti ti-briefcase',
         $projectState,
         $projectReady || $currentPhase === 'project_opened',
         static function () use ($ticket): void {
            TicketProject::showForTicket($ticket);
         }
      );
      echo '</div>';
      DocumentAcceptance::showJourneyAccordionScript();

      if (Session::haveRight(ProjectProfile::RIGHT_FLOW_UPDATE, UPDATE) && $currentPhase !== 'rejected_cancelled') {
         self::showUpdateForm($ticket, $phase);
      }
      if (Session::haveRight(ProjectProfile::RIGHT_REQUEST_DOCUMENTS, READ)) {
         self::showHistory((int) $ticket->getID());
      }
      self::showPhaseSectionsAccordionScript();
      echo '</section>';
   }

   /** @param array<string, mixed> $phase */
   private static function showUpdateForm(Ticket $ticket, array $phase): void
   {
      echo "<section class='projectplus-phase-card projectplus-phase-form'><div class='projectplus-phase-card__heading'><i class='ti ti-alert-triangle'></i><div><h3>" . self::escape(__('Cancelar solicitação', 'projectplus')) . '</h3><p>' . self::escape(__('Use somente em situação excepcional. As demais fases são atualizadas automaticamente pelas ações do fluxo.', 'projectplus')) . "</p></div></div><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo Html::hidden('phase', ['value' => 'rejected_cancelled']);
      echo "<div class='projectplus-phase-form__field projectplus-phase-form__field--wide'><label>" . self::escape(__('Justificativa', 'projectplus')) . '</label>';
      echo "<textarea class='form-control' name='justification' rows='4' required></textarea><small><i class='ti ti-info-circle'></i>" . self::escape(__('Obrigatória para registrar o cancelamento.', 'projectplus')) . '</small></div>';
      echo "<footer class='projectplus-phase-form__footer'><span>" . self::escape(__('A alteração ficará registrada no histórico do fluxo.', 'projectplus')) . "</span><button class='btn btn-danger' type='submit' name='update_phase' value='1'><i class='ti ti-ban'></i> " . self::escape(__('Cancelar solicitação', 'projectplus')) . '</button></footer></form></section>';
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
         echo '<tr><td>' . self::escape(self::formatDateTime((string) $entry['date_creation'])) . '</td>';
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
      if ($status === 'generated') { return 'accepted'; }
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
         'awaiting_pretip'            => 'awaiting-tip',
         'awaiting_pretip_review'     => 'tip-review',
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

   /** Formats stored dates using the GLPI date/time preference of the current user. */
   private static function formatDateTime(string $value): string
   {
      return $value === '' || $value === '0000-00-00 00:00:00' ? '' : Html::convDateTime($value);
   }

   private static function label(string $value): string
   {
      return html_entity_decode(__($value, 'projectplus'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
   }
}
