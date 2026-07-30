<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectInteraction;
use GlpiPlugin\Projectplus\ProjectWorkspace;

Session::checkLoginUser();

$project = new Project();
$projectId = (int) ($_POST['projects_id'] ?? 0);
if ($projectId <= 0 || !$project->getFromDB($projectId) || !Session::haveAccessToEntity((int) $project->getEntityID())) {
   Html::displayErrorAndDie(__('Projeto inválido.', 'projectplus'));
}

try {
   ProjectInteraction::escalate($project, $_POST);
   Session::addMessageAfterRedirect(__('Projeto escalado para a entidade selecionada.', 'projectplus'), true, INFO);
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}

Html::redirect(ProjectWorkspace::getProjectRedirectUrl($projectId, (string) ($_POST['projectplus_workspace_tab'] ?? 'interactions')));
