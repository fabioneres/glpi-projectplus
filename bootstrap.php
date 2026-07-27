<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

define('PLUGIN_PROJECTPLUS_VERSION', '0.4.2-beta');
define('PLUGIN_PROJECTPLUS_MIN_GLPI_VERSION', '10.0.20');
define('PLUGIN_PROJECTPLUS_MAX_GLPI_VERSION', '10.0.99');
define('PLUGIN_PROJECTPLUS_DIR', __DIR__);

spl_autoload_register(static function (string $class): void {
   $prefix = 'GlpiPlugin\\Projectplus\\';
   if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
      return;
   }

   $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
   if (file_exists($file)) {
      require_once $file;
   }
});

require_once __DIR__ . '/src/LegacyDropdowns.php';
