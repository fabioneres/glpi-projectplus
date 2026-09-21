<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\FlowStageDescription;
use GlpiPlugin\Projectplus\TicketPhase;

Session::checkRight('config', UPDATE);

if (isset($_POST['save_flow_descriptions'])) {
   try {
      FlowStageDescription::save($_POST);
      Session::addMessageAfterRedirect(__('Etapas do fluxo atualizadas.', 'projectplus'));
   } catch (RuntimeException $exception) {
      Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   }
   Html::redirect($_SERVER['PHP_SELF']);
}

Html::header(__('Configuração das etapas do fluxo', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
ConfigPage::begin('overview');
echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-message-2'></i> "
   . ConfigPage::escape(__('Configuração das etapas do fluxo', 'projectplus'))
   . "</header><div class='projectplus-config-card__body'>";
echo '<p class="projectplus-config-help">' . ConfigPage::escape(__('Personalize o nome apresentado em Fase atual, escolha os estados internos que ativam cada nome e defina o acompanhamento público criado automaticamente ao entrar na fase. Um mesmo gatilho não pode pertencer a duas fases.', 'projectplus')) . '</p>';
echo "<form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo '<p class="projectplus-config-help"><i class="ti ti-info-circle"></i> ' . ConfigPage::escape(__('Variáveis disponíveis no acompanhamento: {fase}, {fase_anterior}, {chamado_id} e {justificativa}. Deixe vazio para não criar acompanhamento nessa fase.', 'projectplus')) . '</p>';
echo "<table class='tab_cadre_fixe'><thead><tr class='tab_bg_2'><th>" . __('Fase', 'projectplus') . '</th><th>' . __('Nome da fase', 'projectplus') . '</th><th>' . __('Quando ela é ativada', 'projectplus') . '</th><th>' . __('Acompanhamento automático', 'projectplus') . '</th></tr></thead><tbody>';
foreach (FlowStageDescription::getDisplayPhases() as $displayPhase => $definition) {
   echo "<tr class='tab_bg_1'><td><strong>" . ConfigPage::escape($definition['label']) . "</strong></td><td><input class='form-control' maxlength='255' name='phase_names[" . ConfigPage::escape($displayPhase) . "]' value='" . ConfigPage::escape(FlowStageDescription::getPhaseName($displayPhase)) . "'></td><td>";
   Dropdown::showFromArray(
      'phase_triggers[' . $displayPhase . ']',
      TicketPhase::getPhases(),
      ['multiple' => true, 'values' => FlowStageDescription::getPhaseTriggers($displayPhase), 'width' => '100%']
   );
   echo "</td><td><textarea class='form-control' name='phase_followups[" . ConfigPage::escape($displayPhase) . "]' rows='3'>" . ConfigPage::escape(FlowStageDescription::getPhaseFollowup($displayPhase)) . '</textarea></td></tr>';
}
echo '</tbody></table>';
echo '<h3 class="mt-4">' . ConfigPage::escape(__('Acompanhamento de eventos do fluxo', 'projectplus')) . '</h3>';
echo '<p class="projectplus-config-help">' . ConfigPage::escape(__('Alguns acontecimentos precisam de texto próprio porque a fase de destino, sozinha, não explica o que houve. É o caso da devolução do TIP: ela leva à mesma fase da primeira solicitação de preenchimento, mas só ela tem justificativa.', 'projectplus')) . '</p>';
echo "<table class='tab_cadre_fixe'><thead><tr class='tab_bg_2'><th>" . __('Evento', 'projectplus') . '</th><th>' . __('Acompanhamento automático', 'projectplus') . '</th></tr></thead><tbody>';
foreach (FlowStageDescription::getFlowEvents() as $event => $definition) {
   echo "<tr class='tab_bg_1'><td><strong>" . ConfigPage::escape($definition['label']) . "</strong></td>"
      . "<td><textarea class='form-control' name='event_followups[" . ConfigPage::escape($event) . "]' rows='3'>"
      . ConfigPage::escape(FlowStageDescription::getEventFollowup($event)) . '</textarea></td></tr>';
}
echo '</tbody></table>';
echo '<h3 class="mt-4">' . ConfigPage::escape(__('Descrições de orientação', 'projectplus')) . '</h3>';
echo '<p class="projectplus-config-help">' . ConfigPage::escape(__('Defina o texto exibido quando a etapa estiver disponível e o aviso apresentado enquanto ela depender da etapa anterior. Deixe um campo vazio para ocultar seu texto.', 'projectplus')) . '</p>';
echo '<p class="projectplus-config-help"><i class="ti ti-info-circle"></i> ' . ConfigPage::escape(__('Na mensagem de encaminhamento também podem ser usadas as variáveis {chamado_id}, {entidade} e {fase}.', 'projectplus')) . '</p>';
echo "<table class='tab_cadre_fixe'><thead><tr class='tab_bg_2'><th>" . __('Etapa', 'projectplus') . '</th><th>' . __('Quando disponível', 'projectplus') . '</th><th>' . __('Quando pendente', 'projectplus') . '</th></tr></thead><tbody>';
foreach (FlowStageDescription::getStages() as $stage => $definition) {
   echo "<tr class='tab_bg_1'><td><strong>" . ConfigPage::escape($definition['label']) . "</strong></td><td><textarea class='form-control' name='descriptions[" . ConfigPage::escape($stage) . "][active]' rows='3'>" . ConfigPage::escape(FlowStageDescription::get($stage, 'active')) . "</textarea></td><td><textarea class='form-control' name='descriptions[" . ConfigPage::escape($stage) . "][pending]' rows='3'>" . ConfigPage::escape(FlowStageDescription::get($stage, 'pending')) . '</textarea></td></tr>';
}
echo "</tbody></table><div class='center mt-3'><button class='btn btn-primary' type='submit' name='save_flow_descriptions' value='1'><i class='ti ti-device-floppy'></i> " . ConfigPage::escape(__('Salvar', 'projectplus')) . '</button></div></form>';
echo '</div></section>';
ConfigPage::end();
Html::footer();
