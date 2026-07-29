<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Html;
use Project;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Institutional ranking kept independently from GLPI's native priority. */
class ProjectPriority extends CommonDBTM
{
   private const CRITERIA = [
      'pdtic_risk' => 'Risco residual do PDTIC',
      'result_scope' => 'Abrangência dos resultados',
      'project_urgency' => 'Urgência do projeto',
      'estimated_time' => 'Tempo estimado do projeto',
      'team_familiarity' => 'Familiaridade da equipe com a proposta',
      'institutional_priority' => 'Prioridade institucional',
      'waiting_time' => 'Tempo aguardando a execução',
   ];

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_projectpriorities';
   }

   /** @return array<string, mixed> */
   public static function getForProject(int $projectId): array
   {
      global $DB;
      $defaults = array_fill_keys(array_keys(self::CRITERIA), 0);
      $defaults += ['projects_id' => $projectId, 'entities_id' => 0, 'ranking' => 0, 'date_mod' => null];
      if ($projectId <= 0 || !$DB->tableExists(self::getTable())) {
         return $defaults;
      }
      $row = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['projects_id' => $projectId], 'LIMIT' => 1])->current();
      return $row ? array_merge($defaults, $row) : $defaults;
   }

   /** @return array<int, string> */
   public static function getLevelOptions(): array
   {
      return [
         0 => __('Não classificado', 'projectplus'),
         1 => __('1 - Nível inicial (temporário)', 'projectplus'),
         2 => __('2 - Nível baixo (temporário)', 'projectplus'),
         3 => __('3 - Nível médio (temporário)', 'projectplus'),
         4 => __('4 - Nível alto (temporário)', 'projectplus'),
         5 => __('5 - Nível máximo (temporário)', 'projectplus'),
      ];
   }

   public static function getInstitutionalRange(int $ranking): string
   {
      if ($ranking <= 0) return __('Não classificada', 'projectplus');
      if ($ranking <= 5) return __('Muito Baixa', 'projectplus');
      if ($ranking <= 10) return __('Baixa', 'projectplus');
      if ($ranking <= 15) return __('Média', 'projectplus');
      if ($ranking <= 20) return __('Alta', 'projectplus');
      if ($ranking <= 25) return __('Muito Alta', 'projectplus');
      // Seven criteria allow 31–35. Until the approved matrix says otherwise,
      // these values remain in the highest institutional band.
      return __('Crítica', 'projectplus');
   }

   /** Maps the institutional ranking to GLPI's six native priority values. */
   public static function getNativePriorityForRanking(int $ranking): int
   {
      if ($ranking <= 0) return 0;
      if ($ranking <= 5) return 1;      // Muito baixa
      if ($ranking <= 10) return 2;     // Baixa
      if ($ranking <= 15) return 3;     // Média
      if ($ranking <= 20) return 4;     // Alta
      if ($ranking <= 25) return 5;     // Muito alta
      return 6;                          // Crítica (26–35)
   }

   /**
    * Exposes the ranking as a native Project search column for portfolio
    * managers. Once registered, GLPI can add it to either personal or global
    * saved lists through its standard "Listas" interface.
    *
    * @return array<int, array<string, mixed>>
    */
   public static function getSearchOptionsForProject(): array
   {
      if (!Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)) {
         return [];
      }

      return [[
         'id'             => 9520,
         'table'          => self::getTable(),
         'field'          => 'ranking',
         'name'           => __('Ranking', 'projectplus'),
         'datatype'       => 'integer',
         'searchtype'     => ['equals', 'notequals', 'lessthan', 'morethan'],
         'forcegroupby'   => true,
         'massiveaction'  => false,
         'joinparams'     => ['jointype' => 'child'],
      ]];
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item instanceof Project && !$withtemplate && Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)) {
         return self::createTabEntry(__('Priorização', 'projectplus'), 0, $item::getType(), 'ti ti-list-numbers');
      }
      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item instanceof Project && Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)) {
         self::showForProject($item);
      }
      return true;
   }

   public static function saveForProject(Project $project, array $input): void
   {
      if (!Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, UPDATE)
         || !Session::haveRight('project', UPDATE)
         || !Session::haveAccessToEntity((int) $project->getEntityID(), true)) {
         throw new \RuntimeException(__('Sem permissão para priorizar este projeto.', 'projectplus'));
      }
      $values = [];
      $complete = true;
      foreach (self::CRITERIA as $field => $label) {
         $value = (int) ($input[$field] ?? 0);
         if ($value < 0 || $value > 5) throw new \RuntimeException(__('Valor de priorização inválido.', 'projectplus'));
         $values[$field] = $value;
         $complete = $complete && $value > 0;
      }
      $values['ranking'] = $complete ? array_sum($values) : 0;
      $values['entities_id'] = (int) $project->getEntityID();
      $values['date_mod'] = date('Y-m-d H:i:s');
      global $DB;
      $DB->updateOrInsert(self::getTable(), $values + ['projects_id' => (int) $project->getID(), 'date_creation' => date('Y-m-d H:i:s')], ['projects_id' => (int) $project->getID()]);
      if ($complete) {
         $project->update([
            'id' => (int) $project->getID(),
            'priority' => self::getNativePriorityForRanking((int) $values['ranking']),
         ]);
      }
   }

   public static function showForProject(Project $project): void
   {
      if (!Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)) {
         return;
      }
      $data = self::getForProject((int) $project->getID());
      $ranking = (int) $data['ranking'];
      global $CFG_GLPI;
      $url = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/projectpriority.form.php';
      $nativePriority = self::getNativePriorityForRanking($ranking);
      $priorityLabel = $ranking ? __('Prioridade', 'projectplus') . ' – ' . \CommonITILObject::getPriorityName($nativePriority) : self::getInstitutionalRange($ranking);
      $priorityStyle = self::getPriorityStyle($nativePriority);
      $canEdit = Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, UPDATE)
         && Session::haveRight('project', UPDATE)
         && Session::haveAccessToEntity((int) $project->getEntityID(), true);
      echo "<div class='projectplus-priority projectplus-priority--{$nativePriority}' {$priorityStyle}><header class='projectplus-priority__header projectplus-priority__header--{$nativePriority}'><div><h3>" . self::escape(__('Priorização institucional', 'projectplus')) . '</h3><p>' . self::escape(__('O ranking calcula automaticamente a prioridade nativa do GLPI.', 'projectplus')) . "</p></div><div class='projectplus-priority__score projectplus-priority__score--{$nativePriority}'><span>" . self::escape(__('Ranking', 'projectplus')) . '</span><strong>' . ($ranking ?: '—') . "</strong><em class='projectplus-priority__native projectplus-priority__native--{$nativePriority}'>" . self::escape($priorityLabel) . '</em></div></header>';
      if ($canEdit) {
         echo "<form method='post' action='" . self::escape($url) . "' data-track-changes='true'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('projects_id', ['value' => (int) $project->getID()]);
      }
      echo "<div class='projectplus-priority__grid'>";
      foreach (self::CRITERIA as $field => $label) {
         echo "<div class='projectplus-priority__field'><label>" . self::escape(__($label, 'projectplus')) . '</label>';
         Dropdown::showFromArray($field, self::getLevelOptions(), ['value' => (int) $data[$field], 'id' => 'projectplus-priority-' . $field, 'disabled' => !$canEdit]);
         echo '</div>';
      }
      echo '</div><footer class="projectplus-priority__footer"><small>' . self::escape(__('O ranking é calculado pela soma dos sete critérios preenchidos.', 'projectplus')) . '</small>';
      if ($canEdit) {
         echo "<button class='btn btn-primary' type='submit' name='save_priority' value='1'><i class='ti ti-device-floppy'></i> " . self::escape(__('Salvar priorização', 'projectplus')) . '</button>';
      }
      echo '</footer>';
      if ($canEdit) {
         echo '</form>';
      }
      echo '</div>';
   }

   /** @return array<int, array<string, mixed>> */
   public static function getPortfolioProjects(): array
   {
      global $DB;
      $projectTable = Project::getTable();
      $priorityTable = self::getTable();
      $projects = [];
      foreach ($DB->request([
         'SELECT' => [$projectTable . '.id', $projectTable . '.name', $projectTable . '.entities_id', $projectTable . '.priority', $projectTable . '.percent_done', $projectTable . '.date_mod', $priorityTable . '.ranking'],
         'FROM' => $projectTable,
         'LEFT JOIN' => [$priorityTable => ['FKEY' => [$projectTable => 'id', $priorityTable => 'projects_id']]],
         'WHERE' => [$projectTable . '.is_deleted' => 0],
         'ORDER' => [$priorityTable . '.ranking DESC', $projectTable . '.date_mod DESC'],
      ]) as $project) {
         if (Session::haveAccessToEntity((int) $project['entities_id'])) $projects[] = $project;
      }
      return $projects;
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }

   /**
    * Uses the same priority colour that is configured by the administrator in
    * Configurar > Geral > Cores das prioridades. The session values are the
    * native GLPI source used throughout the core project and ticket views.
    */
   private static function getPriorityStyle(int $priority): string
   {
      if ($priority < 1 || $priority > 6) {
         return '';
      }

      $fallbacks = [
         1 => '#fef2f2', 2 => '#fce1e0', 3 => '#fbcfcf',
         4 => '#fac1c0', 5 => '#f9afae', 6 => '#f55d5a',
      ];
      $color = (string) ($_SESSION['glpipriority_' . $priority] ?? $fallbacks[$priority]);
      if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
         $color = $fallbacks[$priority];
      }

      $hex = ltrim($color, '#');
      $red = hexdec(substr($hex, 0, 2));
      $green = hexdec(substr($hex, 2, 2));
      $blue = hexdec(substr($hex, 4, 2));
      $tint = sprintf('#%02x%02x%02x', (int) (($red + 255 * 5) / 6), (int) (($green + 255 * 5) / 6), (int) (($blue + 255 * 5) / 6));
      $brightness = ($red * 299 + $green * 587 + $blue * 114) / 1000;
      $badgeText = $brightness < 150 ? '#ffffff' : '#263e58';

      return "style='--projectplus-priority-color: {$color}; --projectplus-priority-tint: {$tint}; --projectplus-priority-badge-text: {$badgeText};'";
   }
}
