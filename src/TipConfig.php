<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TipConfig
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_tipconfigs';
   }

   /** @return array<string, mixed> */
   public static function getForEntity(int $entityId): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $default = [
         'id' => 0,
         'entities_id' => $entityId,
         'is_active' => 1,
         'title' => __('Termo de Intenção do Projeto - TIP', 'projectplus'),
         'introduction' => __('Preencha as informações para análise da demanda de intenção de projeto.', 'projectplus'),
      ];
      if (!$DB->tableExists(self::getTable())) {
         return $default;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityId],
         'LIMIT' => 1,
      ])->current();
      return $row ? array_merge($default, $row) : $default;
   }

   public static function isActiveForEntity(int $entityId): bool
   {
      return (int) self::getForEntity($entityId)['is_active'] === 1;
   }

   public static function saveForEntity(int $entityId, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
         return false;
      }
      $title = trim((string) ($input['title'] ?? ''));
      if ($title === '') {
         return false;
      }
      $now = date('Y-m-d H:i:s');
      $data = [
         'is_active' => (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0,
         'title' => substr($title, 0, 255),
         'introduction' => trim((string) ($input['introduction'] ?? '')),
         'users_id' => (int) Session::getLoginUserID(),
         'date_mod' => $now,
      ];
      $current = self::getForEntity($entityId);
      if ((int) $current['id'] > 0) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => (int) $current['id']]);
      }
      return (bool) $DB->insert(self::getTable(), $data + ['entities_id' => $entityId, 'date_creation' => $now]);
   }
}
