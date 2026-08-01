<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Entity;
use Group;
use ITILCategory;
use MassiveAction;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Native GLPI-search representation of a Project+ ticket-category setting.
 *
 * A record is kept for each visible ITIL category. This deliberately turns the
 * configuration row into a CommonDBTM object so that Search and MassiveAction
 * can supply their complete native interfaces instead of a custom imitation.
 */
class TicketCategoryConfig extends CommonDBTM
{
   public static $rightname = 'config';
   public $dohistory = true;

   public static function getTable($classname = null): string
   {
      return TicketConfig::getTable();
   }

   public static function getTypeName($nb = 0)
   {
      return _n('Categoria do fluxo de projeto', 'Categorias do fluxo de projeto', $nb, 'projectplus');
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;

      return ($full ? rtrim((string) $CFG_GLPI['root_doc'], '/') : '')
         . '/plugins/projectplus/front/ticketconfig.php';
   }

   /**
    * Ensures that all ITIL categories visible to the administrator have a
    * persistent Project+ state. Existing choices are never overwritten.
    */
   public static function synchronizeAvailableCategories(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!$DB->tableExists(self::getTable())) {
         return;
      }

      $states = TicketConfig::getAllCategoryStates();
      $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
      $userId = (int) Session::getLoginUserID();
      foreach (TicketConfig::getAllAvailableCategories() as $category) {
         $entityId = (int) $category['entities_id'];
         $categoryId = (int) $category['id'];
         $key = $entityId . ':' . $categoryId;
         if (isset($states[$key])) {
            continue;
         }

         $DB->insert(self::getTable(), [
            'entities_id' => $entityId,
            'itilcategories_id' => $categoryId,
            'is_active' => 0,
            'users_id' => $userId,
            'date_creation' => $now,
            'date_mod' => $now,
         ]);
      }
   }

   public function rawSearchOptions()
   {
      $options = [];
      $options[] = [
         'id' => 'common',
         'name' => __('Características'),
      ];
      $options[] = [
         'id' => 1,
         'table' => self::getTable(),
         'field' => 'id',
         'name' => __('ID'),
         'datatype' => 'number',
         'massiveaction' => false,
      ];
      $options[] = [
         'id' => 2,
         'table' => ITILCategory::getTable(),
         'field' => 'completename',
         'name' => __('Categoria ITIL', 'projectplus'),
         'datatype' => 'itemlink',
         'linkfield' => 'itilcategories_id',
         'itemtype' => ITILCategory::class,
         'forcegroupby' => true,
      ];
      $options[] = [
         // GLPI reserves 80 as the standard entity search field. Reusing it
         // gives this list the same automatic entity display and filtering
         // behavior as native entity-aware item lists.
         'id' => 80,
         'table' => Entity::getTable(),
         'field' => 'completename',
         'name' => Entity::getTypeName(1),
         'datatype' => 'itemlink',
         'itemtype' => Entity::class,
         'joinparams' => [
            'jointype' => '',
            'linkfield' => 'entities_id',
         ],
         'forcegroupby' => true,
      ];
      $options[] = [
         'id' => 4,
         'table' => self::getTable(),
         'field' => 'is_active',
         'name' => __('Ativo', 'projectplus'),
         'datatype' => 'bool',
      ];
      $options[] = [
         'id' => 5,
         'table' => Group::getTable(),
         'field' => 'id',
         'name' => __('Grupo', 'projectplus'),
         'datatype' => 'dropdown',
         'itemtype' => Group::class,
         'joinparams' => [
            'beforejoin' => [
               'table' => ITILCategory::getTable(),
               'joinparams' => [
                  'jointype' => '',
                  'linkfield' => 'itilcategories_id',
               ],
            ],
            'jointype' => '',
            'linkfield' => 'groups_id',
         ],
      ];

      return $options;
   }

   /** Avoid generic edits/deletions: the only managed state is activation. */
   public function getForbiddenStandardMassiveAction()
   {
      return [
         'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'update',
         'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'delete',
         'CommonDBTM' . MassiveAction::CLASS_ACTION_SEPARATOR . 'purge',
      ];
   }

   public function getSpecificMassiveActions($checkitem = null)
   {
      $actions = parent::getSpecificMassiveActions($checkitem);
      if (Session::haveRight('config', UPDATE)) {
         $prefix = self::class . MassiveAction::CLASS_ACTION_SEPARATOR;
         $actions[$prefix . 'activate'] = __('Ativar para solicitações de projeto', 'projectplus');
         $actions[$prefix . 'deactivate'] = __('Desativar para solicitações de projeto', 'projectplus');
      }
      return $actions;
   }

   public static function processMassiveActionsForOneItemtype(
      MassiveAction $ma,
      CommonDBTM $item,
      array $ids
   ) {
      $action = $ma->getAction();
      if (!in_array($action, ['activate', 'deactivate'], true)) {
         return;
      }

      foreach (array_keys($ids) as $id) {
         if (!$item->getFromDB((int) $id)
            || !Session::haveAccessToEntity((int) $item->fields['entities_id'], true)
            || !Session::haveRight('config', UPDATE)) {
            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
            continue;
         }

         $updated = $item->update([
            'id' => (int) $id,
            'is_active' => $action === 'activate' ? 1 : 0,
            'users_id' => (int) Session::getLoginUserID(),
         ]);
         $ma->itemDone(
            $item->getType(),
            $id,
            $updated ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO
         );
      }
   }
}
