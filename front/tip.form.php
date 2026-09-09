<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketConfig;
use GlpiPlugin\Projectplus\TicketIntent;
use GlpiPlugin\Projectplus\TipDocumentType;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();

$ticketId = (int) ($_POST['tickets_id'] ?? 0);
$documentType = (string) ($_POST['document_type'] ?? 'tip');
if ($documentType !== TipDocumentType::DETAIL) {
   $documentType = TipDocumentType::TIP;
}
$documentName = $documentType === TipDocumentType::TIP ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : __('TIP - Detalhamento Técnico', 'projectplus');
$ticket = new Ticket();
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !Session::haveAccessToEntity((int) $ticket->getEntityID()) || !TicketConfig::isEnabledForTicket($ticket)) {
   Html::displayErrorAndDie(__('Ticket inválido ou sem permissão de acesso.', 'projectplus'));
}

try {
   if (isset($_POST['request_intent']) || isset($_POST['request_tip'])) {
      TicketIntent::requestForTicket($ticket, $documentType);
      Session::addMessageAfterRedirect(sprintf(__('%s solicitado ao solicitante.', 'projectplus'), $documentName));
   } elseif (isset($_POST['generate_intent'])) {
      TicketIntent::saveForTicket($ticket, $_POST, $documentType, true);
      Session::addMessageAfterRedirect(sprintf(__('%s gerado e disponibilizado para consulta.', 'projectplus'), $documentName));
   } elseif (isset($_POST['save_intent']) || isset($_POST['save_tip'])) {
      TicketIntent::saveForTicket($ticket, $_POST, $documentType);
      Session::addMessageAfterRedirect(sprintf(__('%s enviado para análise do gerente de projetos.', 'projectplus'), $documentName));
   } elseif (isset($_POST['accept_intent']) || isset($_POST['accept_tip'])) {
      TicketIntent::acceptForTicket($ticket, $documentType);
      Session::addMessageAfterRedirect(sprintf(__('%s aceito e encaminhado para classificação.', 'projectplus'), $documentName));
   } elseif (isset($_POST['reject_intent']) || isset($_POST['reject_tip'])) {
      TicketIntent::rejectForTicket($ticket, (string) ($_POST['justification'] ?? ''), $documentType);
      Session::addMessageAfterRedirect(sprintf(__('%s recusado e chamado solucionado.', 'projectplus'), $documentName));
   }
} catch (\RuntimeException $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}

Html::redirect(RoutingNotice::getRedirectUrl($ticket));
