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
      // A negativa chega pela caixa nativa de solucao: conteudo, tipo de solucao
      // e os metadados de upload que o editor do GLPI gera.
      TicketPhase::decideOpening($ticket, false, $_POST);
      Session::addMessageAfterRedirect(__('Abertura negada e chamado solucionado.', 'projectplus'));
   }
} catch (\RuntimeException $exception) {
   Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
}
Html::redirect(RoutingNotice::getRedirectUrl($ticket));
