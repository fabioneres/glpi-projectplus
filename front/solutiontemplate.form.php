<?php
if (!defined('GLPI_ROOT')) { require_once dirname(__DIR__, 3) . '/inc/includes.php'; }
// Queue TinyMCE before dropdown.common.form.php renders GLPI's page header.
Html::requireJs('tinymce');
require_once dirname(__DIR__) . '/bootstrap.php';
$dropdown = new PluginProjectplusSolutionTemplate();
// O GLPI 11 desaconselha incluir o arquivo legado dropdown.common*.php: ele imprime a
// resposta direto e o nucleo registra avisos de saida inesperada a cada abertura. Devolver
// a resposta do controlador nativo mantem a mesma tela, na mesma URL, sem esses avisos.
if (isset($this) && $this instanceof \Glpi\Controller\LegacyFileLoadController
   && class_exists(\Glpi\Controller\DropdownFormController::class)) {
   $request = $this->getRequest();
   $request->attributes->set('class', $dropdown::class);
   return (new \Glpi\Controller\DropdownFormController())($request);
}
include GLPI_ROOT . '/front/dropdown.common.form.php';
