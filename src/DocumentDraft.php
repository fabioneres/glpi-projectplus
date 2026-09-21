<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Session;

if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }

/** Persists configurable TAP, TEP and TA drafts and their generated snapshots. */
class DocumentDraft extends CommonDBTM
{
   public static function getTable($classname = null): string { return 'glpi_plugin_projectplus_documentdrafts'; }

   public static function get(string $itemtype, int $itemsId, string $type): ?array
   {
      global $DB;
      $row = $DB->request(['FROM'=>self::getTable(),'WHERE'=>['itemtype'=>$itemtype,'items_id'=>$itemsId,'document_type'=>$type],'LIMIT'=>1])->current();
      return $row ?: null;
   }

   public static function save(string $itemtype, int $itemsId, int $entityId, string $type, array $answers): void
   {
      global $DB; $now = date('Y-m-d H:i:s'); $current = self::get($itemtype, $itemsId, $type);
      $data = ['content'=>json_encode($answers, JSON_UNESCAPED_UNICODE),'status'=>'draft','users_id'=>(int)Session::getLoginUserID(),'date_mod'=>$now];
      if ($current) $DB->update(self::getTable(), $data, ['id'=>(int)$current['id']]);
      else $DB->insert(self::getTable(), $data+['itemtype'=>$itemtype,'items_id'=>$itemsId,'entities_id'=>$entityId,'document_type'=>$type,'version'=>0,'date_creation'=>$now]);
   }

   /**
    * Devolve o documento ao estado de rascunho, preservando o conteudo.
    *
    * Usado quando o cliente recusa: o texto continua la para ser corrigido, mas
    * a versao gerada deixa de valer. Sem isso o responsavel reenviaria ao
    * cliente exatamente o documento que acabou de ser recusado, porque o botao
    * de envio depende apenas de existir uma versao gerada.
    */
   public static function reopen(string $itemtype, int $itemsId, string $type): void
   {
      global $DB;
      $draft = self::get($itemtype, $itemsId, $type);
      if (!$draft || (string) $draft['status'] !== 'generated') {
         return;
      }
      $DB->update(
         self::getTable(),
         ['status' => 'draft', 'date_mod' => date('Y-m-d H:i:s')],
         ['id' => (int) $draft['id']]
      );
   }

   public static function generate(string $itemtype, int $itemsId, string $type): void
   {
      global $DB; $draft = self::get($itemtype, $itemsId, $type);
      if (!$draft || (string)$draft['content'] === '') throw new \RuntimeException(__('Salve o rascunho antes de gerar o documento.', 'projectplus'));
      $DB->update(self::getTable(), ['status'=>'generated','generated_content'=>(string)$draft['content'],'version'=>(int)$draft['version']+1,'users_id'=>(int)Session::getLoginUserID(),'date_mod'=>date('Y-m-d H:i:s')], ['id'=>(int)$draft['id']]);
   }

}
