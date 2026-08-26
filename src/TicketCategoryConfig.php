<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Dropdown;
use Entity;
use Group;
use Html;
use ITILCategory;
use MassiveAction;
use Session;
use User;

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
   public static function getFormURL($full = true): string
   {
      global $CFG_GLPI;

      return ($full ? rtrim((string) $CFG_GLPI['root_doc'], '/') : '')
         . '/plugins/projectplus/front/ticketconfig.form.php';
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
      /** @var DBmysql $DB */
      global $DB;

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
      // Guarded by fieldExists(): a plugin upgrade that has not finished its
      // migration yet (or a downgrade) must not turn this list into a fatal
      // SQL error just because these columns are momentarily absent.
      foreach (['project_manager_users_id' => __('Gerente de projetos', 'projectplus'), 'portfolio_manager_users_id' => __('Gestor de portfólio', 'projectplus')] as $field => $name) {
         if (!$DB->fieldExists(self::getTable(), $field)) {
            continue;
         }
         // The link field belongs to the Project+ configuration row. Keeping
         // it at the option level is required by native Search; placing it in
         // joinparams makes GLPI fall back to users_id (the last editor),
         // which incorrectly displays "glpi" for an empty responsibility.
         $options[] = [
            'id' => count($options) + 10,
            'table' => User::getTable(),
            'field' => 'name',
            'linkfield' => $field,
            'name' => $name,
            'datatype' => 'dropdown',
            'itemtype' => User::class,
         ];
      }
      if ($DB->fieldExists(self::getTable(), 'is_recursive')) {
         $options[] = ['id' => 30, 'table' => self::getTable(), 'field' => 'is_recursive', 'name' => __('Recursivo', 'projectplus'), 'datatype' => 'bool'];
      }
      $options[] = [
         // This is deliberately virtual: it is only a stable UI action and
         // must never become a database field or a search criterion.
         'id' => 31,
         'table' => self::getTable(),
         'field' => '_virtual_action',
         'name' => __('Ação', 'projectplus'),
         'datatype' => 'text',
         'nosearch' => true,
         'massiveaction' => false,
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
         $actions[$prefix . 'configure_responsibilities'] = __('Definir responsáveis, recursividade e ativação', 'projectplus');
      }
      return $actions;
   }

   /**
    * Renders a native GLPI massive-action form. A value of -1 preserves the
    * existing setting, while the empty user choice deliberately clears it.
    */
   public static function showMassiveActionsSubForm(MassiveAction $ma)
   {
      if ($ma->getAction() !== 'configure_responsibilities') {
         return false;
      }

      $keep = __('Não alterar', 'projectplus');
      echo "<div class='row g-3'>";
      echo "<div class='col-md-6'><label class='form-label' for='dropdown_project_manager_users_id'>"
         . __('Gerente de projetos', 'projectplus') . '</label>';
      User::dropdown([
         'name' => 'project_manager_users_id',
         'value' => -1,
         'toadd' => [-1 => $keep],
         'emptylabel' => __('Em branco', 'projectplus'),
         'right' => ProjectProfile::RIGHT_FILL_TIP,
         'entity' => 0,
         'entity_sons' => true,
         'width' => '100%',
      ]);
      echo '</div>';

      echo "<div class='col-md-6'><label class='form-label' for='dropdown_portfolio_manager_users_id'>"
         . __('Gestor de portfólio', 'projectplus') . '</label>';
      User::dropdown([
         'name' => 'portfolio_manager_users_id',
         'value' => -1,
         'toadd' => [-1 => $keep],
         'emptylabel' => __('Em branco', 'projectplus'),
         'right' => ProjectProfile::RIGHT_PRIORITY_STRATEGIC,
         'entity' => 0,
         'entity_sons' => true,
         'width' => '100%',
      ]);
      echo '</div>';

      foreach ([
         'is_recursive' => __('Recursivo', 'projectplus'),
         'is_active' => __('Ativo', 'projectplus'),
      ] as $field => $label) {
         echo "<div class='col-md-6'><label class='form-label' for='dropdown_{$field}'>" . $label . '</label>';
         Dropdown::showFromArray($field, [
            -1 => $keep,
            0 => __('Não'),
            1 => __('Sim'),
         ], ['value' => -1]);
         echo '</div>';
      }
      echo '</div>';
      echo Html::submit(_sx('button', 'Post'), [
         'id' => 'projectplusmassiveconfig',
         'class' => 'btn btn-primary mt-3',
      ]);

      return true;
   }

   public static function processMassiveActionsForOneItemtype(
      MassiveAction $ma,
      CommonDBTM $item,
      array $ids
   ) {
      $action = $ma->getAction();
      if (!in_array($action, ['activate', 'deactivate', 'configure_responsibilities'], true)) {
         return;
      }

      $massiveValues = [
         'project_manager_users_id' => (int) ($_POST['project_manager_users_id'] ?? -1),
         'portfolio_manager_users_id' => (int) ($_POST['portfolio_manager_users_id'] ?? -1),
         'is_recursive' => (int) ($_POST['is_recursive'] ?? -1),
         'is_active' => (int) ($_POST['is_active'] ?? -1),
      ];
      $rights = [
         'project_manager_users_id' => ProjectProfile::RIGHT_FILL_TIP,
         'portfolio_manager_users_id' => ProjectProfile::RIGHT_PRIORITY_STRATEGIC,
      ];
      if ($action === 'configure_responsibilities' && !array_filter($massiveValues, static function (int $value): bool {
         return $value >= 0;
      })) {
         Session::addMessageAfterRedirect(__('Selecione ao menos uma configuração para alterar.', 'projectplus'), false, ERROR);
      }

      foreach (array_keys($ids) as $id) {
         if (!$item->getFromDB((int) $id)
            || !Session::haveAccessToEntity((int) $item->fields['entities_id'], true)
            || !Session::haveRight('config', UPDATE)) {
            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
            continue;
         }

         $input = [
            'id' => (int) $id,
            'users_id' => (int) Session::getLoginUserID(),
         ];
         if ($action === 'activate' || $action === 'deactivate') {
            $input['is_active'] = $action === 'activate' ? 1 : 0;
         } else {
            $valid = true;
            foreach ($massiveValues as $field => $value) {
               if ($value < 0) {
                  continue;
               }
               if (isset($rights[$field]) && $value > 0) {
                  $user = new User();
                  if (!$user->getFromDB($value) || !$user->hasRight($rights[$field], READ, (int) $item->fields['entities_id'])) {
                     $valid = false;
                     break;
                  }
               }
               $input[$field] = $value;
            }
            if (!$valid) {
               Session::addMessageAfterRedirect(
                  __('O responsável selecionado não possui a permissão Project+ exigida em uma das entidades selecionadas.', 'projectplus'),
                  false,
                  ERROR
               );
            }
            if (!$valid || count($input) === 2) {
               $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
               continue;
            }
         }

         $updated = $item->update($input);
         $ma->itemDone(
            $item->getType(),
            $id,
            $updated ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO
         );
      }
   }
}
