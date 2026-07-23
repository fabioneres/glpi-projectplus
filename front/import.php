<?php

use GlpiPlugin\Projectplus\ProjectImport;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', UPDATE);
Html::header(__('Importar projetos', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

try {
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (($_POST['action'] ?? '') === 'preview') {
         ProjectImport::renderPreview(ProjectImport::preview($_FILES['import_file'] ?? []));
      } elseif (($_POST['action'] ?? '') === 'import') {
         ProjectImport::renderResult(ProjectImport::importPreview());
      }
   }
} catch (Throwable $exception) {
   Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
   Html::redirect($_SERVER['PHP_SELF']);
}

ProjectImport::renderUploadForm();
Html::footer();
