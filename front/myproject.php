<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectPortal;
use Glpi\Exception\Http\NotFoundHttpException;

Session::checkLoginUser();
Session::checkHelpdeskAccess();

$project = ProjectPortal::getProjectForCurrentUser((int) ($_GET['id'] ?? 0));
if ($project === null) {
   throw new NotFoundHttpException();
}

Html::helpHeader(__('Projeto', 'projectplus'), 'projectplus_myprojects', 'myprojects.php');
ProjectPortal::displayProject($project);
Html::footer();
