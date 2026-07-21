<?php

namespace GlpiPlugin\Projectplus;

use CommonDropdown;
use Session;

class PortfolioBasket extends CommonDropdown
{
   public static $rightname = 'config';

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_portfoliobaskets';
   }

   public static function getTypeName($nb = 0)
   {
      return _n('Cesta Portfólio', 'Cestas Portfólio', $nb, 'projectplus');
   }

   public static function canCreate(): bool
   {
      return Session::haveRight('config', UPDATE);
   }

   public static function getFormURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/portfoliobasket.form.php';
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/portfoliobasket.php';
   }
}
