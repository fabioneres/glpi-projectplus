<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\TicketProject;

Session::checkLoginUser();

$ticket = new Ticket();
$ticketId = (int) ($_POST['tickets_id'] ?? 0);
if ($ticketId <= 0
    || !$ticket->getFromDB($ticketId)
    || !Session::haveAccessToEntity((int) $ticket->getEntityID())) {
   Html::displayErrorAndDie(__('Chamado inválido ou sem permissão.', 'projectplus'));
}

try {
   $projectId = TicketProject::createFromTicket($ticket, (string) ($_POST['solution_content'] ?? ''));
   Session::addMessageAfterRedirect(__('Projeto criado e chamado solucionado.', 'projectplus'), true, INFO);
   Html::redirect(Project::getFormURLWithID($projectId));
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   Html::redirect(Ticket::getFormURLWithID($ticketId));
}
