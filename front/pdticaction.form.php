<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

$dropdown = new PluginProjectplusPdticAction();
include GLPI_ROOT . '/front/dropdown.common.form.php';
