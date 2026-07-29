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
   public const RIGHT_CONFIG = 'plugin_projectplus_config';
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
         self::RIGHT_CONFIG,
         self::RIGHT_INTERACTION_REPLY,
         self::RIGHT_INTERACTION_TASK,
         self::RIGHT_INTERACTION_SOLUTION,
         self::RIGHT_INTERACTION_DOCUMENT,
         self::RIGHT_INTERACTION_VALIDATION,
         self::RIGHT_INTERACTION_ESCALATE,
      ];
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
            'label' => __('Solicitacoes de projeto', 'projectplus'),
            'field' => self::RIGHT_REQUESTS,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Priorizacao de portfolio', 'projectplus'),
            'field' => self::RIGHT_PORTFOLIO,
         ],
         [
            'itemtype' => TicketPhase::class,
            'label' => __('Administracao do Project+', 'projectplus'),
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

   /** @return array<string, int> */
   public static function getDefaultRights(): array
   {
      return [
         self::RIGHT_REQUESTS => 0,
         self::RIGHT_PORTFOLIO => 0,
         self::RIGHT_CONFIG => 0,
         // All profiles receive the controls by default. The Project native
         // UPDATE right is still required for manager-only actions below.
         self::RIGHT_INTERACTION_REPLY => READ,
         self::RIGHT_INTERACTION_TASK => UPDATE,
         self::RIGHT_INTERACTION_SOLUTION => UPDATE,
         self::RIGHT_INTERACTION_DOCUMENT => UPDATE,
         self::RIGHT_INTERACTION_VALIDATION => UPDATE,
         self::RIGHT_INTERACTION_ESCALATE => UPDATE,
      ];
   }

   public static function installRights(): void
   {
      $migration = new \Migration(PLUGIN_PROJECTPLUS_VERSION);
      foreach (self::getRightNames() as $right) {
         $migration->addRight($right, ALLSTANDARDRIGHT, [\Config::$rightname => UPDATE]);
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
      if ($canEdit) {
         echo "<form method='post' action='" . GlpiProfile::getFormURL() . "' data-track-changes='true'>";
      }
      $profile->displayRightsChoiceMatrix(self::getAllRights(), [
         'title' => __('Project+', 'projectplus'),
         'canedit' => $canEdit,
         'default_class' => 'tab_bg_2',
      ]);
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
