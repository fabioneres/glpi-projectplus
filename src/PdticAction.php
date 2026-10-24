<?php

namespace GlpiPlugin\Projectplus;

use CommonDropdown;
use Session;
class PdticAction extends CommonDropdown
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_pdticactions';
   }

   /** Um item em uso por projetos nao pode ser excluido: o projeto ficaria apontando para um id inexistente. */
   public function pre_deleteItem()
   {
      if (!parent::pre_deleteItem()) {
         return false;
      }
      $used = ProjectData::countUsage('plugin_projectplus_pdticactions_id', (int) $this->getID());
      if ($used > 0) {
         Session::addMessageAfterRedirect(
            sprintf(__('Não é possível excluir "%1$s": o item está em uso por %2$d projeto(s).', 'projectplus'), (string) ($this->fields['name'] ?? ''), $used),
            false,
            ERROR
         );
         return false;
      }
      return true;
   }

   public static function getTypeName($nb = 0)
   {
      return _n('Ação PDTIC', 'Ações PDTIC', $nb, 'projectplus');
   }

   public static function getFormURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/pdticaction.form.php';
   }

   public static function getSearchURL($full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/pdticaction.php';
   }
}
