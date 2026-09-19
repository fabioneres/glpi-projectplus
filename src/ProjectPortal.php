<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Read-only Project+ views for the simplified helpdesk interface.
 *
 * Access is relationship based: a logged-in user can only see projects for
 * which they are the requester or the focal point in ProjectData.
 */
class ProjectPortal
{
   private const MENU_KEY = 'projectplus_myprojects';

   public static function redefineMenus(array $menus): array
   {
      if (Session::getCurrentInterface() !== 'helpdesk' || !self::hasProjectsForCurrentUser()) {
         return $menus;
      }

      $entry = [
         'default' => '/plugins/projectplus/front/myprojects.php',
         'title'   => __('Meus projetos', 'projectplus'),
         'icon'    => 'ti ti-folders',
      ];

      $result = [];
      $added = false;
      foreach ($menus as $key => $menu) {
         $result[$key] = $menu;
         if (self::isMyTicketsMenu($key, $menu)) {
            $result[self::MENU_KEY] = $entry;
            $added = true;
         }
      }

      if (!$added) {
         $result[self::MENU_KEY] = $entry;
      }

      return $result;
   }

   /** @param array<string, mixed> $menu */
   private static function isMyTicketsMenu(string $key, array $menu): bool
   {
      // Formcreator replaces the native Helpdesk menu in this environment.
      // Its "Meus chamados" entry has the key my_assistance_requests.
      if (in_array($key, ['tickets', 'ticket', 'helpdesk', 'my_assistance_requests'], true)) {
         return true;
      }

      $title = strip_tags((string) ($menu['title'] ?? ''));
      return $title !== '' && $title === __('My tickets');
   }

