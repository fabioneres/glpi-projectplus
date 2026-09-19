<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ItemAccess;

Session::checkLoginUser();
$itemtype = (string) ($_GET['itemtype'] ?? '');
$itemsId = (int) ($_GET['items_id'] ?? 0);
$acceptanceId = (int) ($_GET['acceptance_id'] ?? 0);
if (!in_array($itemtype, [Ticket::class, Project::class], true) || $itemsId <= 0 || $acceptanceId <= 0) { Html::displayErrorAndDie(__('Documento inválido.', 'projectplus')); }
$item = new $itemtype();
if (!$item->getFromDB($itemsId) || !ItemAccess::canView($item)) { Html::displayErrorAndDie(__('Documento inválido ou sem acesso.', 'projectplus')); }
$acceptance = DocumentAcceptance::getViewableAcceptance($item, $acceptanceId);
$document = new Document();
if (!$acceptance || !$document->getFromDB((int)$acceptance['documents_id'])) { Html::displayErrorAndDie(__('Documento não disponível.', 'projectplus')); }
// Resolve the path and confirm it stays inside the GLPI document directory:
// the endpoint must never serve a file from outside it, whatever the stored
// filepath contains.
$root = realpath(GLPI_DOC_DIR);
$path = realpath(GLPI_DOC_DIR . '/' . (string)$document->fields['filepath']);
if ($root === false || $path === false || !is_file($path)
   || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0) {
   Html::displayErrorAndDie(__('Arquivo do documento não encontrado.', 'projectplus'));
}
// The stored mime comes from whoever uploaded the Document. Rendering it
// inline with that type would let an HTML or SVG file execute in the GLPI
// origin, so only types that are safe to embed are served inline.
$inlineTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$mime = strtolower(trim((string) $document->fields['mime']));
$isInline = in_array($mime, $inlineTypes, true);
header('Content-Type: ' . ($isInline ? $mime : 'application/octet-stream'));
header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment')
   . '; filename="' . rawurlencode((string)$document->fields['filename']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
