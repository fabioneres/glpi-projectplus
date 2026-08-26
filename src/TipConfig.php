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
   public static function getForEntity(int $entityId, string $documentType = 'pretip'): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $documentType = self::normalizeDocumentType($documentType);
      $isIntent = $documentType === 'pretip';
      $default = [
         'id' => 0,
         'entities_id' => $entityId,
         'document_type' => $documentType,
         'is_active' => 1,
         'title' => $isIntent
            ? __('TIP - Termo de Intenção de Projeto', 'projectplus')
            : __('TIP - Detalhamento Técnico', 'projectplus'),
         'introduction' => $isIntent
            ? __('Preencha as informações para análise da demanda de intenção de projeto.', 'projectplus')
            : __('Preencha as informações técnicas para detalhamento do projeto.', 'projectplus'),
      ];
      if (!$DB->tableExists(self::getTable())) {
         return $default;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityId, 'document_type' => $documentType],
         'LIMIT' => 1,
      ])->current();
      return $row ? array_merge($default, $row) : $default;
   }

   public static function isActiveForEntity(int $entityId, string $documentType = 'pretip'): bool
   {
      return (int) self::getForEntity($entityId, $documentType)['is_active'] === 1;
   }

   public static function saveForEntity(int $entityId, string $documentType, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
         return false;
      }
      $documentType = self::normalizeDocumentType($documentType);
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
      $current = self::getForEntity($entityId, $documentType);
      if ((int) $current['id'] > 0) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => (int) $current['id']]);
      }
      return (bool) $DB->insert(self::getTable(), $data + [
         'entities_id' => $entityId,
         'document_type' => $documentType,
         'date_creation' => $now,
      ]);
   }

   private static function normalizeDocumentType(string $documentType): string
   {
      return $documentType === 'pretip' ? 'pretip' : 'tip';
   }
}
