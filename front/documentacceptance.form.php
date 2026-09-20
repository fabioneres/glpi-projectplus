<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\ProjectWorkspace;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();
$itemtype = (string) ($_POST['itemtype'] ?? '');
$itemsId = (int) ($_POST['items_id'] ?? 0);
if (!in_array($itemtype, [Ticket::class, Project::class], true) || $itemsId <= 0) {
   Html::displayErrorAndDie(__('Item inválido.', 'projectplus'));
}
$item = new $itemtype();
if (!$item->getFromDB($itemsId) || !ItemAccess::canAct($item)) {
   Html::displayErrorAndDie(__('Item inválido ou sem permissão de acesso.', 'projectplus'));
}
$redirectUrl = $item instanceof Project
   ? ProjectWorkspace::getProjectRedirectUrl($itemsId, (string) ($_POST['projectplus_workspace_tab'] ?? 'documents'))
   : $itemtype::getFormURLWithID($itemsId);

try {
   if (isset($_POST['start_tap'])) {
      if (!$item instanceof Ticket) {
         throw new \RuntimeException(__('Ação disponível somente para o TAP do chamado.', 'projectplus'));
      }
      DocumentAcceptance::startTicketTap($item);
      Session::addMessageAfterRedirect(__('TAP liberado para preenchimento.', 'projectplus'));
   } elseif (isset($_POST['publish_generated'])) {
      DocumentAcceptance::publishGenerated($item, (string) ($_POST['document_type'] ?? ''));
      Session::addMessageAfterRedirect(__('Documento gerado enviado para aceite.', 'projectplus'));
   } elseif (isset($_POST['accept_document'])) {
      DocumentAcceptance::accept($item, (int) ($_POST['acceptance_id'] ?? 0));
      Session::addMessageAfterRedirect(__('Aceite registrado com sucesso.', 'projectplus'));
   } elseif (isset($_POST['reject_document'])) {
      DocumentAcceptance::reject($item, (int) ($_POST['acceptance_id'] ?? 0), (string) ($_POST['rejection_justification'] ?? ''));
      Session::addMessageAfterRedirect(__('Documento recusado e devolvido para revisão.', 'projectplus'));
   }
} catch (\RuntimeException $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}
Html::redirect($item instanceof Ticket ? RoutingNotice::getRedirectUrl($item) : $redirectUrl);
