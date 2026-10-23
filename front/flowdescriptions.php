<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\FlowStageDescription;
use GlpiPlugin\Projectplus\TicketPhase;
use GlpiPlugin\Projectplus\FrontUrl;

Session::checkRight('config', UPDATE);

if (isset($_POST['save_flow_descriptions'])) {
   try {
      FlowStageDescription::save($_POST);
      Session::addMessageAfterRedirect(__('Etapas do fluxo atualizadas.', 'projectplus'));
   } catch (RuntimeException $exception) {
      Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
   }
   Html::redirect(FrontUrl::to(basename(__FILE__)));
}

/** Cabecalho de secao, no mesmo padrao das demais telas de configuracao. */
$sectionTitle = static function (string $icon, string $title, string $help): void {
   echo "<div class='projectplus-tip__section-title projectplus-flowcfg__section'><i class='" . ConfigPage::escape($icon) . "'></i><div><h3>"
      . ConfigPage::escape($title) . '</h3><p>' . ConfigPage::escape($help) . '</p></div></div>';
};

/** Trilho esquerdo do cartao: ordem, nome da etapa e a chave interna. */
$rail = static function (string $label, string $key, ?int $index = null): void {
   echo "<div class='projectplus-flowcfg__rail'>";
   if ($index !== null) {
      echo "<span class='projectplus-flowcfg__num'>" . ConfigPage::escape(str_pad((string) $index, 2, '0', STR_PAD_LEFT)) . '</span>';
   }
   echo '<strong>' . ConfigPage::escape($label) . "</strong><code title='" . ConfigPage::escape(__('Identificador interno', 'projectplus')) . "'>"
      . ConfigPage::escape($key) . '</code></div>';
};

/** Campo do cartao, reaproveitando a tipografia de formulario do plugin. */
$field = static function (string $label, string $help, string $control, string $modifier = ''): void {
   echo "<div class='projectplus-tip__field" . ($modifier !== '' ? ' ' . ConfigPage::escape($modifier) : '') . "'><label>"
      . ConfigPage::escape($label) . '</label><small>' . ConfigPage::escape($help) . '</small>' . $control . '</div>';
};

Html::header(__('Configuração das etapas do fluxo', 'projectplus'), FrontUrl::to(basename(__FILE__)), 'config', 'plugins');
ConfigPage::begin('overview');

echo "<section class='projectplus-tip projectplus-flowcfg'>";
echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-route'></i></div><div><span class='projectplus-tip__eyebrow'>"
   . ConfigPage::escape(__('Configuração do Project+', 'projectplus')) . '</span><h2>'
   . ConfigPage::escape(__('Etapas do fluxo', 'projectplus')) . '</h2><p>'
   . ConfigPage::escape(__('Defina o nome que aparece em Fase atual, escolha os estados internos que ativam cada nome e escreva os textos que o plugin publica sozinho no chamado.', 'projectplus'))
   . '</p></div></header>';

echo "<form method='post' action='" . htmlescape(FrontUrl::to(basename(__FILE__))) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='projectplus-tip__card projectplus-flowcfg__body'>";

// Legenda das variaveis: um administrador precisa dela a mao enquanto escreve
// os textos, e ela se perdia no meio de um paragrafo corrido.
$variables = [
   '{fase}' => __('nome da fase em que a solicitação entrou', 'projectplus'),
   '{fase_anterior}' => __('nome da fase de onde ela saiu', 'projectplus'),
   '{chamado_id}' => __('número do chamado', 'projectplus'),
   '{justificativa}' => __('texto escrito por quem executou a ação', 'projectplus'),
   '{entidade}' => __('entidade do chamado, apenas nos textos de orientação', 'projectplus'),
];
echo "<aside class='projectplus-flowcfg__vars'><span class='projectplus-flowcfg__vars-title'><i class='ti ti-braces'></i> "
   . ConfigPage::escape(__('Variáveis que podem ser usadas nos textos', 'projectplus')) . '</span><ul>';
foreach ($variables as $token => $meaning) {
   echo '<li><code>' . ConfigPage::escape($token) . '</code><span>' . ConfigPage::escape($meaning) . '</span></li>';
}
echo "</ul><p><i class='ti ti-info-circle'></i> " . ConfigPage::escape(__('Deixe um campo vazio para o plugin não publicar nada naquele momento.', 'projectplus')) . '</p></aside>';

