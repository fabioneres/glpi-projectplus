<?php
namespace GlpiPlugin\Projectplus;
use DBmysql;
use Session;
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }
class DocumentTemplate {
   public static function getTable($classname = null): string { return 'glpi_plugin_projectplus_documenttemplates'; }
   public static function types(): array { return ['tap'=>'TAP', 'tep'=>'TEP', 'ta'=>'TA']; }
   public static function save(int $entity, array $in): bool {
      global $DB; if (!Session::haveAccessToEntity($entity) || !isset(self::types()[(string)($in['document_type']??'')])) return false;
      $name=trim((string)($in['name']??'')); $doc=(int)($in['documents_id']??0); if ($name===''||$doc<=0) return false;
      $now=date('Y-m-d H:i:s'); $data=['document_type'=>(string)$in['document_type'],'name'=>substr($name,0,255),'version'=>substr(trim((string)($in['version']??'')),0,64),'documents_id'=>$doc,'is_active'=>(int)($in['is_active']??0)?1:0,'users_id'=>(int)Session::getLoginUserID(),'date_mod'=>$now];
      $id=(int)($in['id']??0); if($id>0) return (bool)$DB->update(self::getTable(),$data,['id'=>$id,'entities_id'=>$entity]);
      return (bool)$DB->insert(self::getTable(),$data+['entities_id'=>$entity,'date_creation'=>$now]);
   }
   public static function delete(int $id,int $entity): bool { global $DB; return Session::haveAccessToEntity($entity) && (bool)$DB->delete(self::getTable(),['id'=>$id,'entities_id'=>$entity]); }
   public static function get(int $entity,int $id=0): array { global $DB; $where=['entities_id'=>$entity]; if($id>0)$where['id']=$id; return iterator_to_array($DB->request(['FROM'=>self::getTable(),'WHERE'=>$where,'ORDER'=>['document_type ASC','name ASC']]),false); }
   /** @return array<int,string> */
   public static function getDocumentsForEntity(int $entity): array {
      global $DB; $result=[];
      foreach($DB->request(['FROM'=>'glpi_documents','WHERE'=>['is_deleted'=>0,'entities_id'=>[0,$entity]],'ORDER'=>['name ASC']]) as $document) { $result[(int)$document['id']]=(string)$document['name']; }
      return $result;
   }
}
