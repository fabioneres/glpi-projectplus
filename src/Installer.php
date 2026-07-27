<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Migration;
use Throwable;
use Toolbox;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class Installer
{
   public static function install(): bool
   {
      try {
         self::ensureSchema();
      } catch (Throwable $exception) {
         Toolbox::logInFile('plugin_projectplus', 'Install failed: ' . $exception->getMessage() . PHP_EOL);
         return false;
      }

      \Config::setConfigurationValues('plugin:projectplus', ['dbversion' => PLUGIN_PROJECTPLUS_VERSION]);
      return true;
   }

   private static function ensureSchema(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = ProjectData::getTable();
      self::ensureDropdownTable(PortfolioBasket::getTable());
      self::ensureDropdownTable(PdticAction::getTable());
      self::ensureTicketConfigTable();
      self::ensureTicketPhaseTable();
      self::ensureTicketPhaseHistoryTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery(
            "CREATE TABLE `{$table}` (
               `id` int unsigned NOT NULL AUTO_INCREMENT,
               `projects_id` int unsigned NOT NULL DEFAULT 0,
               `entities_id` int unsigned NOT NULL DEFAULT 0,
               `portfolio_basket` varchar(255) NOT NULL DEFAULT '',
               `pdtic_action` varchar(255) NOT NULL DEFAULT '',
               `objective` text NULL,
               `expected_result` text NULL,
               `requester_area` varchar(255) NOT NULL DEFAULT '',
               `requester_users_id` int unsigned NOT NULL DEFAULT 0,
               `requester_name` varchar(255) NOT NULL DEFAULT '',
               `requester_email` varchar(255) NOT NULL DEFAULT '',
               `focal_point_users_id` int unsigned NOT NULL DEFAULT 0,
               `focal_point` varchar(255) NOT NULL DEFAULT '',
               `focal_point_email` varchar(255) NOT NULL DEFAULT '',
               `date_creation` timestamp NULL DEFAULT NULL,
               `date_mod` timestamp NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `projects_id` (`projects_id`),
               KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
         );
      }

      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      $fields = [
         'projects_id'       => 'int unsigned NOT NULL DEFAULT 0',
         'entities_id'       => 'int unsigned NOT NULL DEFAULT 0',
         'portfolio_basket'  => "varchar(255) NOT NULL DEFAULT ''",
         'pdtic_action'      => "varchar(255) NOT NULL DEFAULT ''",
         'plugin_projectplus_portfoliobaskets_id' => 'int unsigned NOT NULL DEFAULT 0',
         'plugin_projectplus_pdticactions_id' => 'int unsigned NOT NULL DEFAULT 0',
         'objective'         => 'text NULL',
         'expected_result'   => 'text NULL',
         'requester_area'    => "varchar(255) NOT NULL DEFAULT ''",
         'requester_users_id' => 'int unsigned NOT NULL DEFAULT 0',
         'requester_name'    => "varchar(255) NOT NULL DEFAULT ''",
         'requester_email'   => "varchar(255) NOT NULL DEFAULT ''",
         'focal_point_users_id' => 'int unsigned NOT NULL DEFAULT 0',
         'focal_point'       => "varchar(255) NOT NULL DEFAULT ''",
         'focal_point_email' => "varchar(255) NOT NULL DEFAULT ''",
         'date_creation'     => 'timestamp NULL DEFAULT NULL',
         'date_mod'          => 'timestamp NULL DEFAULT NULL',
      ];
      foreach ($fields as $field => $definition) {
         if (!$DB->fieldExists($table, $field)) {
            $migration->addField($table, $field, $definition);
         }
      }
      foreach (['requester_users_id', 'focal_point_users_id'] as $field) {
         $migration->addKey($table, $field, $field . '_index');
      }
      $migration->executeMigration();
   }

   private static function ensureDropdownTable(string $table): void
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($DB->tableExists($table)) {
         return;
      }

      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `is_recursive` tinyint NOT NULL DEFAULT 0,
            `name` varchar(255) NOT NULL DEFAULT '',
            `comment` text NULL,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `date_mod` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_active` (`is_active`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureTicketConfigTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketConfig::getTable();
      if ($DB->tableExists($table)) {
         return;
      }

      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `itilcategories_id` int unsigned NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entities_id` (`entities_id`),
            KEY `itilcategories_id` (`itilcategories_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureTicketPhaseTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketPhase::getTable();
      if ($DB->tableExists($table)) {
         return;
      }

      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT 0,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `phase` varchar(64) NOT NULL DEFAULT 'classification',
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `justification` text NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tickets_id` (`tickets_id`),
            KEY `entities_phase` (`entities_id`, `phase`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureTicketPhaseHistoryTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketPhaseHistory::getTable();
      if ($DB->tableExists($table)) {
         return;
      }

      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT 0,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `old_phase` varchar(64) NOT NULL DEFAULT '',
            `new_phase` varchar(64) NOT NULL DEFAULT '',
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `justification` text NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ticket_date` (`tickets_id`, `date_creation`),
            KEY `entities_id` (`entities_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   public static function uninstall(): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      $DB->doQuery('DROP TABLE IF EXISTS `' . ProjectData::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . PortfolioBasket::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . PdticAction::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketPhaseHistory::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketPhase::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketConfig::getTable() . '`');
      \Config::deleteConfigurationValues('plugin:projectplus', ['dbversion']);
      return true;
   }
}
