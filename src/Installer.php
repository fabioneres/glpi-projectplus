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

      ProjectProfile::installRights();
      ProjectProfile::initProfile();
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
      self::ensureDropdownTable(FollowupTemplate::getTable());
      self::ensureDropdownTable(SolutionTemplate::getTable());
      self::ensureTicketConfigTable();
      self::ensureTicketPhaseTable();
      self::ensureTicketPhaseHistoryTable();
      self::ensureTicketIntentTable();
      self::ensureTipConfigTable();
      self::ensureTipFieldTable();
      self::ensureTipAnswerTable();
      self::ensureDocumentAcceptanceTable();
      self::ensureDocumentTemplateTable();
      self::ensureDocumentFieldTable();
      self::ensureDocumentDraftTable();
      self::ensureTicketProjectTable();
      self::ensureProjectInteractionTable();
      self::ensureProjectPriorityTable();
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

   private static function ensureTicketProjectTable(): void
   {
      global $DB; $table = TicketProject::getTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery("CREATE TABLE `{$table}` (`id` int unsigned NOT NULL AUTO_INCREMENT, `tickets_id` int unsigned NOT NULL DEFAULT 0, `projects_id` int unsigned NOT NULL DEFAULT 0, `entities_id` int unsigned NOT NULL DEFAULT 0, `users_id` int unsigned NOT NULL DEFAULT 0, `date_creation` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `tickets_id` (`tickets_id`), KEY `projects_id` (`projects_id`), KEY `entities_id` (`entities_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
      }
   }
   private static function ensureProjectInteractionTable(): void { global $DB; $t=ProjectInteraction::getTable(); if(!$DB->tableExists($t))$DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT,`projects_id` int unsigned NOT NULL DEFAULT 0,`entities_id` int unsigned NOT NULL DEFAULT 0,`users_id` int unsigned NOT NULL DEFAULT 0,`content` text NOT NULL,`is_internal` tinyint NOT NULL DEFAULT 0,`notify_participants` tinyint NOT NULL DEFAULT 0,`date_creation` timestamp NULL DEFAULT NULL,PRIMARY KEY(`id`),KEY `projects_id`(`projects_id`),KEY `entities_id`(`entities_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
   private static function ensureProjectPriorityTable(): void { global $DB; $t=ProjectPriority::getTable(); if(!$DB->tableExists($t))$DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT,`projects_id` int unsigned NOT NULL DEFAULT 0,`entities_id` int unsigned NOT NULL DEFAULT 0,`pdtic_risk` tinyint unsigned NOT NULL DEFAULT 0,`result_scope` tinyint unsigned NOT NULL DEFAULT 0,`project_urgency` tinyint unsigned NOT NULL DEFAULT 0,`estimated_time` tinyint unsigned NOT NULL DEFAULT 0,`team_familiarity` tinyint unsigned NOT NULL DEFAULT 0,`institutional_priority` tinyint unsigned NOT NULL DEFAULT 0,`waiting_time` tinyint unsigned NOT NULL DEFAULT 0,`ranking` tinyint unsigned NOT NULL DEFAULT 0,`date_creation` timestamp NULL DEFAULT NULL,`date_mod` timestamp NULL DEFAULT NULL,PRIMARY KEY(`id`),UNIQUE KEY `projects_id`(`projects_id`),KEY `entities_ranking`(`entities_id`,`ranking`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }

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

   private static function ensureTicketIntentTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketIntent::getTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT 0,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `submitter_name` varchar(255) NOT NULL DEFAULT '',
            `submitter_email` varchar(255) NOT NULL DEFAULT '',
            `executor_area` varchar(255) NOT NULL DEFAULT '',
            `requester_name` varchar(255) NOT NULL DEFAULT '',
            `requester_area` varchar(255) NOT NULL DEFAULT '',
            `project_name` varchar(255) NOT NULL DEFAULT '',
            `project_description` text NULL,
            `specific_objective` text NULL,
            `metrics` text NULL,
            `relevance` text NULL,
            `benefits` text NULL,
            `resources` text NULL,
            `observations` text NULL,
            `status` varchar(32) NOT NULL DEFAULT 'requested',
            `requested_users_id` int unsigned NOT NULL DEFAULT 0,
            `date_requested` timestamp NULL DEFAULT NULL,
            `date_submitted` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tickets_id` (`tickets_id`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
         );
      }

      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      foreach ([
         'status' => "varchar(32) NOT NULL DEFAULT 'requested'",
         'requested_users_id' => 'int unsigned NOT NULL DEFAULT 0',
         'date_requested' => 'timestamp NULL DEFAULT NULL',
         'date_submitted' => 'timestamp NULL DEFAULT NULL',
         'review_users_id' => 'int unsigned NOT NULL DEFAULT 0',
         'review_justification' => 'text NULL',
         'date_reviewed' => 'timestamp NULL DEFAULT NULL',
      ] as $field => $definition) {
         if (!$DB->fieldExists($table, $field)) {
            $migration->addField($table, $field, $definition);
         }
      }
      $migration->addKey($table, 'status', 'status_index');
      $migration->executeMigration();
   }

   private static function ensureTipConfigTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TipConfig::getTable();
      if ($DB->tableExists($table)) {
         return;
      }
      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `title` varchar(255) NOT NULL DEFAULT '',
            `introduction` text NULL,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entities_id` (`entities_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureTipFieldTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TipField::getTable();
      if ($DB->tableExists($table)) {
         return;
      }
      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `name` varchar(64) NOT NULL DEFAULT '',
            `label` varchar(255) NOT NULL DEFAULT '',
            `description` text NULL,
            `field_type` varchar(32) NOT NULL DEFAULT 'textarea',
            `values` text NULL,
            `is_required` tinyint NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `sort_order` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entity_order` (`entities_id`, `sort_order`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureTipAnswerTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketIntentAnswer::getTable();
      if ($DB->tableExists($table)) {
         return;
      }
      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `plugin_projectplus_ticketintents_id` int unsigned NOT NULL DEFAULT 0,
            `plugin_projectplus_tipfields_id` int unsigned NOT NULL DEFAULT 0,
            `label` varchar(255) NOT NULL DEFAULT '',
            `value` text NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `intent_field` (`plugin_projectplus_ticketintents_id`, `plugin_projectplus_tipfields_id`),
            KEY `fields_id` (`plugin_projectplus_tipfields_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureDocumentAcceptanceTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;
      $table = DocumentAcceptance::getTable();
      if ($DB->tableExists($table)) {
         return;
      }
      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `itemtype` varchar(64) NOT NULL DEFAULT '',
            `items_id` int unsigned NOT NULL DEFAULT 0,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `document_type` varchar(8) NOT NULL DEFAULT '',
            `documents_id` int unsigned NOT NULL DEFAULT 0,
            `status` varchar(16) NOT NULL DEFAULT 'published',
            `published_users_id` int unsigned NOT NULL DEFAULT 0,
            `accepted_users_id` int unsigned NOT NULL DEFAULT 0,
            `date_published` timestamp NULL DEFAULT NULL,
            `date_accepted` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `item_document_type` (`itemtype`, `items_id`, `document_type`),
            KEY `entity_status` (`entities_id`, `status`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
      );
   }

   private static function ensureDocumentTemplateTable(): void
   {
      /** @var DBmysql $DB */ global $DB;
      $table = DocumentTemplate::getTable();
      if ($DB->tableExists($table)) { return; }
      $DB->doQuery("CREATE TABLE `{$table}` (
         `id` int unsigned NOT NULL AUTO_INCREMENT, `entities_id` int unsigned NOT NULL DEFAULT 0,
         `document_type` varchar(8) NOT NULL DEFAULT '', `name` varchar(255) NOT NULL DEFAULT '',
         `version` varchar(64) NOT NULL DEFAULT '', `documents_id` int unsigned NOT NULL DEFAULT 0,
         `is_active` tinyint NOT NULL DEFAULT 1, `users_id` int unsigned NOT NULL DEFAULT 0,
         `date_creation` timestamp NULL DEFAULT NULL, `date_mod` timestamp NULL DEFAULT NULL,
         PRIMARY KEY (`id`), KEY `entity_type_active` (`entities_id`, `document_type`, `is_active`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
   }

   private static function ensureDocumentFieldTable(): void
   {
      global $DB; $t = DocumentField::getTable();
      if (!$DB->tableExists($t)) $DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT, `entities_id` int unsigned NOT NULL DEFAULT 0, `document_type` varchar(8) NOT NULL, `label` varchar(255) NOT NULL, `description` text NULL, `field_type` varchar(32) NOT NULL DEFAULT 'text', `values` text NULL, `is_required` tinyint NOT NULL DEFAULT 0, `is_active` tinyint NOT NULL DEFAULT 1, `sort_order` int unsigned NOT NULL DEFAULT 0, `date_creation` timestamp NULL DEFAULT NULL, `date_mod` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `entity_type_order` (`entities_id`,`document_type`,`sort_order`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
   }

   private static function ensureDocumentDraftTable(): void
   {
      global $DB; $t = 'glpi_plugin_projectplus_documentdrafts';
      if (!$DB->tableExists($t)) $DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT, `itemtype` varchar(64) NOT NULL, `items_id` int unsigned NOT NULL, `entities_id` int unsigned NOT NULL, `document_type` varchar(8) NOT NULL, `status` varchar(16) NOT NULL DEFAULT 'draft', `content` longtext NULL, `generated_content` longtext NULL, `version` int unsigned NOT NULL DEFAULT 0, `users_id` int unsigned NOT NULL DEFAULT 0, `date_creation` timestamp NULL DEFAULT NULL, `date_mod` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `item_type` (`itemtype`,`items_id`,`document_type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
   }

   public static function uninstall(): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      $DB->doQuery('DROP TABLE IF EXISTS `' . ProjectData::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . PortfolioBasket::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . PdticAction::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . FollowupTemplate::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . SolutionTemplate::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketPhaseHistory::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketPhase::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketIntent::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketIntentAnswer::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . ProjectPriority::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . DocumentAcceptance::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . DocumentTemplate::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TipField::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TipConfig::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketConfig::getTable() . '`');
      \Config::deleteConfigurationValues('plugin:projectplus', ['dbversion']);
      return true;
   }
}
