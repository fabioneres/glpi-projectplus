<?php

namespace GlpiPlugin\Projectplus;

use CommonGLPI;
use DBmysql;
use DbUtils;
use Html;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Project+ rights exposed through GLPI's native Profile configuration. */
class ProjectProfile extends GlpiProfile
{
   public static $rightname = 'profile';

   public const RIGHT_REQUESTS = 'plugin_projectplus_requests';
   public const RIGHT_PORTFOLIO = 'plugin_projectplus_portfolio';
   public const RIGHT_PRIORITY_TECHNICAL = 'plugin_projectplus_priority_technical';
   public const RIGHT_PRIORITY_STRATEGIC = 'plugin_projectplus_priority_strategic';
   public const RIGHT_CONFIG = 'plugin_projectplus_config';
   public const RIGHT_FLOW_UPDATE = 'plugin_projectplus_flow_update';
   public const RIGHT_REQUEST_DOCUMENTS = 'plugin_projectplus_request_documents';
   public const RIGHT_REQUEST_PRETIP = 'plugin_projectplus_request_pretip';
   public const RIGHT_REQUEST_TIP = 'plugin_projectplus_request_tip';
   public const RIGHT_FILL_PRETIP = 'plugin_projectplus_fill_pretip';
   public const RIGHT_FILL_TIP = 'plugin_projectplus_fill_tip';
   public const RIGHT_GENERATE_TIP = 'plugin_projectplus_generate_tip';
   public const RIGHT_PUBLISH_TIP = 'plugin_projectplus_publish_tip';
   public const RIGHT_VALIDATE_INTENTS = 'plugin_projectplus_validate_intents';
   public const RIGHT_VALIDATE_PRETIP = 'plugin_projectplus_validate_pretip';
   public const RIGHT_VALIDATE_TIP = 'plugin_projectplus_validate_tip';
   public const RIGHT_AUTHORIZE_OPENING = 'plugin_projectplus_authorize_opening';
   public const RIGHT_OPEN_PROJECT = 'plugin_projectplus_open_project';
   public const RIGHT_MANAGE_DOCUMENTS = 'plugin_projectplus_manage_documents';
   public const RIGHT_MANAGE_TAP = 'plugin_projectplus_manage_tap';
   public const RIGHT_MANAGE_TEP = 'plugin_projectplus_manage_tep';
   public const RIGHT_MANAGE_TA = 'plugin_projectplus_manage_ta';
   public const RIGHT_FILL_TAP = 'plugin_projectplus_fill_tap';
   public const RIGHT_GENERATE_TAP = 'plugin_projectplus_generate_tap';
   public const RIGHT_SEND_TAP = 'plugin_projectplus_send_tap';
   public const RIGHT_ACCEPT_TAP = 'plugin_projectplus_accept_tap';
   public const RIGHT_FILL_TEP = 'plugin_projectplus_fill_tep';
   public const RIGHT_GENERATE_TEP = 'plugin_projectplus_generate_tep';
   public const RIGHT_SEND_TEP = 'plugin_projectplus_send_tep';
   public const RIGHT_ACCEPT_TEP = 'plugin_projectplus_accept_tep';
   public const RIGHT_FILL_TA = 'plugin_projectplus_fill_ta';
   public const RIGHT_GENERATE_TA = 'plugin_projectplus_generate_ta';
   public const RIGHT_SEND_TA = 'plugin_projectplus_send_ta';
   public const RIGHT_ACCEPT_TA = 'plugin_projectplus_accept_ta';
   public const RIGHT_INTERACTION_REPLY = 'plugin_projectplus_interaction_reply';
   public const RIGHT_INTERACTION_TASK = 'plugin_projectplus_interaction_task';
   public const RIGHT_INTERACTION_SOLUTION = 'plugin_projectplus_interaction_solution';
   public const RIGHT_INTERACTION_DOCUMENT = 'plugin_projectplus_interaction_document';
   public const RIGHT_INTERACTION_VALIDATION = 'plugin_projectplus_interaction_validation';
   public const RIGHT_INTERACTION_ESCALATE = 'plugin_projectplus_interaction_escalate';

