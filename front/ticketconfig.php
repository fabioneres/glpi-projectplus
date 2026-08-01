<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\TicketCategoryConfig;

Session::checkRight('config', UPDATE);

// The rows are persisted before Search builds its native query. This keeps
// inactive categories searchable and selectable by the native massive-action
// engine while preserving every existing activation choice.
TicketCategoryConfig::synchronizeAvailableCategories();

Html::header(__('Fluxo de solicitação de projeto', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
ConfigPage::begin('ticketflow');

echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-git-branch'></i> "
   . ConfigPage::escape(__('Fluxo de solicitação de projeto', 'projectplus'))
   . "</header><div class='projectplus-config-card__body'>";
echo "<p class='projectplus-config-help'>"
   . ConfigPage::escape(__('Use a pesquisa e as ações em massa nativas do GLPI para localizar e ativar as categorias ITIL que iniciam o fluxo de solicitações de projeto.', 'projectplus'))
   . '</p>';

// Search::show renders the GLPI native criteria builder, saved-search rules,
// table preferences, pagination and MassiveAction controls.
Search::show(TicketCategoryConfig::class);

echo "<p class='projectplus-config-help'>"
   . ConfigPage::escape(__('O fluxo será iniciado somente para tickets cuja categoria esteja ativa nesta lista. O status nativo do ticket não será alterado.', 'projectplus'))
   . '</p></div></section>';

ConfigPage::end();
Html::footer();
