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

      echo "<section class='projectplus-workspace' id='{$workspaceId}'>";
      echo "<nav class='projectplus-workspace__tabs' role='tablist' aria-label='Project+'>";
      self::tabButton($workspaceId, 'documents', __('Termos', 'projectplus'), 'ti ti-file-check', true);
      if ($showInteractions) {
         self::tabButton($workspaceId, 'interactions', __('Interações', 'projectplus'), 'ti ti-messages');
      }
      if ($showPriority) {
         self::tabButton($workspaceId, 'priority', __('Priorização', 'projectplus'), 'ti ti-list-numbers');
      }
      echo '</nav>';

      echo "<div class='projectplus-workspace__panel is-active' role='tabpanel' data-projectplus-workspace-panel='documents'>";
      DocumentAcceptance::showForItem($project);
      echo '</div>';
      if ($showInteractions) {
         echo "<div class='projectplus-workspace__panel' role='tabpanel' data-projectplus-workspace-panel='interactions'>";
         ProjectInteraction::show($project);
         echo '</div>';
      }
      if ($showPriority) {
         echo "<div class='projectplus-workspace__panel' role='tabpanel' data-projectplus-workspace-panel='priority'>";
         ProjectPriority::showForProject($project);
         echo '</div>';
      }

      echo "<script>(function () { var workspace = document.getElementById('{$workspaceId}'); if (!workspace || workspace.dataset.projectplusReady) { return; } workspace.dataset.projectplusReady = '1'; workspace.querySelectorAll('[data-projectplus-workspace-tab]').forEach(function (button) { button.addEventListener('click', function () { var target = button.dataset.projectplusWorkspaceTab; workspace.querySelectorAll('[data-projectplus-workspace-tab]').forEach(function (tab) { tab.classList.toggle('is-active', tab === button); tab.setAttribute('aria-selected', tab === button ? 'true' : 'false'); }); workspace.querySelectorAll('[data-projectplus-workspace-panel]').forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.projectplusWorkspacePanel === target); }); }); }); })();</script>";
      echo '</section>';
   }

   private static function tabButton(string $workspaceId, string $name, string $label, string $icon, bool $active = false): void
   {
      echo "<button type='button' role='tab' aria-controls='{$workspaceId}-{$name}' aria-selected='" . ($active ? 'true' : 'false') . "' class='projectplus-workspace__tab" . ($active ? ' is-active' : '') . "' data-projectplus-workspace-tab='{$name}'><i class='" . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . "'></i>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>';
   }
}
