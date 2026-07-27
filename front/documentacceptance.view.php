<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentAcceptance;

Session::checkLoginUser();
$itemtype = (string) ($_GET['itemtype'] ?? '');
$itemsId = (int) ($_GET['items_id'] ?? 0);
$acceptanceId = (int) ($_GET['acceptance_id'] ?? 0);
if (!in_array($itemtype, [Ticket::class, Project::class], true) || $itemsId <= 0 || $acceptanceId <= 0) { Html::displayErrorAndDie(__('Documento inválido.', 'projectplus')); }
$item = new $itemtype();
if (!$item->getFromDB($itemsId) || !Session::haveAccessToEntity((int)$item->getEntityID())) { Html::displayErrorAndDie(__('Documento inválido ou sem acesso.', 'projectplus')); }
$acceptance = DocumentAcceptance::getViewableAcceptance($item, $acceptanceId);
$document = new Document();
if (!$acceptance || !$document->getFromDB((int)$acceptance['documents_id'])) { Html::displayErrorAndDie(__('Documento não disponível.', 'projectplus')); }
$path = GLPI_DOC_DIR . '/' . (string)$document->fields['filepath'];
if (!is_file($path)) { Html::displayErrorAndDie(__('Arquivo do documento não encontrado.', 'projectplus')); }
header('Content-Type: ' . ((string)$document->fields['mime'] ?: 'application/pdf'));
header('Content-Disposition: inline; filename="' . rawurlencode((string)$document->fields['filename']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
