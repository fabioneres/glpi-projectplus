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
      echo "<aside class='projectplus-config-nav'><div class='projectplus-config-nav__title'>" . self::escape(__('Configuracoes', 'projectplus')) . '</div>';
      self::navLink('overview', 'config.php', 'ti ti-settings', __('Geral', 'projectplus'), $active);
      self::navLink('ticketflow', 'ticketconfig.php', 'ti ti-git-branch', __('Fluxo de solicitacao', 'projectplus'), $active);
      self::navLink('tipmodel', 'tipconfig.php', 'ti ti-forms', __('Modelo TIP', 'projectplus'), $active);
      self::navLink('tapmodel', 'documenttemplates.php?document_type=tap', 'ti ti-file-description', __('Modelo TAP', 'projectplus'), $active);
      self::navLink('tepmodel', 'documenttemplates.php?document_type=tep', 'ti ti-file-description', __('Modelo TEP', 'projectplus'), $active);
      self::navLink('tamodel', 'documenttemplates.php?document_type=ta', 'ti ti-file-description', __('Modelo TA', 'projectplus'), $active);
      self::navLink('documents', 'documenttemplates.php', 'ti ti-file-description', __('Modelos de documentos', 'projectplus'), $active);
      self::navLink('import', ProjectImport::getFormURL(), 'ti ti-file-import', __('Importacao de projetos', 'projectplus'), $active);
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
