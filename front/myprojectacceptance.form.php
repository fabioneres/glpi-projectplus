<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ProjectPortal;

Session::checkLoginUser();
Session::checkHelpdeskAccess();

$projectId = (int) ($_POST['projects_id'] ?? $_POST['items_id'] ?? 0);
$portalProject = ProjectPortal::getProjectForCurrentUser($projectId);
if ($portalProject === null) {
   Html::displayNotFoundError();
}

$project = new Project();
if (!$project->getFromDB($projectId)) {
   Html::displayNotFoundError();
}

try {
   if (isset($_POST['accept_document'])) {
      DocumentAcceptance::accept($project, (int) ($_POST['acceptance_id'] ?? 0));
      Session::addMessageAfterRedirect(__('Aceite registrado com sucesso.', 'projectplus'), true, INFO);
   } elseif (isset($_POST['reject_document'])) {
      DocumentAcceptance::reject($project, (int) ($_POST['acceptance_id'] ?? 0), (string) ($_POST['rejection_justification'] ?? ''));
      Session::addMessageAfterRedirect(__('Documento recusado e devolvido para revisão.', 'projectplus'), true, INFO);
   } else {
      throw new RuntimeException(__('Ação documental inválida.', 'projectplus'));
   }
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}

Html::redirect(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/myproject.php?id=' . $projectId . '&projectplus_portal_tab=documents');
