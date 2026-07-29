<?php
if (!defined('GLPI_ROOT')) require_once dirname(__DIR__, 3) . '/inc/includes.php';
require_once dirname(__DIR__) . '/bootstrap.php';
use GlpiPlugin\Projectplus\DocumentDraft;
use GlpiPlugin\Projectplus\DocumentField;
Session::checkLoginUser();
$itemtype=(string)($_POST['itemtype']??''); $id=(int)($_POST['items_id']??0); $type=(string)($_POST['document_type']??'');
if(!in_array($itemtype,[Ticket::class,Project::class],true)||!in_array($type,['tap','tep','ta'],true))Html::displayErrorAndDie('Documento inválido.');
$item=new $itemtype(); if(!$item->getFromDB($id)||!Session::haveAccessToEntity((int)$item->getEntityID()))Html::displayErrorAndDie('Sem permissão.');
if(!Session::haveRight($item instanceof Ticket?'ticket':'project',UPDATE))Html::displayErrorAndDie('Sem permissão.');
$answers=is_array($_POST['document_fields']??null)?$_POST['document_fields']:[]; $saved=[];
foreach(DocumentField::get((int)$item->getEntityID(),$type,true) as $field){if($field['field_type']==='section')continue;$value=trim((string)($answers[(int)$field['id']]??''));if((int)$field['is_required']&&$value===''){Session::addMessageAfterRedirect('Preencha os campos obrigatórios.',false,ERROR);Html::redirect($itemtype::getFormURLWithID($id));}$saved[(int)$field['id']]=['label'=>$field['label'],'value'=>$value];}
if(isset($_POST['generate_document'])){ DocumentDraft::save($itemtype,$id,(int)$item->getEntityID(),$type,$saved); DocumentDraft::generate($itemtype,$id,$type); Session::addMessageAfterRedirect('Documento gerado.',true,INFO); } else { DocumentDraft::save($itemtype,$id,(int)$item->getEntityID(),$type,$saved); Session::addMessageAfterRedirect('Rascunho salvo.',true,INFO); } Html::redirect($itemtype::getFormURLWithID($id));
