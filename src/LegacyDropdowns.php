<?php

use GlpiPlugin\Projectplus\PdticAction;
use GlpiPlugin\Projectplus\PortfolioBasket;
use GlpiPlugin\Projectplus\FollowupTemplate;
use GlpiPlugin\Projectplus\SolutionTemplate;
use GlpiPlugin\Projectplus\TicketPhase;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Legacy class names are required by the GLPI 10 dropdown menu renderer.
 *
 * The menu key is used in the current-page context. Keeping these small
 * adapters lets the plugin retain its namespaced implementation while using
 * the same menu convention as FormCreator.
 */
class PluginProjectplusPortfolioBasket extends PortfolioBasket
{
}

class PluginProjectplusPdticAction extends PdticAction
{
}

class PluginProjectplusFollowupTemplate extends FollowupTemplate
{
}

class PluginProjectplusSolutionTemplate extends SolutionTemplate
{
}

/**
 * GLPI 10 derives this legacy itemtype from the ticket phases table when it
 * asks the plugin to render a custom native-search value selector.
 */
class PluginProjectplusTicketPhase extends TicketPhase
{
}
