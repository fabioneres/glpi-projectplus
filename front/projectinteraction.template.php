<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ItemAccess;
use GlpiPlugin\Projectplus\ProjectInteraction;

Session::checkLoginUser();
header('Content-Type: application/json; charset=UTF-8');

$project = new Project();
$projectId = (int) ($_GET['projects_id'] ?? 0);
try {
   if ($projectId <= 0 || !$project->getFromDB($projectId) || !ItemAccess::canView($project)) {
      throw new RuntimeException(__('Projeto inválido.', 'projectplus'));
   }
   echo json_encode([
      'success' => true,
      'content' => ProjectInteraction::getTemplateContent(
         $project,
         (string) ($_GET['template_type'] ?? ''),
         (int) ($_GET['template_id'] ?? 0)
      ),
   ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
   http_response_code(403);
   echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
