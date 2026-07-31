<?php

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\ProjectImport;

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

Session::checkRight('config', UPDATE);
Html::header(__('Importar projetos', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');
ConfigPage::begin('overview');
echo "<section class='projectplus-import'><header class='projectplus-import__header'><div class='projectplus-import__icon'><i class='ti ti-file-import'></i></div><div><h2>"
   . ConfigPage::escape(__('Importação de projetos existentes', 'projectplus'))
   . '</h2><p>' . ConfigPage::escape(__('Envie uma planilha CSV, valide os dados e crie projetos existentes no GLPI com segurança.', 'projectplus'))
   . '</p></div></header><div class="projectplus-import__body">';

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
echo '</div></section>';
ConfigPage::end();
Html::footer();
