<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentAcceptance;

Session::checkLoginUser();
$itemtype = (string) ($_POST['itemtype'] ?? '');
$itemsId = (int) ($_POST['items_id'] ?? 0);
if (!in_array($itemtype, [Ticket::class, Project::class], true) || $itemsId <= 0) {
   Html::displayErrorAndDie(__('Item inválido.', 'projectplus'));
}
$item = new $itemtype();
if (!$item->getFromDB($itemsId) || !Session::haveAccessToEntity((int)$item->getEntityID())) {
   Html::displayErrorAndDie(__('Item inválido ou sem permissão de acesso.', 'projectplus'));
}
try {
   if (isset($_POST['publish_document'])) {
      DocumentAcceptance::publish($item, (string) ($_POST['document_type'] ?? ''), (int) ($_POST['documents_id'] ?? 0));
      Session::addMessageAfterRedirect(__('Documento publicado para aceite.', 'projectplus'));
   } elseif (isset($_POST['publish_generated'])) {
      DocumentAcceptance::publishGenerated($item, (string) ($_POST['document_type'] ?? ''));
      Session::addMessageAfterRedirect(__('Documento gerado enviado para aceite.', 'projectplus'));
   } elseif (isset($_POST['accept_document']) && (int) ($_POST['accepted'] ?? 0) === 1) {
      DocumentAcceptance::accept($item, (int) ($_POST['acceptance_id'] ?? 0));
      Session::addMessageAfterRedirect(__('Aceite registrado com sucesso.', 'projectplus'));
   }
} catch (\RuntimeException $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}
Html::redirect($itemtype::getFormURLWithID($itemsId));
