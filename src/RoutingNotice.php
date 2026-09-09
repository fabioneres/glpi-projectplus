<?php

namespace GlpiPlugin\Projectplus;

use Entity;
use Session;
use Ticket;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Provides a safe landing page after routing removes access to a ticket. */
class RoutingNotice
{
   private const SESSION_KEY = 'plugin_projectplus_routing_notice';

   public static function getRedirectUrl(Ticket $ticket): string
   {
      global $CFG_GLPI;

      $ticketId = (int) $ticket->getID();
      if ($ticketId > 0) {
         $ticket->getFromDB($ticketId);
      }
      $entityId = (int) $ticket->getEntityID();
      if (Session::haveAccessToEntity($entityId)) {
         return Ticket::getFormURLWithID($ticketId);
      }

      $phase = TicketPhase::getForTicket($ticketId);
      $phaseKey = (string) ($phase['phase'] ?? '');
      $_SESSION[self::SESSION_KEY] = [
         'ticket_id' => $ticketId,
         'entity' => Entity::getFriendlyNameById($entityId),
         'phase' => FlowStageDescription::getNameForTrigger(
            $phaseKey,
            TicketPhase::getPhaseLabelRaw($phaseKey)
         ),
      ];
      return rtrim((string) $CFG_GLPI['root_doc'], '/')
         . '/plugins/projectplus/front/routingnotice.php';
   }

   /** @return array{ticket_id:int,entity:string,phase:string}|null */
   public static function consume(): ?array
   {
      $notice = $_SESSION[self::SESSION_KEY] ?? null;
      unset($_SESSION[self::SESSION_KEY]);
      if (!is_array($notice)) {
         return null;
      }
      return [
         'ticket_id' => (int) ($notice['ticket_id'] ?? 0),
         'entity' => (string) ($notice['entity'] ?? ''),
         'phase' => (string) ($notice['phase'] ?? ''),
      ];
   }

   /** @param array{ticket_id:int,entity:string,phase:string} $notice */
   public static function format(array $notice): string
   {
      return strtr(FlowStageDescription::get('routing_handoff', 'active'), [
         '{chamado_id}' => (string) $notice['ticket_id'],
         '{entidade}' => $notice['entity'],
         '{fase}' => $notice['phase'],
      ]);
   }
}
