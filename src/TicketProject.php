<?php

namespace GlpiPlugin\Projectplus;

use Html;
use ITILSolution;
use Itil_Project;
use Project;
use Session;
use Ticket;

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
      return Session::haveRight(ProjectProfile::RIGHT_OPEN_PROJECT, CREATE)
         && $ticket->canSolve()
         && self::getMissingOpeningRequirements($ticket) === []
         && self::getProjectId((int) $ticket->getID()) === 0;
   }

   /** @return string[] Business conditions required before opening the project. */
   public static function getMissingOpeningRequirements(Ticket $ticket): array
   {
      $missing = [];
      $tip = TicketIntent::getForTicket((int) $ticket->getID(), TipDocumentType::TIP);
      if (!$tip || (string) $tip['status'] !== 'accepted') {
         $missing[] = __('O TIP - Termo de Intenção de Projeto deve estar preenchido e aceito pelo gestor.', 'projectplus');
      }
      $tipDetail = TicketIntent::getForTicket((int) $ticket->getID(), TipDocumentType::DETAIL);
      if (!$tipDetail || (string) $tipDetail['status'] !== 'generated') {
         $missing[] = __('O TIP - Detalhamento Técnico deve estar gerado pelo gestor responsável.', 'projectplus');
      }
      if (!TicketPriority::isComplete((int) $ticket->getID())) {
         $missing[] = __('A priorização institucional deve estar concluída.', 'projectplus');
      }
      $phase = TicketPhase::getForTicket((int) $ticket->getID());
      if (!$phase || !in_array((string) $phase['phase'], TicketPhase::AUTHORIZED_PHASES, true)) {
         $missing[] = __('A autorização para abertura do projeto ainda não foi registrada.', 'projectplus');
      }
      $tap = DocumentAcceptance::getForItem($ticket, 'tap');
      if (!$tap || (string) $tap['status'] !== 'accepted') {
         $missing[] = __('O TAP deve estar gerado, enviado e aceito pelo solicitante.', 'projectplus');
      }
      return $missing;
   }

   /** Returns only the next business condition required to open the project. */
   public static function getNextOpeningRequirement(Ticket $ticket): ?string
   {
      $missing = self::getMissingOpeningRequirements($ticket);
      return $missing[0] ?? null;
   }

   /**
    * Returns the project name, or the documented placeholder when the mapped
    * source is absent, set to manual completion or resolved to an empty value.
    *
    * @param mixed $name
    */
   public static function resolveProjectName($name): string
   {
      $name = trim((string) $name);
      // A source such as the ticket description arrives as rich text: a value
      // made only of markup is empty for naming purposes.
      if (trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8')) === '') {
         return __('Projeto sem nome', 'projectplus');
      }
      return $name;
   }

   /** Opens a project, creates the native GLPI relation, then solves the ticket. */
   /** @param array<string,mixed> $solutionInput */
   public static function createFromTicket(Ticket $ticket, array $solutionInput): int
   {
      TicketRouting::assertPhaseCanBeRouted($ticket, 'project_opened');
      $missing = self::getMissingOpeningRequirements($ticket);
      if ($missing !== []) {
         throw new \RuntimeException(__('Não é possível abrir o projeto:', 'projectplus') . ' ' . implode(' ', $missing));
      }
      if (!self::canCreate($ticket)) {
         throw new \RuntimeException(__('O chamado ainda não está autorizado para abertura ou você não possui permissão.', 'projectplus'));
      }

      $solutionContent = \Glpi\Toolbox\Sanitizer::sanitize(trim((string) ($solutionInput['content'] ?? '')));
      if (trim(html_entity_decode(strip_tags($solutionContent), ENT_QUOTES, 'UTF-8')) === '') {
         throw new \RuntimeException(__('Informe uma mensagem para o solicitante antes de abrir o projeto.', 'projectplus'));
      }

      global $DB;
      $DB->beginTransaction();
      try {
         $projectInput = [
            'entities_id' => (int) $ticket->getEntityID(),
         ];
         // Configurable destinations are populated exclusively by their
         // explicit mappings. An empty source means manual GLPI completion.
         $projectInput = array_replace($projectInput, ProjectFieldConfig::getPrefill($ticket));
         // GLPI refuses a Project without a name.  The mapping may legitimately
         // leave it empty: the administrator chose manual completion, or the
         // configured source produced no value for this ticket.  A placeholder
         // keeps the flow working and leaves the project visible for renaming.
         $projectInput['name'] = self::resolveProjectName($projectInput['name'] ?? '');
         $project = new Project();
         // Values loaded from the database do not pass through GLPI's normal
         // request escaping. Escape the complete programmatic input before
         // handing it to CommonDBTM, including rich-text CSS with apostrophes.
         $projectId = (int) $project->add(\Toolbox::addslashes_deep($projectInput));
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
         $nativeSolutionInput = [
            'itemtype' => Ticket::class,
            'items_id' => (int) $ticket->getID(),
            'content' => $solutionContent,
            'solutiontypes_id' => max(0, (int) ($solutionInput['solutiontypes_id'] ?? 0)),
         ];
         // Keep the upload metadata generated by GLPI's native rich-text
         // editor so pasted images and attached files follow the core flow.
         foreach (['_filename', '_tag_filename', '_prefix_filename', '_content', '_tag_content', '_prefix_content'] as $field) {
            if (array_key_exists($field, $solutionInput)) {
               $nativeSolutionInput[$field] = $solutionInput[$field];
            }
         }
         if ((int) $solution->add($nativeSolutionInput) <= 0) {
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
      } elseif (Session::haveRight(ProjectProfile::RIGHT_OPEN_PROJECT, CREATE)) {
         $missing = self::getMissingOpeningRequirements($ticket);
         if ($missing !== []) {
            $nextRequirement = self::getNextOpeningRequirement($ticket);
            echo "<div class='alert alert-warning'><strong>" . self::escape(__('Próxima etapa do fluxo', 'projectplus')) . '</strong>'
               . '<p class="mb-0">' . self::escape((string) $nextRequirement) . '</p></div>';
            return;
         }
         global $CFG_GLPI;
         $actionUrl = self::escape(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketproject.form.php');
         echo "<div class='projectplus-ticket-open'>"
            . '<p>' . self::escape(__('Preencha a solução do chamado ou selecione um modelo. Ao confirmar, o projeto será criado e o chamado será solucionado.', 'projectplus')) . '</p>'
            . "<div class='timeline-item ITILSolution'><div class='timeline-content card mt-0'>"
            . "<form class='d-flex flex-column' method='post' action='" . $actionUrl . "' enctype='multipart/form-data' data-track-changes='true' data-submit-once>"
            . "<div class='card-body px-3 py-4'>";
         echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
         echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
         echo "<div class='projectplus-ticket-open__editor'>";
         $solution = new ITILSolution();
         $solution->showForm(-1, [
            'parent' => $ticket,
            'noform' => true,
         ]);
         echo "</div></div><div class='card-footer'><button class='btn btn-primary' type='submit' name='create_project' value='1'>";
         echo self::escape(__('Abrir projeto e solucionar chamado', 'projectplus')) . '</button></div></form></div></div></div>';
      }
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
