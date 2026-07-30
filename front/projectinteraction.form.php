<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectInteraction;
use GlpiPlugin\Projectplus\ProjectWorkspace;

Session::checkLoginUser();

$project = new Project();
$project_id = (int) ($_POST['projects_id'] ?? 0);

if ($project_id <= 0
    || !$project->getFromDB($project_id)
    || !Session::haveAccessToEntity((int) $project->getEntityID())) {
   Html::displayErrorAndDie(__('Projeto inválido.', 'projectplus'));
}

try {
   ProjectInteraction::addInteraction($project, $_POST);
   $message = (string) ($_POST['interaction_action'] ?? '') === 'solution'
      ? __('Solução registrada e TEP enviado para aceite.', 'projectplus')
      : __('Interação publicada.', 'projectplus');
   Session::addMessageAfterRedirect($message, true, INFO);
} catch (\Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}

if (Session::getCurrentInterface() === 'helpdesk') {
   global $CFG_GLPI;
   Html::redirect(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/myproject.php?id=' . $project_id . '&projectplus_portal_tab=' . rawurlencode((string) ($_POST['projectplus_portal_tab'] ?? 'interactions')));
}
Html::redirect(ProjectWorkspace::getProjectRedirectUrl($project_id, (string) ($_POST['projectplus_workspace_tab'] ?? 'interactions')));
