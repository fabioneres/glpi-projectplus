<?php

namespace GlpiPlugin\Projectplus;

use Entity;
use Session;
use Ticket;
use Ticket_User;

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
      $participantField = $DB->fieldExists(TicketConfig::getTable(), 'is_participant') ? 'is_participant' : 'is_active';
      $categoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
      foreach (self::getEntityPath((int) $ticket->getEntityID()) as $position => $entityId) {
         $rows = iterator_to_array($DB->request([
            'FROM' => TicketConfig::getTable(),
            'WHERE' => ['entities_id' => $entityId, $participantField => 1, $field => ['>', 0]],
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
      $target = $role === 'project'
         ? self::resolvePreviousProjectManager($ticket)
         : self::resolve($ticket, $role);
      if ($target === null) {
         throw new \RuntimeException(__('Não há responsável configurado para esta etapa do fluxo na entidade atual ou em uma entidade superior.', 'projectplus'));
      }
      $field = $role === 'portfolio' ? 'portfolio_manager_users_id' : 'project_manager_users_id';
      // The configured user remains the official routing and notification
      // reference. Execution, however, follows GLPI's native authorization
      // model so an authorized substitute can continue the workflow without
      // replacing the titular actor in the category configuration.
      if (!Session::haveAccessToEntity((int) $ticket->getEntityID())) {
         throw new \RuntimeException(__('Você não possui acesso à entidade atual do chamado.', 'projectplus'));
      }
      if (!Session::haveRight($right, UPDATE)) {
         throw new \RuntimeException(__('Seu perfil não possui a permissão Project+ obrigatória para esta etapa.', 'projectplus'));
      }
      if (!self::userHasRight((int) $target[$field], $right, (int) $target['resolved_entities_id'])) {
         throw new \RuntimeException(__('O responsável titular configurado não possui a permissão Project+ obrigatória para esta etapa.', 'projectplus'));
      }
      if ($role === 'project') {
         self::rememberProjectManagerEntity($ticket, (int) $target['resolved_entities_id']);
      }
   }

   /** Routes only the stages owned by a configured manager. */
   public static function routeForPhase(Ticket $ticket, string $phase, bool $validateTargetRight = true): void
   {
      if ($validateTargetRight) {
         $target = self::assertPhaseCanBeRouted($ticket, $phase);
      } else {
         $role = self::roleForPhase($phase);
         if ($role === null) {
            return;
         }
         $target = $role === 'project'
            ? self::resolvePreviousProjectManager($ticket)
            : self::resolve($ticket, $role);
         if ($target === null) {
            throw new \RuntimeException(__('Não há responsável configurado para a próxima etapa do fluxo.', 'projectplus'));
         }
      }
      if ($target === null) {
         return;
      }
      self::transfer($ticket, (int) $target['resolved_entities_id']);
      self::syncResponsibilities($ticket);
   }

   /** Stores the currently resolved actors so GLPI Search can filter them natively. */
   public static function syncResponsibilities(Ticket $ticket): void
   {
      global $DB;
      if (!$DB->tableExists(TicketPhase::getTable())
         || !$DB->fieldExists(TicketPhase::getTable(), 'project_manager_users_id')) {
         return;
      }
      $project = self::resolvePreviousProjectManager($ticket);
      $portfolio = self::resolve($ticket, 'portfolio');
      $phase = TicketPhase::getForTicket((int) $ticket->getID());
      $observerRelationId = self::syncPortfolioObserver(
         $ticket,
         (int) ($portfolio['portfolio_manager_users_id'] ?? 0),
         (int) ($phase['portfolio_observer_ticketusers_id'] ?? 0)
      );
      $DB->update(TicketPhase::getTable(), [
         'project_manager_users_id' => (int) ($project['project_manager_users_id'] ?? 0),
         'portfolio_manager_users_id' => (int) ($portfolio['portfolio_manager_users_id'] ?? 0),
         'portfolio_observer_ticketusers_id' => $observerRelationId,
      ], ['tickets_id' => (int) $ticket->getID()]);
   }

   /**
    * Keeps the closest Portfolio Manager as a native GLPI observer.
    *
    * The phase stores the exact Ticket_User relation created by Project+ so
    * manually maintained observers are never claimed or removed.
    */
   private static function syncPortfolioObserver(Ticket $ticket, int $portfolioUserId, int $trackedRelationId): int
   {
      global $DB;
      $ticketId = (int) $ticket->getID();

      if (!$DB->fieldExists(TicketPhase::getTable(), 'portfolio_observer_ticketusers_id')) {
         return 0;
      }

      if ($trackedRelationId > 0) {
         $tracked = $DB->request([
            'FROM' => Ticket_User::getTable(),
            'WHERE' => [
               'id' => $trackedRelationId,
               'tickets_id' => $ticketId,
               'type' => \CommonITILActor::OBSERVER,
            ],
            'LIMIT' => 1,
         ])->current();

         if ($tracked && (int) $tracked['users_id'] === $portfolioUserId && $portfolioUserId > 0) {
            if ((int) $tracked['use_notification'] !== 1) {
               $DB->update(Ticket_User::getTable(), ['use_notification' => 1], ['id' => $trackedRelationId]);
            }
            return $trackedRelationId;
         }

         if ($tracked) {
            $observer = new Ticket_User();
            $observer->delete(['id' => $trackedRelationId, '_disablenotif' => true], true);
         }
      }

      if ($portfolioUserId <= 0) {
         return 0;
      }

      $existing = $DB->request([
         'FROM' => Ticket_User::getTable(),
         'WHERE' => [
            'tickets_id' => $ticketId,
            'users_id' => $portfolioUserId,
            'type' => \CommonITILActor::OBSERVER,
         ],
         'ORDER' => ['id ASC'],
         'LIMIT' => 1,
      ])->current();
      if ($existing) {
         if ((int) $existing['use_notification'] !== 1) {
            $DB->update(Ticket_User::getTable(), ['use_notification' => 1], ['id' => (int) $existing['id']]);
         }
         // A pre-existing observer remains user-managed and is not tracked.
         return 0;
      }

      $observer = new Ticket_User();
      $relationId = $observer->add([
         'tickets_id' => $ticketId,
         'users_id' => $portfolioUserId,
         'type' => \CommonITILActor::OBSERVER,
         'use_notification' => 1,
         '_disablenotif' => true,
      ]);
      return $relationId === false ? 0 : (int) $relationId;
   }

   /** Backfills native-search responsibility fields for existing workflows. */
   public static function syncAllResponsibilities(): void
   {
      global $DB;
      if (!$DB->tableExists(TicketPhase::getTable())) {
         return;
      }

      $ticket = new Ticket();
      foreach ($DB->request([
         'SELECT' => ['tickets_id'],
         'DISTINCT' => true,
         'FROM' => TicketPhase::getTable(),
         'WHERE' => ['tickets_id' => ['>', 0]],
      ]) as $row) {
         if ($ticket->getFromDB((int) $row['tickets_id'])) {
            self::syncResponsibilities($ticket);
         }
      }
   }

   /** @return array<string, mixed>|null Validates a route before an action writes its result. */
   public static function assertPhaseCanBeRouted(Ticket $ticket, string $phase): ?array
   {
      $role = self::roleForPhase($phase);
      if ($role === null) {
         return null;
      }
      $target = $role === 'project'
         ? self::resolvePreviousProjectManager($ticket)
         : self::resolve($ticket, $role);
      if ($target === null) {
         throw new \RuntimeException(__('Não há responsável configurado para a próxima etapa do fluxo.', 'projectplus'));
      }
      $field = $role === 'portfolio' ? 'portfolio_manager_users_id' : 'project_manager_users_id';
      $requiredRight = self::rightForPhase($phase, $role);
      if (!self::userHasRight((int) $target[$field], $requiredRight, (int) $target['resolved_entities_id'])) {
         throw new \RuntimeException(__('O responsável configurado não possui a permissão Project+ obrigatória para a próxima etapa.', 'projectplus'));
      }
      return $target;
   }

   /** Routes to one configured actor without changing the workflow phase. */
   public static function routeToRole(Ticket $ticket, string $role, string $right): void
   {
      $target = self::assertRoleCanBeRouted($ticket, $role, $right);
      self::transfer($ticket, (int) $target['resolved_entities_id']);
      self::syncResponsibilities($ticket);
   }

   /** @return array<string, mixed> Validates a configured route without moving the ticket. */
   public static function assertRoleCanBeRouted(Ticket $ticket, string $role, string $right): array
   {
      $target = $role === 'project'
         ? self::resolvePreviousProjectManager($ticket)
         : self::resolve($ticket, $role);
      if ($target === null) {
         throw new \RuntimeException(__('Não há responsável configurado para a próxima etapa do fluxo.', 'projectplus'));
      }
      $field = $role === 'portfolio' ? 'portfolio_manager_users_id' : 'project_manager_users_id';
      if (!self::userHasRight((int) $target[$field], $right, (int) $target['resolved_entities_id'])) {
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
      $participantField = $DB->fieldExists(TicketConfig::getTable(), 'is_participant') ? 'is_participant' : 'is_active';
      $rows = iterator_to_array($DB->request([
         'FROM' => TicketConfig::getTable(),
         'WHERE' => ['entities_id' => $entityId, $participantField => 1, 'project_manager_users_id' => ['>', 0]],
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
      if (in_array($phase, ['awaiting_authorization'], true)) {
         return 'portfolio';
      }
      if (in_array($phase, [
         'classification',
         'awaiting_pretip_review',
         'awaiting_tip',
         'portfolio_analysis',
         'authorized_opening',
         'awaiting_tap_acceptance',
         'project_opened',
      ], true)) {
         return 'project';
      }
      return null;
   }

   private static function rightForPhase(string $phase, string $role): string
   {
      $rights = [
         'classification' => ProjectProfile::RIGHT_REQUEST_TIP,
         'awaiting_pretip_review' => ProjectProfile::RIGHT_VALIDATE_PRETIP,
         'awaiting_tip' => ProjectProfile::RIGHT_FILL_TIP,
         'portfolio_analysis' => ProjectProfile::RIGHT_PRIORITY_TECHNICAL,
         'awaiting_authorization' => ProjectProfile::RIGHT_AUTHORIZE_OPENING,
         'authorized_opening' => ProjectProfile::RIGHT_FILL_TAP,
         'awaiting_tap_acceptance' => ProjectProfile::RIGHT_SEND_TAP,
         'project_opened' => ProjectProfile::RIGHT_OPEN_PROJECT,
      ];
      return $rights[$phase] ?? ($role === 'portfolio'
         ? ProjectProfile::RIGHT_PRIORITY_STRATEGIC
         : ProjectProfile::RIGHT_FILL_TIP);
   }

   private static function transfer(Ticket $ticket, int $targetEntityId): void
   {
      global $DB;
      $sourceEntityId = (int) $ticket->getEntityID();

      // Routing inside the same entity must never alter the ticket category.
      if ($sourceEntityId === $targetEntityId) {
         return;
      }

      $targetCategoryId = 0;
      $targetConfig = $DB->request([
         'SELECT' => ['itilcategories_id'],
         'FROM' => TicketConfig::getTable(),
         'WHERE' => [
            'entities_id' => $targetEntityId,
            'is_active' => 1,
            'itilcategories_id' => ['>', 0],
         ],
         'ORDER' => ['id ASC'],
         'LIMIT' => 1,
      ])->current();
      if ($targetConfig) {
         $targetCategoryId = (int) $targetConfig['itilcategories_id'];
      }
      // Native update preserves GLPI validation, history and registered hooks.
      if (!$ticket->update(['id' => (int) $ticket->getID(), 'entities_id' => $targetEntityId, 'itilcategories_id' => $targetCategoryId])) {
         throw new \RuntimeException(__('Não foi possível transferir o chamado para a entidade responsável.', 'projectplus'));
      }
      $DB->update(TicketPhase::getTable(), ['entities_id' => $targetEntityId], ['tickets_id' => (int) $ticket->getID()]);
      $ticket->getFromDB((int) $ticket->getID());
   }

   /** @return int[] Current entity followed by each direct parent. */
   private static function getEntityPath(int $entityId): array
   {
      global $DB;
      $path = [];
      $visited = [];
      while ($entityId >= 0 && !isset($visited[$entityId])) {
         $path[] = $entityId;
         $visited[$entityId] = true;
         if ($entityId === 0) {
            break;
         }
         $entity = $DB->request([
            'SELECT' => ['entities_id'],
            'FROM' => Entity::getTable(),
            'WHERE' => ['id' => $entityId],
            'LIMIT' => 1,
         ])->current();
         if (!$entity) {
            break;
         }
         $entityId = (int) $entity['entities_id'];
      }
      return $path;
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
