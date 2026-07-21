<?php

use GlpiPlugin\Projectplus\PortfolioBasket;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

$item = new PortfolioBasket();
if (isset($_POST['add'])) { Session::checkRight('config', UPDATE); $item->add($_POST); Html::redirect(PortfolioBasket::getSearchURL()); }
if (isset($_POST['update'])) { Session::checkRight('config', UPDATE); $item->update($_POST); Html::redirect(PortfolioBasket::getSearchURL()); }
if (isset($_POST['delete']) || isset($_POST['purge'])) { Session::checkRight('config', UPDATE); $item->delete($_POST, isset($_POST['purge'])); Html::redirect(PortfolioBasket::getSearchURL()); }
Session::checkRight('config', READ);
Html::header(PortfolioBasket::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'plugins');
$item->display(['id' => (int) ($_GET['id'] ?? 0)]);
Html::footer();