$sectionTitle(
   'ti ti-list-check',
   __('Fases da solicitação', 'projectplus'),
   __('Cada fase tem um nome visível ao usuário, os estados internos que a ativam e o acompanhamento publicado ao entrar nela. Um mesmo gatilho não pode pertencer a duas fases.', 'projectplus')
);
echo "<div class='projectplus-flowcfg__list'>";
$index = 0;
foreach (FlowStageDescription::getDisplayPhases() as $displayPhase => $definition) {
   $index++;
   $escapado = ConfigPage::escape($displayPhase);
   echo "<article class='projectplus-flowcfg__item'>";
   $rail((string) $definition['label'], $displayPhase, $index);
   echo "<div class='projectplus-flowcfg__fields'>";
   $field(
      __('Nome da fase', 'projectplus'),
      __('Apresentado em Fase atual e no histórico.', 'projectplus'),
      "<input class='form-control' maxlength='255' name='phase_names[{$escapado}]' value='"
         . ConfigPage::escape(FlowStageDescription::getPhaseName($displayPhase)) . "'>"
   );
   ob_start();
   Dropdown::showFromArray(
      'phase_triggers[' . $displayPhase . ']',
      TicketPhase::getPhases(),
      ['multiple' => true, 'values' => FlowStageDescription::getPhaseTriggers($displayPhase), 'width' => '100%']
   );
   $field(__('Quando ela é ativada', 'projectplus'), __('Estados internos que exibem este nome.', 'projectplus'), (string) ob_get_clean());
   $field(
      __('Acompanhamento automático', 'projectplus'),
      __('Publicado no chamado assim que a solicitação entra nesta fase.', 'projectplus'),
      "<textarea class='form-control' name='phase_followups[{$escapado}]' rows='3'>"
         . ConfigPage::escape(FlowStageDescription::getPhaseFollowup($displayPhase)) . '</textarea>',
      'projectplus-tip__field--wide'
   );
   echo '</div></article>';
}
echo '</div>';

$sectionTitle(
   'ti ti-arrow-back-up',
   __('Acompanhamento de eventos do fluxo', 'projectplus'),
   __('Alguns acontecimentos precisam de texto próprio porque a fase de destino, sozinha, não explica o que houve. É o caso da devolução do TIP: ela leva à mesma fase da primeira solicitação de preenchimento, mas só ela tem justificativa.', 'projectplus')
);
echo "<div class='projectplus-flowcfg__list'>";
foreach (FlowStageDescription::getFlowEvents() as $event => $definition) {
   $escapado = ConfigPage::escape($event);
   echo "<article class='projectplus-flowcfg__item'>";
   $rail((string) $definition['label'], $event);
   echo "<div class='projectplus-flowcfg__fields'>";
   $field(
      __('Acompanhamento automático', 'projectplus'),
      __('Publicado no chamado quando o evento acontece.', 'projectplus'),
      "<textarea class='form-control' name='event_followups[{$escapado}]' rows='3'>"
         . ConfigPage::escape(FlowStageDescription::getEventFollowup($event)) . '</textarea>',
      'projectplus-tip__field--wide'
   );
   echo '</div></article>';
}
echo '</div>';

$sectionTitle(
   'ti ti-help-circle',
   __('Textos de orientação das etapas', 'projectplus'),
   __('O que o usuário lê em cada etapa: a instrução quando ela está liberada, o aviso enquanto depende da etapa anterior e, onde existir, o aviso de uma ação irreversível ou de uma recusa. Cada etapa mostra só os textos que fazem sentido para ela.', 'projectplus')
);
echo "<div class='projectplus-flowcfg__list'>";
$stateLabels = FlowStageDescription::getStateLabels();
foreach (FlowStageDescription::getStages() as $stage => $definition) {
   $escapado = ConfigPage::escape($stage);
   // Os campos saem da propria definicao da etapa, na ordem de getStateLabels:
   // 'warning' e 'blocked' so existem onde a etapa tem o que declarar, e uma
   // coluna fixa para eles ficava quase toda vazia.
   $states = array_keys(array_diff_key($definition, ['label' => null]));
   usort($states, static function (string $a, string $b) use ($stateLabels): int {
      $ordem = array_keys($stateLabels);
      return array_search($a, $ordem, true) <=> array_search($b, $ordem, true);
   });
   $destaque = array_intersect(['warning', 'blocked'], $states) !== [];
   echo "<article class='projectplus-flowcfg__item" . ($destaque ? ' projectplus-flowcfg__item--warning' : '') . "'>";
   $rail((string) $definition['label'], $stage);
   echo "<div class='projectplus-flowcfg__fields'>";
   foreach ($states as $state) {
      $rotulo = $stateLabels[$state][0] ?? $state;
      $ajuda = $stateLabels[$state][1] ?? '';
      $modificador = in_array($state, ['warning', 'blocked'], true)
         ? 'projectplus-tip__field--wide projectplus-flowcfg__field--warning'
         : '';
      $field(
         $rotulo,
         $ajuda,
         "<textarea class='form-control' name='descriptions[{$escapado}][" . ConfigPage::escape($state) . "]' rows='3'>"
            . ConfigPage::escape(FlowStageDescription::get($stage, $state)) . '</textarea>',
         $modificador
      );
   }
   echo '</div></article>';
}
echo '</div></div>';

echo "<footer class='projectplus-flowcfg__actions'><span><i class='ti ti-info-circle'></i> "
   . ConfigPage::escape(__('As alterações valem para todas as entidades e passam a valer nos próximos acompanhamentos publicados.', 'projectplus'))
   . "</span><button class='btn btn-primary' type='submit' name='save_flow_descriptions' value='1'><i class='ti ti-device-floppy'></i> "
   . ConfigPage::escape(__('Salvar', 'projectplus')) . '</button></footer>';
echo '</form></section>';

ConfigPage::end();
Html::footer();
