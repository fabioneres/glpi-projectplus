<?php
include('../../../inc/includes.php');
use GlpiPlugin\Projectplus\TicketProject;
Session::checkLoginUser();
$ticket = new Ticket(); $id = (int) ($_POST['tickets_id'] ?? 0);
if ($id <= 0 || !$ticket->getFromDB($id) || !Session::haveAccessToEntity((int) $ticket->getEntityID())) { Html::displayErrorAndDie(__('Chamado inválido ou sem permissão.', 'projectplus')); }
try { $projectId = TicketProject::createFromTicket($ticket); Session::addMessageAfterRedirect(__('Projeto criado e chamado solucionado.', 'projectplus'), true, INFO); Html::redirect(Project::getFormURLWithID($projectId)); } catch (\Throwable $e) { Session::addMessageAfterRedirect($e->getMessage(), false, ERROR); Html::redirect(Ticket::getFormURLWithID($id)); }
