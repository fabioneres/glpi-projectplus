<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Project;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class ProjectData extends CommonDBTM
{
   private const FIELDS = [
      'plugin_projectplus_portfoliobaskets_id',
      'plugin_projectplus_pdticactions_id',
      'objective',
      'expected_result',
      'requester_area',
      'requester_name',
      'requester_email',
      'focal_point',
      'focal_point_email',
   ];

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_projectdata';
   }

   public static function getForProject(int $projects_id): array
   {
      $data = new self();
      if ($projects_id > 0 && $data->getFromDBByCrit(['projects_id' => $projects_id])) {
         return $data->fields;
      }

      return array_fill_keys(self::FIELDS, '');
   }

   public static function postProjectAdd(Project $project): void
   {
      self::saveForProject($project);
   }

   public static function postProjectUpdate(Project $project): void
   {
      self::saveForProject($project);
   }

   public static function preProjectPurge(Project $project): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $DB->delete(self::getTable(), ['projects_id' => (int) $project->getID()]);
   }

   private static function saveForProject(Project $project): void
   {
      $input = $project->input ?? [];
      if (!is_array($input) || !array_intersect(array_keys($input), self::FIELDS)) {
         return;
      }

      $values = [];
      foreach (self::FIELDS as $field) {
         $values[$field] = trim((string) ($input[$field] ?? ''));
      }

      foreach (['plugin_projectplus_portfoliobaskets_id', 'plugin_projectplus_pdticactions_id'] as $field) {
         $values[$field] = max(0, (int) $values[$field]);
      }

      foreach (['requester_email', 'focal_point_email'] as $field) {
         if ($values[$field] !== '' && filter_var($values[$field], FILTER_VALIDATE_EMAIL) === false) {
            Session::addMessageAfterRedirect(__('E-mail inválido.', 'projectplus'), false, ERROR);
            return;
         }
      }

      /** @var DBmysql $DB */
      global $DB;

      $DB->updateOrInsert(
         self::getTable(),
         array_merge($values, [
            'projects_id' => (int) $project->getID(),
            'entities_id' => (int) $project->fields['entities_id'],
            'date_mod'    => $_SESSION['glpi_currenttime'],
         ]),
         ['projects_id' => (int) $project->getID()]
      );
   }
}
