<?php

namespace GlpiPlugin\Projectplus;

use Html;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Shared administrative layout for Project+ configuration screens. */
class ConfigPage
{
   public static function begin(string $active): void
   {
      echo "<div class='projectplus-config-layout'>";
      echo "<aside class='projectplus-config-nav'>";
      self::navLink('overview', 'config.php', 'ti ti-settings', __('Geral', 'projectplus'), $active);
      self::navLink('pretipmodel', 'tipconfig.php?document_type=pretip', 'ti ti-file-search', __('Modelo TIP', 'projectplus'), $active);
      self::navLink('tipmodel', 'tipconfig.php?document_type=tip', 'ti ti-file-text', __('Modelo TIP - DT', 'projectplus'), $active);
      self::navLink('tapmodel', 'documentfields.php?document_type=tap', 'ti ti-file-description', __('Modelo TAP', 'projectplus'), $active);
      self::navLink('tepmodel', 'documentfields.php?document_type=tep', 'ti ti-file-description', __('Modelo TEP', 'projectplus'), $active);
      self::navLink('tamodel', 'documentfields.php?document_type=ta', 'ti ti-file-description', __('Modelo TA', 'projectplus'), $active);
      self::navLink('about', 'about.php', 'ti ti-info-circle', __('Sobre', 'projectplus'), $active);
      echo '</aside><main class="projectplus-config-content">';
   }

   public static function end(): void
   {
      echo '</main></div>';
   }

   public static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }

   private static function navLink(string $key, string $url, string $icon, string $label, string $active): void
   {
      $class = $key === $active ? ' is-active' : '';
      echo "<a class='projectplus-config-nav__link{$class}' href='" . Html::clean($url) . "'><i class='" . self::escape($icon) . "'></i> " . self::escape($label) . '</a>';
   }
}
