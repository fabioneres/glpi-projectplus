<?php

namespace GlpiPlugin\Projectplus;

use CommonITILActor;
use DBmysql;
use Glpi\RichText\RichText;
use Group;
use Session;
use Ticket;
use Ticket_User;
use User;

if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }

/** Maps native Project fields to values available in the originating request. */
class ProjectFieldConfig
{
   /** @return array<string,string> */
   public static function getTargets(): array
   {
      return [
         'date' => __('Data de criação', 'projectplus'), '_date_mod' => __('Última atualização', 'projectplus'),
         'name' => __('Nome', 'projectplus'),
         'code' => __('Código', 'projectplus'), 'priority' => __('Prioridade', 'projectplus'),
         'projectstates_id' => __('Status', 'projectplus'), 'projecttypes_id' => __('Tipo', 'projectplus'),
         'plugin_projectplus_portfoliobaskets_id' => __('Cesta Portfólio', 'projectplus'),
         'plugin_projectplus_pdticactions_id' => __('Ação PDTIC', 'projectplus'),
         'users_id' => __('Gerente', 'projectplus'), 'groups_id' => __('Grupo', 'projectplus'),
         'requester_area' => __('Área do solicitante', 'projectplus'),
         'requester_users_id' => __('Solicitante', 'projectplus'), 'requester_email' => __('E-mail do solicitante', 'projectplus'),
         'focal_point_users_id' => __('Ponto focal', 'projectplus'), 'focal_point_email' => __('E-mail do ponto focal', 'projectplus'),
         'plan_start_date' => __('Data planejada para começo', 'projectplus'), 'plan_end_date' => __('Data planejada para fim', 'projectplus'),
         'real_start_date' => __('Data real do começo', 'projectplus'), 'real_end_date' => __('Data real de fim', 'projectplus'),
         'plan_duration' => __('Duração planejada', 'projectplus'), 'real_duration' => __('Duração efetiva', 'projectplus'),
         'percent_done' => __('Percentual finalizado', 'projectplus'), 'content' => __('Descrição', 'projectplus'),
         'objective' => __('Objetivo', 'projectplus'), 'comment' => __('Comentários', 'projectplus'),
         'expected_result' => __('Produto/Resultado esperado', 'projectplus'),
      ];
   }

   /** @return array<string,string> Project fields whose origins are defined by the workflow. */
   public static function getFixedTargets(): array
   {
      return [
         'date' => __('Data de criação do chamado', 'projectplus'),
         '_date_mod' => __('Data de criação do projeto', 'projectplus'),
         'priority' => __('Prioridade do chamado de origem', 'projectplus'),
         'projectstates_id' => __('Não Iniciado', 'projectplus'),
         'percent_done' => __('0% (controle nativo do GLPI)', 'projectplus'),
         'users_id' => __('Gerente de Projetos da entidade', 'projectplus'),
         'requester_users_id' => __('Requerente do chamado', 'projectplus'),
         'requester_email' => __('E-mail do requerente', 'projectplus'),
      ];
   }

   /** @return array<string,string> */
   public static function getConfigurableTargets(): array
   {
      return array_diff_key(self::getTargets(), self::getFixedTargets());
   }

   public static function getTable($classname = null): string { return 'glpi_plugin_projectplus_projectfieldconfigs'; }