   /** @return array<int, string> */
   public static function getRightNames(): array
   {
      return [
         self::RIGHT_REQUESTS,
         self::RIGHT_PORTFOLIO,
         self::RIGHT_PRIORITY_TECHNICAL,
         self::RIGHT_PRIORITY_STRATEGIC,
         self::RIGHT_CONFIG,
         self::RIGHT_FLOW_UPDATE,
         self::RIGHT_REQUEST_DOCUMENTS,
         self::RIGHT_REQUEST_PRETIP,
         self::RIGHT_REQUEST_TIP,
         self::RIGHT_FILL_PRETIP,
         self::RIGHT_FILL_TIP,
         self::RIGHT_GENERATE_TIP,
         self::RIGHT_PUBLISH_TIP,
         self::RIGHT_VALIDATE_INTENTS,
         self::RIGHT_VALIDATE_PRETIP,
         self::RIGHT_VALIDATE_TIP,
         self::RIGHT_AUTHORIZE_OPENING,
         self::RIGHT_OPEN_PROJECT,
         self::RIGHT_MANAGE_DOCUMENTS,
         self::RIGHT_MANAGE_TAP,
         self::RIGHT_MANAGE_TEP,
         self::RIGHT_MANAGE_TA,
         self::RIGHT_FILL_TAP,
         self::RIGHT_GENERATE_TAP,
         self::RIGHT_SEND_TAP,
         self::RIGHT_ACCEPT_TAP,
         self::RIGHT_FILL_TEP,
         self::RIGHT_GENERATE_TEP,
         self::RIGHT_SEND_TEP,
         self::RIGHT_ACCEPT_TEP,
         self::RIGHT_FILL_TA,
         self::RIGHT_GENERATE_TA,
         self::RIGHT_SEND_TA,
         self::RIGHT_ACCEPT_TA,
         self::RIGHT_INTERACTION_REPLY,
         self::RIGHT_INTERACTION_TASK,
         self::RIGHT_INTERACTION_SOLUTION,
         self::RIGHT_INTERACTION_DOCUMENT,
         self::RIGHT_INTERACTION_VALIDATION,
         self::RIGHT_INTERACTION_ESCALATE,
      ];
   }

   /**
    * Rights introduced with the granular flow matrix. They must never be
    * enabled implicitly when an existing installation is upgraded.
    *
    * @return array<int, string>
    */
   public static function getGranularRightNames(): array
   {
      return [
         self::RIGHT_PRIORITY_TECHNICAL,
         self::RIGHT_PRIORITY_STRATEGIC,
         self::RIGHT_REQUEST_PRETIP,
         self::RIGHT_REQUEST_TIP,
         self::RIGHT_FILL_PRETIP,
         self::RIGHT_FILL_TIP,
         self::RIGHT_GENERATE_TIP,
         self::RIGHT_PUBLISH_TIP,
         self::RIGHT_VALIDATE_PRETIP,
         self::RIGHT_VALIDATE_TIP,
         self::RIGHT_AUTHORIZE_OPENING,
         self::RIGHT_OPEN_PROJECT,
         self::RIGHT_FILL_TAP,
         self::RIGHT_GENERATE_TAP,
         self::RIGHT_SEND_TAP,
         self::RIGHT_ACCEPT_TAP,
         self::RIGHT_FILL_TEP,
         self::RIGHT_GENERATE_TEP,
         self::RIGHT_SEND_TEP,
         self::RIGHT_ACCEPT_TEP,
         self::RIGHT_FILL_TA,
         self::RIGHT_GENERATE_TA,
         self::RIGHT_SEND_TA,
         self::RIGHT_ACCEPT_TA,
         self::RIGHT_INTERACTION_REPLY,
         self::RIGHT_INTERACTION_TASK,
         self::RIGHT_INTERACTION_SOLUTION,
         self::RIGHT_INTERACTION_DOCUMENT,
         self::RIGHT_INTERACTION_VALIDATION,
         self::RIGHT_INTERACTION_ESCALATE,
      ];
   }

