<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TipField;

Session::checkRight('config', UPDATE);
$documentType = (string) ($_REQUEST['document_type'] ?? 'tip');
if ($documentType !== 'pretip') {
   $documentType = 'tip';
}
$entityId = (int) ($_REQUEST['entities_id'] ?? Session::getActiveEntity());
if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) { Html::displayErrorAndDie(__('Entidade inválida.', 'projectplus')); }
if (isset($_POST['save_tip_field'])) {
   if (TipField::save($entityId, $_POST)) { Session::addMessageAfterRedirect(__('Campo do TIP salvo.', 'projectplus')); }
   else { Session::addMessageAfterRedirect(__('Não foi possível salvar o campo.', 'projectplus'), false, ERROR); }
   Html::redirect('tipconfig.php?entities_id=' . $entityId . '&document_type=' . $documentType);
}
$field = TipField::getById((int) ($_GET['id'] ?? 0), $entityId) ?? ['id'=>0,'label'=>'','description'=>'','field_type'=>'text','values'=>'','is_required'=>0,'is_active'=>1,'sort_order'=>0];
Html::header((int) $field['id'] ? __('Editar campo TIP', 'projectplus') : __('Adicionar campo TIP', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
echo "<div class='center'><form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>" . Html::hidden('_glpi_csrf_token', ['value'=>Session::getNewCSRFToken()]) . Html::hidden('entities_id',['value'=>$entityId]) . Html::hidden('document_type',['value'=>$documentType]) . Html::hidden('id',['value'=>(int)$field['id']]);
echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th colspan='2'>" . ((int)$field['id'] ? __('Editar campo', 'projectplus') : __('Novo campo', 'projectplus')) . '</th></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Rótulo', 'projectplus') . "</td><td><input class='form-control' required name='label' value='" . htmlspecialchars((string)$field['label'], ENT_QUOTES, 'UTF-8') . "'></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Tipo', 'projectplus') . '</td><td>'; Dropdown::showFromArray('field_type', TipField::getTypes(), ['value'=>(string)$field['field_type']]); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Orientação', 'projectplus') . "</td><td><textarea class='form-control' name='description' rows='3'>" . htmlspecialchars((string)$field['description'], ENT_QUOTES, 'UTF-8') . "</textarea></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Opções da lista', 'projectplus') . "</td><td><textarea class='form-control' name='values' rows='4'>" . htmlspecialchars((string)$field['values'], ENT_QUOTES, 'UTF-8') . "</textarea><small>" . __('Uma opção por linha; utilizado somente para lista suspensa.', 'projectplus') . '</small></td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Ordem', 'projectplus') . "</td><td><input type='number' min='0' name='sort_order' value='" . (int)$field['sort_order'] . "'></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Obrigatório', 'projectplus') . '</td><td>'; Dropdown::showYesNo('is_required',(int)$field['is_required']); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Ativo', 'projectplus') . '</td><td>'; Dropdown::showYesNo('is_active',(int)$field['is_active']); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_tip_field' value='1'>" . __('Salvar') . '</button></td></tr></table></form></div>';
Html::footer();
