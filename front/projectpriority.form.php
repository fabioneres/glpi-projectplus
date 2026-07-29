<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectPriority;

Session::checkLoginUser();
$project = new Project();
$projectId = (int) ($_POST['projects_id'] ?? 0);
if ($projectId <= 0 || !$project->getFromDB($projectId) || !Session::haveAccessToEntity((int) $project->getEntityID())) {
   Html::displayErrorAndDie(__('Projeto inválido.', 'projectplus'));
}
try {
   ProjectPriority::saveForProject($project, $_POST);
   Session::addMessageAfterRedirect(__('Priorização institucional salva.', 'projectplus'));
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}
Html::redirect(Project::getFormURLWithID($projectId));