   public static function hasProjectsForCurrentUser(): bool
   {
      $userId = (int) Session::getLoginUserID();
      if ($userId <= 0) {
         return false;
      }

      foreach (self::getProjectsForUser($userId, 1) as $project) {
         return true;
      }

      return false;
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   public static function getProjectsForCurrentUser(): array
   {
      $userId = (int) Session::getLoginUserID();
      return $userId > 0 ? self::getProjectsForUser($userId) : [];
   }

   /**
    * @return array<string, mixed>|null
    */
   public static function getProjectForCurrentUser(int $projectId): ?array
   {
      if ($projectId <= 0) {
         return null;
      }

      foreach (self::getProjectsForCurrentUser() as $project) {
         if ((int) $project['id'] === $projectId) {
            return $project;
         }
      }

      return null;
   }

   /**
    * Lista os projetos do solicitante com a apresentacao nativa do GLPI.
    *
    * A tabela e renderizada pelo mesmo componente que a tela nativa de
    * Projetos usa (`components/table.html.twig`) e cada celula e montada pelos
    * mesmos helpers do core - cracha de entidade, bloco de prioridade, cor do
    * estado e resumo de texto rico. Assim a aparencia acompanha o GLPI em vez
    * de imitar uma versao dele.
    */
   public static function displayList(): void
   {
      $projects = self::getProjectsForCurrentUser();
      $table = [
         'class' => 'table table-hover table-striped card-table',
         // Rotulos do proprio core, para acompanharem o idioma e a nomenclatura
         // da tela nativa de Projetos em vez de traduzirem de novo no plugin.
         'header_rows' => [[
            ['content' => __('Name')],
            ['content' => \Entity::getTypeName(1)],
            ['content' => __('ID')],
            ['content' => __('Priority')],
            ['content' => __('Ranking', 'projectplus')],
            ['content' => __('Code')],
            ['content' => _n('State', 'States', 1)],
            ['content' => __('Percent done')],
            ['content' => __('Creation date')],
            ['content' => __('Description')],
         ]],
         'rows' => [],
      ];

      foreach ($projects as $project) {
         $url = self::getProjectUrl((int) $project['id']);
         $stateColor = self::getProjectStateColor((int) $project['projectstates_id']);
         $table['rows'][] = [
            'values' => [
               ['content' => '<a href="' . self::escape($url) . '">' . self::escape((string) $project['name']) . '</a>'],
               ['content' => self::entityBadge((int) $project['entities_id'])],
               ['content' => (int) $project['id']],
               ['content' => self::priorityBlock((int) $project['priority'])],
               ['content' => ((int) ($project['ranking'] ?? 0)) > 0 ? (string) (int) $project['ranking'] : '&nbsp;'],
               ['content' => self::textValue((string) $project['code'])],
               [
                  'content' => self::escape(self::getProjectState((int) $project['projectstates_id'])),
                  // O core pinta a celula inteira com a cor do estado.
                  'class' => $stateColor !== '' ? 'shadow-none' : '',
                  'style' => $stateColor !== '' ? 'background-color: ' . $stateColor . ';' : '',
               ],
               ['content' => ((int) $project['percent_done']) . '%'],
               ['content' => self::textValue(self::formatDateTime((string) $project['date_creation']))],
               ['content' => self::resumeRichText((string) $project['content'])],
            ],
         ];
      }

      if ($table['rows'] === []) {
         $table['rows'][] = [
            'values' => [[
               'colspan' => 10,
               'content' => '<i>' . self::escape(__('Nenhum projeto encontrado.', 'projectplus')) . '</i>',
            ]],
         ];
      }

      echo "<div class='card'>";
      TemplateRenderer::getInstance()->display('components/table.html.twig', $table);
      echo '</div>';
   }

   /** Cracha de entidade identico ao das listas nativas. */
   private static function entityBadge(int $entityId): string
   {
      $badge = \Entity::badgeCompletenameById($entityId);
      return $badge !== null && $badge !== ''
         ? $badge
         : self::escape(\Entity::getFriendlyNameById($entityId));
   }

   /** Bloco de prioridade identico ao de `Search::giveItem`. */
   private static function priorityBlock(int $priority): string
   {
      $color = (string) ($_SESSION['glpipriority_' . $priority] ?? '');
      $name = self::escape(\CommonITILObject::getPriorityName($priority));
      return "<div class='priority_block' style='border-color: " . self::escape($color) . "'>"
         . "<span style='background: " . self::escape($color) . "'></span>&nbsp;" . $name . '</div>';
   }

   private static function getProjectStateColor(int $stateId): string
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($stateId <= 0) {
         return '';
      }
      $row = $DB->request([
         'SELECT' => ['color'],
         'FROM' => 'glpi_projectstates',
         'WHERE' => ['id' => $stateId],
         'LIMIT' => 1,
      ])->current();
      return (string) ($row['color'] ?? '');
   }

   /** Resumo de texto rico no mesmo formato das colunas nativas. */
   private static function resumeRichText(string $content): string
   {
      global $CFG_GLPI;

      if (trim($content) === '') {
         return '&nbsp;';
      }
      $plain = \Glpi\RichText\RichText::getTextFromHtml($content, true, true, true);
      return Html::resume_text($plain, (int) ($CFG_GLPI['cut'] ?? 255));
   }