   /** Ensures upgrades start the granular controls disabled for every profile. */
   public static function disableGranularRightsForAllProfiles(): void
   {
      global $DB;
      if ($DB->tableExists('glpi_profilerights')) {
         $DB->update('glpi_profilerights', ['rights' => 0], ['name' => self::getGranularRightNames()]);
      }
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item instanceof GlpiProfile && $item->getID()) {
         return self::createTabEntry(__('Project+', 'projectplus'), 0, $item::getType(), 'ti ti-folders');
      }
      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item instanceof GlpiProfile) {
         self::addDefaultProfileInfos((int) $item->getID(), self::getDefaultRights());
         $profile = new self();
         $profile->showForm((int) $item->getID());
      }
      return true;
   }

   /** @return array<int, array<string, string>> */
   public static function getAllRights(): array
   {
      return [
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Fluxo: visualizar solicitação de projeto', 'projectplus'),
            'field' => self::RIGHT_REQUESTS,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP: solicitar preenchimento', 'projectplus'),
            'field' => self::RIGHT_REQUEST_PRETIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP: preencher', 'projectplus'),
            'field' => self::RIGHT_FILL_PRETIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP: aceitar ou recusar', 'projectplus'),
            'field' => self::RIGHT_VALIDATE_PRETIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Fluxo: atualizar fases da solicitação', 'projectplus'),
            'field' => self::RIGHT_FLOW_UPDATE,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP-DT: liberar preenchimento', 'projectplus'),
            'field' => self::RIGHT_REQUEST_TIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP-DT: preencher', 'projectplus'),
            'field' => self::RIGHT_FILL_TIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP-DT: gerar', 'projectplus'),
            'field' => self::RIGHT_GENERATE_TIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('TIP-DT: disponibilizar ao solicitante', 'projectplus'),
            'field' => self::RIGHT_PUBLISH_TIP,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Fluxo: autorizar abertura do projeto', 'projectplus'),
            'field' => self::RIGHT_AUTHORIZE_OPENING,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Fluxo: abrir projeto e solucionar chamado', 'projectplus'),
            'field' => self::RIGHT_OPEN_PROJECT,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TAP: gerar', 'projectplus'),
            'field' => self::RIGHT_GENERATE_TAP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TAP: preencher', 'projectplus'),
            'field' => self::RIGHT_FILL_TAP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TAP: enviar para aceite', 'projectplus'),
            'field' => self::RIGHT_SEND_TAP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TAP: aceitar ou recusar', 'projectplus'),
            'field' => self::RIGHT_ACCEPT_TAP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TEP: gerar', 'projectplus'),
            'field' => self::RIGHT_GENERATE_TEP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TEP: preencher', 'projectplus'),
            'field' => self::RIGHT_FILL_TEP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TEP: enviar para aceite', 'projectplus'),
            'field' => self::RIGHT_SEND_TEP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TEP: aceitar ou recusar', 'projectplus'),
            'field' => self::RIGHT_ACCEPT_TEP,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TA: gerar', 'projectplus'),
            'field' => self::RIGHT_GENERATE_TA,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TA: preencher', 'projectplus'),
            'field' => self::RIGHT_FILL_TA,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TA: enviar para aceite', 'projectplus'),
            'field' => self::RIGHT_SEND_TA,
         ],
         [
            'itemtype' => DocumentAcceptance::class,
            'label' => __('TA: aceitar ou recusar', 'projectplus'),
            'field' => self::RIGHT_ACCEPT_TA,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Priorização técnica', 'projectplus'),
            'field' => self::RIGHT_PRIORITY_TECHNICAL,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Priorização estratégica', 'projectplus'),
            'field' => self::RIGHT_PRIORITY_STRATEGIC,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Administração do Project+', 'projectplus'),
            'field' => self::RIGHT_CONFIG,
         ],
         [
            'itemtype' => ProjectInteraction::class,
            'label' => __('Interações: responder', 'projectplus'),
            'field' => self::RIGHT_INTERACTION_REPLY,
         ],
         [
            'itemtype' => ProjectInteraction::class,
            'label' => __('Interações: tarefa', 'projectplus'),
            'field' => self::RIGHT_INTERACTION_TASK,
         ],
         [
            'itemtype' => ProjectInteraction::class,
            'label' => __('Interações: solução com TEP', 'projectplus'),
            'field' => self::RIGHT_INTERACTION_SOLUTION,
         ],
         [
            'itemtype' => ProjectInteraction::class,
            'label' => __('Interações: documento', 'projectplus'),
            'field' => self::RIGHT_INTERACTION_DOCUMENT,
         ],
         [
            'itemtype' => ProjectInteraction::class,
            'label' => __('Interações: validação', 'projectplus'),
            'field' => self::RIGHT_INTERACTION_VALIDATION,
         ],
         [
            'itemtype' => ProjectInteraction::class,
            'label' => __('Interações: escalar', 'projectplus'),
            'field' => self::RIGHT_INTERACTION_ESCALATE,
         ],
      ];
   }

   /** @return array<string, array<int, array<string, string>>> */
   public static function getRightsByBlock(): array
   {
      $fields = [
         __('Assistência', 'projectplus') => [
            self::RIGHT_REQUESTS,
            self::RIGHT_REQUEST_PRETIP,
            self::RIGHT_FILL_PRETIP,
            self::RIGHT_VALIDATE_PRETIP,
            self::RIGHT_FLOW_UPDATE,
            self::RIGHT_REQUEST_TIP,
            self::RIGHT_FILL_TIP,
            self::RIGHT_GENERATE_TIP,
            self::RIGHT_PUBLISH_TIP,
            self::RIGHT_PRIORITY_TECHNICAL,
            self::RIGHT_PRIORITY_STRATEGIC,
            self::RIGHT_AUTHORIZE_OPENING,
            self::RIGHT_OPEN_PROJECT,
            self::RIGHT_GENERATE_TAP,
            self::RIGHT_FILL_TAP,
            self::RIGHT_SEND_TAP,
            self::RIGHT_ACCEPT_TAP,
         ],
         __('Projetos', 'projectplus') => [
            self::RIGHT_INTERACTION_REPLY,
            self::RIGHT_INTERACTION_TASK,
            self::RIGHT_INTERACTION_SOLUTION,
            self::RIGHT_INTERACTION_DOCUMENT,
            self::RIGHT_INTERACTION_VALIDATION,
            self::RIGHT_INTERACTION_ESCALATE,
            self::RIGHT_PRIORITY_STRATEGIC,
            self::RIGHT_GENERATE_TEP,
            self::RIGHT_FILL_TEP,
            self::RIGHT_SEND_TEP,
            self::RIGHT_ACCEPT_TEP,
            self::RIGHT_GENERATE_TA,
            self::RIGHT_FILL_TA,
            self::RIGHT_SEND_TA,
            self::RIGHT_ACCEPT_TA,
         ],
         __('Configurar', 'projectplus') => [
            self::RIGHT_CONFIG,
         ],
      ];

      $all = [];
      foreach (self::getAllRights() as $right) {
         $all[(string) $right['field']] = $right;
      }

      $blocks = [];
      foreach ($fields as $title => $blockFields) {
         $blocks[$title] = [];
         foreach ($blockFields as $field) {
            if (isset($all[$field])) {
               $blocks[$title][] = $all[$field];
            }
         }
      }
      return $blocks;
   }

   /** @return array<string, int> */
   public static function getDefaultRights(): array
   {
      return [
         self::RIGHT_REQUESTS => 0,
         self::RIGHT_PORTFOLIO => 0,
         self::RIGHT_PRIORITY_TECHNICAL => 0,
         self::RIGHT_PRIORITY_STRATEGIC => 0,
         self::RIGHT_CONFIG => 0,
         self::RIGHT_FLOW_UPDATE => 0,
         self::RIGHT_REQUEST_DOCUMENTS => 0,
         self::RIGHT_REQUEST_PRETIP => 0,
         self::RIGHT_REQUEST_TIP => 0,
         self::RIGHT_FILL_PRETIP => 0,
         self::RIGHT_FILL_TIP => 0,
         self::RIGHT_GENERATE_TIP => 0,
         self::RIGHT_PUBLISH_TIP => 0,
         self::RIGHT_VALIDATE_INTENTS => 0,
         self::RIGHT_VALIDATE_PRETIP => 0,
         self::RIGHT_VALIDATE_TIP => 0,
         self::RIGHT_AUTHORIZE_OPENING => 0,
         self::RIGHT_OPEN_PROJECT => 0,
         self::RIGHT_MANAGE_DOCUMENTS => 0,
         self::RIGHT_MANAGE_TAP => 0,
         self::RIGHT_MANAGE_TEP => 0,
         self::RIGHT_MANAGE_TA => 0,
         self::RIGHT_FILL_TAP => 0,
         self::RIGHT_GENERATE_TAP => 0,
         self::RIGHT_SEND_TAP => 0,
         self::RIGHT_ACCEPT_TAP => 0,
         self::RIGHT_FILL_TEP => 0,
         self::RIGHT_GENERATE_TEP => 0,
         self::RIGHT_SEND_TEP => 0,
         self::RIGHT_ACCEPT_TEP => 0,
         self::RIGHT_FILL_TA => 0,
         self::RIGHT_GENERATE_TA => 0,
         self::RIGHT_SEND_TA => 0,
         self::RIGHT_ACCEPT_TA => 0,
         // Novos controles granulares não são concedidos automaticamente.
         // O administrador os habilita explicitamente em cada perfil.
         self::RIGHT_INTERACTION_REPLY => 0,
         self::RIGHT_INTERACTION_TASK => 0,
         self::RIGHT_INTERACTION_SOLUTION => 0,
         self::RIGHT_INTERACTION_DOCUMENT => 0,
         self::RIGHT_INTERACTION_VALIDATION => 0,
         self::RIGHT_INTERACTION_ESCALATE => 0,
      ];
   }

   public static function getIntentRequestRight(string $documentType): string
   {
      return $documentType === 'pretip' ? self::RIGHT_REQUEST_PRETIP : self::RIGHT_REQUEST_TIP;
   }

   public static function getIntentValidationRight(string $documentType): string
   {
      return $documentType === 'pretip' ? self::RIGHT_VALIDATE_PRETIP : self::RIGHT_VALIDATE_TIP;
   }

   public static function getDocumentManagementRight(string $documentType): string
   {
      if ($documentType === 'tap') {
         return self::RIGHT_MANAGE_TAP;
      }
      if ($documentType === 'tep') {
         return self::RIGHT_MANAGE_TEP;
      }
      return self::RIGHT_MANAGE_TA;
   }

   /**
    * Returns the granular right for a document action. The previous manage
    * rights remain registered so existing profiles continue to work during
    * the transition to the separated matrix.
    */
   public static function getDocumentActionRight(string $documentType, string $action): string
   {
      $rights = [
         'tap' => [
            'fill' => self::RIGHT_FILL_TAP,
            'generate' => self::RIGHT_GENERATE_TAP,
            'send' => self::RIGHT_SEND_TAP,
            'accept' => self::RIGHT_ACCEPT_TAP,
         ],
         'tep' => [
            'fill' => self::RIGHT_FILL_TEP,
            'generate' => self::RIGHT_GENERATE_TEP,
            'send' => self::RIGHT_SEND_TEP,
            'accept' => self::RIGHT_ACCEPT_TEP,
         ],
         'ta' => [
            'fill' => self::RIGHT_FILL_TA,
            'generate' => self::RIGHT_GENERATE_TA,
            'send' => self::RIGHT_SEND_TA,
            'accept' => self::RIGHT_ACCEPT_TA,
         ],
      ];
      return $rights[$documentType][$action] ?? self::getDocumentManagementRight($documentType);
   }

   public static function haveDocumentActionRight(string $documentType, string $action, int $right = UPDATE): bool
   {
      return Session::haveRight(self::getDocumentActionRight($documentType, $action), $right)
         || Session::haveRight(self::getDocumentManagementRight($documentType), UPDATE);
   }

   public static function installRights(): void
   {
      $migration = new \Migration(PLUGIN_PROJECTPLUS_VERSION);
      foreach (self::getRightNames() as $right) {
         // Direitos novos não podem ser concedidos automaticamente durante um
         // upgrade: cada perfil precisa ser configurado explicitamente.
         $migration->addRight($right, 0, []);
      }
      $migration->executeMigration();
      ProfileRight::cleanAllPossibleRights();
   }

   public static function initProfile(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!isset($_SESSION['glpiactiveprofile']['id']) || !$DB->tableExists('glpi_profilerights')) {
         return;
      }
      $profileId = (int) $_SESSION['glpiactiveprofile']['id'];
      self::addDefaultProfileInfos($profileId, self::getDefaultRights());
      foreach ($DB->request([
         'SELECT' => ['name', 'rights'],
         'FROM' => 'glpi_profilerights',
         'WHERE' => ['profiles_id' => $profileId, 'name' => self::getRightNames()],
      ]) as $row) {
         $_SESSION['glpiactiveprofile'][$row['name']] = (int) $row['rights'];
      }
   }

   public static function removeRightsFromSession(): void
   {
      foreach (self::getRightNames() as $right) {
         unset($_SESSION['glpiactiveprofile'][$right]);
      }
   }

   /** @param array<string, int> $rights */
   public static function addDefaultProfileInfos(int $profileId, array $rights): void
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!$DB->tableExists('glpi_profilerights')) {
         return;
      }
      $profileRight = new ProfileRight();
      $dbu = new DbUtils();
      foreach ($rights as $right => $value) {
         $criteria = ['profiles_id' => $profileId, 'name' => $right];
         if ($dbu->countElementsInTable('glpi_profilerights', $criteria) === 0) {
            $profileRight->add($criteria + ['rights' => $value]);
         }
      }
   }

   public function showForm($ID, $options = [])
   {
      if (!self::canView()) {
         return false;
      }
      $canEdit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
      $profile = new GlpiProfile();
      if (!$profile->getFromDB((int) $ID)) {
         return false;
      }
      echo "<div class='spaced'>";
      echo "<div class='alert alert-info'><i class='ti ti-shield-check'></i> "
         . htmlspecialchars(__('Configure neste perfil quais etapas do fluxo Project+ serão executadas. Por exemplo, o Gestor de Portfólio pode preencher o TIP, priorizar e autorizar a abertura; o Gestor de Projetos pode liberar e validar os termos.', 'projectplus'), ENT_QUOTES, 'UTF-8')
         . '</div>';
      if ($canEdit) {
         echo "<form method='post' action='" . GlpiProfile::getFormURL() . "' data-track-changes='true'>";
      }
      foreach (self::getRightsByBlock() as $title => $rights) {
         $profile->displayRightsChoiceMatrix($rights, [
            'title' => $title,
            'canedit' => $canEdit,
            'default_class' => 'tab_bg_2',
         ]);
      }
      if ($canEdit) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary mt-2']);
         echo '</div>';
         Html::closeForm();
      }
      echo '</div>';
      return true;
   }
}
