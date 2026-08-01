<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\TicketConfig;

Session::checkRight('config', UPDATE);

$perPage = (int) ($_REQUEST['per_page'] ?? 20);
if (!in_array($perPage, [20, 50, 100], true)) {
   $perPage = 20;
}
$page = max(1, (int) ($_REQUEST['page'] ?? 1));
$filterCategory = trim((string) ($_REQUEST['filter_category'] ?? ''));
$filterEntityId = max(0, (int) ($_REQUEST['filter_entity'] ?? 0));
$filterActive = (string) ($_REQUEST['filter_active'] ?? '');
if (!in_array($filterActive, ['', '0', '1'], true)) {
   $filterActive = '';
}
$baseUrl = (string) $_SERVER['PHP_SELF'];
$filterParameters = [
   'filter_category' => $filterCategory,
   'filter_entity' => $filterEntityId,
   'filter_active' => $filterActive,
];
$buildUrl = static function (array $parameters) use ($baseUrl): string {
   return $baseUrl . '?' . http_build_query($parameters);
};

if (isset($_POST['toggle_category'])) {
   $entityId = (int) ($_POST['entities_id'] ?? -1);
   $categoryId = (int) ($_POST['itilcategories_id'] ?? 0);
   $isActive = (int) ($_POST['is_active'] ?? 0) === 1;
   if (TicketConfig::saveCategoryState($entityId, $categoryId, $isActive)) {
      Session::addMessageAfterRedirect(__('Ativação da categoria atualizada.', 'projectplus'));
   } else {
      Session::addMessageAfterRedirect(__('Categoria inválida ou sem permissão de configuração.', 'projectplus'), false, ERROR);
   }
   Html::redirect($buildUrl(array_merge($filterParameters, ['page' => $page, 'per_page' => $perPage])));
}

if (isset($_POST['mass_update_categories'])) {
   $action = (string) ($_POST['mass_action'] ?? '');
   $keys = $_POST['category_keys'] ?? [];
   $isActive = $action === 'activate';
   $updated = 0;
   if (!is_array($keys) || $keys === [] || !in_array($action, ['activate', 'deactivate'], true)) {
      Session::addMessageAfterRedirect(__('Selecione uma ação válida e ao menos uma categoria.', 'projectplus'), false, ERROR);
   } else {
      foreach (array_unique($keys) as $key) {
         $parts = explode(':', (string) $key, 2);
         if (count($parts) !== 2) {
            continue;
         }
         if (TicketConfig::saveCategoryState((int) $parts[0], (int) $parts[1], $isActive)) {
            ++$updated;
         }
      }
      Session::addMessageAfterRedirect(sprintf(__('Ação aplicada a %d categoria(s).', 'projectplus'), $updated));
   }
   Html::redirect($buildUrl(array_merge($filterParameters, ['page' => $page, 'per_page' => $perPage])));
}

$entities = TicketConfig::getAvailableEntities();
$entityNames = [];
foreach ($entities as $entity) {
   $entityNames[(int) $entity['id']] = (string) ($entity['completename'] ?: $entity['name']);
}
$categories = TicketConfig::getAllAvailableCategories();
$states = TicketConfig::getAllCategoryStates();
$categories = array_values(array_filter($categories, static function (array $category) use ($filterCategory, $filterEntityId, $filterActive, $states): bool {
   $categoryId = (int) $category['id'];
   $entityId = (int) $category['entities_id'];
   $name = (string) ($category['completename'] ?: $category['name']);
   $isActive = isset($states[$entityId . ':' . $categoryId])
      && (int) $states[$entityId . ':' . $categoryId]['is_active'] === 1;

   if ($filterCategory !== '' && stripos($name, $filterCategory) === false) {
      return false;
   }
   if ($filterEntityId > 0 && $entityId !== $filterEntityId) {
      return false;
   }
   if ($filterActive !== '' && $isActive !== ($filterActive === '1')) {
      return false;
   }
   return true;
}));
$totalCategories = count($categories);
$totalPages = max(1, (int) ceil($totalCategories / $perPage));
$page = min($page, $totalPages);
$pageCategories = array_slice($categories, ($page - 1) * $perPage, $perPage);

