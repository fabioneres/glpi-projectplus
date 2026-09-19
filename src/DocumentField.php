<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use Entity;
use MassiveAction;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Configurable fields for TAP, TEP and TA. */
class DocumentField extends CommonDBTM
{
   public static $rightname = 'config';

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_documentfields';
   }

   public static function getTypeName($nb = 0)
   {
      return _n('Campo documental', 'Campos documentais', $nb, 'projectplus');
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;

      return ($full ? rtrim((string) $CFG_GLPI['root_doc'], '/') : '')
         . '/plugins/projectplus/front/documentfields.php';
   }

   /** @return array<string, string> */
   public static function types(): array
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

   public function rawSearchOptions()
   {
      return TipField::getFieldSearchOptions(self::getTable());
   }

   public function getForbiddenStandardMassiveAction()
   {
      return [
         'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'update',
         'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'delete',
         'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'purge',
      ];
   }
   public function getSpecificMassiveActions($checkitem = null) {
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
   public static function processMassiveActionsForOneItemtype(
      MassiveAction $ma,
      CommonDBTM $item,
      array $ids
   ) {
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
            $documentType = (string) $item->fields['document_type'];
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
   /** @return array<int, array<string, mixed>> */
   public static function get(int $entity, string $type, bool $active = false): array
   {
      global $DB;

      $entityIds = array_values(array_unique(array_merge(
         [$entity],
         array_map('intval', array_values(getAncestorsOf(Entity::getTable(), $entity)))
      )));
      $where = ['entities_id' => $entityIds, 'document_type' => $type];
      if ($DB->fieldExists(self::getTable(), 'is_deleted')) {
         $where['is_deleted'] = 0;
      }
      if ($active) {
         $where['is_active'] = 1;
      }
      $rows = iterator_to_array($DB->request([
         'FROM' => self::getTable(),
         'WHERE' => $where,
         'ORDER' => ['sort_order ASC', 'id ASC'],
      ]), false);
      return array_values(array_filter(array_map(static function (array $row) use ($entity): array {
         $row['is_inherited'] = (int) $row['entities_id'] !== $entity ? 1 : 0;
         return $row;
      }, $rows), static function (array $row) use ($entity): bool {
         return (int) $row['entities_id'] === $entity || (int) ($row['is_recursive'] ?? 0) === 1;
      }));
   }

   /** @return array<string, mixed>|null */
   public static function one(int $id, int $entity, string $type): ?array
   {
      global $DB;

      $where = ['id' => $id, 'entities_id' => $entity, 'document_type' => $type];
      if ($DB->fieldExists(self::getTable(), 'is_deleted')) {
         $where['is_deleted'] = 0;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => $where,
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function save(int $entity, string $type, array $in): bool
   {
      global $DB;

      if (!in_array($type, ['tap', 'tep', 'ta'], true) || !Session::haveAccessToEntity($entity)) {
         return false;
      }
      $label = trim((string) ($in['label'] ?? ''));
      $fieldType = (string) ($in['field_type'] ?? 'text');
      if ($label === '' || !isset(self::types()[$fieldType])) {
         return false;
      }
      if (in_array($fieldType, ['select', 'radio', 'checkboxes', 'multiselect'], true)
         && FieldValue::options(['values' => $in['values'] ?? '']) === []) {
         return false;
      }
      $data = [
         'label' => substr($label, 0, 255),
         'description' => trim((string) ($in['description'] ?? '')),
         'field_type' => $fieldType,
         'values' => trim((string) ($in['values'] ?? '')),
         'source' => FieldValue::isDisplayOnly($fieldType) || FieldValue::isFileType($fieldType)
            ? ''
            : substr(trim((string) ($in['source'] ?? '')), 0, 128),
         'display_rule' => 'always',
         'condition_field_id' => 0,
         'condition_operator' => 'equals',
         'condition_value' => '',
         'is_required' => FieldValue::isDisplayOnly($fieldType) ? 0 : (int) !empty($in['is_required']),
         'is_active' => (int) !empty($in['is_active']),
         'is_recursive' => (int) !empty($in['is_recursive']),
         'sort_order' => max(0, (int) ($in['sort_order'] ?? 0)),
         'date_mod' => date('Y-m-d H:i:s'),
      ];
      $id = (int) ($in['id'] ?? 0);
      if ($id > 0 && self::one($id, $entity, $type) !== null) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => $id]);
      }
      return (bool) $DB->insert(self::getTable(), $data + [
         'entities_id' => $entity,
         'document_type' => $type,
         'date_creation' => date('Y-m-d H:i:s'),
      ]);
   }

   public static function deleteForEntity(int $id, int $entity, string $type): bool
   {
      global $DB;
      if (!Session::haveAccessToEntity($entity) || self::one($id, $entity, $type) === null) {
         return false;
      }
      $DB->beginTransaction();
      try {
         FieldSource::clearFieldReferences($type, $id);
         $used = self::isUsedInDrafts($id, $type);
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

   private static function isUsedInDrafts(int $id, string $type): bool
   {
      global $DB;
      if (!$DB->tableExists(DocumentDraft::getTable())) {
         return false;
      }
      foreach ($DB->request([
         'SELECT' => ['content', 'generated_content'],
         'FROM' => DocumentDraft::getTable(),
         'WHERE' => ['document_type' => $type],
      ]) as $draft) {
         foreach (['content', 'generated_content'] as $column) {
            $answers = json_decode((string) ($draft[$column] ?? ''), true);
            if (is_array($answers) && array_key_exists($id, $answers)) {
               return true;
            }
         }
      }
      return false;
   }
}
