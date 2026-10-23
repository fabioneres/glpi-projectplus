<?php

namespace GlpiPlugin\Projectplus;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * URLs of the plugin front pages.
 *
 * GLPI 11 routes every request through public/index.php, so
 * $_SERVER['PHP_SELF'] is always "/index.php" and cannot be used to point a
 * form, a redirect or the menu back to the current page.
 */
final class FrontUrl
{
   public static function to(string $file, array $query = []): string
   {
      global $CFG_GLPI;

      $url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/projectplus/front/' . ltrim($file, '/');

      return $query === [] ? $url : $url . '?' . http_build_query($query);
   }
}