Html::header(__('Fluxo de solicitação de projeto', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
ConfigPage::begin('ticketflow');

echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-git-branch'></i> " . ConfigPage::escape(__('Fluxo de solicitação de projeto', 'projectplus')) . '</header><div class="projectplus-config-card__body">';
echo "<p class='projectplus-config-help'>" . ConfigPage::escape(__('Ative as categorias ITIL que devem iniciar o fluxo de solicitação de projetos. Cada linha informa a entidade proprietária da categoria.', 'projectplus')) . '</p>';

if ($categories === []) {
   echo "<div class='alert alert-info'>" . ConfigPage::escape(__('Não há categorias ITIL disponíveis nas entidades acessíveis.', 'projectplus')) . '</div>';
} else {
   echo "<form method='get' action='" . ConfigPage::escape($baseUrl) . "' class='projectplus-native-search'>";
   echo "<div class='projectplus-native-search__title'><i class='ti ti-search'></i> Filtros de pesquisa</div>";
   echo "<div class='projectplus-native-search__fields'><label><span>Categoria ITIL</span><input class='form-control' type='search' name='filter_category' value='" . ConfigPage::escape($filterCategory) . "' placeholder='contém'></label>";
   echo "<label><span>Entidade</span><select class='form-select' name='filter_entity'><option value='0'>Todas</option>";
   foreach ($entityNames as $entityId => $entityName) {
      echo "<option value='" . (int) $entityId . "'" . ((int) $entityId === $filterEntityId ? ' selected' : '') . '>' . ConfigPage::escape($entityName) . '</option>';
   }
   echo "</select></label><label><span>Ativo</span><select class='form-select' name='filter_active'><option value=''>Todos</option><option value='1'" . ($filterActive === '1' ? ' selected' : '') . ">Sim</option><option value='0'" . ($filterActive === '0' ? ' selected' : '') . ">Não</option></select></label></div>";
   echo Html::hidden('per_page', ['value' => $perPage]) . Html::hidden('page', ['value' => 1]);
   echo "<div class='projectplus-native-search__actions'><button class='btn btn-warning' type='submit'><i class='ti ti-search'></i> Pesquisar</button><a class='btn btn-outline-secondary' href='" . ConfigPage::escape($baseUrl) . "'><i class='ti ti-x'></i> Limpar</a></div></form>";
   echo "<form method='get' action='" . Html::clean($_SERVER['PHP_SELF']) . "' class='projectplus-flow-pagination'><label for='projectplus-per-page'>" . ConfigPage::escape(__('Linhas por página', 'projectplus')) . "</label><select id='projectplus-per-page' name='per_page' class='form-select' onchange='this.form.submit()'>";
   foreach ([20, 50, 100] as $option) {
      echo "<option value='" . $option . "'" . ($option === $perPage ? ' selected' : '') . '>' . $option . '</option>';
   }
   echo "</select>" . Html::hidden('page', ['value' => 1]) . Html::hidden('filter_category', ['value' => $filterCategory]) . Html::hidden('filter_entity', ['value' => $filterEntityId]) . Html::hidden('filter_active', ['value' => $filterActive]) . '</form>';
   echo "<form id='projectplus-mass-action-form' method='post' action='" . ConfigPage::escape($baseUrl) . "' class='projectplus-flow-mass-actions'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('page', ['value' => $page]) . Html::hidden('per_page', ['value' => $perPage]) . Html::hidden('filter_category', ['value' => $filterCategory]) . Html::hidden('filter_entity', ['value' => $filterEntityId]) . Html::hidden('filter_active', ['value' => $filterActive]) . "<label for='projectplus-mass-action'>Ações</label><select id='projectplus-mass-action' class='form-select' name='mass_action'><option value=''>-----</option><option value='activate'>Ativar</option><option value='deactivate'>Desativar</option></select><button class='btn btn-warning' type='submit' name='mass_update_categories' value='1'><i class='ti ti-check'></i> Aplicar</button></form>";
   echo "<div class='projectplus-flow-category-table'><table><thead><tr><th>" . ConfigPage::escape(__('ID', 'projectplus')) . '</th><th>' . ConfigPage::escape(__('Categoria ITIL', 'projectplus')) . '</th><th>' . ConfigPage::escape(__('Entidade', 'projectplus')) . '</th><th class="center">' . ConfigPage::escape(__('Ativo', 'projectplus')) . '</th></tr></thead><tbody>';
   foreach ($pageCategories as $category) {
      $categoryId = (int) $category['id'];
      $entityId = (int) $category['entities_id'];
      $state = $states[$entityId . ':' . $categoryId] ?? null;
      $isActive = $state !== null && (int) $state['is_active'] === 1;
      $name = (string) ($category['completename'] ?: $category['name']);
      $categoryEntity = $entityNames[$entityId] ?? __('Entidade', 'projectplus') . ' #' . $entityId;
      echo "<tr><td class='center'><input class='projectplus-flow-mass-checkbox' type='checkbox' name='category_keys[]' value='" . $entityId . ':' . $categoryId . "' form='projectplus-mass-action-form' aria-label='Selecionar categoria'></td><td>" . $categoryId . '</td><td>' . ConfigPage::escape($name) . '</td><td>' . ConfigPage::escape($categoryEntity) . "</td><td class='center'><form method='post' action='" . ConfigPage::escape($baseUrl) . "' class='projectplus-flow-config__toggle-form'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('entities_id', ['value' => $entityId]) . Html::hidden('itilcategories_id', ['value' => $categoryId]) . Html::hidden('is_active', ['value' => $isActive ? 0 : 1]) . Html::hidden('page', ['value' => $page]) . Html::hidden('per_page', ['value' => $perPage]) . Html::hidden('filter_category', ['value' => $filterCategory]) . Html::hidden('filter_entity', ['value' => $filterEntityId]) . Html::hidden('filter_active', ['value' => $filterActive]) . "<button type='submit' name='toggle_category' value='1' class='projectplus-switch" . ($isActive ? ' is-active' : '') . "' aria-label='" . ConfigPage::escape($isActive ? __('Desabilitar categoria', 'projectplus') : __('Habilitar categoria', 'projectplus')) . "'><i></i></button></form></td></tr>";
   }
   echo '</tbody></table></div>';
   echo "<script>(function(){var table=document.querySelector('.projectplus-flow-category-table table');if(!table){return;}var head=table.querySelector('thead tr');if(head&&!head.querySelector('.projectplus-flow-mass-all')){head.insertAdjacentHTML('afterbegin',\"<th class='center'><input class='projectplus-flow-mass-all' type='checkbox' aria-label='Selecionar todas as categorias da página'></th>\");}var all=table.querySelector('.projectplus-flow-mass-all');if(all){all.addEventListener('change',function(){table.querySelectorAll('.projectplus-flow-mass-checkbox').forEach(function(box){box.checked=all.checked;});});}})();</script>";
   $first = (($page - 1) * $perPage) + 1;
   $last = min($page * $perPage, $totalCategories);
   echo "<nav class='projectplus-flow-pagination__footer' aria-label='" . ConfigPage::escape(__('Paginação das categorias', 'projectplus')) . "'><span>" . ConfigPage::escape(sprintf(__('Exibindo %1$d a %2$d de %3$d categorias', 'projectplus'), $first, $last, $totalCategories)) . '</span><div>';
   if ($page > 1) {
      echo "<a class='btn btn-sm btn-secondary' href='" . ConfigPage::escape($buildUrl(array_merge($filterParameters, ['page' => $page - 1, 'per_page' => $perPage]))) . "'>" . ConfigPage::escape(__('Anterior', 'projectplus')) . '</a>';
   }
   echo "<span class='projectplus-flow-pagination__current'>" . ConfigPage::escape(sprintf(__('Página %1$d de %2$d', 'projectplus'), $page, $totalPages)) . '</span>';
   if ($page < $totalPages) {
      echo "<a class='btn btn-sm btn-secondary' href='" . ConfigPage::escape($buildUrl(array_merge($filterParameters, ['page' => $page + 1, 'per_page' => $perPage]))) . "'>" . ConfigPage::escape(__('Próxima', 'projectplus')) . '</a>';
   }
   echo '</div></nav>';
   echo "<form method='get' action='" . ConfigPage::escape($baseUrl) . "' class='projectplus-flow-pagination projectplus-flow-pagination--bottom'><label for='projectplus-per-page-bottom'>Linhas por página</label><select id='projectplus-per-page-bottom' name='per_page' class='form-select' onchange='this.form.submit()'>";
   foreach ([20, 50, 100] as $option) {
      echo "<option value='" . $option . "'" . ($option === $perPage ? ' selected' : '') . '>' . $option . '</option>';
   }
   echo "</select>" . Html::hidden('page', ['value' => 1]) . Html::hidden('filter_category', ['value' => $filterCategory]) . Html::hidden('filter_entity', ['value' => $filterEntityId]) . Html::hidden('filter_active', ['value' => $filterActive]) . '</form>';
}

echo "<p class='projectplus-config-help'>" . ConfigPage::escape(__('O fluxo será iniciado somente para tickets cuja categoria esteja ativa nesta lista. O status nativo do ticket não será alterado.', 'projectplus')) . '</p></div></section>';

ConfigPage::end();
Html::footer();
