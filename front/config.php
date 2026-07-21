<?php

use GlpiPlugin\Projectplus\PdticAction;
use GlpiPlugin\Projectplus\PortfolioBasket;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', READ);
Html::header(__('Projetos+', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

echo "<div class='center'><table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Listas de Projetos', 'projectplus') . '</th></tr>';
foreach ([PortfolioBasket::class, PdticAction::class] as $itemtype) {
   echo "<tr class='tab_bg_1'><td>" . $itemtype::getTypeName(2) . '</td><td>';
   echo "<a class='btn btn-primary' href='" . Html::clean($itemtype::getSearchURL()) . "'>" . __('Gerenciar', 'projectplus') . '</a>';
   echo '</td></tr>';
}
echo '</table></div>';
Html::footer();
