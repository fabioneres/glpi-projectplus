<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\DocumentField;
use GlpiPlugin\Projectplus\TipConfig;
use GlpiPlugin\Projectplus\FrontUrl;

Session::checkRight('config', UPDATE);

$entityId = (int) ($_REQUEST['entities_id'] ?? Session::getActiveEntity());
$documentType = projectplus_param($_REQUEST, 'document_type', 'tap');
if (!in_array($documentType, ['tap', 'tep', 'ta'], true)) {
   $documentType = 'tap';
}
if ($entityId < 0 || !Session::haveAccessToEntity($entityId)) {
   Html::displayErrorAndDie(__('Entidade inválida ou sem permissão de configuração.', 'projectplus'));
}

if (isset($_POST['save_document_config'])) {
   if (TipConfig::saveForEntity($entityId, $documentType, $_POST)) {
      Session::addMessageAfterRedirect(htmlescape(sprintf(__('Modelo %s salvo.', 'projectplus'), strtoupper($documentType))));
   } else {
      Session::addMessageAfterRedirect(__('Informe o título do modelo.', 'projectplus'), false, ERROR);
   }
   Html::redirect(FrontUrl::to(basename(__FILE__)) . '?entities_id=' . $entityId . '&document_type=' . rawurlencode($documentType));
}

$config = TipConfig::getForEntity($entityId, $documentType);

Html::header(
   sprintf(__('Modelo %s', 'projectplus'), strtoupper($documentType)),
   FrontUrl::to(basename(__FILE__)),
   'config',
   'plugins'
);
ConfigPage::begin($documentType . 'model');

echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-list-details'></i> "
   . ConfigPage::escape(sprintf(__('Campos do modelo %s', 'projectplus'), strtoupper($documentType)))
   . "</header><div class='projectplus-config-card__body'>";

echo "<form method='post' action='" . htmlescape(FrontUrl::to(basename(__FILE__))) . "'>"
   . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()])
   . Html::hidden('entities_id', ['value' => $entityId])
   . Html::hidden('document_type', ['value' => $documentType]);
echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th colspan='2'>"
   . ConfigPage::escape(sprintf(__('Disponibilização do %s', 'projectplus'), strtoupper($documentType))) . '</th></tr>';
echo "<tr class='tab_bg_1'><td>" . ConfigPage::escape(sprintf(__('Habilitar modelo %s', 'projectplus'), strtoupper($documentType))) . '</td><td>';
Dropdown::showYesNo('is_active', (int) $config['is_active']);
echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Título', 'projectplus') . "</td><td><input class='form-control' type='text' required name='title' value='"
   . ConfigPage::escape((string) $config['title']) . "'></td></tr>";
echo "<tr class='tab_bg_1'><td>" . __('Introdução', 'projectplus') . "</td><td><textarea class='form-control' name='introduction' rows='4'>"
   . ConfigPage::escape((string) $config['introduction']) . '</textarea></td></tr>';
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_document_config' value='1'>"
   . __('Salvar', 'projectplus') . '</button></td></tr></table></form>';
echo "<p class='projectplus-config-help'>"
   . ConfigPage::escape(__('Selecione os campos e use as ações em massa nativas do GLPI para alterar obrigatoriedade, ativação ou exclusão.', 'projectplus'))
   . '</p>';
echo "<p><a class='btn btn-primary' href='documentfield.form.php?entities_id=" . $entityId
   . '&document_type=' . rawurlencode($documentType) . "'>" . __('Adicionar campo', 'projectplus') . '</a></p>';

