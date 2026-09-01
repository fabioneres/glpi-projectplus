<?php

namespace GlpiPlugin\Projectplus;

use Entity;
use Session;
use Ticket;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Resolves configured Project+ managers and routes requests between entities. */
class TicketRouting
{
   /** @return array<string, mixed>|null */
   public static function resolve(Ticket $ticket, string $role): ?array
   {
      global $DB;
      $field = $role === 'portfolio' ? 'portfolio_manager_users_id' : 'project_manager_users_id';
      $categoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
      $entities = [(int) $ticket->getEntityID()];
      foreach (getAncestorsOf(Entity::getTable(), (int) $ticket->getEntityID()) as $entityId) {
         $entities[] = (int) $entityId;
      }
      foreach (array_values(array_unique($entities)) as $position => $entityId) {
         $rows = iterator_to_array($DB->request([
            'FROM' => TicketConfig::getTable(),
            'WHERE' => ['entities_id' => $entityId, 'is_active' => 1, $field => ['>', 0]],
            'ORDER' => ['id ASC'],
         ]), false);
         usort($rows, static function (array $a, array $b) use ($categoryId): int {
            $aSame = (int) $a['itilcategories_id'] === $categoryId;
            $bSame = (int) $b['itilcategories_id'] === $categoryId;
            return ($aSame === $bSame) ? ((int) $a['id'] <=> (int) $b['id']) : ($aSame ? -1 : 1);
         });
         foreach ($rows as $row) {
            // A row in the current entity applies directly. A parent row is
            // inherited only when its administrator marked it recursive.
            if ($position > 0 && (int) ($row['is_recursive'] ?? 0) !== 1) {
               continue;
            }
            return $row + ['resolved_entities_id' => $entityId, 'role' => $role];
         }
      }
      return null;
   }

   public static function assertActor(Ticket $ticket, string $role, string $right): void
   {
      $target = self::resolve($ticket, $role);
      if ($target === null) {
         throw new \RuntimeException(__('Não há responsável configurado para esta etapa do fluxo na entidade atual ou em uma entidade superior.', 'projectplus'));
      }
      $field = $role === 'portfolio' ? 'portfolio_manager_users_id' : 'project_manager_users_id';
      if ((int) $target[$field] !== (int) Session::getLoginUserID()) {
         throw new \RuntimeException(__('Esta etapa deve ser executada pelo responsável configurado para a entidade e a categoria do fluxo.', 'projectplus'));
      }
      if (!Session::haveRight($right, UPDATE) || !self::userHasRight((int) $target[$field], $right, (int) $target['resolved_entities_id'])) {
         throw new \RuntimeException(__('O responsável configurado não possui a permissão Project+ obrigatória para esta etapa.', 'projectplus'));
      }
      if ($role === 'project') {
         self::rememberProjectManagerEntity($ticket, (int) $target['resolved_entities_id']);
      }
   }

   /** Routes only the stages owned by a configured manager. */
   public static function routeForPhase(Ticket $ticket, string $phase): void
   {
      $target = self::assertPhaseCanBeRouted($ticket, $phase);
      if ($target === null) {
         return;
      }
      self::transfer($ticket, (int) $target['resolved_entities_id']);
   }

   /** @return array<string, mixed>|null Validates a route before an action writes its result. */
   public static function assertPhaseCanBeRouted(Ticket $ticket, string $phase): ?array
   {
      $role = self::roleForPhase($phase);
      if ($role === null) {
         return null;
      }
      $target = $role === 'project' && $phase === 'project_opened'
         ? self::resolvePreviousProjectManager($ticket)
         : self::resolve($ticket, $role);
      if ($target === null) {
         throw new \RuntimeException(__('Não há responsável configurado para a próxima etapa do fluxo.', 'projectplus'));
      }
      $field = $role === 'portfolio' ? 'portfolio_manager_users_id' : 'project_manager_users_id';
      $requiredRight = $role === 'portfolio' ? ProjectProfile::RIGHT_PRIORITY_STRATEGIC : ProjectProfile::RIGHT_FILL_TIP;
      if (!self::userHasRight((int) $target[$field], $requiredRight, (int) $target['resolved_entities_id'])) {
         throw new \RuntimeException(__('O responsável configurado não possui a permissão Project+ obrigatória para a próxima etapa.', 'projectplus'));
      }
      return $target;
   }

   /** Best-effort initial routing; creating a ticket remains possible while configuration is incomplete. */
   public static function routeInitial(Ticket $ticket): void
   {
      $target = self::resolve($ticket, 'project');
      if ($target !== null) {
         self::transfer($ticket, (int) $target['resolved_entities_id']);
         self::rememberProjectManagerEntity($ticket, (int) $target['resolved_entities_id']);
      }
   }

