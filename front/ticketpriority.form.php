<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\TicketPriority;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();
$ticketId = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !ItemAccess::canAct($ticket)) {
   Html::displayErrorAndDie(__('Ticket inválido ou sem permissão de acesso.', 'projectplus'));
}

try {
   TicketPriority::saveForTicket($ticket, $_POST);
   Session::addMessageAfterRedirect(
      isset($_POST['send_to_portfolio'])
         ? __('Priorização técnica enviada ao Gestor de Portfólio.', 'projectplus')
         : __('Priorização da solicitação salva.', 'projectplus')
   );
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
}
Html::redirect(RoutingNotice::getRedirectUrl($ticket));
