<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\TipConfig;
use GlpiPlugin\Projectplus\TipField;

Session::checkRight('config', UPDATE);
$documentType = (string) ($_REQUEST['document_type'] ?? 'tip');
if ($documentType !== 'pretip') {
   $documentType = 'tip';
}
$documentLabel = $documentType === 'pretip'
   ? __('TIP - Termo de Intenção de Projeto', 'projectplus')
   : __('TIP - Detalhamento Técnico', 'projectplus');
$entityId = (int) Session::getActiveEntity();
if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
   Html::displayErrorAndDie(__('Entidade inválida ou sem permissão de configuração.', 'projectplus'));
}
if (isset($_POST['save_tip_config'])) {
   if (TipConfig::saveForEntity($entityId, $documentType, $_POST)) {
      Session::addMessageAfterRedirect(__('Modelo TIP salvo.', 'projectplus'));
   } else {
      Session::addMessageAfterRedirect(__('Informe o título do modelo TIP.', 'projectplus'), false, ERROR);
   }
   Html::redirect($_SERVER['PHP_SELF'] . '?document_type=' . $documentType);
}
if (isset($_POST['delete_tip_field'])) {
   if (TipField::delete((int) ($_POST['id'] ?? 0), $entityId, $documentType)) {
      Session::addMessageAfterRedirect(__('Campo removido.', 'projectplus'));
   }
   Html::redirect($_SERVER['PHP_SELF'] . '?document_type=' . $documentType);
}

$config = TipConfig::getForEntity($entityId, $documentType);
$fields = TipField::getForEntity($entityId, $documentType, false);
Html::header($documentLabel, $_SERVER['PHP_SELF'], 'config', 'plugins');
ConfigPage::begin($documentType === 'pretip' ? 'pretipmodel' : 'tipmodel');
echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-forms'></i> " . ConfigPage::escape(sprintf(__('Modelo do %s', 'projectplus'), $documentLabel)) . '</header><div class="projectplus-config-card__body">';
echo "<form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('entities_id', ['value' => $entityId]) . Html::hidden('document_type', ['value' => $documentType]);
echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th colspan='2'>" . __('Disponibilização do TIP', 'projectplus') . '</th></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Habilitar modelo TIP', 'projectplus') . '</td><td>'; Dropdown::showYesNo('is_active', (int) $config['is_active']); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Título', 'projectplus') . "</td><td><input class='form-control' type='text' required name='title' value='" . ConfigPage::escape((string) $config['title']) . "'></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Introdução', 'projectplus') . "</td><td><textarea class='form-control' name='introduction' rows='4'>" . ConfigPage::escape((string) $config['introduction']) . "</textarea></td></tr>";
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_tip_config' value='1'>" . __('Salvar', 'projectplus') . '</button></td></tr></table></form>';
echo "<p class='projectplus-config-help'>" . ConfigPage::escape(__('Os campos deste modelo são independentes e serão exibidos somente neste documento.', 'projectplus')) . '</p>';
echo "<div class='center'><a class='btn btn-primary' href='tipfield.form.php?entities_id=" . $entityId . '&document_type=' . $documentType . "'>" . __('Adicionar campo', 'projectplus') . '</a></div>';
echo "<table class='tab_cadre_fixe'><tr><th>" . __('Ordem', 'projectplus') . '</th><th>' . __('Campo', 'projectplus') . '</th><th>' . __('Tipo', 'projectplus') . '</th><th>' . __('Obrigatório', 'projectplus') . '</th><th>' . __('Ativo', 'projectplus') . '</th><th>' . __('Ações', 'projectplus') . '</th></tr>';
foreach ($fields as $field) {
   echo "<tr class='tab_bg_1'><td>" . (int) $field['sort_order'] . '</td><td>' . ConfigPage::escape((string) $field['label']) . '</td><td>' . ConfigPage::escape(TipField::getTypes()[(string) $field['field_type']] ?? '') . '</td><td>' . ((int) $field['is_required'] ? __('Sim') : __('Não')) . '</td><td>' . ((int) $field['is_active'] ? __('Sim') : __('Não')) . "</td><td><a class='btn btn-sm btn-secondary' href='tipfield.form.php?entities_id=" . $entityId . '&document_type=' . $documentType . '&id=' . (int) $field['id'] . "'>" . __('Editar') . "</a> <form style='display:inline' method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('entities_id', ['value' => $entityId]) . Html::hidden('document_type', ['value' => $documentType]) . Html::hidden('id', ['value' => (int) $field['id']]) . "<button class='btn btn-sm btn-danger' type='submit' name='delete_tip_field' value='1'>" . __('Excluir') . '</button></form></td></tr>';
}
echo '</table></div></section>';
ConfigPage::end(); Html::footer();
