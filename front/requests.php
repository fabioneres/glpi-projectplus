<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\CentralRequests;
use GlpiPlugin\Projectplus\ProjectProfile;

Session::checkLoginUser();
Session::checkRight(ProjectProfile::RIGHT_REQUESTS, READ);

Html::header(__('Solicitações de projeto', 'projectplus'), $_SERVER['PHP_SELF'], 'helpdesk', 'ticket');
echo "<div class='container-fluid'><h2 class='mb-3'>" . htmlspecialchars(__('Solicitações de projeto', 'projectplus'), ENT_QUOTES, 'UTF-8') . '</h2>';
CentralRequests::display(false);
echo '</div>';
Html::footer();