// Entity and document type are fixed internal constraints. They ensure that
// native search and massive actions never mix fields from another document.
$_GET['criteria'] = is_array($_GET['criteria'] ?? null) ? $_GET['criteria'] : [];
// The selected tab owns the document type. Keep it as a list constraint,
// not as a redundant criterion displayed to the administrator.
$_GET['criteria'] = array_values(array_filter($_GET['criteria'], static function (array $criterion): bool {
   return (int) ($criterion['field'] ?? 0) !== 8;
}));
$_GET['criteria'][] = [
   'link' => 'AND',
   'field' => 80,
   'searchtype' => 'equals',
   'value' => $entityId,
];
$searchParams = Search::manageParams(DocumentField::class, $_GET);
$searchParams['display_type'] = Search::HTML_OUTPUT;
$listInput = $_GET;
$listInput['criteria'][] = [
   'link' => 'AND',
   'field' => 8,
   'searchtype' => 'equals',
   'value' => $documentType,
];
$listParams = Search::manageParams(DocumentField::class, $listInput);
$listParams['display_type'] = Search::HTML_OUTPUT;
echo "<div class='search_page row'><div class='col search-container'>";
Search::showGenericSearch(DocumentField::class, $searchParams);
Search::showList(DocumentField::class, $listParams, [2, 3, 4, 5, 6, 9, 7]);
echo '</div></div>';

$editUrl = 'documentfield.form.php?entities_id=' . $entityId
   . '&document_type=' . rawurlencode($documentType) . '&id=';
$editUrlJson = json_encode($editUrl);
echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
   const renderProjectplusActions = function () {
      document.querySelectorAll('.search-container table').forEach(function (table) {
      const headers = Array.from(table.querySelectorAll('thead th'));
      // GLPI 11 renders each header as "- <label>" followed by the sort
      // indicator, so the raw text never equals the label.
      const headerLabel = function (cell) {
         return cell.textContent.replace(/^\s*-\s*/, '').trim();
      };
      const idIndex = headers.findIndex(function (cell) {
         return headerLabel(cell).indexOf('ID') === 0;
      });
      const actionIndex = headers.findIndex(function (cell) {
         return headerLabel(cell) === 'Ação';
      });
      const documentTypeIndex = headers.findIndex(function (cell) {
         return headerLabel(cell) === 'Tipo de documento';
      });
      // The document type limits this native table internally. It is not a
      // field the administrator needs to see in the model's default view.
      if (documentTypeIndex >= 0) {
         headers[documentTypeIndex].hidden = true;
         table.querySelectorAll('tbody tr').forEach(function (row) {
            const cells = row.querySelectorAll('td');
            if (cells[documentTypeIndex]) {
               cells[documentTypeIndex].hidden = true;
            }
         });
      }
      if (actionIndex < 0) {
         return;
      }
      table.querySelectorAll('tbody tr').forEach(function (row) {
         const cells = row.querySelectorAll('td');
         const actionCell = cells[actionIndex];
         const checkbox = row.querySelector('input[type="checkbox"]');
         const checkboxNameMatch = checkbox ? (checkbox.name || '').match(/\[(\d+)\](?:\[[^\]]*\])?$/) : null;
         const checkboxId = checkboxNameMatch ? checkboxNameMatch[1] : '';
         const idMatch = idIndex >= 0 && cells[idIndex]
            ? cells[idIndex].textContent.trim().match(/(\d+)\s*$/)
            : null;
         const id = idMatch ? idMatch[1] : checkboxId;
         if (!actionCell || id === '') {
            return;
         }
         if (actionCell.dataset.projectplusActionId === id) {
            return;
         }
         const link = document.createElement('a');
         link.className = 'btn btn-sm btn-outline-secondary';
         link.textContent = 'Editar';
         link.href = {$editUrlJson} + encodeURIComponent(id);
         actionCell.replaceChildren(link);
         actionCell.dataset.projectplusActionId = id;
      });
      });
   };

   renderProjectplusActions();
   const container = document.querySelector('.search-container');
   if (container) {
      new MutationObserver(renderProjectplusActions).observe(container, {
         childList: true,
         subtree: true,
      });
   }
});
</script>
HTML;

echo '</div></section>';
ConfigPage::end();
Html::footer();
