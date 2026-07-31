<?php

namespace GlpiPlugin\Projectplus;

use AbstractITILChildTemplate;
use SolutionType;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Project solution template using the native ticket-solution form structure. */
class SolutionTemplate extends AbstractITILChildTemplate
{
   public $dohistory = true;
   public $can_be_translated = true;

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_solutiontemplates';
   }

   public static function getTypeName($nb = 0)
   {
      return _n('Modelo de solução do projeto', 'Modelos de solução do projeto', $nb, 'projectplus');
   }

   public static function getFormURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/solutiontemplate.form.php';
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/solutiontemplate.php';
   }

   public function getAdditionalFields(): array
   {
      return [
         [
            'name' => 'solutiontypes_id',
            'label' => SolutionType::getTypeName(1),
            'type' => 'dropdownValue',
            'list' => true,
         ],
         [
            'name' => 'content',
            'label' => __('Conteúdo', 'projectplus'),
            'type' => 'tinymce',
            'convert_images_to_documents' => false,
         ],
      ];
   }

   public static function getIcon()
   {
      return 'fas fa-layer-group';
   }
}
