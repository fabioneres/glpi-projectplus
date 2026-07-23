<?php

use GlpiPlugin\Projectplus\Installer;

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
   ];
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
   return Installer::uninstall();
}
