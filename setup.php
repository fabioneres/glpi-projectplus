<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Projectplus\ProjectData;
use GlpiPlugin\Projectplus\ProjectForm;
use GlpiPlugin\Projectplus\ProjectPortal;

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
   $PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['projectplus'] = [ProjectPortal::class, 'redefineMenus'];
   $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['projectplus'] = 'front/config.php';
   $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['projectplus'] = [ProjectForm::class, 'postItemForm'];
   $PLUGIN_HOOKS[Hooks::ITEM_ADD]['projectplus'][Project::class] = [ProjectData::class, 'postProjectAdd'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['projectplus'][Project::class] = [ProjectData::class, 'preProjectUpdate'];
   $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['projectplus'][Project::class] = [ProjectData::class, 'preProjectPurge'];
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