   /** @return array<string, mixed>|null */
   private static function resolvePreviousProjectManager(Ticket $ticket): ?array
   {
      global $DB;
      $phase = TicketPhase::getForTicket((int) $ticket->getID());
      $entityId = (int) ($phase['project_manager_entities_id'] ?? 0);
      if ($entityId <= 0) {
         return self::resolve($ticket, 'project');
      }
      $categoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
      $rows = iterator_to_array($DB->request([
         'FROM' => TicketConfig::getTable(),
         'WHERE' => ['entities_id' => $entityId, 'is_active' => 1, 'project_manager_users_id' => ['>', 0]],
         'ORDER' => ['id ASC'],
      ]), false);
      usort($rows, static function (array $a, array $b) use ($categoryId): int {
         $aSame = (int) $a['itilcategories_id'] === $categoryId;
         $bSame = (int) $b['itilcategories_id'] === $categoryId;
         return ($aSame === $bSame) ? ((int) $a['id'] <=> (int) $b['id']) : ($aSame ? -1 : 1);
      });
      return isset($rows[0]) ? $rows[0] + ['resolved_entities_id' => $entityId, 'role' => 'project'] : self::resolve($ticket, 'project');
   }

   private static function rememberProjectManagerEntity(Ticket $ticket, int $entityId): void
   {
      global $DB;
      $DB->update(TicketPhase::getTable(), ['project_manager_entities_id' => $entityId], ['tickets_id' => (int) $ticket->getID()]);
   }

   private static function roleForPhase(string $phase): ?string
   {
      if (in_array($phase, ['portfolio_analysis', 'awaiting_authorization', 'authorized_opening', 'awaiting_tap_acceptance'], true)) {
         return 'portfolio';
      }
      if (in_array($phase, ['classification', 'awaiting_pretip_review', 'awaiting_tip', 'project_opened'], true)) {
         return 'project';
      }
      return null;
   }

   private static function transfer(Ticket $ticket, int $targetEntityId): void
   {
      global $DB;
      $sourceEntityId = (int) $ticket->getEntityID();
      $sourceCategoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
      $targetCategoryId = 0;
      foreach ($DB->request(['FROM' => TicketConfig::getTable(), 'WHERE' => ['entities_id' => $targetEntityId, 'is_active' => 1], 'ORDER' => ['id ASC']]) as $config) {
         if ($targetCategoryId === 0 || (int) $config['itilcategories_id'] === $sourceCategoryId) {
            $targetCategoryId = (int) $config['itilcategories_id'];
         }
         if ($targetCategoryId === $sourceCategoryId) {
            break;
         }
      }
      if ($sourceEntityId === $targetEntityId && $sourceCategoryId === $targetCategoryId) {
         return;
      }
      // Native update preserves GLPI validation, history and registered hooks.
      if (!$ticket->update(['id' => (int) $ticket->getID(), 'entities_id' => $targetEntityId, 'itilcategories_id' => $targetCategoryId])) {
         throw new \RuntimeException(__('Não foi possível transferir o chamado para a entidade responsável.', 'projectplus'));
      }
      $DB->update(TicketPhase::getTable(), ['entities_id' => $targetEntityId], ['tickets_id' => (int) $ticket->getID()]);
      $ticket->getFromDB((int) $ticket->getID());
   }

   private static function userHasRight(int $userId, string $right, int $entityId): bool
   {
      global $DB;
      $ancestors = array_flip(array_map('intval', getAncestorsOf(Entity::getTable(), $entityId)));
      foreach ($DB->request([
         'SELECT' => ['glpi_profiles_users.entities_id', 'glpi_profiles_users.is_recursive', 'glpi_profilerights.rights'],
         'FROM' => 'glpi_profiles_users',
         'INNER JOIN' => ['glpi_profilerights' => ['ON' => ['glpi_profiles_users' => 'profiles_id', 'glpi_profilerights' => 'profiles_id']]],
         'WHERE' => ['glpi_profiles_users.users_id' => $userId, 'glpi_profilerights.name' => $right],
      ]) as $profile) {
         $profileEntity = (int) $profile['entities_id'];
         $applies = $profileEntity === $entityId || ((int) $profile['is_recursive'] === 1 && isset($ancestors[$profileEntity]));
         if ($applies && (((int) $profile['rights'] & UPDATE) === UPDATE)) {
            return true;
         }
      }
      return false;
   }
}
