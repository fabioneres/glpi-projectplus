<?php

use GlpiPlugin\Projectplus\Installer;
use GlpiPlugin\Projectplus\ProjectProfile;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

require_once __DIR__ . '/bootstrap.php';

/**
 * Adds Project+ dropdowns to the native Configuration > Dropdowns page.
 *
 * CommonDropdown supplies the standard create, edit, delete and entity scope
 * interfaces for the classes returned here.
 */
function plugin_projectplus_getDropdown(): array
{
   return [
      'PluginProjectplusPortfolioBasket' => __('Cestas Portfólio', 'projectplus'),
      'PluginProjectplusPdticAction'      => __('Ações PDTIC', 'projectplus'),
      'PluginProjectplusFollowupTemplate' => __('Modelos de acompanhamento de Projetos', 'projectplus'),
      'PluginProjectplusSolutionTemplate' => __('Modelos de solução do projeto', 'projectplus'),
   ];
}

/** Adds the Project+ ranking to GLPI's native project search options. */
function plugin_projectplus_getAddSearchOptionsNew($itemtype): array
{
   if ($itemtype === \Project::class || $itemtype === 'Project') {
      return \GlpiPlugin\Projectplus\ProjectPriority::getSearchOptionsForProject();
   }
   if ($itemtype === \Ticket::class || $itemtype === 'Ticket') {
      return \GlpiPlugin\Projectplus\TicketPhase::getSearchOptionsForTicket();
   }
   return [];
}

/**
 * Applies numeric ranking comparisons requested from the native Project search.
 *
 * GLPI renders the generic morethan/lessthan operators as date-oriented labels
 * and otherwise treats numeric custom search options as exact values.  The
 * Project+ ranking is an integer score, so this hook provides its explicit
 * numeric comparison while keeping the native search form and SQL builder.
 */
function plugin_projectplus_addWhere($link, $nott, $itemtype, $id, $value, $searchtype): string
{
   $isProjectRanking = ($itemtype === \Project::class || $itemtype === 'Project') && (int) $id === 9520;
   $isTicketRanking = ($itemtype === \Ticket::class || $itemtype === 'Ticket') && (int) $id === 9524;
   $isTicketPhase = ($itemtype === \Ticket::class || $itemtype === 'Ticket') && (int) $id === 9523;

   if ($isTicketPhase) {
      $phases = \GlpiPlugin\Projectplus\TicketPhase::getPhases();
      if (!isset($phases[$value]) || !in_array($searchtype, ['equals', 'notequals'], true)) {
         return $link . ' (1 = 0) ';
      }

      $operator = $searchtype === 'notequals' ? '<>' : '=';
      return $link . ' (`' . \GlpiPlugin\Projectplus\TicketPhase::getTable() . '`.`phase` '
         . $operator . ' ' . \DBmysql::quoteValue($value) . ') ';
   }

   if (!$isProjectRanking && !$isTicketRanking) {
      return '';
   }

   $value = trim((string) $value);
   if (!preg_match('/^\d{1,2}$/', $value) || (int) $value > 35) {
      return $link . ' (1 = 0) ';
   }

   $operator = [
      'equals'    => '=',
      'notequals' => '<>',
      'morethan'  => '>',
      'lessthan'  => '<',
   ][$searchtype] ?? null;
   if ($operator === null) {
      return '';
   }

   $rankingTable = $isTicketRanking
      ? \GlpiPlugin\Projectplus\TicketPriority::getTable()
      : \GlpiPlugin\Projectplus\ProjectPriority::getTable();

   return $link . ' (`' . $rankingTable . '`.`ranking` '
      . $operator . ' ' . (int) $value . ') ';
}

/** Renders the controlled list of Project+ phases in Ticket native search. */
function plugin_projectplus_searchOptionsValues($options = []): bool
{
   $searchOption = $options['searchoption'] ?? [];
   if (($searchOption['table'] ?? '') !== \GlpiPlugin\Projectplus\TicketPhase::getTable()
      || ($searchOption['field'] ?? '') !== 'phase') {
      return false;
   }

   \Dropdown::showFromArray(
      (string) ($options['name'] ?? ''),
      \GlpiPlugin\Projectplus\TicketPhase::getSearchablePhases(),
      [
         'value' => (string) ($options['value'] ?? ''),
         'width' => '100%',
      ]
   );
   return true;
}

/** Displays the translated phase label when the search option is used as a column. */
function plugin_projectplus_giveItem($type, $id, $data, $num): string
{
   if (($type !== \Ticket::class && $type !== 'Ticket') || (int) $id !== 9523) {
      return '';
   }

   $value = (string) ($data[$num][0]['name'] ?? '');
   return \GlpiPlugin\Projectplus\TicketPhase::getPhaseLabel($value);
}

function plugin_projectplus_install(): bool
{
   return Installer::install();
}

function plugin_projectplus_upgrade($old_version): bool
{
   return Installer::install();
}

function plugin_projectplus_uninstall(): bool
{
   $result = Installer::uninstall();
   if ($result) {
      ProfileRight::deleteProfileRights(ProjectProfile::getRightNames());
      ProjectProfile::removeRightsFromSession();
   }
   return $result;
}
