<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\ProjectInteraction;
use GlpiPlugin\Projectplus\ProjectWorkspace;

Session::checkLoginUser();

$project = new Project();
$projectId = (int) ($_POST['projects_id'] ?? 0);
if ($projectId <= 0 || !$project->getFromDB($projectId) || !ItemAccess::canView($project)) {
   Html::displayErrorAndDie(__('Projeto inválido.', 'projectplus'));
}

try {
   ProjectInteraction::escalate($project, $_POST);
   Session::addMessageAfterRedirect(__('Projeto transferido para a entidade selecionada.', 'projectplus'), true, INFO);
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
}

Html::redirect(ProjectWorkspace::getProjectRedirectUrl($projectId, projectplus_param($_POST, 'projectplus_workspace_tab', 'interactions')));
