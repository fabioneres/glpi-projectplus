<?php

use GlpiPlugin\Projectplus\PortfolioBasket;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', READ);
Html::header(PortfolioBasket::getTypeName(2), $_SERVER['PHP_SELF'], 'config', 'plugins');
Search::show(PortfolioBasket::class);
Html::footer();
