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

// O redirect lanca RedirectException no GLPI 11: dentro do try ele cairia no catch e o usuario
// seria devolvido ao chamado mesmo com o projeto criado (LA-111, AP-028). Decide-se o destino
// dentro do try e redireciona-se fora dele.
$target = '';
try {
   $projectId = TicketProject::createFromTicket($ticket, $_POST);
   Session::addMessageAfterRedirect(__('Projeto criado e chamado solucionado.', 'projectplus'), true, INFO);
   if (!empty($_POST['_sol_to_kb'])) {
      $target = KnowbaseItem::getFormURLWithParam(['_sol_to_kb' => 1])
         . '&item_itemtype=' . rawurlencode(Ticket::class) . '&item_items_id=' . $ticketId;
   } else {
      $target = Project::getFormURLWithID($projectId);
   }
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
   $target = RoutingNotice::getRedirectUrl($ticket);
}
Html::redirect($target);
