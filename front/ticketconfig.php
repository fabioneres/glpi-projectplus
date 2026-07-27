<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketConfig;
use GlpiPlugin\Projectplus\ConfigPage;

Session::checkRight('config', UPDATE);

$entityId = (int) ($_REQUEST['entities_id'] ?? Session::getActiveEntity());
if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
   Html::displayErrorAndDie(__('Entidade invalida ou sem permissao de configuracao.', 'projectplus'));
}

if (isset($_POST['save_ticket_config'])) {
   if (TicketConfig::saveForEntity($entityId, $_POST)) {
      Session::addMessageAfterRedirect(__('Configuracao do fluxo de solicitacao de projeto salva.', 'projectplus'));
   } else {
      Session::addMessageAfterRedirect(__('Selecione uma categoria para habilitar o fluxo.', 'projectplus'), false, ERROR);
   }
   Html::redirect($_SERVER['PHP_SELF'] . '?entities_id=' . $entityId);
}

$config = TicketConfig::getForEntity($entityId);
Html::header(__('Fluxo de solicitacao de projeto', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

ConfigPage::begin('ticketflow');
echo "<section class='projectplus-config-card'><header class='projectplus-config-card__header'><i class='ti ti-git-branch'></i> " . ConfigPage::escape(__('Fluxo de solicitacao de projeto', 'projectplus')) . '</header>';
echo "<div class='projectplus-config-card__body'>";
echo "<form method='get' action='" . Html::clean($_SERVER['PHP_SELF']) . "'><table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_1'><td>" . __('Entidade configurada', 'projectplus') . '</td><td>';
Entity::dropdown([
   'name' => 'entities_id',
   'value' => $entityId,
   'entity' => $_SESSION['glpiactiveentities'] ?? [$entityId],
]);
echo " <button class='btn btn-secondary' type='submit'>" . __('Carregar', 'projectplus') . '</button>';
echo '</td></tr></table></form>';

echo "<form method='post' action='" . Html::clean($_SERVER['PHP_SELF']) . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('entities_id', ['value' => $entityId]);
echo "<table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Ativacao por entidade', 'projectplus') . '</th></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Entidade', 'projectplus') . '</td><td>' . Html::clean(Entity::getFriendlyNameById($entityId)) . '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Habilitar fluxo', 'projectplus') . '</td><td>';
Dropdown::showYesNo('is_active', (int) $config['is_active']);
echo '</td></tr>';
echo "<tr class='tab_bg_1'><td>" . __('Categoria ITIL que ativa o fluxo', 'projectplus') . '</td><td>';
ITILCategory::dropdown([
   'name' => 'itilcategories_id',
   'value' => (int) $config['itilcategories_id'],
   'entity' => $entityId,
]);
echo '</td></tr>';
echo "<tr class='tab_bg_1'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_ticket_config' value='1'>" . __('Salvar', 'projectplus') . '</button></td></tr>';
echo '</table></form>';

echo "<p class='projectplus-config-help'>" . ConfigPage::escape(__('Quando um Ticket dessa entidade receber a categoria configurada, a aba Solicitacao de projeto sera exibida para controlar a fase interna. O status nativo do Ticket nao e alterado.', 'projectplus')) . '</p></div></section>';
ConfigPage::end();
Html::footer();
