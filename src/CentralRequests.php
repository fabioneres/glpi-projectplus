<?php
namespace GlpiPlugin\Projectplus;

use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;
use Ticket;

if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }

/** Fixed Central block listing Project+ requests in the active entity. */
class CentralRequests
{
   /** Upper bound of requests examined per rendering. */
   private const SCAN_LIMIT = 500;

   public static function display($params = []): void
   {
      // Plugin hooks receive their parameters as an array. A boolean false is
      // used only by the dedicated listing page below.
      $forCentral = !is_bool($params) || $params;
      if (!Session::haveRight(ProjectProfile::RIGHT_REQUESTS, READ)) {
         return;
      }
      $items = self::getVisibleRequests();
      $total = count($items);
      $displayed = $forCentral
         ? min((int) ($_SESSION['glpidisplay_count_on_home'] ?? 0), $total)
         : $total;
      $table = [
         // Same component and classes used by GLPI's native Central ticket lists.
         'class'       => 'table table-borderless table-striped table-hover card-table',
         'header_rows' => [
            [
               [
                  'colspan' => 5,
                  'content' => Html::makeTitle(
                     '<a href="' . htmlspecialchars(self::getListUrl(), ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars(__('Solicitações de projeto', 'projectplus'), ENT_QUOTES, 'UTF-8') . '</a>',
                     $displayed,
                     $total
                  ),
               ],
            ],
            [
               ['content' => __('ID'), 'style' => 'width: 75px'],
               ['content' => _n('Requester', 'Requesters', 1), 'style' => 'width: 20%'],
               ['content' => __('Description')],
               ['content' => __('Fase atual', 'projectplus'), 'style' => 'width: 20%'],
               ['content' => __('Última atualização', 'projectplus')],
            ],
         ],
         'rows'        => [],
      ];
      foreach (array_slice($items, 0, $displayed) as $row) {
         $url = Ticket::getFormURLWithID((int) $row['id']);
         $priorityColor = $_SESSION['glpipriority_' . (int) $row['priority']] ?? '';
         $table['rows'][] = [
            'values' => [
               [
                  // Native Central ticket lists use this priority block for their ID column.
                  'content' => "<div class='priority_block' style='border-color: $priorityColor'>"
                     . "<span style='background: $priorityColor'></span>&nbsp;"
                     . sprintf(__('ID: %s'), (int) $row['id']) . '</div>',
               ],
               self::getRequesterDisplay((int) $row['id']),
               '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><span class="b">'
                  . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</span></a>',
               htmlspecialchars(TicketPhase::getPhaseLabel((string) $row['phase']), ENT_QUOTES, 'UTF-8'),
               htmlspecialchars(Html::convDateTime((string) $row['date_mod']), ENT_QUOTES, 'UTF-8'),
            ],
         ];
      }

      if ($total === 0) {
         $table['rows'][] = [
            'values' => [[
               'colspan' => 5,
               'content' => '<i>' . htmlspecialchars(__('Nenhuma solicitação de projeto na entidade atual.', 'projectplus'), ENT_QUOTES, 'UTF-8') . '</i>',
            ]],
         ];
      }

      if (!$forCentral) {
         echo "<div class='card'><div class='card-body p-0'>";
         TemplateRenderer::getInstance()->display('components/table.html.twig', $table);
         echo '</div></div>';
         return;
      }

      // DISPLAY_CENTRAL is rendered before GLPI's masonry grid. Render a valid
      // hook row first, then move the native card into that grid on DOM ready.
      // This is the exact container (`grid-item col-xl-6`) used by Central's
      // built-in ticket lists, so widths, gutters and theme styling stay native.
      $rand = mt_rand();
      $rowId = 'projectplus-central-request-row-' . $rand;
      $cardId = 'projectplus-central-request-card-' . $rand;
      echo "<tr id='$rowId'><td colspan='2'><div id='$cardId' class='card'><div class='card-body p-0'>";
      TemplateRenderer::getInstance()->display('components/table.html.twig', $table);
      echo "</div></div></td></tr>";
      echo "<script>$(function () {"
         . "var card = $('#$cardId').detach();"
         . "$('#$rowId').remove();"
         . "var grid = $('[id^=home-dashboard] .masonry_grid').first();"
         . "if (card.length && grid.length) {"
         . "grid.prepend($('<div class=\"grid-item col-xl-6\"></div>').append(card));"
         . "$(document).trigger('masonry_grid:layout');"
         . "}"
         . "});</script>";
   }

   /**
    * Project+ requests of the active entity that the user may actually open.
    *
    * The Project+ right authorizes the listing; it does not decide which
    * tickets appear in it. A list of its own bypasses GLPI's Search engine and,
    * with it, every visibility rule the core would have applied, so each
    * candidate is confirmed against `Ticket::canViewItem()` - the same rule
    * that decides whether the ticket opens at all.
    *
    * @return array<int, array<string, mixed>>
    */
   private static function getVisibleRequests(): array
   {
      /** @var \DBmysql $DB */
      global $DB;

      $entityId = (int) ($_SESSION['glpiactive_entity'] ?? 0);
      $ticket = new Ticket();
      $visible = [];
      foreach ($DB->request([
         'SELECT' => [
            'glpi_tickets.id',
            'glpi_tickets.name',
            'glpi_tickets.date_mod',
            'glpi_tickets.priority',
            TicketPhase::getTable() . '.phase',
         ],
         'FROM' => 'glpi_tickets',
         'INNER JOIN' => [TicketPhase::getTable() => ['FKEY' => [TicketPhase::getTable() => 'tickets_id', 'glpi_tickets' => 'id']]],
         'WHERE' => ['glpi_tickets.entities_id' => $entityId, 'glpi_tickets.is_deleted' => 0],
         'ORDER' => ['glpi_tickets.date_mod DESC'],
         // The confirmation below costs one read per candidate, so the scan is
         // bounded. Only requests in the Project+ flow reach this point.
         'LIMIT' => self::SCAN_LIMIT,
      ]) as $row) {
         if ($ticket->getFromDB((int) $row['id']) && $ticket->canViewItem()) {
            $visible[] = $row;
         }
      }
      return $visible;
   }

   public static function getListUrl(): string
   {
      global $CFG_GLPI;
      return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/requests.php';
   }

   /**
    * Mirrors the requester rendering used by GLPI's native Central ticket lists.
    */
   private static function getRequesterDisplay(int $ticketId): string
   {
      global $DB;

      $requesters = [];
      foreach ($DB->request([
         'SELECT' => ['users_id', 'alternative_email'],
         'FROM'   => \Ticket_User::getTable(),
         'WHERE'  => [
            'tickets_id' => $ticketId,
            'type'       => \CommonITILActor::REQUESTER,
         ],
      ]) as $requester) {
         if ((int) $requester['users_id'] > 0) {
            $user = \getUserName((int) $requester['users_id'], 2);
            $requesters[] = '<i class="fas fa-sm fa-fw fa-user text-muted me-1"></i>'
               . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
         } elseif (!empty($requester['alternative_email'])) {
            $requesters[] = '<i class="fas fa-sm fa-fw fa-envelope text-muted me-1"></i>'
               . htmlspecialchars((string) $requester['alternative_email'], ENT_QUOTES, 'UTF-8');
         }
      }

      return implode('<br>', $requesters);
   }
}
