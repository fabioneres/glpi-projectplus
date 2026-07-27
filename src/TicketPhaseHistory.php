<?php

namespace GlpiPlugin\Projectplus;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TicketPhaseHistory
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketphasehistories';
   }
}
