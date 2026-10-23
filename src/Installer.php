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

      $installedVersion = (string) \Config::getConfigurationValue('plugin:projectplus', 'dbversion');
      ProjectProfile::installRights();
      if ($installedVersion === '' || version_compare($installedVersion, '0.9.0-beta', '<')) {
         ProjectProfile::disableGranularRightsForAllProfiles();
      }
      ProjectProfile::initProfile();
      // Nao aborta a instalacao: sem os modelos o plugin continua funcionando,
      // so nao envia e-mail das interacoes.
      try {
         ProjectNotification::install();
      } catch (Throwable $exception) {
         Toolbox::logInFile('plugin_projectplus', 'Notification setup failed: ' . $exception->getMessage() . PHP_EOL);
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
      self::ensureDropdownTable(FollowupTemplate::getTable());
      self::ensureDropdownTable(SolutionTemplate::getTable());
      self::ensureITILTemplateFields(FollowupTemplate::getTable(), true);
      self::ensureITILTemplateFields(SolutionTemplate::getTable(), false);
      self::ensureTicketConfigTable();
      self::ensureTicketPhaseTable();
      TicketRouting::syncAllResponsibilities();
      self::ensureTicketPhaseHistoryTable();
      self::ensureTicketIntentTable();
      self::ensureTipConfigTable();
      self::ensureTipFieldTable();
      self::ensureTipAnswerTable();
      TipDocumentType::migrateLegacyValues();
      self::ensureDocumentAcceptanceTable();
      // Os modelos PDF foram removidos na 0.9.14-beta (REL-002). A tabela nao e
      // mais criada nem lida; a existente permanece no banco, porque apagar dado
      // de usuario durante um upgrade seria migracao destrutiva.
      self::ensureDocumentFieldTable();
      self::ensureDocumentDraftTable();
      self::ensureProjectFieldConfigTable();
      self::ensureTicketProjectTable();
      self::ensureProjectInteractionTable();
      self::ensureTransferRuleTable();
      self::ensureProjectPriorityTable();
      self::ensureTicketPriorityTable();
      self::ensureTechnicalPrioritySubmission();
      self::normalizePriorityRankings();
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

   private static function ensureProjectFieldConfigTable(): void
   {
      global $DB;
      $table = ProjectFieldConfig::getTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery("CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `project_field` varchar(100) NOT NULL DEFAULT '',
            `source` varchar(128) NOT NULL DEFAULT '',
            `display_rule` varchar(16) NOT NULL DEFAULT 'always',
            `condition_field_id` int unsigned NOT NULL DEFAULT 0,
            `condition_operator` varchar(16) NOT NULL DEFAULT 'equals',
            `condition_value` text NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entity_field` (`entities_id`, `project_field`),
            KEY `entities_id` (`entities_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
      }
   }

   private static function ensureTicketProjectTable(): void
   {
      global $DB; $table = TicketProject::getTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery("CREATE TABLE `{$table}` (`id` int unsigned NOT NULL AUTO_INCREMENT, `tickets_id` int unsigned NOT NULL DEFAULT 0, `projects_id` int unsigned NOT NULL DEFAULT 0, `entities_id` int unsigned NOT NULL DEFAULT 0, `users_id` int unsigned NOT NULL DEFAULT 0, `date_creation` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `tickets_id` (`tickets_id`), KEY `projects_id` (`projects_id`), KEY `entities_id` (`entities_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
      }
   }
   private static function ensureTransferRuleTable(): void { global $DB; $t=TransferRule::getTable(); if(!$DB->tableExists($t))$DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT,`entities_id` int unsigned NOT NULL DEFAULT 0,`target_entities_id` int unsigned NOT NULL DEFAULT 0,`date_creation` timestamp NULL DEFAULT NULL,PRIMARY KEY(`id`),UNIQUE KEY `par`(`entities_id`,`target_entities_id`),KEY `entities_id`(`entities_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }

   private static function ensureProjectInteractionTable(): void { global $DB; $t=ProjectInteraction::getTable(); if(!$DB->tableExists($t))$DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT,`projects_id` int unsigned NOT NULL DEFAULT 0,`entities_id` int unsigned NOT NULL DEFAULT 0,`users_id` int unsigned NOT NULL DEFAULT 0,`content` text NOT NULL,`is_internal` tinyint NOT NULL DEFAULT 0,`notify_participants` tinyint NOT NULL DEFAULT 0,`date_creation` timestamp NULL DEFAULT NULL,PRIMARY KEY(`id`),KEY `projects_id`(`projects_id`),KEY `entities_id`(`entities_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
   private static function ensureProjectPriorityTable(): void { global $DB; $t=ProjectPriority::getTable(); if(!$DB->tableExists($t))$DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT,`projects_id` int unsigned NOT NULL DEFAULT 0,`entities_id` int unsigned NOT NULL DEFAULT 0,`pdtic_risk` tinyint unsigned NOT NULL DEFAULT 0,`result_scope` tinyint unsigned NOT NULL DEFAULT 0,`project_urgency` tinyint unsigned NOT NULL DEFAULT 0,`estimated_time` tinyint unsigned NOT NULL DEFAULT 0,`team_familiarity` tinyint unsigned NOT NULL DEFAULT 0,`institutional_priority` tinyint unsigned NOT NULL DEFAULT 0,`waiting_time` tinyint unsigned NOT NULL DEFAULT 0,`ranking` tinyint unsigned NOT NULL DEFAULT 0,`date_creation` timestamp NULL DEFAULT NULL,`date_mod` timestamp NULL DEFAULT NULL,PRIMARY KEY(`id`),UNIQUE KEY `projects_id`(`projects_id`),KEY `entities_ranking`(`entities_id`,`ranking`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
   private static function ensureTicketPriorityTable(): void { global $DB; $t=TicketPriority::getTable(); if(!$DB->tableExists($t))$DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT,`tickets_id` int unsigned NOT NULL DEFAULT 0,`entities_id` int unsigned NOT NULL DEFAULT 0,`pdtic_risk` tinyint unsigned NOT NULL DEFAULT 0,`result_scope` tinyint unsigned NOT NULL DEFAULT 0,`project_urgency` tinyint unsigned NOT NULL DEFAULT 0,`estimated_time` tinyint unsigned NOT NULL DEFAULT 0,`team_familiarity` tinyint unsigned NOT NULL DEFAULT 0,`institutional_priority` tinyint unsigned NOT NULL DEFAULT 0,`waiting_time` tinyint unsigned NOT NULL DEFAULT 0,`ranking` tinyint unsigned NOT NULL DEFAULT 0,`date_creation` timestamp NULL DEFAULT NULL,`date_mod` timestamp NULL DEFAULT NULL,PRIMARY KEY(`id`),UNIQUE KEY `tickets_id`(`tickets_id`),KEY `entities_ranking`(`entities_id`,`ranking`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }

   /**
    * Records when the technical priority was submitted to the Portfolio Manager.
    *
    * Completing the technical criteria used to route the request by itself.  The
    * submission is now an explicit action, so it needs a state of its own.
    */
   private static function ensureTechnicalPrioritySubmission(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketPriority::getTable();
      if (!$DB->tableExists($table)) {
         return;
      }
      $fields = [
         'date_technical_sent' => 'TIMESTAMP NULL DEFAULT NULL',
         'technical_sent_users_id' => 'INT UNSIGNED NOT NULL DEFAULT 0',
      ];
      $created = false;
      foreach ($fields as $field => $definition) {
         if (!$DB->fieldExists($table, $field)) {
            $DB->doQuery("ALTER TABLE `{$table}` ADD `{$field}` {$definition}");
            $created = true;
         }
      }
      if (!$created) {
         return;
      }
      // Requests whose technical block was already complete had, by the previous
      // rule, been routed to the Portfolio Manager.  Backfilling keeps them out
      // of a state where the strategic block would look locked again.
      $DB->doQuery(
         "UPDATE `{$table}`
            SET `date_technical_sent` = COALESCE(`date_mod`, `date_creation`, NOW())
          WHERE `date_technical_sent` IS NULL
            AND `result_scope` > 0
            AND `project_urgency` > 0
            AND `estimated_time` > 0
            AND `team_familiarity` > 0"
      );
   }

   /** Converts legacy five-level values and recalculates rankings without risk. */
   private static function normalizePriorityRankings(): void
   {
      global $DB;
      $sets = [
         [TicketPriority::getTable(), 'tickets_id', '\\Ticket'],
         [ProjectPriority::getTable(), 'projects_id', '\\Project'],
      ];
      foreach ($sets as $set) {
         list($table, $itemField, $itemClass) = $set;
         if (!$DB->tableExists($table)) {
            continue;
         }
         foreach ($DB->request(['FROM' => $table]) as $row) {
            $values = [];
            $complete = true;
            foreach (ProjectPriority::getCriteria() as $field => $label) {
               $value = (int) ($row[$field] ?? 0);
               if ($value === 2) {
                  $value = 1;
               } elseif ($value === 4) {
                  $value = 5;
               }
               if (!in_array($value, [0, 1, 3, 5], true)) {
                  $value = 0;
               }
               $values[$field] = $value;
               $complete = $complete && $value > 0;
            }
            $ranking = $complete ? array_sum($values) : 0;
            $DB->update($table, $values + ['ranking' => $ranking, 'date_mod' => date('Y-m-d H:i:s')], ['id' => (int) $row['id']]);
            $DB->update($itemClass::getTable(), ['priority' => ProjectPriority::getNativePriorityForRanking($ranking)], ['id' => (int) $row[$itemField]]);
         }
      }
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

   /** Adds the native ITIL-template fields while preserving existing models. */
   private static function ensureITILTemplateFields(string $table, bool $isFollowup): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $fields = [
         'content' => 'MEDIUMTEXT NULL',
      ];
      if ($isFollowup) {
         $fields['requesttypes_id'] = 'INT UNSIGNED NOT NULL DEFAULT 0';
         $fields['is_private'] = 'TINYINT NOT NULL DEFAULT 0';
      } else {
         $fields['solutiontypes_id'] = 'INT UNSIGNED NOT NULL DEFAULT 0';
      }
      foreach ($fields as $field => $definition) {
         if (!$DB->fieldExists($table, $field)) {
            $DB->doQuery("ALTER TABLE `{$table}` ADD `{$field}` {$definition}");
         }
      }

      // Legacy Project+ templates stored their rich content in comment.
      $DB->doQuery("UPDATE `{$table}` SET `content` = `comment` WHERE (`content` IS NULL OR `content` = '') AND `comment` IS NOT NULL AND `comment` <> ''");
   }

   private static function ensureTicketConfigTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TicketConfig::getTable();
      if ($DB->tableExists($table)) {
         // These fields were introduced after the first Project+ releases.
         // Apply their migration to the ticket-category configuration table
         // itself, so upgrades preserve the existing rows and immediately
         // expose the responsible users and recursive scope in native Search.
         $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
         $participantFieldAdded = false;
         foreach ([
            'project_manager_users_id' => 'int unsigned NOT NULL DEFAULT 0',
            'portfolio_manager_users_id' => 'int unsigned NOT NULL DEFAULT 0',
            'is_recursive' => 'tinyint NOT NULL DEFAULT 0',
            'is_participant' => 'tinyint NOT NULL DEFAULT 0',
         ] as $field => $definition) {
            if (!$DB->fieldExists($table, $field)) {
               $migration->addField($table, $field, $definition, ['after' => 'is_active']);
               $participantFieldAdded = $participantFieldAdded || $field === 'is_participant';
            }
         }
         $migration->executeMigration();
         if ($participantFieldAdded) {
            // Preserve current production behavior: every category that was
            // already active represented an entity participating in the flow.
            $DB->doQuery("UPDATE `{$table}` SET `is_participant` = 1 WHERE `is_active` = 1");
         }

         $legacyKeyExists = false;
         $entityCategoryKeyExists = false;
         $entityActiveKeyExists = false;
         $entityParticipantKeyExists = false;
         foreach ($DB->doQuery("SHOW INDEX FROM `{$table}`") as $index) {
            $key = (string) ($index['Key_name'] ?? '');
            $legacyKeyExists = $legacyKeyExists || $key === 'entities_id';
            $entityCategoryKeyExists = $entityCategoryKeyExists || $key === 'entity_category';
            $entityActiveKeyExists = $entityActiveKeyExists || $key === 'entity_active';
            $entityParticipantKeyExists = $entityParticipantKeyExists || $key === 'entity_participant';
         }
         if ($legacyKeyExists) {
            $DB->doQuery("ALTER TABLE `{$table}` DROP INDEX `entities_id`");
         }
         if (!$entityCategoryKeyExists) {
            $DB->doQuery("ALTER TABLE `{$table}` ADD UNIQUE KEY `entity_category` (`entities_id`, `itilcategories_id`)");
         }
         if (!$entityActiveKeyExists) {
            $DB->doQuery("ALTER TABLE `{$table}` ADD KEY `entity_active` (`entities_id`, `is_active`)");
         }
         if (!$entityParticipantKeyExists) {
            $DB->doQuery("ALTER TABLE `{$table}` ADD KEY `entity_participant` (`entities_id`, `is_participant`)");
         }
         return;
      }

      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `itilcategories_id` int unsigned NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `is_participant` tinyint NOT NULL DEFAULT 0,
            `project_manager_users_id` int unsigned NOT NULL DEFAULT 0,
            `portfolio_manager_users_id` int unsigned NOT NULL DEFAULT 0,
            `is_recursive` tinyint NOT NULL DEFAULT 0,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entity_category` (`entities_id`, `itilcategories_id`),
            KEY `entity_active` (`entities_id`, `is_active`),
            KEY `entity_participant` (`entities_id`, `is_participant`),
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
         if (!$DB->fieldExists($table, 'project_manager_entities_id')) {
            $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
            $migration->addField($table, 'project_manager_entities_id', 'int unsigned NOT NULL DEFAULT 0', ['after' => 'entities_id']);
            $migration->executeMigration();
         }
         $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
         foreach (['project_manager_users_id', 'portfolio_manager_users_id', 'portfolio_observer_ticketusers_id'] as $field) {
            if (!$DB->fieldExists($table, $field)) {
               $migration->addField($table, $field, 'int unsigned NOT NULL DEFAULT 0', ['after' => 'project_manager_entities_id']);
               $migration->addKey($table, $field, $field);
            }
         }
         $migration->executeMigration();
         return;
      }

      $DB->doQuery(
         "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL DEFAULT 0,
            `document_type` varchar(16) NOT NULL DEFAULT 'tip',
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `project_manager_entities_id` int unsigned NOT NULL DEFAULT 0,
            `project_manager_users_id` int unsigned NOT NULL DEFAULT 0,
            `portfolio_manager_users_id` int unsigned NOT NULL DEFAULT 0,
            `portfolio_observer_ticketusers_id` int unsigned NOT NULL DEFAULT 0,
            `phase` varchar(64) NOT NULL DEFAULT 'classification',
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `justification` text NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_document` (`tickets_id`, `document_type`),
            KEY `entities_phase` (`entities_id`, `phase`),
            KEY `project_manager_users_id` (`project_manager_users_id`),
            KEY `portfolio_manager_users_id` (`portfolio_manager_users_id`),
            KEY `portfolio_observer_ticketusers_id` (`portfolio_observer_ticketusers_id`)
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
         'document_type' => "varchar(16) NOT NULL DEFAULT 'tip'",
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
      // Apply column additions before inspecting indexes. On an upgrade from
      // an older release, document_type only exists after this migration.
      $migration->executeMigration();
      // A single Ticket can now contain both Pre-TIP and TIP. Existing rows
      // keep the default "tip" type so the upgrade is non-destructive.
      if ($DB->fieldExists($table, 'document_type')) {
         $legacyKeyExists = false;
         $newKeyExists = false;
         foreach ($DB->doQuery("SHOW INDEX FROM `{$table}`") as $index) {
            $legacyKeyExists = $legacyKeyExists || (string) ($index['Key_name'] ?? '') === 'tickets_id';
            $newKeyExists = $newKeyExists || (string) ($index['Key_name'] ?? '') === 'ticket_document';
         }
         if ($legacyKeyExists) {
            $DB->doQuery("ALTER TABLE `{$table}` DROP INDEX `tickets_id`");
         }
         if (!$newKeyExists) {
            $DB->doQuery("ALTER TABLE `{$table}` ADD UNIQUE KEY `ticket_document` (`tickets_id`, `document_type`)");
         }
      }
      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      $migration->addKey($table, 'status', 'status_index');
      $migration->executeMigration();
   }

   private static function ensureTipConfigTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TipConfig::getTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery(
            "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `document_type` varchar(16) NOT NULL DEFAULT 'tip',
            `is_active` tinyint NOT NULL DEFAULT 1,
            `title` varchar(255) NOT NULL DEFAULT '',
            `introduction` text NULL,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entity_document_type` (`entities_id`, `document_type`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
         );
      }
      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      if (!$DB->fieldExists($table, 'document_type')) {
         $migration->addField($table, 'document_type', "varchar(16) NOT NULL DEFAULT 'tip'", ['after' => 'entities_id']);
      }
      $migration->executeMigration();
      self::ensureTipDocumentTypeKey($table, 'entity_document_type', ['entities_id', 'document_type'], 'entities_id');
   }

   private static function ensureTipFieldTable(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $table = TipField::getTable();
      if (!$DB->tableExists($table)) {
         $DB->doQuery(
            "CREATE TABLE `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `document_type` varchar(16) NOT NULL DEFAULT 'tip',
            `name` varchar(64) NOT NULL DEFAULT '',
            `label` varchar(255) NOT NULL DEFAULT '',
            `description` text NULL,
            `field_type` varchar(32) NOT NULL DEFAULT 'textarea',
            `values` text NULL,
            `source` varchar(128) NOT NULL DEFAULT '',
            `is_required` tinyint NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `is_recursive` tinyint NOT NULL DEFAULT 0,
            `is_deleted` tinyint NOT NULL DEFAULT 0,
            `sort_order` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entity_type_order` (`entities_id`, `document_type`, `sort_order`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
         );
      }
      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      if (!$DB->fieldExists($table, 'document_type')) {
         // Existing shared fields remain assigned to the original TIP. The
         // technical TIP starts with its own independent defaults on demand.
         $migration->addField($table, 'document_type', "varchar(16) NOT NULL DEFAULT 'tip'", ['after' => 'entities_id']);
      }
      if (!$DB->fieldExists($table, 'source')) {
         $migration->addField($table, 'source', "varchar(128) NOT NULL DEFAULT ''", ['after' => 'values']);
      }
      if (!$DB->fieldExists($table, 'is_deleted')) {
         $migration->addField($table, 'is_deleted', 'tinyint NOT NULL DEFAULT 0', ['after' => 'is_active']);
      }
      if (!$DB->fieldExists($table, 'is_recursive')) {
         $migration->addField($table, 'is_recursive', 'tinyint NOT NULL DEFAULT 0', ['after' => 'is_active']);
      }
      if (!$DB->fieldExists($table, 'display_rule')) $migration->addField($table, 'display_rule', "varchar(16) NOT NULL DEFAULT 'always'", ['after' => 'source']);
      if (!$DB->fieldExists($table, 'condition_field_id')) $migration->addField($table, 'condition_field_id', 'int unsigned NOT NULL DEFAULT 0', ['after' => 'display_rule']);
      if (!$DB->fieldExists($table, 'condition_operator')) $migration->addField($table, 'condition_operator', "varchar(16) NOT NULL DEFAULT 'equals'", ['after' => 'condition_field_id']);
      if (!$DB->fieldExists($table, 'condition_value')) $migration->addField($table, 'condition_value', 'text NULL', ['after' => 'condition_operator']);
      $migration->executeMigration();
      self::ensureTipDocumentTypeKey($table, 'entity_type_order', ['entities_id', 'document_type', 'sort_order']);
   }

   /** @param array<int,string> $fields */
   private static function ensureTipDocumentTypeKey(string $table, string $name, array $fields, string $legacyUnique = ''): void
   {
      /** @var DBmysql $DB */
      global $DB;

      $keys = [];
      foreach ($DB->doQuery("SHOW INDEX FROM `{$table}`") as $index) {
         $keys[(string) ($index['Key_name'] ?? '')] = true;
      }
      if ($legacyUnique !== '' && isset($keys[$legacyUnique])) {
         $DB->doQuery("ALTER TABLE `{$table}` DROP INDEX `{$legacyUnique}`");
      }
      if (!isset($keys[$name])) {
         $columns = implode('`, `', $fields);
         $unique = $legacyUnique !== '' ? 'UNIQUE ' : '';
         $DB->doQuery("ALTER TABLE `{$table}` ADD {$unique}KEY `{$name}` (`{$columns}`)");
      }
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
      if (!$DB->tableExists($table)) {
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
      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      foreach ([
         'rejected_users_id' => 'int unsigned NOT NULL DEFAULT 0',
         'rejection_justification' => 'text NULL',
         'date_rejected' => 'timestamp NULL DEFAULT NULL',
      ] as $field => $definition) {
         if (!$DB->fieldExists($table, $field)) {
            $migration->addField($table, $field, $definition);
         }
      }
      $migration->addKey($table, 'rejected_users_id', 'rejected_users_id_index');
      $migration->executeMigration();
   }

   private static function ensureDocumentFieldTable(): void
   {
      global $DB; $t = DocumentField::getTable();
      if (!$DB->tableExists($t)) $DB->doQuery("CREATE TABLE `{$t}` (`id` int unsigned NOT NULL AUTO_INCREMENT, `entities_id` int unsigned NOT NULL DEFAULT 0, `document_type` varchar(8) NOT NULL, `label` varchar(255) NOT NULL, `description` text NULL, `field_type` varchar(32) NOT NULL DEFAULT 'text', `values` text NULL, `source` varchar(128) NOT NULL DEFAULT '', `display_rule` varchar(16) NOT NULL DEFAULT 'always', `condition_field_id` int unsigned NOT NULL DEFAULT 0, `condition_operator` varchar(16) NOT NULL DEFAULT 'equals', `condition_value` text NULL, `is_required` tinyint NOT NULL DEFAULT 0, `is_active` tinyint NOT NULL DEFAULT 1, `is_recursive` tinyint NOT NULL DEFAULT 0, `is_deleted` tinyint NOT NULL DEFAULT 0, `sort_order` int unsigned NOT NULL DEFAULT 0, `date_creation` timestamp NULL DEFAULT NULL, `date_mod` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `entity_type_order` (`entities_id`,`document_type`,`sort_order`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $migration = new Migration(PLUGIN_PROJECTPLUS_VERSION);
      if (!$DB->fieldExists($t, 'source')) $migration->addField($t, 'source', "varchar(128) NOT NULL DEFAULT ''", ['after' => 'values']);
      if (!$DB->fieldExists($t, 'is_deleted')) $migration->addField($t, 'is_deleted', 'tinyint NOT NULL DEFAULT 0', ['after' => 'is_active']);
      if (!$DB->fieldExists($t, 'is_recursive')) $migration->addField($t, 'is_recursive', 'tinyint NOT NULL DEFAULT 0', ['after' => 'is_active']);
      if (!$DB->fieldExists($t, 'display_rule')) $migration->addField($t, 'display_rule', "varchar(16) NOT NULL DEFAULT 'always'", ['after' => 'source']);
      if (!$DB->fieldExists($t, 'condition_field_id')) $migration->addField($t, 'condition_field_id', 'int unsigned NOT NULL DEFAULT 0', ['after' => 'display_rule']);
      if (!$DB->fieldExists($t, 'condition_operator')) $migration->addField($t, 'condition_operator', "varchar(16) NOT NULL DEFAULT 'equals'", ['after' => 'condition_field_id']);
      if (!$DB->fieldExists($t, 'condition_value')) $migration->addField($t, 'condition_value', 'text NULL', ['after' => 'condition_operator']);
      $migration->executeMigration();
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
      // Nome literal: a classe saiu na 0.9.14-beta, mas a desinstalacao ainda
      // precisa limpar a tabela de quem instalou uma versao anterior.
      $DB->doQuery('DROP TABLE IF EXISTS `glpi_plugin_projectplus_documenttemplates`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TipField::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TipConfig::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TicketConfig::getTable() . '`');
      $DB->doQuery('DROP TABLE IF EXISTS `' . TransferRule::getTable() . '`');
      ProjectNotification::uninstall();
      \Config::deleteConfigurationValues('plugin:projectplus', ['dbversion']);
      return true;
   }
}
