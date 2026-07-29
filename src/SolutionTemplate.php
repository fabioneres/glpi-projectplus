<?php

namespace GlpiPlugin\Projectplus;

use CommonDropdown;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Reusable rich-text content for project solution messages. */
class SolutionTemplate extends CommonDropdown
{
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
}
