<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectPortal;
use Glpi\Exception\Http\NotFoundHttpException;

Session::checkLoginUser();
Session::checkHelpdeskAccess();

if (!ProjectPortal::hasProjectsForCurrentUser()) {
   throw new NotFoundHttpException();
}

Html::helpHeader(__('Meus projetos', 'projectplus'), 'projectplus_myprojects', 'myprojects.php');
ProjectPortal::displayList();
Html::footer();
