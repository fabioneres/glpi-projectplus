<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentDraft;
use GlpiPlugin\Projectplus\DocumentField;
use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ProjectWorkspace;

Session::checkLoginUser();

$itemtype = (string) ($_POST['itemtype'] ?? '');
$id = (int) ($_POST['items_id'] ?? 0);
$type = (string) ($_POST['document_type'] ?? '');
if (!in_array($itemtype, [Ticket::class, Project::class], true)
   || !in_array($type, ['tap', 'tep', 'ta'], true)) {
   Html::displayErrorAndDie(__('Documento inválido.', 'projectplus'));
}

$item = new $itemtype();
if (!$item->getFromDB($id) || !Session::haveAccessToEntity((int) $item->getEntityID())) {
   Html::displayErrorAndDie(__('Sem permissão.', 'projectplus'));
}
if (!DocumentAcceptance::canManageDocument($item, $type)) {
   Html::displayErrorAndDie(__('Sem permissão.', 'projectplus'));
}
if (isset($_POST['generate_document']) && !DocumentAcceptance::canManageDocument($item, $type, 'generate')) {
   Html::displayErrorAndDie(__('Sem permissão para gerar este documento.', 'projectplus'));
}

$redirectUrl = $item instanceof Project
   ? ProjectWorkspace::getProjectRedirectUrl($id, (string) ($_POST['projectplus_workspace_tab'] ?? 'documents'))
   : $itemtype::getFormURLWithID($id);
$answers = is_array($_POST['document_fields'] ?? null) ? $_POST['document_fields'] : [];
$saved = [];
foreach (DocumentField::get((int) $item->getEntityID(), $type, true) as $field) {
   if ($field['field_type'] === 'section') {
      continue;
   }
   $value = trim((string) ($answers[(int) $field['id']] ?? ''));
   if ((int) $field['is_required'] && $value === '') {
      Session::addMessageAfterRedirect(__('Preencha os campos obrigatórios.', 'projectplus'), false, ERROR);
      Html::redirect($redirectUrl);
   }
   $saved[(int) $field['id']] = ['label' => $field['label'], 'value' => $value];
}

if (isset($_POST['generate_document'])) {
   DocumentDraft::save($itemtype, $id, (int) $item->getEntityID(), $type, $saved);
   DocumentDraft::generate($itemtype, $id, $type);
   Session::addMessageAfterRedirect(__('Documento gerado.', 'projectplus'), true, INFO);
} else {
   DocumentDraft::save($itemtype, $id, (int) $item->getEntityID(), $type, $saved);
   Session::addMessageAfterRedirect(__('Rascunho salvo.', 'projectplus'), true, INFO);
}
Html::redirect($redirectUrl);
