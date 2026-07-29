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
   if (!isset($_POST['accept_document']) || (int) ($_POST['accepted'] ?? 0) !== 1) {
      throw new RuntimeException(__('Marque a confirmação de aceite antes de continuar.', 'projectplus'));
   }
   DocumentAcceptance::accept($project, (int) ($_POST['acceptance_id'] ?? 0));
   Session::addMessageAfterRedirect(__('Aceite registrado com sucesso.', 'projectplus'), true, INFO);
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
}

Html::redirect(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/myproject.php?id=' . $projectId);
