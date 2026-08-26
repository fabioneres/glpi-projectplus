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

// Keep the native GLPI criteria builder, pagination and massive actions. The
// forced display list only pins the configuration columns (including Ação), so
// a user preference cannot hide the edit control required to manage a row.
$params = Search::manageParams(TicketCategoryConfig::class, $_GET);
$params['display_type'] = Search::HTML_OUTPUT;
echo "<div class='search_page row'><div class='col search-container'>";
Search::showGenericSearch(TicketCategoryConfig::class, $params);
Search::showList(TicketCategoryConfig::class, $params, [1, 80, 2, 15, 16, 30, 4, 31]);
echo '</div></div>';

// GLPI does not select a value for virtual search columns. Use the column
// reserved above to render the row action after the native table is built.
// The form still checks the configuration right and the record ID server-side.
$editUrl = TicketCategoryConfig::getFormURL(true);
echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
   document.querySelectorAll('.search-container table').forEach(function (table) {
      const headerCells = Array.from(table.querySelectorAll('thead th'));
      const idIndex = headerCells.findIndex(function (cell) {
         return cell.textContent.trim().indexOf('ID') === 0;
      });
      const actionIndex = headerCells.findIndex(function (cell) {
         return cell.textContent.trim() === 'Ação';
      });
      if (idIndex < 0 || actionIndex < 0) {
         return;
      }
      table.querySelectorAll('tbody tr').forEach(function (row) {
         const cells = row.querySelectorAll('td');
         const id = cells[idIndex] ? cells[idIndex].textContent.trim() : '';
         const actionCell = cells[actionIndex];
         if (!actionCell || !/^\\d+$/.test(id)) {
            return;
         }
         const link = document.createElement('a');
         link.className = 'btn btn-sm btn-outline-secondary';
         link.textContent = 'Editar';
         link.href = '{$editUrl}?id=' + encodeURIComponent(id);
         actionCell.replaceChildren(link);
      });
   });
});
</script>
HTML;

echo "<p class='projectplus-config-help'>"
   . ConfigPage::escape(__('O fluxo será iniciado somente para tickets cuja categoria esteja ativa nesta lista. O status nativo do ticket não será alterado.', 'projectplus'))
   . '</p></div></section>';

ConfigPage::end();
Html::footer();
