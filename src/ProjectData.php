<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Profile_User;
use Project;
use Session;
use User;
use UserEmail;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class ProjectData extends CommonDBTM
{
   private const FIELDS = [
      'plugin_projectplus_portfoliobaskets_id',
      'plugin_projectplus_pdticactions_id',
      'objective',
      'expected_result',
      'requester_area',
      'requester_users_id',
      'requester_name',
      'requester_email',
      'focal_point_users_id',
      'focal_point',
      'focal_point_email',
   ];

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_projectdata';
   }

   public static function getForProject(int $projects_id): array
   {
      $data = new self();
      if ($projects_id > 0 && $data->getFromDBByCrit(['projects_id' => $projects_id])) {
         return $data->fields;
      }

      return array_fill_keys(self::FIELDS, '');
   }

   /** Quantos projetos usam o item de uma lista (Acao PDTIC, Cesta do portfolio). */
   public static function countUsage(string $column, int $itemId): int
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!in_array($column, ['plugin_projectplus_portfoliobaskets_id', 'plugin_projectplus_pdticactions_id'], true) || $itemId <= 0) {
         return 0;
      }
      return (int) ($DB->request(['COUNT' => 'cpt', 'FROM' => self::getTable(), 'WHERE' => [$column => $itemId]])->current()['cpt'] ?? 0);
   }

   public static function postProjectAdd(Project $project): void
   {
      self::saveForProject($project);
   }

   /**
    * Persists Project+ fields before the native project update is filtered.
    *
    * GLPI only emits ITEM_UPDATE when a native project column changes. The
    * requester and focal-point fields are owned by this plugin, so saving
    * them through the post-update hook drops updates made exclusively here.
    */
   public static function preProjectUpdate(Project $project): void
   {
      // A trava de encerramento vem antes de qualquer gravacao: quando ela
      // barra, zera a entrada e o GLPI aborta a atualizacao inteira, entao
      // gravar os campos proprios aqui deixaria os dois lados divergentes.
      if (!ProjectClosure::allowUpdate($project)) {
         return;
      }
      self::saveForProject($project);
   }

   public static function preProjectPurge(Project $project): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $projectId = (int) $project->getID();
      $DB->delete(self::getTable(), ['projects_id' => $projectId]);
      // O vinculo com o chamado tem UNIQUE em tickets_id: se sobrasse, o chamado
      // nunca mais conseguiria abrir um projeto.
      $DB->delete(TicketProject::getTable(), ['projects_id' => $projectId]);
      $DB->delete(ProjectPriority::getTable(), ['projects_id' => $projectId]);
      $DB->delete(ProjectInteraction::getTable(), ['projects_id' => $projectId]);
      self::purgeTerms('Project', $projectId);
   }

   /** Remove os dados do plugin de um chamado purgado (sem deixar linhas orfas). */
   public static function preTicketPurge(\Ticket $ticket): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $ticketId = (int) $ticket->getID();
      foreach ($DB->request(['SELECT' => ['id'], 'FROM' => TicketIntent::getTable(), 'WHERE' => ['tickets_id' => $ticketId]]) as $intent) {
         $DB->delete(TicketIntentAnswer::getTable(), ['plugin_projectplus_ticketintents_id' => (int) $intent['id']]);
      }
      foreach ([TicketIntent::getTable(), TicketPhase::getTable(), TicketPhaseHistory::getTable(), TicketPriority::getTable(), TicketProject::getTable()] as $table) {
         $DB->delete($table, ['tickets_id' => $ticketId]);
      }
      self::purgeTerms('Ticket', $ticketId);
   }

   private static function purgeTerms(string $itemtype, int $itemId): void
   {
      /** @var DBmysql $DB */
      global $DB;

      foreach ([DocumentAcceptance::getTable(), DocumentDraft::getTable()] as $table) {
         $DB->delete($table, ['itemtype' => $itemtype, 'items_id' => $itemId]);
      }
   }

   private static function saveForProject(Project $project): void
   {
      $input = $project->input ?? [];
      if (!is_array($input) || !array_intersect(array_keys($input), self::FIELDS)) {
         return;
      }

      $current = self::getForProject((int) $project->getID());
      $values = [];
      foreach (self::FIELDS as $field) {
         $values[$field] = array_key_exists($field, $input)
            ? trim((string) $input[$field])
            : trim((string) ($current[$field] ?? ''));
      }

      foreach ([
         'plugin_projectplus_portfoliobaskets_id',
         'plugin_projectplus_pdticactions_id',
         'requester_users_id',
         'focal_point_users_id',
      ] as $field) {
         $values[$field] = max(0, (int) $values[$field]);
      }

      $entityId = (int) $project->fields['entities_id'];
      foreach ([
         'requester_users_id' => ['requester_name', 'requester_email'],
         'focal_point_users_id' => ['focal_point', 'focal_point_email'],
      ] as $field => $displayFields) {
         $userId = (int) $values[$field];
         if ($userId === 0) {
            if (array_key_exists($field, $input)) {
               $values[$displayFields[0]] = '';
               $values[$displayFields[1]] = '';
            }
            continue;
         }

         $user = new User();
         if (!$user->getFromDB($userId) || empty($user->fields['is_active'])) {
            Session::addMessageAfterRedirect(__('Usuário selecionado inválido ou inativo.', 'projectplus'), false, ERROR);
            return;
         }
         if (!in_array($entityId, Profile_User::getUserEntities($userId, true))) {
            Session::addMessageAfterRedirect(__('O usuário selecionado não possui acesso à entidade do projeto.', 'projectplus'), false, ERROR);
            return;
         }

         $email = UserEmail::getDefaultForUser($userId);
         if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::addMessageAfterRedirect(__('O usuário selecionado não possui e-mail válido cadastrado.', 'projectplus'), false, ERROR);
            return;
         }
         $values[$displayFields[0]] = User::getFriendlyNameById($userId);
         $values[$displayFields[1]] = $email;
      }

      foreach (['requester_email', 'focal_point_email'] as $field) {
         if ($values[$field] !== '' && filter_var($values[$field], FILTER_VALIDATE_EMAIL) === false) {
            Session::addMessageAfterRedirect(__('E-mail inválido.', 'projectplus'), false, ERROR);
            return;
         }
      }

      /** @var DBmysql $DB */
      global $DB;

      $DB->updateOrInsert(
         self::getTable(),
         array_merge($values, [
            'projects_id' => (int) $project->getID(),
            'entities_id' => $entityId,
            'date_mod'    => $_SESSION['glpi_currenttime'],
         ]),
         ['projects_id' => (int) $project->getID()]
      );
   }
}
