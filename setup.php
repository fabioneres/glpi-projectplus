<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Projectplus\ProjectData;
use GlpiPlugin\Projectplus\ProjectForm;
use GlpiPlugin\Projectplus\ProjectPortal;
use GlpiPlugin\Projectplus\TicketPhase;
use GlpiPlugin\Projectplus\Menu;
use GlpiPlugin\Projectplus\ProjectProfile;
use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ProjectInteraction;
use GlpiPlugin\Projectplus\ProjectNotification;
use GlpiPlugin\Projectplus\ProjectPriority;
use GlpiPlugin\Projectplus\ProjectWorkspace;
use GlpiPlugin\Projectplus\TicketCategoryConfig;
use GlpiPlugin\Projectplus\CentralRequests;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/bootstrap.php';

function plugin_init_projectplus(): void
{
   global $CFG_GLPI, $PLUGIN_HOOKS;

   Plugin::loadLang('projectplus');

   if (!Plugin::isPluginActive('projectplus')) {
      return;
   }

   // GLPI 11 derives the itemtype of a table from the class name, which does
   // not match this table (it is shared with TicketConfig). Without the map,
   // getItemTypeForTable() returns null and the native "Modify" mass action
   // fails with a TypeError.
   $CFG_GLPI['glpiitemtypetables'][TicketCategoryConfig::getTable()] = TicketCategoryConfig::class;
   $CFG_GLPI['glpitablesitemtype'][TicketCategoryConfig::class] = TicketCategoryConfig::getTable();

   // The Project+ interaction composer uses the same rich-text editor as
   // native ticket followups.  It must be queued before GLPI renders the
   // page header; queuing it while rendering a Project tab is too late for
   // the current response.
   $requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
   if (substr($requestPath, -strlen('/front/project.form.php')) === '/front/project.form.php'
      || strpos($requestPath, '/plugins/projectplus/front/followuptemplate.form.php') !== false
      || strpos($requestPath, '/plugins/projectplus/front/solutiontemplate.form.php') !== false) {
      Html::requireJs('tinymce');
   }

   // Dropdowns use legacy class names so GLPI can resolve the current
   // menu option and render the native list actions.
   Plugin::registerClass('PluginProjectplusPortfolioBasket');
   Plugin::registerClass('PluginProjectplusPdticAction');
   Plugin::registerClass('PluginProjectplusFollowupTemplate');
   Plugin::registerClass('PluginProjectplusSolutionTemplate');
   Plugin::registerClass(Menu::class);
   Plugin::registerClass(ProjectProfile::class, ['addtabon' => [\Profile::class]]);
   Plugin::registerClass(TicketPhase::class, ['addtabon' => [Ticket::class]]);
   Plugin::registerClass(TicketCategoryConfig::class);
   // Project documents, interactions and prioritization are consolidated in
   // one native Project+ tab, preserving their own internal permissions.
   Plugin::registerClass(ProjectWorkspace::class, ['addtabon' => [Project::class]]);
   // Notificacao das interacoes de projeto: os eventos entram na lista do
   // NotificationTargetProject nativo e as tags do texto, nos dados do modelo.
   // Nao ha itemtype de notificacao proprio, pelo mesmo motivo que o
   // acompanhamento de chamado nao tem: quem notifica e o objeto pai.
   // Plugin::doHook indexa estes dois hooks pela classe do objeto passado, que
   // aqui e o proprio NotificationTarget: sem essa chave o hook nunca e
   // chamado, e o e-mail sai com as tags por substituir.
   $PLUGIN_HOOKS[Hooks::ITEM_GET_EVENTS]['projectplus'][\NotificationTargetProject::class] = [ProjectNotification::class, 'addEvents'];
   $PLUGIN_HOOKS[Hooks::ITEM_GET_DATA]['projectplus'][\NotificationTargetProject::class] = [ProjectNotification::class, 'addData'];
   $PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['projectplus'] = [ProjectPortal::class, 'redefineMenus'];
   $PLUGIN_HOOKS[Hooks::DISPLAY_CENTRAL]['projectplus'] = [CentralRequests::class, 'display'];
   $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['projectplus'] = 'front/config.php';
   // One stylesheet for the whole plugin.  GLPI appends "?v=<plugin version>"
   // to it (Plugin::getPluginFilesVersion, used by the add_css hook), so a
   // bumped plugin version already invalidates the browser cache: a visual fix
   // belongs inside this file, never in a new "-vN" one.
   $PLUGIN_HOOKS[Hooks::ADD_CSS]['projectplus'][] = 'css/projectplus.css';
   $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['projectplus'][] = 'js/projectplus-ranking-search.js';
   $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['projectplus'] = [ProjectForm::class, 'postItemForm'];
   $PLUGIN_HOOKS[Hooks::ITEM_ADD]['projectplus'][Project::class] = [ProjectData::class, 'postProjectAdd'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['projectplus'][Project::class] = [ProjectData::class, 'preProjectUpdate'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['projectplus'][Project::class] = [ProjectData::class, 'preProjectPurge'];
   $PLUGIN_HOOKS[Hooks::ITEM_ADD]['projectplus'][Ticket::class] = [TicketPhase::class, 'postTicketAdd'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['projectplus'][Ticket::class] = [TicketPhase::class, 'preTicketUpdate'];
   $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['projectplus'][Ticket::class] = [TicketPhase::class, 'postTicketUpdate'];
   $PLUGIN_HOOKS[Hooks::CHANGE_PROFILE]['projectplus'] = [ProjectProfile::class, 'initProfile'];

   // GLPI caches the complete menu in the current session. Invalidate it
   // once per plugin version so newly registered Plugin-menu entries appear
   // immediately without requiring the user to log out and log back in.
   if (Session::getLoginUserID()) {
      ProjectProfile::initProfile();
      $menuCacheKey = 'plugin_projectplus_menu_cache_' . PLUGIN_PROJECTPLUS_VERSION;
      if (($_SESSION[$menuCacheKey] ?? 0) !== 1) {
         unset($_SESSION['glpimenu']);
         $_SESSION[$menuCacheKey] = 1;
      }
   }

   $PLUGIN_HOOKS[Hooks::MENU_TOADD]['projectplus'] = [
      'plugins' => Menu::class,
   ];
}

function plugin_version_projectplus(): array
{
   return [
      'name'         => __('Project+', 'projectplus'),
      'version'      => PLUGIN_PROJECTPLUS_VERSION,
      'author'       => 'Fabio Neres',
      'license'      => 'GPLv3+',
      'homepage'     => '',
      'requirements' => [
         'php' => [
            'min' => '8.2',
         ],
         'glpi' => [
            'min' => PLUGIN_PROJECTPLUS_MIN_GLPI_VERSION,
            'max' => PLUGIN_PROJECTPLUS_MAX_GLPI_VERSION,
         ],
      ],
   ];
}

function plugin_projectplus_check_prerequisites(): bool
{
   if (
      version_compare(GLPI_VERSION, PLUGIN_PROJECTPLUS_MIN_GLPI_VERSION, 'lt')
      || version_compare(GLPI_VERSION, PLUGIN_PROJECTPLUS_MAX_GLPI_VERSION, 'gt')
   ) {
      Plugin::messageIncompatible(
         'core',
         PLUGIN_PROJECTPLUS_MIN_GLPI_VERSION,
         PLUGIN_PROJECTPLUS_MAX_GLPI_VERSION
      );
      return false;
   }

   return true;
}

function plugin_projectplus_check_config(): bool
{
   return true;
}
