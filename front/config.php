<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', READ);
Html::header(__('Project+', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

echo "<div class='center'><table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Administração', 'projectplus') . '</th></tr>';
if (Session::haveRight('config', UPDATE)) {
   echo "<tr class='tab_bg_1'><td>" . __('Fluxo de solicitacao de projeto', 'projectplus') . '</td><td>';
   echo "<a class='btn btn-primary' href='ticketconfig.php'>" . __('Configurar categoria por entidade', 'projectplus') . '</a>';
   echo '</td></tr>';
   echo "<tr class='tab_bg_1'><td>" . __('Importação de projetos', 'projectplus') . '</td><td>';
   echo "<a class='btn btn-primary' href='" . Html::clean(\GlpiPlugin\Projectplus\ProjectImport::getFormURL()) . "'>" . __('Importar CSV', 'projectplus') . '</a>';
   echo '</td></tr>';
}
echo '</table></div>';
Html::footer();
