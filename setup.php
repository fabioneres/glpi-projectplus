<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Projectplus\ProjectData;
use GlpiPlugin\Projectplus\ProjectForm;
use GlpiPlugin\Projectplus\ProjectPortal;
use GlpiPlugin\Projectplus\TicketPhase;
use GlpiPlugin\Projectplus\Menu;
use GlpiPlugin\Projectplus\ProjectProfile;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/bootstrap.php';

function plugin_init_projectplus(): void
{
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['projectplus'] = true;
   Plugin::loadLang('projectplus');

   if (!Plugin::isPluginActive('projectplus')) {
      return;
   }

   // Dropdowns use legacy class names so GLPI can resolve the current
   // menu option and render the native list actions.
   Plugin::registerClass('PluginProjectplusPortfolioBasket');
   Plugin::registerClass('PluginProjectplusPdticAction');
   Plugin::registerClass(Menu::class);
   Plugin::registerClass(ProjectProfile::class, ['addtabon' => [\Profile::class]]);
   Plugin::registerClass(TicketPhase::class, ['addtabon' => [Ticket::class]]);
   $PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['projectplus'] = [ProjectPortal::class, 'redefineMenus'];
   $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['projectplus'] = 'front/config.php';
   $PLUGIN_HOOKS[Hooks::ADD_CSS]['projectplus'][] = 'css/projectplus.css';
   $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['projectplus'] = [ProjectForm::class, 'postItemForm'];
   $PLUGIN_HOOKS[Hooks::ITEM_ADD]['projectplus'][Project::class] = [ProjectData::class, 'postProjectAdd'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['projectplus'][Project::class] = [ProjectData::class, 'preProjectUpdate'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['projectplus'][Project::class] = [ProjectData::class, 'preProjectPurge'];
   $PLUGIN_HOOKS[Hooks::ITEM_ADD]['projectplus'][Ticket::class] = [TicketPhase::class, 'postTicketAdd'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['projectplus'][Ticket::class] = [TicketPhase::class, 'preTicketUpdate'];
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
