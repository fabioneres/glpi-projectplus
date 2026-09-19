<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\TicketPhase;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();
$ticket = new Ticket();
$ticketId = (int) ($_POST['tickets_id'] ?? 0);
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !ItemAccess::canAct($ticket)) {
   Html::displayErrorAndDie(__('Chamado inválido ou sem permissão.', 'projectplus'));
}
try {
   if (isset($_POST['authorize_opening'])) {
      TicketPhase::decideOpening($ticket, true);
      Session::addMessageAfterRedirect(__('Abertura do projeto autorizada.', 'projectplus'));
   } elseif (isset($_POST['deny_opening'])) {
      TicketPhase::decideOpening($ticket, false, (string) ($_POST['justification'] ?? ''));
      Session::addMessageAfterRedirect(__('Abertura do projeto negada.', 'projectplus'));
   }
} catch (\RuntimeException $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}
Html::redirect(RoutingNotice::getRedirectUrl($ticket));
