<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentDraft;
use GlpiPlugin\Projectplus\DocumentField;
use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\ProjectWorkspace;
use GlpiPlugin\Projectplus\FieldAttachment;
use GlpiPlugin\Projectplus\FieldValue;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();

$itemtype = (string) ($_POST['itemtype'] ?? '');
$id = (int) ($_POST['items_id'] ?? 0);
$type = (string) ($_POST['document_type'] ?? '');
if (!in_array($itemtype, [Ticket::class, Project::class], true)
   || !in_array($type, ['tap', 'tep', 'ta'], true)) {
   Html::displayErrorAndDie(__('Documento inválido.', 'projectplus'));
}

$item = new $itemtype();
if (!$item->getFromDB($id) || !ItemAccess::canAct($item)) {
   Html::displayErrorAndDie(__('Sem permissão.', 'projectplus'));
}
if (!DocumentAcceptance::canEditDraft($item, $type)) {
   Html::displayErrorAndDie(__('Este documento não pode ser alterado depois de publicado ou aceito. Aguarde uma recusa formal para revisá-lo.', 'projectplus'));
}
if (isset($_POST['generate_document']) && !DocumentAcceptance::canManageDocument($item, $type, 'generate')) {
   Html::displayErrorAndDie(__('Sem permissão para gerar este documento.', 'projectplus'));
}

$redirectUrl = $item instanceof Project
   ? ProjectWorkspace::getProjectRedirectUrl($id, (string) ($_POST['projectplus_workspace_tab'] ?? 'documents'))
   : $itemtype::getFormURLWithID($id);
$answers = is_array($_POST['document_fields'] ?? null) ? $_POST['document_fields'] : [];
$draft = DocumentDraft::get($itemtype, $id, $type);
$stored = $draft ? (json_decode((string) $draft['content'], true) ?: []) : [];
$saved = [];
$fileFields = [];
foreach (DocumentField::get((int) $item->getEntityID(), $type, true) as $field) {
   $fieldType = (string) $field['field_type'];
   if (FieldValue::isDisplayOnly($fieldType)) {
      continue;
   }
   // Anexos ficam para depois da validação dos demais campos: criar documento é
   // efeito colateral, e uma recusa adiante deixaria arquivos órfãos no registro.
   // A chave é reservada agora para o documento sair na ordem configurada.
   if (FieldValue::isFileType($fieldType)) {
      $saved[(int) $field['id']] = ['label' => $field['label'], 'value' => ''];
      $fileFields[] = $field;
      continue;
   }
   try {
      $value = FieldValue::normalize($field, $answers[(int) $field['id']] ?? '');
   } catch (\RuntimeException $exception) {
      Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
      Html::redirect($redirectUrl);
   }
   if ((int) $field['is_required'] && $value === '') {
      Session::addMessageAfterRedirect(__('Preencha os campos obrigatórios.', 'projectplus'), false, ERROR);
      Html::redirect($redirectUrl);
   }
   $saved[(int) $field['id']] = ['label' => $field['label'], 'value' => $value];
}
foreach ($fileFields as $field) {
   try {
      $value = FieldAttachment::collect(
         'document_fields',
         $field,
         (string) ($stored[(int) $field['id']]['value'] ?? ''),
         $_POST,
         $item
      );
   } catch (\RuntimeException $exception) {
      Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
      Html::redirect($redirectUrl);
   }
   if ((int) $field['is_required'] && $value === '') {
      Session::addMessageAfterRedirect(__('Preencha os campos obrigatórios.', 'projectplus'), false, ERROR);
      Html::redirect($redirectUrl);
   }
   $saved[(int) $field['id']]['value'] = $value;
}

if (isset($_POST['generate_document'])) {
   DocumentDraft::save($itemtype, $id, (int) $item->getEntityID(), $type, $saved);
   DocumentDraft::generate($itemtype, $id, $type);
   Session::addMessageAfterRedirect(__('Documento gerado.', 'projectplus'), true, INFO);
} else {
   DocumentDraft::save($itemtype, $id, (int) $item->getEntityID(), $type, $saved);
   Session::addMessageAfterRedirect(__('Rascunho salvo.', 'projectplus'), true, INFO);
}
Html::redirect($item instanceof Ticket ? RoutingNotice::getRedirectUrl($item) : $redirectUrl);
