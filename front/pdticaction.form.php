<?php

use GlpiPlugin\Projectplus\PdticAction;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

$item = new PdticAction();
if (isset($_POST['add'])) { Session::checkRight('config', UPDATE); $item->add($_POST); Html::redirect(PdticAction::getSearchURL()); }
if (isset($_POST['update'])) { Session::checkRight('config', UPDATE); $item->update($_POST); Html::redirect(PdticAction::getSearchURL()); }
if (isset($_POST['delete']) || isset($_POST['purge'])) { Session::checkRight('config', UPDATE); $item->delete($_POST, isset($_POST['purge'])); Html::redirect(PdticAction::getSearchURL()); }
Session::checkRight('config', READ);
Html::header(PdticAction::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'plugins');
$item->display(['id' => (int) ($_GET['id'] ?? 0)]);
Html::footer();
