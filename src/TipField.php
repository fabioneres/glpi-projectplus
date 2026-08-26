<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Configurable fields for the two independent TIP documents. */
class TipField
{
   private const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'section'];

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_tipfields';
   }

   /** @return array<string, string> */
   public static function getTypes(): array
   {
      return [
         'text'     => __('Texto curto', 'projectplus'),
         'textarea' => __('Texto longo', 'projectplus'),
         'number'   => __('Número', 'projectplus'),
         'date'     => __('Data', 'projectplus'),
         'select'   => __('Lista suspensa', 'projectplus'),
         'section'  => __('Título de seção', 'projectplus'),
      ];
   }

   public static function normalizeDocumentType(string $documentType): string
   {
      return $documentType === 'pretip' ? 'pretip' : 'tip';
   }

   public static function ensureDefaults(int $entityId, string $documentType = 'pretip'): void
   {
      // New entities start with no fields. Existing configurations are never
      // removed or regenerated automatically.
   }

   /** @return array<int, array<string, mixed>> */
   public static function getForEntity(int $entityId, string $documentType = 'pretip', bool $activeOnly = true): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $documentType = self::normalizeDocumentType($documentType);
      self::ensureDefaults($entityId, $documentType);
      $where = ['entities_id' => $entityId, 'document_type' => $documentType];
      if ($activeOnly) {
         $where['is_active'] = 1;
      }
      return iterator_to_array($DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => $where,
         'ORDER' => ['sort_order ASC', 'id ASC'],
      ]), false);
   }

   /** @return array<string, mixed>|null */
   public static function getById(int $id, int $entityId, string $documentType = 'pretip'): ?array
   {
      /** @var DBmysql $DB */
      global $DB;

      $row = $DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => [
            'id'            => $id,
            'entities_id'   => $entityId,
            'document_type' => self::normalizeDocumentType($documentType),
         ],
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function save(int $entityId, string $documentType, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!Session::haveAccessToEntity($entityId, true)) {
         return false;
      }
      $documentType = self::normalizeDocumentType($documentType);
      $fieldType = (string) ($input['field_type'] ?? 'text');
      $label = trim((string) ($input['label'] ?? ''));
      if ($label === '' || !in_array($fieldType, self::TYPES, true)) {
         return false;
      }
      $now = date('Y-m-d H:i:s');
      $data = [
         'label'       => substr($label, 0, 255),
         'description' => trim((string) ($input['description'] ?? '')),
         'field_type'  => $fieldType,
         'values'      => trim((string) ($input['values'] ?? '')),
         'source'      => $fieldType === 'section' ? '' : substr(trim((string) ($input['source'] ?? '')), 0, 128),
         'is_required' => $fieldType === 'section' ? 0 : ((int) ($input['is_required'] ?? 0) === 1 ? 1 : 0),
         'is_active'   => (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0,
         'sort_order'  => max(0, (int) ($input['sort_order'] ?? 0)),
         'date_mod'    => $now,
      ];
      $id = (int) ($input['id'] ?? 0);
      if ($id > 0 && self::getById($id, $entityId, $documentType) !== null) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => $id]);
      }
      $data += [
         'entities_id'   => $entityId,
         'document_type' => $documentType,
         'name'          => '',
         'date_creation' => $now,
      ];
      if (!$DB->insert(self::getTable(), $data)) {
         return false;
      }
      $id = (int) $DB->insertId();
      return (bool) $DB->update(self::getTable(), ['name' => 'field_' . $id], ['id' => $id]);
   }

   public static function delete(int $id, int $entityId, string $documentType): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!Session::haveAccessToEntity($entityId, true)
         || self::getById($id, $entityId, $documentType) === null) {
         return false;
      }
      return (bool) $DB->delete(self::getTable(), ['id' => $id]);
   }
}
