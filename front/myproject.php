<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectPortal;

Session::checkLoginUser();
Session::checkHelpdeskAccess();

$project = ProjectPortal::getProjectForCurrentUser((int) ($_GET['id'] ?? 0));
if ($project === null) {
   Html::displayNotFoundError();
}

Html::helpHeader(__('Projeto', 'projectplus'), 'projectplus_myprojects', 'myprojects.php');
ProjectPortal::displayProject($project);
Html::footer();
