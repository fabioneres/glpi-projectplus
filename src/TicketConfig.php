<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Entity;
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

   /** @return array<int, array<string, mixed>> */
   public static function getCategoriesForEntity(int $entityId): array
   {
      global $DB;
      if ($entityId < 0 || !$DB->tableExists(self::getTable())) {
         return [];
      }
      $configs = [];
      foreach ($DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityId],
      ]) as $row) {
         $configs[(int) $row['itilcategories_id']] = $row;
      }
      return $configs;
   }

   public static function isEntityEnabled(int $entityId): bool
   {
      $configs = self::getCategoriesForEntity($entityId);
      if (isset($configs[0])) {
         return (int) $configs[0]['is_active'] === 1;
      }
      // Before the entity switch was introduced, an active category meant the
      // entity itself was active. Keep that behavior on upgrade.
      foreach ($configs as $categoryId => $config) {
         if ($categoryId > 0 && (int) $config['is_active'] === 1) {
            return true;
         }
      }
      return false;
   }

   /** @return array<int, array<string, mixed>> */
   public static function getAvailableEntities(): array
   {
      global $DB;
      $ids = array_map('intval', (array) ($_SESSION['glpiactiveentities'] ?? [Session::getActiveEntity()]));
      $ids = array_values(array_filter($ids, static function (int $id): bool {
         return $id >= 0 && Session::haveAccessToEntity($id);
      }));
      if ($ids === []) {
         return [];
      }
      return iterator_to_array($DB->request([
         'SELECT' => ['id', 'name', 'completename'],
         'FROM' => 'glpi_entities',
         'WHERE' => ['id' => $ids],
         'ORDER' => 'completename',
      ]));
   }

   /** @return array<int, array<string, mixed>> */
   public static function getAvailableCategories(int $entityId): array
   {
      global $DB;
      if ($entityId < 0 || !Session::haveAccessToEntity($entityId)) {
         return [];
      }

      // The native ITIL-category dropdown includes categories defined in the
      // selected entity and recursive categories inherited from its parents.
      // Mirror that behavior here so a child entity exposes the same valid
      // choices a ticket in that entity can receive.
      $ancestorIds = array_values(array_unique(array_map(
         'intval',
         getAncestorsOf(Entity::getTable(), $entityId)
      )));
      $entityCriteria = ['entities_id' => $entityId];
      $criteria = [$entityCriteria];
      if ($ancestorIds !== []) {
         $criteria[] = [
            'entities_id' => $ancestorIds,
            'is_recursive' => 1,
         ];
      }

      return iterator_to_array($DB->request([
         'SELECT' => ['id', 'name', 'completename'],
         'FROM' => 'glpi_itilcategories',
         'WHERE' => [
            'OR' => $criteria,
         ],
         'ORDER' => 'completename',
      ]));
   }

   /**
    * Returns the categories directly owned by the entities available to the
    * current administrator. This is used by the configuration screen, where
    * every category is managed in one table together with its entity.
    *
    * @return array<int, array<string, mixed>>
    */
   public static function getAllAvailableCategories(): array
   {
      global $DB;
      $entityIds = array_map(static function (array $entity): int {
         return (int) $entity['id'];
      }, self::getAvailableEntities());
      if ($entityIds === []) {
         return [];
      }

      $where = ['entities_id' => $entityIds];
      if ($DB->fieldExists('glpi_itilcategories', 'is_deleted')) {
         $where['is_deleted'] = 0;
      }

      return iterator_to_array($DB->request([
         'SELECT' => ['id', 'entities_id', 'name', 'completename'],
         'FROM' => 'glpi_itilcategories',
         'WHERE' => $where,
         'ORDER' => ['entities_id', 'completename'],
      ]));
   }

   /** @return array<string, array<string, mixed>> */
   public static function getAllCategoryStates(): array
   {
      global $DB;
      $states = [];
      $entityIds = array_map(static function (array $entity): int {
         return (int) $entity['id'];
      }, self::getAvailableEntities());
      if ($entityIds === [] || !$DB->tableExists(self::getTable())) {
         return $states;
      }
      foreach ($DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityIds],
      ]) as $state) {
         if ((int) $state['itilcategories_id'] === 0) {
            continue;
         }
         $states[(int) $state['entities_id'] . ':' . (int) $state['itilcategories_id']] = $state;
      }
      return $states;
   }

   public static function isEnabledForTicket(Ticket $ticket): bool
   {
      global $DB;

      // Once the request has entered Project+, its phase row is the source of
      // truth. During hierarchical routing the ticket may legitimately move
      // to an entity with no active Project+ category and therefore receive
      // an empty category without losing access to the ongoing workflow.
      if ((int) $ticket->getID() > 0
         && $DB->tableExists(TicketPhase::getTable())
         && TicketPhase::getForTicket((int) $ticket->getID()) !== null) {
         return true;
      }

      $entityId = (int) $ticket->getEntityID();
      $categoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
      $configs = self::getCategoriesForEntity($entityId);
      return $categoryId > 0
         && isset($configs[$categoryId])
         && (int) $configs[$categoryId]['is_active'] === 1;
   }

   public static function saveEntityState(int $entityId, bool $isActive): bool
   {
      return self::saveState($entityId, 0, $isActive);
   }

   public static function saveCategoryState(int $entityId, int $categoryId, bool $isActive): bool
   {
      if ($categoryId <= 0) {
         return false;
      }
      foreach (self::getAvailableCategories($entityId) as $category) {
         if ((int) $category['id'] === $categoryId) {
            return self::saveState($entityId, $categoryId, $isActive);
         }
      }
      return false;
   }

   private static function saveState(int $entityId, int $categoryId, bool $isActive): bool
   {
      global $DB;
      if ($entityId < 0 || !Session::haveAccessToEntity($entityId)) {
         return false;
      }
      $now = date('Y-m-d H:i:s');
      $existing = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityId, 'itilcategories_id' => $categoryId],
         'LIMIT' => 1,
      ])->current();
      $data = ['is_active' => $isActive ? 1 : 0, 'users_id' => (int) Session::getLoginUserID(), 'date_mod' => $now];
      if ($existing) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => (int) $existing['id']]);
      }
      return (bool) $DB->insert(self::getTable(), $data + [
         'entities_id' => $entityId,
         'itilcategories_id' => $categoryId,
         'date_creation' => $now,
      ]);
   }

   public static function saveForEntity(int $entityId, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($entityId < 0 || !Session::haveAccessToEntity($entityId)) {
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
