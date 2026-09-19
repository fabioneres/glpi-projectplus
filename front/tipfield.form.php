<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TipField;
use GlpiPlugin\Projectplus\FieldSource;
use GlpiPlugin\Projectplus\TipDocumentType;

Session::checkRight('config', UPDATE);
$documentType = (string) ($_REQUEST['document_type'] ?? 'tip');
if ($documentType !== TipDocumentType::DETAIL) {
   $documentType = TipDocumentType::TIP;
}
$entityId = (int) ($_REQUEST['entities_id'] ?? Session::getActiveEntity());
if ($entityId < 0 || !Session::haveAccessToEntity($entityId)) { Html::displayErrorAndDie(__('Entidade inválida.', 'projectplus')); }
if (isset($_POST['save_tip_field'])) {
   if (TipField::save($entityId, $documentType, $_POST)) { Session::addMessageAfterRedirect(__('Campo do TIP salvo.', 'projectplus')); }
   else { Session::addMessageAfterRedirect(__('Não foi possível salvar o campo.', 'projectplus'), false, ERROR); }
   Html::redirect('tipconfig.php?entities_id=' . $entityId . '&document_type=' . $documentType);
}
$field = TipField::getForEntityById((int) ($_GET['id'] ?? 0), $entityId, $documentType) ?? ['id'=>0,'label'=>'','description'=>'','field_type'=>'text','values'=>'','source'=>'','is_required'=>0,'is_active'=>1,'is_recursive'=>0,'sort_order'=>0];
$field['source'] = (string) ($field['source'] ?? '');
Html::header((int) $field['id'] ? __('Editar campo TIP', 'projectplus') : __('Adicionar campo TIP', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
echo "<div class='projectplus-question-editor'><form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>" . Html::hidden('_glpi_csrf_token', ['value'=>Session::getNewCSRFToken()]) . Html::hidden('entities_id',['value'=>$entityId]) . Html::hidden('document_type',['value'=>$documentType]) . Html::hidden('id',['value'=>(int)$field['id']]);
echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th colspan='2'>" . ((int)$field['id'] ? __('Editar campo', 'projectplus') : __('Novo campo', 'projectplus')) . '</th></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Rótulo', 'projectplus') . "</td><td><input class='form-control' required name='label' value='" . htmlspecialchars((string)$field['label'], ENT_QUOTES, 'UTF-8') . "'></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Tipo', 'projectplus') . '</td><td>'; Dropdown::showFromArray('field_type', TipField::getTypes(), ['value'=>(string)$field['field_type']]); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Orientação', 'projectplus') . "</td><td><textarea class='form-control' name='description' rows='3'>" . htmlspecialchars((string)$field['description'], ENT_QUOTES, 'UTF-8') . "</textarea></td></tr>";
echo "<tr class='tab_bg_1' id='projectplus-choice-options-row'><td>" . __('Opções de escolha', 'projectplus') . "</td><td><textarea class='form-control' name='values' rows='4'>" . htmlspecialchars((string)$field['values'], ENT_QUOTES, 'UTF-8') . "</textarea><small>" . __('Uma opção por linha; utilizado em listas, seleção única e seleção múltipla.', 'projectplus') . '</small></td></tr>';
echo "<tr class='tab_bg_1' id='projectplus-source-row'><td>" . __('Preencher com origem', 'projectplus') . '</td><td>'; Dropdown::showFromArray('source', FieldSource::getOptions($entityId, $documentType), ['value' => $field['source']]); echo '<small>' . __('Sugere o valor ao criar o documento; o responsável ainda pode alterá-lo.', 'projectplus') . '</small></td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Ordem', 'projectplus') . "</td><td><input type='number' min='0' name='sort_order' value='" . (int)$field['sort_order'] . "'></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Obrigatório', 'projectplus') . '</td><td>'; Dropdown::showYesNo('is_required',(int)$field['is_required']); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Ativo', 'projectplus') . '</td><td>'; Dropdown::showYesNo('is_active',(int)$field['is_active']); echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Entidades filhas', 'projectplus') . '</td><td>'; Dropdown::showYesNo('is_recursive',(int)($field['is_recursive'] ?? 0)); echo "<small>" . __('Disponibiliza somente este campo nas entidades descendentes.', 'projectplus') . '</small></td></tr>';
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_tip_field' value='1'>" . __('Salvar') . '</button></td></tr></table></form></div>';
echo "<script>(function(){const type=document.querySelector('select[name=\"field_type\"]');const row=document.getElementById('projectplus-choice-options-row');const source=document.getElementById('projectplus-source-row');if(!type||!row)return;const choices=['select','radio','checkboxes','multiselect'];const toggle=()=>{row.style.display=choices.includes(type.value)?'table-row':'none';if(source)source.style.display=(type.value==='attachment'||type.value==='section'||type.value==='info')?'none':'table-row';};type.addEventListener('change',toggle);if(window.jQuery)jQuery(type).on('select2:select select2:clear',toggle);toggle();})();</script>";
Html::footer();
