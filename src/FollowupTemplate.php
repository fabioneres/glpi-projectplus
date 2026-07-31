<?php

namespace GlpiPlugin\Projectplus;

use AbstractITILChildTemplate;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Project followup template using the same native form structure as ticket
 * followup templates: source, privacy and rich-text content.
 */
class FollowupTemplate extends AbstractITILChildTemplate
{
   public $dohistory = true;
   public $can_be_translated = true;

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_followuptemplates';
   }

   public static function getTypeName($nb = 0)
   {
      return _n('Modelo de acompanhamento de Projetos', 'Modelos de acompanhamento de Projetos', $nb, 'projectplus');
   }

   public static function getFormURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/followuptemplate.form.php';
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/followuptemplate.php';
   }

   public function getAdditionalFields(): array
   {
      return [
         [
            'name' => 'requesttypes_id',
            'label' => __('Origem do acompanhamento', 'projectplus'),
            'type' => 'dropdownValue',
            'list' => true,
         ],
         [
            'name' => 'is_private',
            'label' => __('Privado', 'projectplus'),
            'type' => 'bool',
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
