<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\ProjectPriority;
use GlpiPlugin\Projectplus\ProjectWorkspace;

Session::checkLoginUser();
$project = new Project();
$projectId = (int) ($_POST['projects_id'] ?? 0);
if ($projectId <= 0 || !$project->getFromDB($projectId) || !ItemAccess::canAct($project)) {
   Html::displayErrorAndDie(__('Projeto inválido.', 'projectplus'));
}
try {
   ProjectPriority::saveForProject($project, $_POST);
   Session::addMessageAfterRedirect(__('Priorização institucional salva.', 'projectplus'));
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
}
Html::redirect(ProjectWorkspace::getProjectRedirectUrl($projectId, (string) ($_POST['projectplus_workspace_tab'] ?? 'priority')));
