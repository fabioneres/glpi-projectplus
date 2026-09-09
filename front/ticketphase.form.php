<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\TicketPhase;
use GlpiPlugin\Projectplus\RoutingNotice;

Session::checkLoginUser();

$ticketId = (int) ($_POST['tickets_id'] ?? 0);
$ticket = new Ticket();
if ($ticketId <= 0 || !$ticket->getFromDB($ticketId) || !Session::haveAccessToEntity((int) $ticket->getEntityID())) {
   Html::displayErrorAndDie(__('Ticket inválido ou sem permissão de acesso.', 'projectplus'));
}

if (isset($_POST['update_phase'])) {
   try {
      if (!TicketPhase::updateForTicket($ticket, $_POST)) {
         Session::addMessageAfterRedirect(__('Não foi possível atualizar a fase. Informe uma justificativa ao recusar ou cancelar.', 'projectplus'), false, ERROR);
      } else {
         Session::addMessageAfterRedirect(__('Fase da solicitação de projeto atualizada.', 'projectplus'));
      }
   } catch (\Throwable $exception) {
      Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   }
}

Html::redirect(RoutingNotice::getRedirectUrl($ticket));
