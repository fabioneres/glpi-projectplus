<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TicketIntentAnswer
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketintentanswers';
   }

   /** @return array<int, array<string, mixed>> */
   public static function getForIntent(int $intentId): array
   {
      /** @var DBmysql $DB */
      global $DB;
      return iterator_to_array($DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['plugin_projectplus_ticketintents_id' => $intentId],
         'ORDER' => ['id ASC'],
      ]), false);
   }
}
