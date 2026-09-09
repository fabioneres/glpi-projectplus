<?php

if (!defined('GLPI_ROOT')) { require_once dirname(__DIR__, 3) . '/inc/includes.php'; }
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\FieldSource;
use GlpiPlugin\Projectplus\ProjectFieldConfig;

Session::checkRight('config', UPDATE);
$entityId = (int) ($_REQUEST['entities_id'] ?? Session::getActiveEntity());
if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
   Html::displayErrorAndDie(__('Entidade inválida ou sem acesso.', 'projectplus'));
}
if (isset($_POST['save'])) {
   if (ProjectFieldConfig::save($entityId, $_POST)) {
      Session::addMessageAfterRedirect(__('Origens dos campos do Projeto salvas.', 'projectplus'), true, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Não foi possível salvar as origens.', 'projectplus'), false, ERROR);
   }
   Html::redirect('projectfieldconfig.php?entities_id=' . $entityId);
}

$configured = ProjectFieldConfig::getForEntity($entityId);
$sources = FieldSource::getProjectDocumentOptions($entityId);
Html::header(__('Campos do Projeto', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
ConfigPage::begin('projectfields');
echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-arrows-exchange'></i> " . ConfigPage::escape(__('Origem dos campos do Projeto', 'projectplus')) . '</header><div class="projectplus-config-card__body">';
echo '<p class="projectplus-config-help">' . ConfigPage::escape(__('Defina de onde cada campo do formulário nativo de Projeto será sugerido. O Project+ usa automaticamente a entidade do chamado de origem e aplica o mapeamento ao projeto vinculado. Sem origem configurada, o GLPI mantém seu preenchimento normal.', 'projectplus')) . '</p>';
echo "<form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('entities_id', ['value' => $entityId]);
echo '<table class="tab_cadre_fixe projectplus-project-fields-table"><colgroup><col class="projectplus-project-fields-table__name"><col class="projectplus-project-fields-table__source"></colgroup><tr class="tab_bg_2"><th>' . __('Campo do Projeto', 'projectplus') . '</th><th>' . __('Preencher com origem', 'projectplus') . '</th></tr>';
foreach (ProjectFieldConfig::getTargets() as $field => $label) {
   echo '<tr class="tab_bg_1"><td>' . ConfigPage::escape($label) . '</td><td>';
   $fixed = ProjectFieldConfig::getFixedTargets();
   if (isset($fixed[$field])) {
      echo '<span class="badge bg-blue-lt projectplus-project-fields-table__fixed">' . ConfigPage::escape($fixed[$field]) . '</span>';
   } else {
      Dropdown::showFromArray('sources[' . $field . ']', $sources, ['value' => (string) ($configured[$field] ?? '')]);
   }
   echo '</td></tr>';
}
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save' value='1'>" . __('Salvar') . '</button></td></tr></table></form></div></section>';
ConfigPage::end(); Html::footer();