   /** @return array<string,string> */
   public static function getForEntity(int $entityId): array
   {
      global $DB;
      if ($entityId < 0 || !$DB->tableExists(self::getTable())) { return []; }
      $rows = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['entities_id' => $entityId]]);
      $result = [];
      foreach ($rows as $row) { $result[(string) $row['project_field']] = (string) $row['source']; }
      return $result;
   }

   public static function save(int $entityId, array $input): bool
   {
      global $DB;
      if (!Session::haveAccessToEntity($entityId, true)) { return false; }
      $allowed = FieldSource::getProjectDocumentOptions($entityId);
      foreach (self::getConfigurableTargets() as $field => $label) {
         $source = trim((string) (($input['sources'][$field] ?? '')));
         if (!isset($allowed[$source])) { $source = ''; }
         $DB->updateOrInsert(self::getTable(), [
            'entities_id' => $entityId, 'project_field' => $field, 'source' => $source,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
         ], ['entities_id' => $entityId, 'project_field' => $field]);
      }
      return true;
   }

   /** @return array<string,mixed> Values compatible with Project::add(). */
   public static function getPrefill(Ticket $ticket): array
   {
      $entityId = (int) $ticket->getEntityID(); $config = self::getForEntity($entityId);
      $input = self::getFixedPrefill($ticket);
      foreach (self::getConfigurableTargets() as $field => $label) {
         $source = (string) ($config[$field] ?? '');
         if ($source === '') { continue; }
         $value = FieldSource::getValue($ticket, $source);
         if ($value === '') { continue; }
         $converted = self::convert($field, $value, $ticket);
         if ($converted !== null && $converted !== '') { $input[$field] = $converted; }
      }
      return $input;
   }

   /** @return array<string,mixed> */
   private static function getFixedPrefill(Ticket $ticket): array
   {
      global $DB;
      $input = [
         'date' => (string) ($ticket->fields['date'] ?? ''),
         'priority' => (int) ($ticket->fields['priority'] ?? 0),
         'percent_done' => 0,
      ];

      $requester = $DB->request([
         'SELECT' => ['users_id'],
         'FROM' => Ticket_User::getTable(),
         'WHERE' => [
            'tickets_id' => (int) $ticket->getID(),
            'type' => CommonITILActor::REQUESTER,
         ],
         'ORDER' => ['id ASC'],
         'LIMIT' => 1,
      ])->current();
      $requesterId = (int) ($requester['users_id'] ?? 0);
      if ($requesterId > 0) {
         $input['requester_users_id'] = $requesterId;
         $input['requester_email'] = \UserEmail::getDefaultForUser($requesterId);
      }

      $manager = TicketRouting::resolve($ticket, 'project');
      $managerId = (int) ($manager['project_manager_users_id'] ?? 0);
      if ($managerId > 0) {
         $input['users_id'] = $managerId;
      }

      $state = $DB->request([
         'SELECT' => ['id'],
         'FROM' => 'glpi_projectstates',
         'WHERE' => ['name' => ['Não Iniciado', 'Nao Iniciado', 'Não iniciado', 'Nao iniciado']],
         'ORDER' => ['id ASC'],
         'LIMIT' => 1,
      ])->current();
      if ($state) {
         $input['projectstates_id'] = (int) $state['id'];
      }
      return array_filter($input, static function ($value): bool { return $value !== ''; });
   }

   /** @return int|string|null */
   private static function convert(string $field, string $value, Ticket $ticket)
   {
      if (in_array($field, ['users_id', 'requester_users_id', 'focal_point_users_id'], true)) { return self::findUser($value, $ticket, $field); }
      if ($field === 'groups_id') { return self::findDropdownId(Group::getTable(), $value, (int) $ticket->getEntityID()); }
      $tables = ['projectstates_id' => 'glpi_projectstates', 'projecttypes_id' => 'glpi_projecttypes', 'plugin_projectplus_portfoliobaskets_id' => PortfolioBasket::getTable(), 'plugin_projectplus_pdticactions_id' => PdticAction::getTable()];
      if (isset($tables[$field])) { return self::findDropdownId($tables[$field], $value, (int) $ticket->getEntityID()); }
      if ($field === 'priority') { return preg_match('/^[0-9]+$/', $value) ? max(0, min(5, (int) $value)) : null; }
      if ($field === 'percent_done') { return is_numeric(str_replace(',', '.', $value)) ? max(0, min(100, (float) str_replace(',', '.', $value))) : null; }
      if (in_array($field, ['plan_duration', 'real_duration'], true)) { return is_numeric($value) ? max(0, (int) $value) : null; }
      if (in_array($field, ['date', 'plan_start_date', 'plan_end_date', 'real_start_date', 'real_end_date'], true)) { return preg_match('/^\\d{4}-\\d{2}-\\d{2}(?:[ T]\\d{2}:\\d{2}(?::\\d{2})?)?$/', $value) ? $value : null; }
      if (in_array($field, ['content', 'objective', 'comment', 'expected_result'], true)) {
         return RichText::getTextFromHtml($value, false, false, false, false, true);
      }
      if (in_array($field, ['name', 'code', 'requester_area', 'requester_email', 'focal_point_email'], true)) {
         return RichText::getTextFromHtml($value, false, false, false);
      }
      return $value;
   }

   private static function findDropdownId(string $table, string $value, int $entityId): ?int
   {
      global $DB;
      if (ctype_digit($value)) { return (int) $value; }
      $where = ['name' => $value];
      if ($DB->fieldExists($table, 'entities_id')) { $where['entities_id'] = [0, $entityId]; }
      $row = $DB->request(['FROM' => $table, 'WHERE' => $where, 'ORDER' => ['entities_id DESC'], 'LIMIT' => 1])->current();
      return $row ? (int) $row['id'] : null;
   }

   private static function findUser(string $value, Ticket $ticket, string $field): ?int
   {
      global $DB;
      if (strpos($value, '@') === false && ctype_digit($value)) { return (int) $value; }
      if (in_array($field, ['requester_users_id', 'focal_point_users_id'], true) && strpos($value, '@') !== false) {
         $row = $DB->request(['FROM' => 'glpi_useremails', 'WHERE' => ['email' => $value], 'LIMIT' => 1])->current();
         if ($row) { return (int) $row['users_id']; }
      }
      $row = $DB->request(['FROM' => User::getTable(), 'WHERE' => ['name' => $value, 'is_active' => 1], 'LIMIT' => 1])->current();
      if ($row) { return (int) $row['id']; }
      if ($field === 'requester_users_id' && strpos($value, '@') === false) {
         $requester = $DB->request(['FROM' => Ticket_User::getTable(), 'WHERE' => ['tickets_id' => (int) $ticket->getID(), 'type' => CommonITILActor::REQUESTER], 'LIMIT' => 1])->current();
         return $requester ? (int) $requester['users_id'] : null;
      }
      return null;
   }
}
