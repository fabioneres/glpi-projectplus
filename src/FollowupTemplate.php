<?php

namespace GlpiPlugin\Projectplus;

use CommonDropdown;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Reusable rich-text content for project followups. Content is kept in comment. */
class FollowupTemplate extends CommonDropdown
{
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
}
