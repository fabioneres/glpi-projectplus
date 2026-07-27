<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use DBmysql;
use Document;
use Dropdown;
use Html;
use Project;
use Session;
use Ticket;
use Ticket_User;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Publishes TAP, TEP and TA PDFs and records an auditable customer acceptance. */
class DocumentAcceptance extends CommonDBTM
{
   protected static $notable = true;

   public static function getTable($classname = null): string { return 'glpi_plugin_projectplus_documentacceptances'; }

   /** @return array<string,string> */
   private static function typesForItem(CommonDBTM $item): array
   {
      if ($item instanceof Ticket) { return ['tap' => __('TAP - Termo de Abertura do Projeto', 'projectplus')]; }
      if ($item instanceof Project) { return ['tep' => __('TEP - Relatório de Entrega do Projeto', 'projectplus'), 'ta' => __('TA - Termo de Aceite', 'projectplus')]; }
      return [];
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if ($withtemplate || !($item instanceof Ticket || $item instanceof Project)) { return ''; }
      if ($item instanceof Ticket && !TicketConfig::isEnabledForTicket($item)) { return ''; }
      return self::createTabEntry(__('Documentos e aceites', 'projectplus'), 0, $item::getType(), 'ti ti-file-check');
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item instanceof Ticket || $item instanceof Project) { self::showForItem($item); }
      return true;
   }

   /** @return array<string,mixed>|null */
   public static function getForItem(CommonDBTM $item, string $type): ?array
   {
      /** @var DBmysql $DB */ global $DB;
      $row = $DB->request(['FROM'=>self::getTable(),'WHERE'=>['itemtype'=>$item::getType(),'items_id'=>(int)$item->getID(),'document_type'=>$type],'LIMIT'=>1])->current();
      return $row ?: null;
   }

   private static function canManage(CommonDBTM $item): bool
   {
      return ($item instanceof Ticket && Session::haveRight('ticket', UPDATE)) || ($item instanceof Project && Session::haveRight('project', UPDATE));
   }

   private static function canAccept(CommonDBTM $item): bool
   {
      $userId = (int) Session::getLoginUserID();
      if ($userId <= 0) { return false; }
      /** @var DBmysql $DB */ global $DB;
      if ($item instanceof Ticket) {
         return (int)($DB->request(['COUNT'=>'count','FROM'=>Ticket_User::getTable(),'WHERE'=>['tickets_id'=>(int)$item->getID(),'users_id'=>$userId,'type'=>CommonITILActor::REQUESTER]])->current()['count'] ?? 0) > 0;
      }
      if ($item instanceof Project) {
         $data = ProjectData::getForProject((int)$item->getID());
         return in_array($userId, [(int)$data['requester_users_id'], (int)$data['focal_point_users_id']], true);
      }
      return false;
   }

   public static function publish(CommonDBTM $item, string $type, int $templateId): void
   {
      if (!self::canManage($item) || !array_key_exists($type, self::typesForItem($item))) { throw new \RuntimeException(__('Sem permissão para publicar este documento.', 'projectplus')); }
      $templates = DocumentTemplate::get((int)$item->getEntityID(), $templateId);
      $template = $templates[0] ?? null;
      if (!$template || (string)$template['document_type'] !== $type || !(int)$template['is_active']) { throw new \RuntimeException(__('Selecione um modelo ativo compatível.', 'projectplus')); }
      $documentId = (int)$template['documents_id'];
      $document = new Document();
      if ($documentId <= 0 || !$document->getFromDB($documentId)) { throw new \RuntimeException(__('Selecione um documento válido.', 'projectplus')); }
      /** @var DBmysql $DB */ global $DB;
      $now = date('Y-m-d H:i:s'); $current = self::getForItem($item, $type);
      $data = ['documents_id'=>$documentId,'status'=>'published','published_users_id'=>(int)Session::getLoginUserID(),'accepted_users_id'=>0,'date_published'=>$now,'date_accepted'=>null,'date_mod'=>$now];
      if ($current) { $DB->update(self::getTable(),$data,['id'=>(int)$current['id']]); }
      else { $DB->insert(self::getTable(),$data+['itemtype'=>$item::getType(),'items_id'=>(int)$item->getID(),'entities_id'=>(int)$item->getEntityID(),'document_type'=>$type]); }
      if ($item instanceof Ticket && $type === 'tap') { TicketPhase::setPhase($item, 'awaiting_tap_acceptance', __('TAP publicado para aceite.', 'projectplus')); }
   }

   public static function accept(CommonDBTM $item, int $acceptanceId): void
   {
      /** @var DBmysql $DB */ global $DB;
      $row = $DB->request(['FROM'=>self::getTable(),'WHERE'=>['id'=>$acceptanceId,'itemtype'=>$item::getType(),'items_id'=>(int)$item->getID(),'status'=>'published'],'LIMIT'=>1])->current();
      if (!$row || !self::canAccept($item)) { throw new \RuntimeException(__('Você não possui permissão para aceitar este documento.', 'projectplus')); }
      $now = date('Y-m-d H:i:s');
      $DB->update(self::getTable(),['status'=>'accepted','accepted_users_id'=>(int)Session::getLoginUserID(),'date_accepted'=>$now,'date_mod'=>$now],['id'=>(int)$row['id']]);
      if ($item instanceof Ticket && (string)$row['document_type'] === 'tap') { TicketPhase::setPhase($item, 'authorized_opening', __('TAP aceito pelo cliente.', 'projectplus')); }
   }

   public static function showForItem(CommonDBTM $item): void
   {
      $types = self::typesForItem($item);
      echo "<div class='projectplus-tip projectplus-acceptance'><header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-check'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::esc(__('Validação documental', 'projectplus')) . '</span><h2>' . self::esc(__('Documentos e aceites', 'projectplus')) . '</h2><p>' . self::esc(__('Leia o documento publicado e registre seu aceite de forma auditável.', 'projectplus')) . '</p></div></header>';
      foreach ($types as $type => $label) { self::showDocument($item, $type, $label); }
      echo '</div>';
   }

   private static function showDocument(CommonDBTM $item, string $type, string $label): void
   {
      $row = self::getForItem($item, $type);
      echo "<section class='projectplus-tip__card projectplus-acceptance__card'><div class='projectplus-tip__section-title'><i class='ti ti-file-description'></i><div><h3>" . self::esc($label) . '</h3><p>' . self::esc($row ? ((string)$row['status']==='accepted' ? __('Aceite registrado.', 'projectplus') : __('Aguardando aceite do cliente.', 'projectplus')) : __('Documento ainda não publicado.', 'projectplus')) . '</p></div></div>';
      if (!$row && self::canManage($item)) { self::showPublishForm($item, $type); }
      elseif ($row) { self::showAcceptance($item, $row); }
      echo '</section>';
   }

   private static function showPublishForm(CommonDBTM $item, string $type): void
   {
      echo "<form method='post' action='" . self::esc(self::url()) . "'>" . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('document_type',['value'=>$type]);
      $options = [];
      foreach (DocumentTemplate::get((int)$item->getEntityID()) as $model) { if ((string)$model['document_type'] === $type && (int)$model['is_active']) { $options[(int)$model['id']] = (string)$model['name'] . ((string)$model['version'] !== '' ? ' — v' . (string)$model['version'] : ''); } }
      echo "<div class='projectplus-acceptance__publish'><div><label>" . self::esc(__('Modelo PDF', 'projectplus')) . '</label>';
      Dropdown::showFromArray('documents_id',$options);
      echo '</div><button class="btn btn-primary" type="submit" name="publish_document" value="1"><i class="ti ti-send"></i> ' . self::esc(__('Publicar para aceite', 'projectplus')) . '</button></div></form>';
   }

   /** @param array<string,mixed> $row */
   private static function showAcceptance(CommonDBTM $item, array $row): void
   {
      global $CFG_GLPI;
      $document = new Document(); $document->getFromDB((int)$row['documents_id']);
      $src = rtrim((string)$CFG_GLPI['root_doc'],'/') . '/front/document.send.php?docid=' . (int)$row['documents_id'];
      echo "<div class='projectplus-acceptance__document'><div><strong>" . self::esc((string)$document->getName()) . '</strong><a target="_blank" rel="noopener" href="' . self::esc($src) . '">' . self::esc(__('Abrir documento', 'projectplus')) . '</a></div><iframe title="' . self::esc((string)$document->getName()) . '" src="' . self::esc($src) . '"></iframe></div>';
      if ((string)$row['status'] === 'accepted') { echo "<div class='projectplus-acceptance__accepted'><i class='ti ti-circle-check'></i> " . self::esc(__('Aceito por', 'projectplus')) . ' ' . self::esc(User::getFriendlyNameById((int)$row['accepted_users_id'])) . ' — ' . self::esc((string)$row['date_accepted']) . '</div>'; return; }
      if (self::canAccept($item)) {
         echo "<form method='post' action='" . self::esc(self::url()) . "' class='projectplus-acceptance__form'>" . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('acceptance_id',['value'=>(int)$row['id']]) . "<label><input required type='checkbox' name='accepted' value='1'> " . self::esc(__('Li e aceito os termos deste documento.', 'projectplus')) . "</label><button class='btn btn-primary' type='submit' name='accept_document' value='1'><i class='ti ti-check'></i> " . self::esc(__('Aceitar e continuar', 'projectplus')) . '</button></form>';
      }
   }

   private static function url(): string { global $CFG_GLPI; return rtrim((string)$CFG_GLPI['root_doc'],'/') . '/plugins/projectplus/front/documentacceptance.form.php'; }
   private static function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
