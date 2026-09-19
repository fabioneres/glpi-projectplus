<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\TicketProject;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();

$ticket = new Ticket();
$ticketId = (int) ($_POST['tickets_id'] ?? 0);
if ($ticketId <= 0
    || !$ticket->getFromDB($ticketId)
    || !ItemAccess::canAct($ticket)) {
   Html::displayErrorAndDie(__('Chamado inválido ou sem permissão.', 'projectplus'));
}

try {
   $projectId = TicketProject::createFromTicket($ticket, $_POST);
   Session::addMessageAfterRedirect(__('Projeto criado e chamado solucionado.', 'projectplus'), true, INFO);
   if (!empty($_POST['_sol_to_kb'])) {
      $knowledgeBaseUrl = KnowbaseItem::getFormURLWithParam(['_sol_to_kb' => 1]);
      $knowledgeBaseUrl .= '&item_itemtype=' . rawurlencode(Ticket::class) . '&item_items_id=' . $ticketId;
      Html::redirect($knowledgeBaseUrl);
   }
   Html::redirect(Project::getFormURLWithID($projectId));
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   Html::redirect(RoutingNotice::getRedirectUrl($ticket));
}
