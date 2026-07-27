<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketPhase;

Session::checkRight('ticket', UPDATE);

$ticketId = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !Session::haveAccessToEntity((int) $ticket->getEntityID())) {
   Html::displayErrorAndDie(__('Ticket invalido ou sem permissao de acesso.', 'projectplus'));
}

if (isset($_POST['update_phase'])) {
   if (!TicketPhase::updateForTicket($ticket, $_POST)) {
      Session::addMessageAfterRedirect(__('Nao foi possivel atualizar a fase. Informe uma justificativa ao recusar ou cancelar.', 'projectplus'), false, ERROR);
   } else {
      Session::addMessageAfterRedirect(__('Fase da solicitacao de projeto atualizada.', 'projectplus'));
   }
}

Html::redirect(Ticket::getFormURLWithID($ticketId));
