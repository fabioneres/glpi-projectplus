<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Entity;
use MassiveAction;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Configurable fields for the two independent TIP documents. */
class TipField extends CommonDBTM
{
   public static $rightname = 'config';
   private const TYPES = ['text', 'textarea', 'number', 'decimal', 'email', 'date', 'datetime', 'time', 'select', 'radio', 'checkboxes', 'multiselect', FieldAttachment::TYPE, 'section', 'info'];

   public static function getTypeName($nb = 0)
   {
      return _n('Campo do TIP', 'Campos do TIP', $nb, 'projectplus');
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim((string) $CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/tipconfig.php';
   }

   public function rawSearchOptions()
   {
      return self::getFieldSearchOptions();
   }

   /** @return array<int, array<string, mixed>> */
   public static function getFieldSearchOptions(?string $table = null): array
   {
      $table = $table ?? self::getTable();

      return [
         ['id' => 1, 'table' => $table, 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false],
         ['id' => 2, 'table' => $table, 'field' => 'sort_order', 'name' => __('Ordem', 'projectplus'), 'datatype' => 'number'],
         ['id' => 3, 'table' => $table, 'field' => 'label', 'name' => __('Campo', 'projectplus'), 'datatype' => 'text'],
         ['id' => 4, 'table' => $table, 'field' => 'field_type', 'name' => __('Tipo', 'projectplus'), 'datatype' => 'text'],
         ['id' => 5, 'table' => $table, 'field' => 'is_required', 'name' => __('Obrigatório', 'projectplus'), 'datatype' => 'bool'],
         ['id' => 6, 'table' => $table, 'field' => 'is_active', 'name' => __('Ativo', 'projectplus'), 'datatype' => 'bool'],
         ['id' => 9, 'table' => $table, 'field' => 'is_recursive', 'name' => __('Entidades filhas', 'projectplus'), 'datatype' => 'bool'],
         ['id' => 7, 'table' => $table, 'field' => '_virtual_action', 'name' => __('Ação', 'projectplus'), 'datatype' => 'text', 'nosearch' => true, 'massiveaction' => false],
         ['id' => 8, 'table' => $table, 'field' => 'document_type', 'name' => __('Tipo de documento', 'projectplus'), 'datatype' => 'text'],
         ['id' => 80, 'table' => Entity::getTable(), 'field' => 'completename', 'name' => Entity::getTypeName(1), 'datatype' => 'itemlink', 'itemtype' => Entity::class, 'joinparams' => ['jointype' => '', 'linkfield' => 'entities_id'], 'forcegroupby' => true],
      ];
   }

   public function getForbiddenStandardMassiveAction()
   {
      return ['CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'update', 'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'delete', 'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'purge'];
   }

   public function getSpecificMassiveActions($checkitem = null)
   {
      $actions = parent::getSpecificMassiveActions($checkitem);
      if (Session::haveRight('config', UPDATE)) {
         $prefix = self::class . MassiveAction::CLASS_ACTION_SEPARATOR;
         $actions[$prefix . 'set_required'] = __('Marcar como obrigatório', 'projectplus');
         $actions[$prefix . 'set_optional'] = __('Desmarcar como obrigatório', 'projectplus');
         $actions[$prefix . 'activate'] = __('Ativar', 'projectplus');
         $actions[$prefix . 'deactivate'] = __('Desativar', 'projectplus');
         $actions[$prefix . 'set_recursive'] = __('Disponibilizar para entidades filhas', 'projectplus');
         $actions[$prefix . 'unset_recursive'] = __('Restringir à entidade atual', 'projectplus');
         $actions[$prefix . 'delete_field'] = __('Excluir campos selecionados', 'projectplus');
      }
      return $actions;
   }

   public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
   {
      $action = $ma->getAction();
      if (!in_array($action, ['set_required', 'set_optional', 'activate', 'deactivate', 'set_recursive', 'unset_recursive', 'delete_field'], true)) {
         return;
      }
      foreach (array_keys($ids) as $id) {
         if (!$item->getFromDB((int) $id) || !Session::haveAccessToEntity((int) $item->fields['entities_id'])) {
            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
            continue;
         }
         $input = ['id' => (int) $id];
         if ($action === 'delete_field') {
            $entityId = (int) $item->fields['entities_id'];
            $documentType = self::normalizeDocumentType((string) $item->fields['document_type']);
            $ma->itemDone(
               $item->getType(),
               $id,
               self::deleteForEntity((int) $id, $entityId, $documentType)
                  ? MassiveAction::ACTION_OK
                  : MassiveAction::ACTION_KO
            );
            continue;
         }
         if ($action === 'set_required') {
            $input['is_required'] = FieldValue::isDisplayOnly((string) $item->fields['field_type']) ? 0 : 1;
         } elseif ($action === 'set_optional') {
            $input['is_required'] = 0;
         } elseif (in_array($action, ['activate', 'deactivate'], true)) {
            $input['is_active'] = $action === 'activate' ? 1 : 0;
         } else {
            $input['is_recursive'] = $action === 'set_recursive' ? 1 : 0;
         }
         $ma->itemDone($item->getType(), $id, $item->update($input) ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
      }
   }

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
         'number'   => __('Número inteiro', 'projectplus'),
         'decimal'  => __('Número decimal', 'projectplus'),
         'email'    => __('E-mail', 'projectplus'),
         'date'     => __('Data', 'projectplus'),
         'datetime' => __('Data e hora', 'projectplus'),
         'time'     => __('Hora', 'projectplus'),
         'select'   => __('Lista suspensa', 'projectplus'),
         'radio'    => __('Seleção única', 'projectplus'),
         'checkboxes' => __('Caixas de seleção', 'projectplus'),
         'multiselect' => __('Seleção múltipla', 'projectplus'),
         FieldAttachment::TYPE => __('Anexo', 'projectplus'),
         'section'  => __('Título de seção', 'projectplus'),
         'info'     => __('Texto informativo', 'projectplus'),
      ];
   }

   public static function normalizeDocumentType(string $documentType): string
   {
      return $documentType === TipDocumentType::DETAIL ? TipDocumentType::DETAIL : TipDocumentType::TIP;
   }

   public static function ensureDefaults(int $entityId, string $documentType = 'tip'): void
   {
      // New entities start with no fields. Existing configurations are never
      // removed or regenerated automatically.
   }

   /** @return array<int, array<string, mixed>> */
   public static function getForEntity(int $entityId, string $documentType = 'tip', bool $activeOnly = true): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $documentType = self::normalizeDocumentType($documentType);
      self::ensureDefaults($entityId, $documentType);
      $entityIds = array_values(array_unique(array_merge(
         [$entityId],
         array_map('intval', array_values(getAncestorsOf(Entity::getTable(), $entityId)))
      )));
      $where = ['entities_id' => $entityIds, 'document_type' => $documentType];
      if ($DB->fieldExists(self::getTable(), 'is_deleted')) {
         $where['is_deleted'] = 0;
      }
      if ($activeOnly) {
         $where['is_active'] = 1;
      }
      $rows = iterator_to_array($DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => $where,
         'ORDER' => ['sort_order ASC', 'id ASC'],
      ]), false);
      return array_values(array_filter(array_map(static function (array $row) use ($entityId): array {
         $row['is_inherited'] = (int) $row['entities_id'] !== $entityId ? 1 : 0;
         return $row;
      }, $rows), static function (array $row) use ($entityId): bool {
         return (int) $row['entities_id'] === $entityId || (int) ($row['is_recursive'] ?? 0) === 1;
      }));
   }

   /** @return array<string, mixed>|null */
   public static function getForEntityById(int $id, int $entityId, string $documentType = 'tip'): ?array
   {
      /** @var DBmysql $DB */
      global $DB;

      $where = [
         'id'            => $id,
         'entities_id'   => $entityId,
         'document_type' => self::normalizeDocumentType($documentType),
      ];
      if ($DB->fieldExists(self::getTable(), 'is_deleted')) {
         $where['is_deleted'] = 0;
      }
      $row = $DB->request([
         'FROM'  => self::getTable(),
         'WHERE' => $where,
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function save(int $entityId, string $documentType, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!Session::haveAccessToEntity($entityId)) {
         return false;
      }
      $documentType = self::normalizeDocumentType($documentType);
      $fieldType = (string) ($input['field_type'] ?? 'text');
      $label = trim((string) ($input['label'] ?? ''));
      if ($label === '' || !in_array($fieldType, self::TYPES, true)) {
         return false;
      }
      if (in_array($fieldType, ['select', 'radio', 'checkboxes', 'multiselect'], true)
         && FieldValue::options(['values' => $input['values'] ?? '']) === []) {
         return false;
      }
      $now = date('Y-m-d H:i:s');
      $data = [
         'label'       => substr($label, 0, 255),
         'description' => trim((string) ($input['description'] ?? '')),
         'field_type'  => $fieldType,
         'values'      => trim((string) ($input['values'] ?? '')),
         'source'      => FieldValue::isDisplayOnly($fieldType) || FieldValue::isFileType($fieldType)
            ? ''
            : substr(trim((string) ($input['source'] ?? '')), 0, 128),
         'display_rule' => 'always',
         'condition_field_id' => 0,
         'condition_operator' => 'equals',
         'condition_value' => '',
         'is_required' => FieldValue::isDisplayOnly($fieldType) ? 0 : ((int) ($input['is_required'] ?? 0) === 1 ? 1 : 0),
         'is_active'   => (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0,
         'is_recursive' => (int) ($input['is_recursive'] ?? 0) === 1 ? 1 : 0,
         'sort_order'  => max(0, (int) ($input['sort_order'] ?? 0)),
         'date_mod'    => $now,
      ];
      $id = (int) ($input['id'] ?? 0);
      if ($id > 0 && self::getForEntityById($id, $entityId, $documentType) !== null) {
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

   public static function deleteForEntity(int $id, int $entityId, string $documentType): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!Session::haveAccessToEntity($entityId)
         || self::getForEntityById($id, $entityId, $documentType) === null) {
         return false;
      }
      $documentType = self::normalizeDocumentType($documentType);
      $DB->beginTransaction();
      try {
         FieldSource::clearFieldReferences($documentType, $id);
         $used = (int) ($DB->request([
            'COUNT' => 'total',
            'FROM' => TicketIntentAnswer::getTable(),
            'WHERE' => ['plugin_projectplus_tipfields_id' => $id],
         ])->current()['total'] ?? 0) > 0;
         $ok = $used
            ? $DB->update(self::getTable(), ['is_active' => 0, 'is_deleted' => 1, 'date_mod' => date('Y-m-d H:i:s')], ['id' => $id])
            : $DB->delete(self::getTable(), ['id' => $id]);
         if (!$ok) {
            throw new \RuntimeException(__('Não foi possível excluir o campo.', 'projectplus'));
         }
         $DB->commit();
         return true;
      } catch (\Throwable $exception) {
         $DB->rollBack();
         return false;
      }
   }
}
