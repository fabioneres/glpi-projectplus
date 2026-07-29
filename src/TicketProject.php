<?php

namespace GlpiPlugin\Projectplus;

use CommonITILActor;
use DBmysql;
use Html;
use Project;
use Session;
use Ticket;
use Ticket_User;

if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }

/** Creates and auditable links projects opened from approved ticket requests. */
class TicketProject
{
   public static function getTable($classname = null): string { return 'glpi_plugin_projectplus_ticketprojects'; }

   public static function getProjectId(int $ticketId): int
   {
      global $DB;
      if ($ticketId <= 0 || !$DB->tableExists(self::getTable())) { return 0; }
      $row = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['tickets_id' => $ticketId], 'LIMIT' => 1])->current();
      return (int) ($row['projects_id'] ?? 0);
   }

   public static function canCreate(Ticket $ticket): bool
   {
      $phase = TicketPhase::getForTicket((int) $ticket->getID());
      return Session::haveRight('project', CREATE)
         && Session::haveRight('ticket', UPDATE)
         && $phase !== null && (string) $phase['phase'] === 'authorized_opening'
         && self::getProjectId((int) $ticket->getID()) === 0;
   }

   public static function createFromTicket(Ticket $ticket): int
   {
      if (!self::canCreate($ticket)) { throw new \RuntimeException(__('O chamado ainda não está autorizado para abertura ou você não possui permissão.', 'projectplus')); }
      global $DB;
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
         'priority' => (int) ($ticket->fields['priority'] ?? 1),
         'requester_users_id' => $requester,
         'requester_area' => $answers['área solicitante'] ?? $answers['area solicitante'] ?? '',
         'objective' => $answers['objetivo específico'] ?? $answers['objetivo especifico'] ?? '',
         'expected_result' => $answers['métricas'] ?? $answers['metricas'] ?? '',
      ]);
      if ($projectId <= 0) { throw new \RuntimeException(__('Não foi possível criar o projeto.', 'projectplus')); }
      $now = date('Y-m-d H:i:s');
      $DB->insert(self::getTable(), ['tickets_id' => (int) $ticket->getID(), 'projects_id' => $projectId, 'entities_id' => (int) $ticket->getEntityID(), 'users_id' => (int) Session::getLoginUserID(), 'date_creation' => $now]);
      $url = Project::getFormURLWithID($projectId);
      $ticket->update(['id' => (int) $ticket->getID(), 'status' => Ticket::SOLVED, 'solution' => sprintf(__('Projeto aberto: %s', 'projectplus'), '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . __('Acessar projeto', 'projectplus') . '</a>')]);
      TicketPhase::setPhase($ticket, 'project_opened', __('Projeto criado e chamado solucionado com vínculo para o projeto.', 'projectplus'));
      return $projectId;
   }

   public static function showForTicket(Ticket $ticket): void
   {
      $projectId = self::getProjectId((int) $ticket->getID());
      if ($projectId > 0) {
         echo "<div class='alert alert-success'>" . self::escape(__('Projeto criado a partir desta solicitação:', 'projectplus')) . ' <a href="' . self::escape(Project::getFormURLWithID($projectId)) . '">' . self::escape(__('Acessar projeto', 'projectplus')) . '</a></div>';
      } elseif (self::canCreate($ticket)) {
         global $CFG_GLPI;
         $actionUrl = self::escape(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/ticketproject.form.php');
         echo "<div class='center'><form method='post' action='" . $actionUrl . "'>";
         echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
         echo "<button class='btn btn-primary' type='submit' name='create_project' value='1'>" . self::escape(__('Abrir projeto e solucionar chamado', 'projectplus')) . '</button></form></div>';
      }
   }

   private static function getRequesterId(int $ticketId): int { global $DB; $r=$DB->request(['FROM'=>Ticket_User::getTable(),'WHERE'=>['tickets_id'=>$ticketId,'type'=>CommonITILActor::REQUESTER],'LIMIT'=>1])->current(); return (int)($r['users_id']??0); }
   private static function getIntentAnswers(int $ticketId): array
   {
      global $DB; $intent = TicketIntent::getForTicket($ticketId); if (!$intent) { return []; }
      $out = [];
      foreach ($DB->request(['FROM' => TicketIntentAnswer::getTable(), 'WHERE' => ['plugin_projectplus_ticketintents_id' => (int) $intent['id']]]) as $row) {
         $out[mb_strtolower(trim((string) $row['label']))] = (string) $row['value'];
      }
      return $out;
   }
   private static function escape(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
