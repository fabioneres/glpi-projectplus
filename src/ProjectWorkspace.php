<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonGLPI;
use Project;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Consolidated Project+ workspace, rendered as one native Project tab. */
class ProjectWorkspace extends CommonDBTM
{
   public static function getTable($classname = null): string
   {
      return ProjectInteraction::getTable();
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item instanceof Project && !$withtemplate && Session::haveRight('project', READ)) {
         return self::createTabEntry('Project+', 0, $item::getType(), 'ti ti-apps');
      }
      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item instanceof Project) {
         self::show($item);
      }
      return true;
   }

   private static function show(Project $project): void
   {
      $workspaceId = 'projectplus-workspace-' . (int) $project->getID();
      $showInteractions = ProjectInteraction::canViewProjectplus($project);
      $showPriority = Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ);
      $activeTab = self::getRequestedTab();
      if (($activeTab === 'interactions' && !$showInteractions)
         || ($activeTab === 'priority' && !$showPriority)) {
         $activeTab = 'documents';
      }

      echo "<section class='projectplus-workspace' id='{$workspaceId}'>";
      echo "<nav class='projectplus-workspace__tabs' role='tablist' aria-label='Project+'>";
      self::tabButton($workspaceId, 'documents', __('Termos', 'projectplus'), 'ti ti-file-check', $activeTab === 'documents');
      if ($showInteractions) {
         self::tabButton($workspaceId, 'interactions', __('Interações', 'projectplus'), 'ti ti-messages', $activeTab === 'interactions');
      }
      if ($showPriority) {
         self::tabButton($workspaceId, 'priority', __('Priorização', 'projectplus'), 'ti ti-list-numbers', $activeTab === 'priority');
      }
      echo '</nav>';

      echo "<div class='projectplus-workspace__panel" . ($activeTab === 'documents' ? ' is-active' : '') . "' role='tabpanel' data-projectplus-workspace-panel='documents'>";
      DocumentAcceptance::showForItem($project);
      echo '</div>';
      if ($showInteractions) {
         echo "<div class='projectplus-workspace__panel" . ($activeTab === 'interactions' ? ' is-active' : '') . "' role='tabpanel' data-projectplus-workspace-panel='interactions'>";
         ProjectInteraction::show($project);
         echo '</div>';
      }
      if ($showPriority) {
         echo "<div class='projectplus-workspace__panel" . ($activeTab === 'priority' ? ' is-active' : '') . "' role='tabpanel' data-projectplus-workspace-panel='priority'>";
         ProjectPriority::showForProject($project);
         echo '</div>';
      }

      echo "<script>(function () { var workspace = document.getElementById('{$workspaceId}'); if (!workspace || workspace.dataset.projectplusReady) { return; } workspace.dataset.projectplusReady = '1'; function isAvailable(target) { return ['documents', 'interactions', 'priority'].indexOf(target) !== -1 && !!workspace.querySelector('[data-projectplus-workspace-tab=' + target + ']'); } function setActiveTab(target, updateLocation) { if (!isAvailable(target)) { target = 'documents'; } workspace.querySelectorAll('[data-projectplus-workspace-tab]').forEach(function (tab) { var active = tab.dataset.projectplusWorkspaceTab === target; tab.classList.toggle('is-active', active); tab.setAttribute('aria-selected', active ? 'true' : 'false'); }); workspace.querySelectorAll('[data-projectplus-workspace-panel]').forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.projectplusWorkspacePanel === target); }); workspace.querySelectorAll('form').forEach(function (form) { var field = form.querySelector('input[name=projectplus_workspace_tab]'); if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'projectplus_workspace_tab'; form.appendChild(field); } field.value = target; }); if (updateLocation && window.history && window.history.replaceState && window.URL) { var url = new URL(window.location.href); url.searchParams.set('projectplus_workspace_tab', target); window.history.replaceState({}, document.title, url.toString()); } } workspace.querySelectorAll('[data-projectplus-workspace-tab]').forEach(function (button) { button.addEventListener('click', function () { setActiveTab(button.dataset.projectplusWorkspaceTab, true); }); }); var requested = window.URL ? new URL(window.location.href).searchParams.get('projectplus_workspace_tab') : ''; setActiveTab(isAvailable(requested) ? requested : '{$activeTab}', false); })();</script>";
      echo '</section>';
   }

   public static function getProjectRedirectUrl(int $projectId, string $tab = ''): string
   {
      $tab = self::normalizeTab($tab);
      return Project::getFormURLWithID($projectId) . '&projectplus_workspace_tab=' . rawurlencode($tab);
   }

   private static function getRequestedTab(): string
   {
      return self::normalizeTab((string) ($_REQUEST['projectplus_workspace_tab'] ?? ''));
   }

   private static function normalizeTab(string $tab): string
   {
      return in_array($tab, ['documents', 'interactions', 'priority'], true) ? $tab : 'documents';
   }

   private static function tabButton(string $workspaceId, string $name, string $label, string $icon, bool $active = false): void
   {
      echo "<button type='button' role='tab' aria-controls='{$workspaceId}-{$name}' aria-selected='" . ($active ? 'true' : 'false') . "' class='projectplus-workspace__tab" . ($active ? ' is-active' : '') . "' data-projectplus-workspace-tab='{$name}'><i class='" . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . "'></i>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>';
   }
}
