<?php
if (!defined('GLPI_ROOT')) { require_once dirname(__DIR__, 3) . '/inc/includes.php'; }
// Queue TinyMCE before dropdown.common.form.php renders GLPI's page header.
Html::requireJs('tinymce');
require_once dirname(__DIR__) . '/bootstrap.php';
$dropdown = new PluginProjectplusSolutionTemplate();
include GLPI_ROOT . '/front/dropdown.common.form.php';
