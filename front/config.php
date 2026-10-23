<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\ProjectImport;
use GlpiPlugin\Projectplus\FrontUrl;

Session::checkRight('config', READ);
Html::header(__('Project+', 'projectplus'), FrontUrl::to(basename(__FILE__)), 'config', 'plugins');

ConfigPage::begin('overview');
echo "<section class='projectplus-tip projectplus-config-overview'>";
echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-settings'></i></div><div><span class='projectplus-tip__eyebrow'>" . ConfigPage::escape(__('Administração do plugin', 'projectplus')) . '</span><h2>' . ConfigPage::escape(__('Configurações do Project+', 'projectplus')) . '</h2><p>' . ConfigPage::escape(__('Gerencie o fluxo de solicitações e a importação de projetos existentes.', 'projectplus')) . '</p></div></header>';
echo "<div class='projectplus-tip__card projectplus-config-overview__body'><div class='projectplus-config-overview__section-title'><i class='ti ti-adjustments'></i><div><h3>" . ConfigPage::escape(__('Recursos administrativos', 'projectplus')) . '</h3><p>' . ConfigPage::escape(__('Escolha um recurso para configurar o comportamento do Project+.', 'projectplus')) . "</p></div></div><div class='projectplus-config-overview__grid'>";

echo "<article class='projectplus-config-option'><div class='projectplus-config-option__icon'><i class='ti ti-git-branch'></i></div><div>";
echo '<h3>' . ConfigPage::escape(__('Fluxo de solicitação de projeto', 'projectplus')) . '</h3>';
echo '<p>' . ConfigPage::escape(__('Defina, por entidade, qual categoria ITIL inicia o fluxo interno de solicitação de projetos.', 'projectplus')) . '</p>';
if (Session::haveRight('config', UPDATE)) {
   echo "<a class='btn btn-primary' href='ticketconfig.php'>" . ConfigPage::escape(__('Configurar fluxo', 'projectplus')) . '</a>';
}
echo '</div></article>';

echo "<article class='projectplus-config-option'><div class='projectplus-config-option__icon'><i class='ti ti-message-2'></i></div><div>";
echo '<h3>' . ConfigPage::escape(__('Etapas do fluxo', 'projectplus')) . '</h3>';
echo '<p>' . ConfigPage::escape(__('Personalize os nomes, gatilhos e textos de orientação de cada etapa do fluxo.', 'projectplus')) . '</p>';
if (Session::haveRight('config', UPDATE)) {
   echo "<a class='btn btn-primary' href='flowdescriptions.php'>" . ConfigPage::escape(__('Configurar etapas', 'projectplus')) . '</a>';
}
echo '</div></article>';

echo "<article class='projectplus-config-option'><div class='projectplus-config-option__icon'><i class='ti ti-arrow-up-right'></i></div><div>";
echo '<h3>' . ConfigPage::escape(__('Transferência de projetos', 'projectplus')) . '</h3>';
echo '<p>' . ConfigPage::escape(__('Defina, para cada entidade, quais entidades podem receber os projetos dela.', 'projectplus')) . '</p>';
if (Session::haveRight('config', UPDATE)) {
   echo "<a class='btn btn-primary' href='transferrules.php'>" . ConfigPage::escape(__('Configurar transferência', 'projectplus')) . '</a>';
}
echo '</div></article>';

echo "<article class='projectplus-config-option'><div class='projectplus-config-option__icon'><i class='ti ti-file-import'></i></div><div>";
echo '<h3>' . ConfigPage::escape(__('Importação de projetos existentes', 'projectplus')) . '</h3>';
echo '<p>' . ConfigPage::escape(__('Importe uma planilha CSV, valide os dados e preencha os projetos existentes no GLPI.', 'projectplus')) . '</p>';
if (Session::haveRight('config', UPDATE)) {
   echo "<a class='btn btn-primary' href='" . htmlescape(ProjectImport::getFormURL()) . "'>" . ConfigPage::escape(__('Abrir importação', 'projectplus')) . '</a>';
}
echo '</div></article>';

echo '</div></div></section>';
ConfigPage::end();
Html::footer();
