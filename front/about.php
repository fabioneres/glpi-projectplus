<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;

Session::checkRight('config', READ);
Html::header(__('Project+', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

ConfigPage::begin('about');
echo "<section class='projectplus-tip projectplus-about'>";
echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-route'></i></div><div><span class='projectplus-tip__eyebrow'>" . ConfigPage::escape(__('Guia operacional', 'projectplus')) . '</span><h2>' . ConfigPage::escape(__('Como funciona o Project+', 'projectplus')) . '</h2><p>' . ConfigPage::escape(__('Visão resumida do ciclo da solicitação de projeto, dos termos e do encerramento documental.', 'projectplus')) . '</p></div></header>';
echo "<div class='projectplus-tip__card projectplus-about__body'><div class='projectplus-tip__section-title'><i class='ti ti-list-check'></i><div><h3>" . ConfigPage::escape(__('Fluxo passo a passo', 'projectplus')) . '</h3><p>' . ConfigPage::escape(__('As permissões do perfil e a entidade do usuário definem quais ações estarão disponíveis em cada etapa.', 'projectplus')) . "</p></div></div><ol class='projectplus-about__steps'>";

$steps = [
   [__('1. Configurar o fluxo', 'projectplus'), __('O administrador escolhe, por entidade, a categoria ITIL que identifica tickets que devem seguir o fluxo de solicitação de projeto.', 'projectplus'), 'ti ti-settings'],
   [__('2. Receber e classificar a solicitação', 'projectplus'), __('Ao receber a categoria configurada, o ticket passa a exibir a área Project+. O gestor analisa a necessidade e solicita o preenchimento do TIP quando necessário.', 'projectplus'), 'ti ti-ticket'],
   [__('3. Preencher e validar os TIPs', 'projectplus'), __('O fluxo registra o TIP - Termo de Intenção de Projeto e o TIP - Detalhamento Técnico. O gestor visualiza o documento, aceita para continuidade ou recusa registrando uma justificativa.', 'projectplus'), 'ti ti-file-text'],
   [__('4. Analisar, priorizar e autorizar', 'projectplus'), __('A necessidade pode ser analisada no portfólio e receber ranking. Após a autorização, o fluxo libera a abertura do projeto e o TAP.', 'projectplus'), 'ti ti-list-numbers'],
   [__('5. Abrir o projeto e formalizar o TAP', 'projectplus'), __('O gerente cria o projeto, preenche e gera o TAP. O documento é enviado ao solicitante para leitura e aceite auditável.', 'projectplus'), 'ti ti-file-description'],
   [__('6. Executar e acompanhar', 'projectplus'), __('O projeto é acompanhado no módulo de Projetos. Solicitante, ponto focal e gestores podem utilizar Interações conforme as permissões do perfil.', 'projectplus'), 'ti ti-messages'],
   [__('7. Entregar com TEP e TA', 'projectplus'), __('Ao concluir a execução, o responsável gera o TEP e envia para aceite. Depois, o TA registra a confirmação documental final do projeto.', 'projectplus'), 'ti ti-file-check'],
   [__('8. Encerrar e arquivar', 'projectplus'), __('Com os termos concluídos, o projeto é solucionado e permanece com histórico, interações e documentos disponíveis para consulta.', 'projectplus'), 'ti ti-archive'],
];

foreach ($steps as [$title, $description, $icon]) {
   echo "<li><span class='projectplus-about__step-icon'><i class='" . ConfigPage::escape($icon) . "'></i></span><div><h4>" . ConfigPage::escape($title) . '</h4><p>' . ConfigPage::escape($description) . '</p></div></li>';
}
echo '</ol></div></section>';
ConfigPage::end();
Html::footer();
