<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Canonical identifiers for the two independent TIP documents. */
final class TipDocumentType
{
   public const TIP = 'tip';
   public const DETAIL = 'tip_dt';

   /**
    * Converts the historic pretip/tip pair without losing configurations,
    * answers, generated documents or configured field sources.
    */
   public static function migrateLegacyValues(): void
   {
      /** @var DBmysql $DB */
      global $DB;

      foreach ([TipConfig::getTable(), TipField::getTable(), TicketIntent::getTable()] as $table) {
         if (!$DB->tableExists($table) || !$DB->fieldExists($table, 'document_type')) {
            continue;
         }
         $legacy = $DB->request([
            'COUNT' => 'total',
            'FROM' => $table,
            'WHERE' => ['document_type' => 'pretip'],
         ])->current();
         // No legacy identifier means this table was already converted. Never
         // reinterpret the canonical "tip" rows as TIP-DT on later upgrades.
         if ((int) ($legacy['total'] ?? 0) === 0) {
            continue;
         }
         // A temporary value makes the swap safe where a unique key contains
         // entities_id/tickets_id and document_type.
         $DB->update($table, ['document_type' => '__legacy_tip__'], ['document_type' => 'pretip']);
         $DB->update($table, ['document_type' => self::DETAIL], ['document_type' => self::TIP]);
         $DB->update($table, ['document_type' => self::TIP], ['document_type' => '__legacy_tip__']);
      }

      foreach ([TipField::getTable(), DocumentField::getTable(), ProjectFieldConfig::getTable()] as $table) {
         if (!$DB->tableExists($table) || !$DB->fieldExists($table, 'source')) {
            continue;
         }
         $legacy = $DB->request("SELECT COUNT(*) AS `total` FROM `{$table}` WHERE `source` LIKE 'document:pretip:%'")->current();
         if ((int) ($legacy['total'] ?? 0) === 0) {
            continue;
         }
         $DB->doQuery("UPDATE `{$table}` SET `source` = REPLACE(`source`, 'document:tip:', 'document:__legacy_tip__:') WHERE `source` LIKE 'document:tip:%'");
         $DB->doQuery("UPDATE `{$table}` SET `source` = REPLACE(`source`, 'document:pretip:', 'document:tip:') WHERE `source` LIKE 'document:pretip:%'");
         $DB->doQuery("UPDATE `{$table}` SET `source` = REPLACE(`source`, 'document:__legacy_tip__:', 'document:tip_dt:') WHERE `source` LIKE 'document:__legacy_tip__:%'");
      }
   }
}
