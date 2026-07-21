<?php

use GlpiPlugin\Projectplus\Installer;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/bootstrap.php';

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
