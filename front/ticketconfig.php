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
// normal columns follow the personal/global view.  Ação is appended to that
// view and therefore remains fixed even when preferences or filters change.
$params = Search::manageParams(TicketCategoryConfig::class, $_GET);
$params['display_type'] = Search::HTML_OUTPUT;
$displayColumns = DisplayPreference::getForTypeUser(
   TicketCategoryConfig::class,
   (int) Session::getLoginUserID()
);
if ($displayColumns === []) {
   $displayColumns = [80, 2, 15, 16, 29, 30, 4];
}
$displayColumns = array_values(array_unique(array_map('intval', $displayColumns)));
$displayColumns = array_values(array_filter($displayColumns, static function (int $column): bool {
   return $column !== 31;
}));
$displayColumns[] = 31;
echo "<div class='search_page row'><div class='col search-container disable-overflow-y'>";
Search::showGenericSearch(TicketCategoryConfig::class, $params);
Search::showList(TicketCategoryConfig::class, $params, $displayColumns);
echo '</div></div>';

// GLPI refreshes native search results through ajax/search.php. That endpoint
// does not receive this page's forced-display list, so keep the structural
// action column stable after every partial refresh. Normal columns remain
// entirely controlled by the personal/global display preference.
$editUrl = json_encode(TicketCategoryConfig::getFormURL(true));
$actionLabel = json_encode(__('Ação', 'projectplus'));
$editLabel = json_encode(__('Editar'));
echo <<<HTML
<script>
(function () {
   const container = document.querySelector('.search-container');
   if (!container || container.dataset.projectplusFixedActionReady) {
      return;
   }
   container.dataset.projectplusFixedActionReady = '1';
   const editUrl = {$editUrl};
   const actionLabel = {$actionLabel};
   const editLabel = {$editLabel};

   function rowId(row) {
      const checkbox = row.querySelector('input[type="checkbox"][value]');
      if (checkbox) {
         const nameMatch = checkbox.name.match(/\[(\d+)\]$/);
         if (nameMatch) {
            return nameMatch[1];
         }
      }
      const cells = Array.from(row.querySelectorAll('td'));
      for (const cell of cells) {
         const value = cell.textContent.trim();
         if (/^\d+$/.test(value)) {
            return value;
         }
      }
      return '';
   }

   function syncTable(table) {
      const headerRow = table.querySelector('thead tr');
      const body = table.querySelector('tbody');
      if (!headerRow || !body) {
         return;
      }

      let actionHeader = Array.from(headerRow.querySelectorAll('th')).find(function (cell) {
         return cell.textContent.trim() === actionLabel;
      });
      let actionIndex = actionHeader
         ? Array.from(headerRow.children).indexOf(actionHeader)
         : -1;

      if (!actionHeader) {
         actionHeader = document.createElement('th');
         actionHeader.textContent = actionLabel;
         actionHeader.className = 'text-center';
         headerRow.appendChild(actionHeader);
      } else if (actionHeader !== headerRow.lastElementChild) {
         headerRow.appendChild(actionHeader);
      }

      body.querySelectorAll('tr').forEach(function (row) {
         const id = rowId(row);
         if (!id) {
            return;
         }
         let cell = actionIndex >= 0 ? row.children[actionIndex] : null;
         if (!cell) {
            cell = document.createElement('td');
         }
         cell.dataset.projectplusActionCell = '1';
         if (cell !== row.lastElementChild) {
            row.appendChild(cell);
         }
         let link = cell.querySelector('a[data-projectplus-edit]');
         if (!link) {
            link = document.createElement('a');
            link.dataset.projectplusEdit = '1';
            link.className = 'btn btn-sm btn-outline-secondary';
            link.textContent = editLabel;
            cell.replaceChildren(link);
         }
         link.href = editUrl + '?id=' + encodeURIComponent(id);
      });
   }

   function syncAll() {
      container.querySelectorAll('table').forEach(syncTable);
   }

   let scheduled = false;
   const observer = new MutationObserver(function () {
      if (scheduled) {
         return;
      }
      scheduled = true;
      window.requestAnimationFrame(function () {
         scheduled = false;
         syncAll();
      });
   });
   observer.observe(container, {childList: true, subtree: true});
   syncAll();
})();
</script>
HTML;

echo "<p class='projectplus-config-help'>"
   . ConfigPage::escape(__('Ativo define se a categoria inicia o Project+. Entidade participante define se seus responsáveis podem atuar no fluxo; Recursivo estende essa atuação às entidades filhas.', 'projectplus'))
   . '</p></div></section>';

ConfigPage::end();
Html::footer();
