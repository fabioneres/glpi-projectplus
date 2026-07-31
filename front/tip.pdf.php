<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketIntent;
use GlpiPlugin\Projectplus\TicketIntentAnswer;

Session::checkLoginUser();
$ticket = new Ticket();
$ticketId = (int) ($_GET['tickets_id'] ?? 0);
$documentType = (string) ($_GET['document_type'] ?? 'tip');
if ($documentType !== 'pretip') {
   $documentType = 'tip';
}
$documentName = $documentType === 'pretip' ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : __('TIP - Detalhamento Técnico', 'projectplus');
$documentTitle = $documentName;
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !Session::haveAccessToEntity((int) $ticket->getEntityID()) || !TicketIntent::canViewForTicket($ticket, $documentType)) {
   Html::displayErrorAndDie(__('TIP não encontrado ou sem permissão de acesso.', 'projectplus'));
}
$intent = TicketIntent::getForTicket($ticketId, $documentType);
if ($intent === null || !in_array((string) $intent['status'], ['submitted', 'accepted', 'rejected'], true)) {
   Html::displayErrorAndDie(__('TIP ainda não foi enviado.', 'projectplus'));
}

$esc = static function (string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); };
$pdf = new TCPDF();
$pdf->SetCreator('Project+');
$pdf->SetTitle($documentName . ' - Ticket #' . $ticketId);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->SetFont('dejavusans', '', 9);
$pdf->AddPage();
$html = '<style>body{color:#18372e;font-family:dejavusans;font-size:9pt}.eyebrow{color:#d8eee1;font-size:8pt;font-weight:bold}.title{color:#fff;font-size:21pt;font-weight:bold}.subtitle{color:#e5f3ea;font-size:10pt}.section{color:#1f6040;font-size:13pt;font-weight:bold}.muted{color:#60756c}.label{color:#48655a;font-size:8pt;font-weight:bold}.value{color:#173b2d;font-size:10pt;font-weight:bold}.answer-title{color:#1d4435;font-size:10pt;font-weight:bold}.answer-value{color:#30483e;font-size:9pt}</style>';
$html .= '<table width="100%" cellpadding="12" bgcolor="#256b45"><tr><td><div class="eyebrow">SOLICITAÇÃO DE PROJETO</div><div class="title">' . $esc($documentTitle) . '</div><br><div class="subtitle">Formulário preenchido pelo solicitante e disponível para análise.</div></td></tr></table><br>';
$html .= '<table width="100%" cellpadding="7"><tr><td><div class="section">' . $esc($documentName) . ' enviado</div><div class="muted">Dados de identificação e registro do envio.</div></td></tr></table><br>';
$html .= '<table width="100%" cellspacing="5" cellpadding="9"><tr><td width="20%" bgcolor="#eff6f1"><div class="label">TICKET</div><div class="value">#' . $ticketId . '</div></td><td width="35%" bgcolor="#eff6f1"><div class="label">ENVIADO EM</div><div class="value">' . $esc(Html::convDateTime((string) $intent['date_submitted'])) . '</div></td><td width="45%" bgcolor="#eff6f1"><div class="label">ENVIADO POR</div><div class="value">' . $esc((string) $intent['submitter_name']) . '</div><div class="muted">' . $esc((string) $intent['submitter_email']) . '</div></td></tr></table><br>';
$html .= '<table width="100%" cellpadding="7"><tr><td><div class="section">Respostas do formulário</div><div class="muted">Informações declaradas no ' . $esc($documentTitle) . '.</div></td></tr></table><br>';
foreach (TicketIntentAnswer::getForIntent((int) $intent['id']) as $answer) {
   $html .= '<table width="100%" cellpadding="9" bgcolor="#f8fbf9"><tr><td><div class="answer-title">' . $esc((string) $answer['label']) . '</div><br><div class="answer-value">' . nl2br($esc((string) $answer['value'])) . '</div></td></tr></table><br>';
}
$pdf->writeHTML($html);
$pdf->Output(strtolower($documentType) . '-ticket-' . $ticketId . '.pdf', 'I');
