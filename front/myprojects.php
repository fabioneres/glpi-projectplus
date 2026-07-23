<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectPortal;

Session::checkLoginUser();
Session::checkHelpdeskAccess();

if (!ProjectPortal::hasProjectsForCurrentUser()) {
   Html::displayNotFoundError();
}

Html::helpHeader(__('Meus projetos', 'projectplus'), 'projectplus_myprojects', 'myprojects.php');
ProjectPortal::displayList();
Html::footer();
