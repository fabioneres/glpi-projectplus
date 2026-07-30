<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketPriority;

Session::checkRight('ticket', UPDATE);
$ticketId = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !Session::haveAccessToEntity((int) $ticket->getEntityID(), true)) {
   Html::displayErrorAndDie(__('Ticket inválido ou sem permissão de acesso.', 'projectplus'));
}

try {
   TicketPriority::saveForTicket($ticket, $_POST);
   Session::addMessageAfterRedirect(__('Priorização da solicitação salva.', 'projectplus'));
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}
Html::redirect(Ticket::getFormURLWithID($ticketId));
