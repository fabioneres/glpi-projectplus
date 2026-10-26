<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\DocumentAcceptance;
use GlpiPlugin\Projectplus\ProjectPortal;
use Glpi\Exception\Http\NotFoundHttpException;

// O GLPI 11 inclui o arquivo dentro de um metodo do controlador: sem global, $CFG_GLPI
// fica indefinido e o redirecionamento perde o root_doc (quebra em instalacao em subpasta).
global $CFG_GLPI;

Session::checkLoginUser();
Session::checkHelpdeskAccess();

$projectId = (int) ($_POST['projects_id'] ?? $_POST['items_id'] ?? 0);
$portalProject = ProjectPortal::getProjectForCurrentUser($projectId);
if ($portalProject === null) {
   throw new NotFoundHttpException();
}

$project = new Project();
if (!$project->getFromDB($projectId)) {
   throw new NotFoundHttpException();
}

try {
   if (isset($_POST['accept_document'])) {
      DocumentAcceptance::accept($project, (int) ($_POST['acceptance_id'] ?? 0));
      Session::addMessageAfterRedirect(__('Aceite registrado com sucesso.', 'projectplus'), true, INFO);
   } elseif (isset($_POST['reject_document'])) {
      DocumentAcceptance::reject($project, (int) ($_POST['acceptance_id'] ?? 0), projectplus_param($_POST, 'rejection_justification', ''));
      Session::addMessageAfterRedirect(__('Documento recusado e devolvido para revisão.', 'projectplus'), true, INFO);
   } else {
      throw new RuntimeException(__('Ação documental inválida.', 'projectplus'));
   }
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
}

Html::redirect(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/myproject.php?id=' . $projectId . '&projectplus_portal_tab=documents');