   /**
    * Display the Project data in read-only form controls. Terms and public
    * interactions are available below for the requester and focal point.
    *
    * @param array<string, mixed> $project
    */
   public static function displayProject(array $project): void
   {
      $projectId = (int) $project['id'];
      $nativeProject = new \Project();
      $hasNativeProject = $nativeProject->getFromDB($projectId);
      $canViewInteractions = $hasNativeProject && ProjectInteraction::canViewProjectplus($nativeProject);
      $workspaceId = 'projectplus-portal-workspace-' . $projectId;

      echo "<section id='{$workspaceId}' class='projectplus-portal-workspace'>";
      echo "<style>#{$workspaceId} .projectplus-portal-workspace__panel{display:none}#{$workspaceId} .projectplus-portal-workspace__panel.is-active{display:block}</style>";
      echo "<ul class='nav nav-tabs mb-3' role='tablist'>";
      self::portalTabButton('project', __('Projeto', 'projectplus'), true);
      if ($hasNativeProject) {
         self::portalTabButton('documents', __('Termos', 'projectplus'));
      }
      if ($canViewInteractions) {
         self::portalTabButton('interactions', __('Interações', 'projectplus'));
      }
      echo '</ul>';
      echo "<div class='projectplus-portal-workspace__panel is-active' data-projectplus-portal-panel='project'>";
      echo "<div class='center'><table class='tab_cadre_fixe'>";

      // Os campos que tambem aparecem na lista usam os rotulos do core, para a
      // mesma informacao ter o mesmo nome nas duas telas e no GLPI nativo.
      self::headerRow(__('Dados do Projeto', 'projectplus'));
      self::fieldRow(__('Creation date'), self::formatDateTime((string) $project['date']), __('Last update'), self::formatDateTime((string) $project['date_mod']));
      self::fieldRow(__('Name'), (string) $project['name'], __('Code'), (string) $project['code']);
      self::fieldRow(__('Priority'), \CommonITILObject::getPriorityName((int) $project['priority']), __('Filho de', 'projectplus'), self::friendlyName(\Project::class, (int) $project['projects_id']));
      self::fieldRow(_n('State', 'States', 1), self::getProjectState((int) $project['projectstates_id']), __('Percent done'), (string) ((int) $project['percent_done']) . '%');
      self::fieldRow(_n('Type', 'Types', 1), self::friendlyName(\ProjectType::class, (int) $project['projecttypes_id']), '', '');
      self::fieldRow(__('Cesta Portfólio', 'projectplus'), self::friendlyName(PortfolioBasket::class, (int) $project['plugin_projectplus_portfoliobaskets_id']), __('Ação PDTIC', 'projectplus'), self::friendlyName(PdticAction::class, (int) $project['plugin_projectplus_pdticactions_id']));

      self::headerRow(__('Gerente', 'projectplus'));
      self::fieldRow(__('Usuário', 'projectplus'), self::friendlyName(\User::class, (int) $project['users_id']), __('Grupo', 'projectplus'), self::friendlyName(\Group::class, (int) $project['groups_id']));

      self::headerRow(__('Dados do solicitante (Cliente)', 'projectplus'));
      self::fieldRow(__('Área do Solicitante', 'projectplus'), (string) $project['requester_area'], __('Solicitante', 'projectplus'), (string) $project['requester_name']);
      self::fieldRow(__('E-mail do Solicitante', 'projectplus'), (string) $project['requester_email'], __('Ponto focal', 'projectplus'), (string) $project['focal_point']);
      self::fieldRow(__('E-mail do Ponto focal', 'projectplus'), (string) $project['focal_point_email'], '', '');

      self::headerRow(__('Planejamento', 'projectplus'));
      self::fieldRow(__('Data planejada para começo', 'projectplus'), (string) $project['plan_start_date'], __('Data real do começo', 'projectplus'), (string) $project['real_start_date']);
      self::fieldRow(__('Data planejada para fim', 'projectplus'), (string) $project['plan_end_date'], __('Data real de fim', 'projectplus'), (string) $project['real_end_date']);
      self::durationRow((int) $project['id']);
      self::textareaRow(__('Descrição', 'projectplus'), (string) $project['content'], __('Objetivo', 'projectplus'), (string) $project['objective']);
      self::textareaRow(__('Comentários', 'projectplus'), (string) $project['comment'], __('Produto/Resultado esperado', 'projectplus'), (string) $project['expected_result']);
      echo '</table></div></div>';

      if ($hasNativeProject) {
         echo "<div class='projectplus-portal-workspace__panel' data-projectplus-portal-panel='documents'>";
         DocumentAcceptance::showPortalForProject($nativeProject);
         echo '</div>';
         if ($canViewInteractions) {
            echo "<div class='projectplus-portal-workspace__panel' data-projectplus-portal-panel='interactions'>";
            echo "<section class='projectplus-portal-interactions'><h3>" . self::escape(__('Interações', 'projectplus')) . '</h3>';
            ProjectInteraction::show($nativeProject);
            echo '</section></div>';
         }
      }
      echo "<script>(function () { var workspace = document.getElementById('{$workspaceId}'); if (!workspace || workspace.dataset.projectplusPortalReady) { return; } workspace.dataset.projectplusPortalReady = '1'; function isAvailable(target) { return !!workspace.querySelector('[data-projectplus-portal-tab=' + target + ']'); } function setActiveTab(target, updateLocation) { if (!isAvailable(target)) { target = 'project'; } workspace.querySelectorAll('[data-projectplus-portal-tab]').forEach(function (item) { var active = item.dataset.projectplusPortalTab === target; item.classList.toggle('active', active); item.setAttribute('aria-selected', active ? 'true' : 'false'); }); workspace.querySelectorAll('[data-projectplus-portal-panel]').forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.projectplusPortalPanel === target); }); workspace.querySelectorAll('form').forEach(function (form) { var field = form.querySelector('input[name=projectplus_portal_tab]'); if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'projectplus_portal_tab'; form.appendChild(field); } field.value = target; }); if (updateLocation && window.history && window.history.replaceState && window.URL) { var url = new URL(window.location.href); url.searchParams.set('projectplus_portal_tab', target); window.history.replaceState({}, document.title, url.toString()); } } workspace.querySelectorAll('[data-projectplus-portal-tab]').forEach(function (tab) { tab.addEventListener('click', function () { setActiveTab(tab.dataset.projectplusPortalTab, true); }); }); var requested = window.URL ? new URL(window.location.href).searchParams.get('projectplus_portal_tab') : ''; setActiveTab(isAvailable(requested) ? requested : 'project', false); })();</script>";
      echo '</section>';
   }

