<?php

use GlpiPlugin\Projectplus\PdticAction;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', READ);
Html::header(PdticAction::getTypeName(2), $_SERVER['PHP_SELF'], 'config', 'plugins');
Search::show(PdticAction::class);
Html::footer();
