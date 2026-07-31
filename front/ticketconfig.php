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

if (isset($_POST['toggle_category'])) {
   $entityId = (int) ($_POST['entities_id'] ?? -1);
   $categoryId = (int) ($_POST['itilcategories_id'] ?? 0);
   $isActive = (int) ($_POST['is_active'] ?? 0) === 1;
   if (TicketConfig::saveCategoryState($entityId, $categoryId, $isActive)) {
      Session::addMessageAfterRedirect(__('Ativação da categoria atualizada.', 'projectplus'));
   } else {
      Session::addMessageAfterRedirect(__('Categoria inválida ou sem permissão de configuração.', 'projectplus'), false, ERROR);
   }
   Html::redirect($_SERVER['PHP_SELF'] . '?page=' . $page . '&per_page=' . $perPage);
}

$entities = TicketConfig::getAvailableEntities();
$entityNames = [];
foreach ($entities as $entity) {
   $entityNames[(int) $entity['id']] = (string) ($entity['completename'] ?: $entity['name']);
}
$categories = TicketConfig::getAllAvailableCategories();
$states = TicketConfig::getAllCategoryStates();
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
   echo "<form method='get' action='" . Html::clean($_SERVER['PHP_SELF']) . "' class='projectplus-flow-pagination'><label for='projectplus-per-page'>" . ConfigPage::escape(__('Linhas por página', 'projectplus')) . "</label><select id='projectplus-per-page' name='per_page' class='form-select' onchange='this.form.submit()'>";
   foreach ([20, 50, 100] as $option) {
      echo "<option value='" . $option . "'" . ($option === $perPage ? ' selected' : '') . '>' . $option . '</option>';
   }
   echo "</select>" . Html::hidden('page', ['value' => 1]) . '</form>';
   echo "<div class='projectplus-flow-category-table'><table><thead><tr><th>" . ConfigPage::escape(__('ID', 'projectplus')) . '</th><th>' . ConfigPage::escape(__('Categoria ITIL', 'projectplus')) . '</th><th>' . ConfigPage::escape(__('Entidade', 'projectplus')) . '</th><th class="center">' . ConfigPage::escape(__('Ativo', 'projectplus')) . '</th></tr></thead><tbody>';
   foreach ($pageCategories as $category) {
      $categoryId = (int) $category['id'];
      $entityId = (int) $category['entities_id'];
      $state = $states[$entityId . ':' . $categoryId] ?? null;
      $isActive = $state !== null && (int) $state['is_active'] === 1;
      $name = (string) ($category['completename'] ?: $category['name']);
      $categoryEntity = $entityNames[$entityId] ?? __('Entidade', 'projectplus') . ' #' . $entityId;
      echo "<tr><td>" . $categoryId . '</td><td>' . ConfigPage::escape($name) . '</td><td>' . ConfigPage::escape($categoryEntity) . "</td><td class='center'><form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "' class='projectplus-flow-config__toggle-form'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('entities_id', ['value' => $entityId]) . Html::hidden('itilcategories_id', ['value' => $categoryId]) . Html::hidden('is_active', ['value' => $isActive ? 0 : 1]) . "<button type='submit' name='toggle_category' value='1' class='projectplus-switch" . ($isActive ? ' is-active' : '') . "' aria-label='" . ConfigPage::escape($isActive ? __('Desabilitar categoria', 'projectplus') : __('Habilitar categoria', 'projectplus')) . "'><i></i></button></form></td></tr>";
   }
   echo '</tbody></table></div>';
   $first = (($page - 1) * $perPage) + 1;
   $last = min($page * $perPage, $totalCategories);
   echo "<nav class='projectplus-flow-pagination__footer' aria-label='" . ConfigPage::escape(__('Paginação das categorias', 'projectplus')) . "'><span>" . ConfigPage::escape(sprintf(__('Exibindo %1$d a %2$d de %3$d categorias', 'projectplus'), $first, $last, $totalCategories)) . '</span><div>';
   if ($page > 1) {
      echo "<a class='btn btn-sm btn-secondary' href='" . Html::clean($_SERVER['PHP_SELF']) . '?page=' . ($page - 1) . '&per_page=' . $perPage . "'>" . ConfigPage::escape(__('Anterior', 'projectplus')) . '</a>';
   }
   echo "<span class='projectplus-flow-pagination__current'>" . ConfigPage::escape(sprintf(__('Página %1$d de %2$d', 'projectplus'), $page, $totalPages)) . '</span>';
   if ($page < $totalPages) {
      echo "<a class='btn btn-sm btn-secondary' href='" . Html::clean($_SERVER['PHP_SELF']) . '?page=' . ($page + 1) . '&per_page=' . $perPage . "'>" . ConfigPage::escape(__('Próxima', 'projectplus')) . '</a>';
   }
   echo '</div></nav>';
}

echo "<p class='projectplus-config-help'>" . ConfigPage::escape(__('O fluxo será iniciado somente para tickets cuja categoria esteja ativa nesta lista. O status nativo do ticket não será alterado.', 'projectplus')) . '</p></div></section>';

ConfigPage::end();
Html::footer();
