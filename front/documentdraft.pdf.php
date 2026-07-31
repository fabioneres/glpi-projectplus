<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\DocumentDraft;

Session::checkLoginUser();
$itemtype = (string) ($_GET['itemtype'] ?? '');
$id = (int) ($_GET['items_id'] ?? 0);
$type = (string) ($_GET['document_type'] ?? '');
if (!in_array($itemtype, [Ticket::class, Project::class], true) || !in_array($type, ['tap', 'tep', 'ta'], true)) {
   Html::displayErrorAndDie(__('Documento inválido.', 'projectplus'));
}
$item = new $itemtype();
if (!$item->getFromDB($id) || !Session::haveAccessToEntity((int) $item->getEntityID())) {
   Html::displayErrorAndDie(__('Sem permissão.', 'projectplus'));
}

if (!DocumentAcceptance::canViewDocument($item, $type)) {
   Html::displayErrorAndDie(__('Sem permissão.', 'projectplus'));
}
$draft = DocumentDraft::get($itemtype, $id, $type);
if (!$draft || (string) $draft['status'] !== 'generated') {
   Html::displayErrorAndDie(__('Documento ainda não foi gerado.', 'projectplus'));
}

$labels = ['tap' => 'Termo de Abertura do Projeto - TAP', 'tep' => 'Termo de Entrega do Projeto - TEP', 'ta' => 'Termo de Aceite - TA'];
$title = $labels[$type];
$answers = json_decode((string) $draft['generated_content'], true) ?: [];
$esc = static function (string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); };
$pdf = new TCPDF();
$pdf->SetCreator('Project+');
$pdf->SetTitle($title);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->SetFont('dejavusans', '', 9);
$pdf->AddPage();
$html = '<style>body{color:#18372e;font-family:dejavusans;font-size:9pt}.eyebrow{color:#d8eee1;font-size:8pt;font-weight:bold}.title{color:#fff;font-size:20pt;font-weight:bold}.subtitle{color:#e5f3ea;font-size:10pt}.section{color:#1f6040;font-size:13pt;font-weight:bold}.muted{color:#60756c}.label{color:#48655a;font-size:8pt;font-weight:bold}.value{color:#173b2d;font-size:10pt;font-weight:bold}.answer-title{color:#1d4435;font-size:10pt;font-weight:bold}.answer-value{color:#30483e;font-size:9pt}</style>';
$html .= '<table width="100%" cellpadding="12" bgcolor="#256b45"><tr><td><div class="eyebrow">VALIDAÇÃO DOCUMENTAL</div><div class="title">' . $esc($title) . '</div><br><div class="subtitle">Versão gerada e disponível para validação.</div></td></tr></table><br>';
$html .= '<table width="100%" cellpadding="7"><tr><td><div class="section">Documento gerado</div><div class="muted">Registro formal das informações declaradas.</div></td></tr></table><br>';
$html .= '<table width="100%" cellspacing="5" cellpadding="9"><tr><td width="33%" bgcolor="#eff6f1"><div class="label">PROJETO/CHAMADO</div><div class="value">#' . $id . '</div></td><td width="33%" bgcolor="#eff6f1"><div class="label">DOCUMENTO</div><div class="value">' . $esc(strtoupper($type)) . '</div></td><td width="34%" bgcolor="#eff6f1"><div class="label">VERSÃO</div><div class="value">' . (int) $draft['version'] . '</div></td></tr></table><br>';
foreach ($answers as $answer) {
   $html .= '<table width="100%" cellpadding="9" bgcolor="#f8fbf9"><tr><td><div class="answer-title">' . $esc((string) $answer['label']) . '</div><br><div class="answer-value">' . nl2br($esc((string) $answer['value'])) . '</div></td></tr></table><br>';
}
$pdf->writeHTML($html);
$pdf->Output(strtolower($type) . '-' . $id . '-v' . $draft['version'] . '.pdf', 'I');
