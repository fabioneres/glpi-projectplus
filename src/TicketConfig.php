<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Session;
use Ticket;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Configuration that identifies project-request tickets in each entity. */
class TicketConfig
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketconfigs';
   }

   /** @return array<string, mixed> */
   public static function getForEntity(int $entityId): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $default = [
         'id' => 0,
         'entities_id' => $entityId,
         'itilcategories_id' => 0,
         'is_active' => 0,
      ];
      if ($entityId < 0 || !$DB->tableExists(self::getTable())) {
         return $default;
      }

      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityId],
         'LIMIT' => 1,
      ])->current();

      return $row ? array_merge($default, $row) : $default;
   }

   public static function isEnabledForTicket(Ticket $ticket): bool
   {
      $config = self::getForEntity((int) $ticket->getEntityID());
      return (int) $config['is_active'] === 1
         && (int) $config['itilcategories_id'] > 0
         && (int) ($ticket->fields['itilcategories_id'] ?? 0) === (int) $config['itilcategories_id'];
   }

   public static function saveForEntity(int $entityId, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
         return false;
      }

      $categoryId = (int) ($input['itilcategories_id'] ?? 0);
      $isActive = (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0;
      if ($isActive && $categoryId <= 0) {
         return false;
      }

      $now = date('Y-m-d H:i:s');
      $data = [
         'itilcategories_id' => $categoryId,
         'is_active' => $isActive,
         'users_id' => (int) Session::getLoginUserID(),
         'date_mod' => $now,
      ];
      $existing = self::getForEntity($entityId);
      if ((int) $existing['id'] > 0) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => (int) $existing['id']]);
      }

      $data['entities_id'] = $entityId;
      $data['date_creation'] = $now;
      return (bool) $DB->insert(self::getTable(), $data);
   }
}
