<?php
namespace GlpiPlugin\Projectplus;
use DBmysql; use Session;
if (!defined('GLPI_ROOT')) die("Sorry. You can't access directly to this file");
class DocumentField {
 public static function getTable($c=null):string{return 'glpi_plugin_projectplus_documentfields';}
 public static function types():array{return ['text'=>__('Texto curto','projectplus'),'textarea'=>__('Texto longo','projectplus'),'number'=>__('Número','projectplus'),'date'=>__('Data','projectplus'),'select'=>__('Lista suspensa','projectplus'),'section'=>__('Título de seção','projectplus')];}
 public static function get(int $entity,string $type,bool $active=false):array{global $DB;$where=['entities_id'=>$entity,'document_type'=>$type];if($active)$where['is_active']=1;return iterator_to_array($DB->request(['FROM'=>self::getTable(),'WHERE'=>$where,'ORDER'=>['sort_order ASC','id ASC']]),false);}
 public static function one(int $id,int $entity,string $type):?array{global $DB;$r=$DB->request(['FROM'=>self::getTable(),'WHERE'=>['id'=>$id,'entities_id'=>$entity,'document_type'=>$type],'LIMIT'=>1])->current();return $r?:null;}
 public static function save(int $entity,string $type,array $in):bool{global $DB;if(!in_array($type,['tap','tep','ta'],true)||!Session::haveAccessToEntity($entity,true))return false;$label=trim((string)($in['label']??''));$ft=(string)($in['field_type']??'text');if($label===''||!isset(self::types()[$ft]))return false;$data=['label'=>substr($label,0,255),'description'=>trim((string)($in['description']??'')),'field_type'=>$ft,'values'=>trim((string)($in['values']??'')),'source'=>$ft==='section'?'':substr(trim((string)($in['source']??'')),0,128),'is_required'=>$ft==='section'?0:(int)!empty($in['is_required']),'is_active'=>(int)!empty($in['is_active']),'sort_order'=>max(0,(int)($in['sort_order']??0)),'date_mod'=>date('Y-m-d H:i:s')];$id=(int)($in['id']??0);if($id&&self::one($id,$entity,$type))return(bool)$DB->update(self::getTable(),$data,['id'=>$id]);return(bool)$DB->insert(self::getTable(),$data+['entities_id'=>$entity,'document_type'=>$type,'date_creation'=>date('Y-m-d H:i:s')]);}
 public static function delete(int $id,int $entity,string $type):bool{global $DB;return Session::haveAccessToEntity($entity,true)&&self::one($id,$entity,$type)!==null&&(bool)$DB->delete(self::getTable(),['id'=>$id]);}
}