   private static function portalTabButton(string $name, string $label, bool $active = false): void
   {
      echo "<li class='nav-item'><button type='button' class='nav-link" . ($active ? ' active' : '')
         . "' role='tab' aria-selected='" . ($active ? 'true' : 'false')
         . "' data-projectplus-portal-tab='{$name}'>" . self::escape($label) . '</button></li>';
   }

   private static function getProjectUrl(int $projectId): string
   {
      global $CFG_GLPI;
      return rtrim($CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/myproject.php?id=' . $projectId;
   }

   /**
    * @return array<int, array<string, mixed>>
    */
   private static function getProjectsForUser(int $userId, int $limit = 0): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $projectTable = \Project::getTable();
      $dataTable = ProjectData::getTable();
      $priorityTable = ProjectPriority::getTable();
      $criteria = [
         'SELECT' => [
            $projectTable . '.id AS id',
            $projectTable . '.entities_id AS entities_id',
            $projectTable . '.name AS name',
            $projectTable . '.code AS code',
            $projectTable . '.priority AS priority',
            $projectTable . '.content AS content',
            $projectTable . '.comment AS comment',
            $projectTable . '.date AS date',
            $projectTable . '.date_creation AS date_creation',
            $projectTable . '.date_mod AS date_mod',
            $projectTable . '.projects_id AS projects_id',
            $projectTable . '.projecttypes_id AS projecttypes_id',
            $projectTable . '.users_id AS users_id',
            $projectTable . '.groups_id AS groups_id',
            $projectTable . '.plan_start_date AS plan_start_date',
            $projectTable . '.plan_end_date AS plan_end_date',
            $projectTable . '.real_start_date AS real_start_date',
            $projectTable . '.real_end_date AS real_end_date',
            $projectTable . '.percent_done AS percent_done',
            $projectTable . '.projectstates_id AS projectstates_id',
            $dataTable . '.plugin_projectplus_portfoliobaskets_id AS plugin_projectplus_portfoliobaskets_id',
            $dataTable . '.plugin_projectplus_pdticactions_id AS plugin_projectplus_pdticactions_id',
            $dataTable . '.objective AS objective',
            $dataTable . '.expected_result AS expected_result',
            $dataTable . '.requester_area AS requester_area',
            $dataTable . '.requester_users_id AS requester_users_id',
            $dataTable . '.requester_name AS requester_name',
            $dataTable . '.requester_email AS requester_email',
            $dataTable . '.focal_point_users_id AS focal_point_users_id',
            $dataTable . '.focal_point AS focal_point',
            $dataTable . '.focal_point_email AS focal_point_email',
            $priorityTable . '.ranking AS ranking',
         ],
         'FROM' => $projectTable,
         'INNER JOIN' => [
            $dataTable => [
               'FKEY' => [
                  $projectTable => 'id',
                  $dataTable    => 'projects_id',
               ],
            ],
         ],
         // O ranking e opcional: projeto sem priorizacao continua na lista.
         'LEFT JOIN' => [
            $priorityTable => [
               'FKEY' => [
                  $projectTable  => 'id',
                  $priorityTable => 'projects_id',
               ],
            ],
         ],
         'WHERE' => [
            $projectTable . '.is_deleted' => 0,
            'OR' => [
               $dataTable . '.requester_users_id'   => $userId,
               $dataTable . '.focal_point_users_id' => $userId,
            ],
         ],
         'ORDER' => [$projectTable . '.date_mod DESC'],
      ];
      if ($limit > 0) {
         $criteria['LIMIT'] = $limit;
      }

      $projects = [];
      foreach ($DB->request($criteria) as $project) {
         if (Session::haveAccessToEntity((int) $project['entities_id'])) {
            $projects[] = $project;
         }
      }

      return $projects;
   }

