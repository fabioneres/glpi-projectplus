<?php

use GlpiPlugin\Projectplus\ProjectImport;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', UPDATE);
ProjectImport::downloadTemplate();
