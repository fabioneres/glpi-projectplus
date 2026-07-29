<?php

use GlpiPlugin\Projectplus\Installer;
use GlpiPlugin\Projectplus\ProjectProfile;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/bootstrap.php';

/**
 * Adds Project+ dropdowns to the native Configuration > Dropdowns page.
 *
 * CommonDropdown supplies the standard create, edit, delete and entity scope
 * interfaces for the classes returned here.
 */
function plugin_projectplus_getDropdown(): array
{
   return [
      'PluginProjectplusPortfolioBasket' => __('Cestas Portfólio', 'projectplus'),
      'PluginProjectplusPdticAction'      => __('Ações PDTIC', 'projectplus'),
      'PluginProjectplusFollowupTemplate' => __('Modelos de acompanhamento de Projetos', 'projectplus'),
      'PluginProjectplusSolutionTemplate' => __('Modelos de solução do projeto', 'projectplus'),
   ];
}

/** Adds the Project+ ranking to GLPI's native project search options. */
function plugin_projectplus_getAddSearchOptionsNew($itemtype): array
{
   if ($itemtype !== \Project::class && $itemtype !== 'Project') {
      return [];
   }

   return \GlpiPlugin\Projectplus\ProjectPriority::getSearchOptionsForProject();
}

function plugin_projectplus_install(): bool
{
   return Installer::install();
}

function plugin_projectplus_upgrade($old_version): bool
{
   return Installer::install();
}

function plugin_projectplus_uninstall(): bool
{
   $result = Installer::uninstall();
   if ($result) {
      ProfileRight::deleteProfileRights(ProjectProfile::getRightNames());
      ProjectProfile::removeRightsFromSession();
   }
   return $result;
}