   /** Projeto sem estado definido fica em branco, como na tela nativa. */
   private static function getProjectState(int $stateId): string
   {
      return $stateId > 0
         ? (string) Dropdown::getDropdownName('glpi_projectstates', $stateId)
         : '';
   }

   private static function headerRow(string $label): void
   {
      echo '<tr><td colspan="4" class="subheader">' . self::escape($label) . '</td></tr>';
   }

   private static function fieldRow(string $firstLabel, string $firstValue, string $secondLabel, string $secondValue): void
   {
      echo '<tr class="tab_bg_1"><td>' . self::escape($firstLabel) . '</td><td>' . self::input($firstValue) . '</td>';
      echo '<td>' . self::escape($secondLabel) . '</td><td>' . self::input($secondValue) . '</td></tr>';
   }

   private static function textareaRow(string $firstLabel, string $firstValue, string $secondLabel, string $secondValue): void
   {
      echo '<tr class="tab_bg_1"><td>' . self::escape($firstLabel) . '</td><td>' . self::textarea($firstValue) . '</td>';
      echo '<td>' . self::escape($secondLabel) . '</td><td>' . self::textarea($secondValue) . '</td></tr>';
   }

   private static function durationRow(int $projectId): void
   {
      $planned = \ProjectTask::getTotalPlannedDurationForProject($projectId);
      $effective = \ProjectTask::getTotalEffectiveDurationForProject($projectId);
      self::fieldRow(
         __('Duração planejada', 'projectplus'),
         Html::timestampToString($planned, false),
         __('Duração efetiva', 'projectplus'),
         Html::timestampToString($effective, false)
      );
   }

   private static function input(string $value): string
   {
      return '<input type="text" class="form-control" value="' . self::escape(self::decode($value)) . '" readonly>';
   }

   private static function textarea(string $value): string
   {
      return '<textarea class="form-control" rows="6" readonly>' . self::escape(self::decode($value)) . '</textarea>';
   }

   /**
    * @param class-string $class
    */
   private static function friendlyName(string $class, int $id): string
   {
      return $id > 0 ? (string) $class::getFriendlyNameById($id) : '';
   }

   private static function textValue(string $value): string
   {
      return $value === '' ? '&nbsp;' : nl2br(self::escape(self::decode(strip_tags($value))));
   }

   private static function decode(string $value): string
   {
      return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }

   /** Formats stored dates using the GLPI date/time preference of the current user. */
   private static function formatDateTime(string $value): string
   {
      return $value === '' || $value === '0000-00-00 00:00:00' ? '' : Html::convDateTime($value);
   }
}
