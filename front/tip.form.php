<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketIntent;
use GlpiPlugin\Projectplus\TicketConfig;

Session::checkLoginUser();

$ticketId = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !Session::haveAccessToEntity((int) $ticket->getEntityID()) || !TicketConfig::isEnabledForTicket($ticket)) {
   Html::displayErrorAndDie(__('Ticket inválido ou sem permissão de acesso.', 'projectplus'));
}

if (isset($_POST['request_tip'])) {
   try {
      TicketIntent::requestForTicket($ticket);
      Session::addMessageAfterRedirect(__('TIP solicitado ao solicitante.', 'projectplus'));
   } catch (\RuntimeException $exception) {
      Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   }
} elseif (isset($_POST['save_tip'])) {
   try {
      TicketIntent::saveForTicket($ticket, $_POST);
      Session::addMessageAfterRedirect(__('TIP enviado para análise do gerente de projetos.', 'projectplus'));
   } catch (\RuntimeException $exception) {
      Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   }
} elseif (isset($_POST['accept_tip'])) {
   try {
      TicketIntent::acceptForTicket($ticket);
      Session::addMessageAfterRedirect(__('TIP aceito e encaminhado para classificação.', 'projectplus'));
   } catch (\RuntimeException $exception) {
      Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   }
} elseif (isset($_POST['reject_tip'])) {
   try {
      TicketIntent::rejectForTicket($ticket, (string) ($_POST['justification'] ?? ''));
      Session::addMessageAfterRedirect(__('TIP recusado e chamado solucionado.', 'projectplus'));
   } catch (\RuntimeException $exception) {
      Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   }
}

Html::redirect(Ticket::getFormURLWithID($ticketId));
