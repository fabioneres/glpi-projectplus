<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\ProjectImport;

Session::checkRight('config', READ);
Html::header(__('Project+', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

ConfigPage::begin('overview');
echo "<section class='projectplus-config-card'>";
echo "<header class='projectplus-config-card__header'><i class='ti ti-settings'></i> " . ConfigPage::escape(__('Configuracoes do Project+', 'projectplus')) . '</header>';
echo "<div class='projectplus-config-card__body'><div class='row g-4'>";

echo "<div class='col-12 col-lg-6'><article class='projectplus-config-option'>";
echo "<div class='projectplus-config-option__icon'><i class='ti ti-git-branch'></i></div><div>";
echo '<h3>' . ConfigPage::escape(__('Fluxo de solicitacao de projeto', 'projectplus')) . '</h3>';
echo '<p>' . ConfigPage::escape(__('Defina, por entidade, qual categoria ITIL inicia o fluxo interno de solicitacao de projetos.', 'projectplus')) . '</p>';
if (Session::haveRight('config', UPDATE)) {
   echo "<a class='btn btn-primary' href='ticketconfig.php'>" . ConfigPage::escape(__('Configurar fluxo', 'projectplus')) . '</a>';
}
echo '</div></article></div>';

echo "<div class='col-12 col-lg-6'><article class='projectplus-config-option'>";
echo "<div class='projectplus-config-option__icon'><i class='ti ti-file-import'></i></div><div>";
echo '<h3>' . ConfigPage::escape(__('Importacao de projetos existentes', 'projectplus')) . '</h3>';
echo '<p>' . ConfigPage::escape(__('Importe uma planilha CSV, valide os dados e preencha os projetos existentes no GLPI.', 'projectplus')) . '</p>';
if (Session::haveRight('config', UPDATE)) {
   echo "<a class='btn btn-primary' href='" . Html::clean(ProjectImport::getFormURL()) . "'>" . ConfigPage::escape(__('Abrir importacao', 'projectplus')) . '</a>';
}
echo '</div></article></div>';

echo '</div></div></section>';
ConfigPage::end();
Html::footer();
