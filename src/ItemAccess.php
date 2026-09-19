<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use Project;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Single place where Project+ decides whether the current user may reach one
 * specific Ticket or Project.
 *
 * A Project+ right authorizes a feature; it never authorizes a record.  Entity
 * access alone is not enough either: inside one entity GLPI still decides who
 * sees which ticket or project.  Every plugin screen, endpoint and PDF that
 * receives an identifier from the request must pass through here before
 * reading or writing anything about that item.
 */
class ItemAccess
{
   /**
    * Native visibility of the item, plus the Project+ portal relationship.
    *
    * The portal intentionally serves requesters and focal points who have no
    * native right over the Project: for them the plugin fields are the link,
    * and `ProjectPortal` is the authority.
    */
   public static function canView(CommonDBTM $item): bool
   {
      if ((int) $item->getID() <= 0) {
         return false;
      }
      if ($item->canViewItem()) {
         return true;
      }
      return $item instanceof Project
         && ProjectPortal::getProjectForCurrentUser((int) $item->getID()) !== null;
   }

   /**
    * Same native rule, without the portal fallback.
    *
    * Plugin actions are additionally gated by their own Project+ right; what
    * this answers is only whether the user may act on this specific item from
    * the internal interface.
    */
   public static function canAct(CommonDBTM $item): bool
   {
      return (int) $item->getID() > 0 && $item->canViewItem();
   }
}
