<?php

namespace GlpiPlugin\Projectplus;

use CommonITILActor;
use Html;
use ITILSolution;
use Itil_Project;
use Project;
use Session;
use Ticket;
use Ticket_User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Creates auditable project links from approved ticket requests. */
class TicketProject
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketprojects';
   }

   public static function getProjectId(int $ticketId): int
   {
      global $DB;
      if ($ticketId <= 0 || !$DB->tableExists(self::getTable())) {
         return 0;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['tickets_id' => $ticketId],
         'LIMIT' => 1,
      ])->current();
      return (int) ($row['projects_id'] ?? 0);
   }

   public static function getTicketIdForProject(int $projectId): int
   {
      global $DB;
      if ($projectId <= 0 || !$DB->tableExists(self::getTable())) {
         return 0;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['projects_id' => $projectId],
         'LIMIT' => 1,
      ])->current();
      return (int) ($row['tickets_id'] ?? 0);
   }

   public static function canCreate(Ticket $ticket): bool
   {
      return Session::haveRight('project', CREATE)
         && Session::haveRight('ticket', UPDATE)
         && Session::haveRight(ProjectProfile::RIGHT_OPEN_PROJECT, CREATE)
         && $ticket->canSolve()
         && self::getMissingOpeningRequirements($ticket) === []
         && self::getProjectId((int) $ticket->getID()) === 0;
   }

   /** @return string[] Business conditions required before opening the project. */
   public static function getMissingOpeningRequirements(Ticket $ticket): array
   {
      $missing = [];
      $intent = TicketIntent::getForTicket((int) $ticket->getID());
      if (!$intent || (string) $intent['status'] !== 'accepted') {
         $missing[] = __('O TIP deve estar preenchido e aceito pelo gestor.', 'projectplus');
      }
      if (!TicketPriority::isComplete((int) $ticket->getID())) {
         $missing[] = __('A priorização institucional deve estar concluída.', 'projectplus');
      }
      $tap = DocumentAcceptance::getForItem($ticket, 'tap');
      if (!$tap || (string) $tap['status'] !== 'accepted') {
         $missing[] = __('O TAP deve estar gerado, enviado e aceito pelo solicitante.', 'projectplus');
      }
      $phase = TicketPhase::getForTicket((int) $ticket->getID());
      if (!$phase || (string) $phase['phase'] !== 'authorized_opening') {
         $missing[] = __('A autorização para abertura do projeto ainda não foi registrada.', 'projectplus');
      }
      return $missing;
   }

   /** Opens a project, creates the native GLPI relation, then solves the ticket. */
   public static function createFromTicket(Ticket $ticket, string $solutionContent): int
   {
      $missing = self::getMissingOpeningRequirements($ticket);
      if ($missing !== []) {
         throw new \RuntimeException(__('Não é possível abrir o projeto:', 'projectplus') . ' ' . implode(' ', $missing));
      }
      if (!self::canCreate($ticket)) {
         throw new \RuntimeException(__('O chamado ainda não está autorizado para abertura ou você não possui permissão.', 'projectplus'));
      }

      $solutionContent = \Glpi\Toolbox\Sanitizer::sanitize(trim($solutionContent));
      if (trim(html_entity_decode(strip_tags($solutionContent), ENT_QUOTES, 'UTF-8')) === '') {
         throw new \RuntimeException(__('Informe uma mensagem para o solicitante antes de abrir o projeto.', 'projectplus'));
      }

      global $DB;
      $DB->beginTransaction();
      try {
         $requester = self::getRequesterId((int) $ticket->getID());
         $answers = self::getIntentAnswers((int) $ticket->getID());
         $name = $answers['nome do projeto'] ?? trim((string) $ticket->fields['name']);
         $content = $answers['descrição do projeto'] ?? $answers['descricao do projeto'] ?? (string) $ticket->fields['content'];

         $project = new Project();
         $projectId = (int) $project->add([
            'name' => mb_substr($name !== '' ? $name : __('Projeto originado do chamado', 'projectplus') . ' #' . $ticket->getID(), 0, 255),
            'content' => $content,
            'entities_id' => (int) $ticket->getEntityID(),
            'users_id' => (int) Session::getLoginUserID(),
            'priority' => ProjectPriority::getNativePriorityForRanking((int) TicketPriority::getForTicket((int) $ticket->getID())['ranking']),
            'requester_users_id' => $requester,
            'requester_area' => $answers['área solicitante'] ?? $answers['area solicitante'] ?? '',
            'objective' => $answers['objetivo específico'] ?? $answers['objetivo especifico'] ?? '',
            'expected_result' => $answers['métricas'] ?? $answers['metricas'] ?? '',
         ]);
         if ($projectId <= 0 || !$project->getFromDB($projectId)) {
            throw new \RuntimeException(__('Não foi possível criar o projeto.', 'projectplus'));
         }

         TicketPriority::copyToProject($ticket, $project);

         $nativeLink = new Itil_Project();
         if ((int) $nativeLink->add([
            'itemtype' => Ticket::class,
            'items_id' => (int) $ticket->getID(),
            'projects_id' => $projectId,
         ]) <= 0) {
            throw new \RuntimeException(__('Não foi possível vincular o projeto ao chamado.', 'projectplus'));
         }

         $DB->insert(self::getTable(), [
            'tickets_id' => (int) $ticket->getID(),
            'projects_id' => $projectId,
            'entities_id' => (int) $ticket->getEntityID(),
            'users_id' => (int) Session::getLoginUserID(),
            'date_creation' => date('Y-m-d H:i:s'),
         ]);

         $solution = new ITILSolution();
         if ((int) $solution->add([
            'itemtype' => Ticket::class,
            'items_id' => (int) $ticket->getID(),
            'content' => $solutionContent,
            'solutiontypes_id' => 0,
         ]) <= 0) {
            throw new \RuntimeException(__('Não foi possível registrar a solução do chamado.', 'projectplus'));
         }
         $DB->commit();
      } catch (\Throwable $exception) {
         $DB->rollBack();
         throw $exception;
      }

      TicketPhase::setPhase($ticket, 'project_opened', __('Projeto criado e chamado solucionado com vínculo para o projeto.', 'projectplus'));
      return $projectId;
   }

   public static function showForTicket(Ticket $ticket): void
   {
      $projectId = self::getProjectId((int) $ticket->getID());
      if ($projectId > 0) {
         echo "<div class='alert alert-success'>" . self::escape(__('Projeto criado a partir desta solicitação:', 'projectplus'))
            . ' <a href="' . self::escape(Project::getFormURLWithID($projectId)) . '">'
            . self::escape(__('Acessar projeto', 'projectplus')) . '</a></div>';
      } elseif (Session::haveRight('project', CREATE) && Session::haveRight('ticket', UPDATE) && Session::haveRight(ProjectProfile::RIGHT_OPEN_PROJECT, CREATE)) {
         $missing = self::getMissingOpeningRequirements($ticket);
         if ($missing !== []) {
            echo "<div class='alert alert-warning'><strong>" . self::escape(__('O projeto ainda não pode ser aberto.', 'projectplus')) . '</strong><ul>';
            foreach ($missing as $requirement) {
               echo '<li>' . self::escape($requirement) . '</li>';
            }
            echo '</ul></div>';
            return;
         }
         if (!$ticket->canSolve()) {
            echo "<div class='alert alert-warning'>" . self::escape(__('Você não possui permissão para solucionar este chamado.', 'projectplus')) . '</div>';
            return;
         }
         global $CFG_GLPI;
         $actionUrl = self::escape(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketproject.form.php');
         echo "<section class='projectplus-ticket-open'><h4>" . self::escape(__('Abrir projeto e solucionar chamado', 'projectplus')) . '</h4>';
         echo '<p>' . self::escape(__('Informe ao solicitante que o projeto foi aberto. Este texto será registrado como a solução do chamado.', 'projectplus')) . "</p><form method='post' action='" . $actionUrl . "'>";
         echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
         echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
         echo "<div class='projectplus-ticket-open__editor'><label for='projectplus_solution_content'>"
            . self::escape(__('Mensagem de solução para o solicitante', 'projectplus'))
            . "</label><textarea id='projectplus_solution_content' class='form-control' name='solution_content' rows='6' required></textarea></div>"
            . "<div class='center'><button class='btn btn-primary' type='submit' name='create_project' value='1'>";
         echo self::escape(__('Abrir projeto e solucionar chamado', 'projectplus')) . '</button></div></form></section>';
      }
   }

   private static function getRequesterId(int $ticketId): int
   {
      global $DB;
      $row = $DB->request([
         'FROM' => Ticket_User::getTable(),
         'WHERE' => ['tickets_id' => $ticketId, 'type' => CommonITILActor::REQUESTER],
         'LIMIT' => 1,
      ])->current();
      return (int) ($row['users_id'] ?? 0);
   }

   private static function getIntentAnswers(int $ticketId): array
   {
      global $DB;
      $intent = TicketIntent::getForTicket($ticketId);
      if (!$intent) {
         return [];
      }
      $answers = [];
      foreach ($DB->request([
         'FROM' => TicketIntentAnswer::getTable(),
         'WHERE' => ['plugin_projectplus_ticketintents_id' => (int) $intent['id']],
      ]) as $row) {
         $answers[mb_strtolower(trim((string) $row['label']))] = (string) $row['value'];
      }
      return $answers;
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
