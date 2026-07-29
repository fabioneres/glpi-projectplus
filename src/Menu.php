<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Native GLPI Plugins-menu entry for the Project+ administration area. */
class Menu extends CommonDBTM
{
   protected static $notable = true;

   public static function getTypeName($nb = 0): string
   {
      return __('Project+', 'projectplus');
   }

   public static function getIcon(): string
   {
      return 'ti ti-folders';
   }

   public static function getMenuName(): string
   {
      return self::getTypeName();
   }

   /** @return array<string, mixed>|false */
   public static function getMenuContent()
   {
      if (!Session::haveRight('config', READ)) {
         return false;
      }

      $menu = [
         'title' => self::getMenuName(),
         'page' => '/plugins/projectplus/front/config.php',
         'icon' => self::getIcon(),
      ];
      if (Session::haveRight('config', UPDATE)) {
         $menu['links']['config'] = '/plugins/projectplus/front/config.php';
      }
      if (Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)) {
         $menu['options']['portfolio'] = [
            'title' => __('Visão do portfólio', 'projectplus'),
            'page' => '/plugins/projectplus/front/portfolio.php',
         ];
      }

      return $menu;
   }
}
