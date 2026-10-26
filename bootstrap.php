<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

define('PLUGIN_PROJECTPLUS_VERSION', '1.0.0-alpha.1');
define('PLUGIN_PROJECTPLUS_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_PROJECTPLUS_MAX_GLPI_VERSION', '11.0.99');
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

if (!function_exists('projectplus_param')) {
   /**
    * Parametro de requisicao como texto: um array enviado no lugar de um valor
    * ("?document_type[]=x") vira o padrao em vez de "Array to string conversion".
    */
   function projectplus_param(array $source, string $key, string $default = ''): string
   {
      $value = $source[$key] ?? null;
      return is_scalar($value) ? (string) $value : $default;
   }
}
