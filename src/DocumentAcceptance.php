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
use ProjectState;
use Session;
use Ticket;
use Ticket_User;
use User;
use ITILFollowup;
use GlpiPlugin\Projectplus\DocumentDraft;
use GlpiPlugin\Projectplus\DocumentField;

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
      if ($item instanceof Project && $type === 'ta') {
         $tep = self::getForItem($item, 'tep');
         if (!$tep || (string) $tep['status'] !== 'accepted') {
            throw new \RuntimeException(__('O TA só pode ser publicado após o aceite do TEP.', 'projectplus'));
         }
      }
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

   public static function publishGenerated(CommonDBTM $item, string $type): void
   {
      if (!self::canManage($item)) throw new \RuntimeException(__('Sem permissão para enviar este documento.', 'projectplus'));
      $draft = DocumentDraft::get($item::getType(), (int)$item->getID(), $type);
      if (!$draft || (string)$draft['status'] !== 'generated') throw new \RuntimeException(__('Gere o documento antes de enviá-lo para aceite.', 'projectplus'));
      global $DB; $now=date('Y-m-d H:i:s'); $current=self::getForItem($item,$type);
      $data=['documents_id'=>0,'status'=>'published','published_users_id'=>(int)Session::getLoginUserID(),'accepted_users_id'=>0,'date_published'=>$now,'date_accepted'=>null,'date_mod'=>$now];
      if($current)$DB->update(self::getTable(),$data,['id'=>(int)$current['id']]);else $DB->insert(self::getTable(),$data+['itemtype'=>$item::getType(),'items_id'=>(int)$item->getID(),'entities_id'=>(int)$item->getEntityID(),'document_type'=>$type]);
      self::addFollowup($item, $type, __('Documento gerado enviado para aceite.', 'projectplus'));
      if($item instanceof Ticket&&$type==='tap')TicketPhase::setPhase($item,'awaiting_tap_acceptance',__('TAP enviado para aceite.','projectplus'));
   }

   public static function accept(CommonDBTM $item, int $acceptanceId): void
   {
      /** @var DBmysql $DB */ global $DB;
      $row = $DB->request(['FROM'=>self::getTable(),'WHERE'=>['id'=>$acceptanceId,'itemtype'=>$item::getType(),'items_id'=>(int)$item->getID(),'status'=>'published'],'LIMIT'=>1])->current();
      if (!$row || !self::canAccept($item)) { throw new \RuntimeException(__('Você não possui permissão para aceitar este documento.', 'projectplus')); }
      $now = date('Y-m-d H:i:s');
      $DB->update(self::getTable(),['status'=>'accepted','accepted_users_id'=>(int)Session::getLoginUserID(),'date_accepted'=>$now,'date_mod'=>$now],['id'=>(int)$row['id']]);
      self::addFollowup($item, (string)$row['document_type'], __('Aceite do documento registrado.', 'projectplus'));
      if ($item instanceof Ticket && (string)$row['document_type'] === 'tap') { TicketPhase::setPhase($item, 'authorized_opening', __('TAP aceito pelo cliente.', 'projectplus')); }
      if ($item instanceof Project && (string) $row['document_type'] === 'ta') {
         $projectUpdate = ['id' => (int) $item->getID(), 'percent_done' => 100, 'real_end_date' => $now];
         $finishedStateId = self::getFinishedProjectStateId();
         if ($finishedStateId > 0) {
            $projectUpdate['projectstates_id'] = $finishedStateId;
         }
         $item->update($projectUpdate);
      }
   }

   /** Returns the native GLPI project state marked as finished, if configured. */
   private static function getFinishedProjectStateId(): int
   {
      /** @var DBmysql $DB */ global $DB;
      $state = $DB->request([
         'FROM'  => ProjectState::getTable(),
         // ProjectState in GLPI 10 does not have an is_deleted column.
         // The is_finished flag is the native source of truth for closure.
         'WHERE' => ['is_finished' => 1],
         'ORDER' => ['id ASC'],
         'LIMIT' => 1,
      ])->current();
      return $state ? (int) $state['id'] : 0;
   }

   /** @return array<string,mixed>|null */
   public static function getViewableAcceptance(CommonDBTM $item, int $acceptanceId): ?array
   {
      /** @var DBmysql $DB */ global $DB;
      $row = $DB->request(['FROM'=>self::getTable(),'WHERE'=>['id'=>$acceptanceId,'itemtype'=>$item::getType(),'items_id'=>(int)$item->getID()],'LIMIT'=>1])->current();
      if (!$row || (!self::canManage($item) && !self::canAccept($item))) { return null; }
      return $row;
   }

   public static function showForItem(CommonDBTM $item): void
   {
      $types = self::typesForItem($item);
      echo "<div class='projectplus-tip projectplus-acceptance'><header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-check'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::esc(__('Validação documental', 'projectplus')) . '</span><h2>' . self::esc(__('Documentos e aceites', 'projectplus')) . '</h2><p>' . self::esc(__('Leia o documento publicado e registre seu aceite de forma auditável.', 'projectplus')) . '</p></div></header>';
      foreach ($types as $type => $label) { self::showDocument($item, $type, $label); }
      echo '</div>';
   }

   /**
    * Displays published project documents to a related helpdesk user without
    * exposing native Project tabs or document-drafting controls.
    */
   public static function showPortalForProject(Project $project): void
   {
      if (!self::canAccept($project)) {
         return;
      }

      $documents = [];
      foreach (['tep' => __('TEP - Relatório de Entrega do Projeto', 'projectplus'), 'ta' => __('TA - Termo de Aceite', 'projectplus')] as $type => $label) {
         $row = self::getForItem($project, $type);
         if ($row && in_array((string) $row['status'], ['published', 'accepted'], true)) {
            $documents[] = ['label' => $label, 'row' => $row];
         }
      }
      if (!$documents) {
         return;
      }

      echo "<section class='projectplus-tip projectplus-acceptance projectplus-portal-acceptance'><header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-check'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::esc(__('Validação documental', 'projectplus')) . '</span><h2>' . self::esc(__('Documentos e aceites', 'projectplus')) . '</h2><p>' . self::esc(__('Leia o documento publicado e registre seu aceite.', 'projectplus')) . '</p></div></header>';
      foreach ($documents as $document) {
         echo "<section class='projectplus-tip__card projectplus-acceptance__card'><div class='projectplus-tip__section-title'><i class='ti ti-file-description'></i><div><h3>" . self::esc((string) $document['label']) . '</h3><p>' . self::esc(__('Documento disponível para aceite.', 'projectplus')) . '</p></div></div>';
         self::showAcceptance($project, $document['row'], self::portalUrl());
         echo '</section>';
      }
      echo '</section>';
   }

   private static function showDocument(CommonDBTM $item, string $type, string $label): void
   {
      $row = self::getForItem($item, $type);
      echo "<section class='projectplus-tip__card projectplus-acceptance__card'><div class='projectplus-tip__section-title'><i class='ti ti-file-description'></i><div><h3>" . self::esc($label) . '</h3><p>' . self::esc($row ? ((string)$row['status']==='accepted' ? __('Aceite registrado.', 'projectplus') : __('Aguardando aceite do cliente.', 'projectplus')) : __('Documento ainda não publicado.', 'projectplus')) . '</p></div></div>';
      if (!$row && self::canManage($item)) {
         if ($item instanceof Project && $type === 'ta' && (!(self::getForItem($item, 'tep')) || (string) (self::getForItem($item, 'tep')['status'] ?? '') !== 'accepted')) {
            echo "<div class='alert alert-info'>" . self::esc(__('O TA será liberado após o aceite do TEP.', 'projectplus')) . '</div>';
         } else { self::showDraftForm($item, $type); }
      }
      elseif ($row) { self::showAcceptance($item, $row); }
      echo '</section>';
   }

   private static function showDraftForm(CommonDBTM $item, string $type): void
   {
      global $CFG_GLPI;
      $fields = DocumentField::get((int)$item->getEntityID(), $type, true);
      if (!$fields) { echo "<div class='alert alert-info'>" . self::esc(__('Configure os campos deste documento antes de preenchê-lo.', 'projectplus')) . '</div>'; return; }
      $draft = DocumentDraft::get($item::getType(), (int)$item->getID(), $type);
      $answers = $draft ? (json_decode((string)$draft['content'], true) ?: []) : [];
      $url = rtrim((string)$CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/documentdraft.form.php';
      echo "<form method='post' action='" . self::esc($url) . "' class='projectplus-tip__grid'>" . Html::hidden('_glpi_csrf_token',['value'=>Session::getNewCSRFToken()]) . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('document_type',['value'=>$type]);
      foreach ($fields as $field) { if ($field['field_type'] === 'section') { echo "<div class='projectplus-tip__section-title projectplus-tip__section-title--divider'><h3>" . self::esc((string)$field['label']) . '</h3></div>'; continue; } $value = (string)($answers[(int)$field['id']]['value'] ?? ''); echo "<div class='projectplus-tip__field'><label>" . self::esc((string)$field['label']) . ((int)$field['is_required']?' *':'') . '</label><small>' . self::esc((string)$field['description']) . '</small>'; if ($field['field_type']==='textarea') echo "<textarea class='form-control' name='document_fields[".(int)$field['id']."]'".((int)$field['is_required']?' required':'').">".self::esc($value).'</textarea>'; else echo "<input class='form-control' type='".($field['field_type']==='number'?'number':($field['field_type']==='date'?'date':'text'))."' name='document_fields[".(int)$field['id']."]' value='".self::esc($value)."'".((int)$field['is_required']?' required':'').">"; echo '</div>'; }
      $generated = $draft && (string)$draft['status'] === 'generated';
      echo "<div class='projectplus-tip__footer'><span>" . self::esc($generated ? __('Versão gerada. Edite e gere novamente caso necessário.', 'projectplus') : __('Salve o rascunho antes de gerar o documento.', 'projectplus')) . "</span><button class='btn btn-secondary' type='submit'>" . self::esc(__('Salvar rascunho', 'projectplus')) . "</button><button class='btn btn-primary' type='submit' name='generate_document' value='1'>" . self::esc(__('Gerar ', 'projectplus') . strtoupper($type)) . '</button></div></form>';
      if ($generated) echo "<form method='post' action='" . self::esc(self::url()) . "' class='projectplus-tip__footer'>" . Html::hidden('_glpi_csrf_token',['value'=>Session::getNewCSRFToken()]) . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('document_type',['value'=>$type]) . "<a target='_blank' href='" . self::esc(rtrim((string)$CFG_GLPI['root_doc'],'/') . '/plugins/projectplus/front/documentdraft.pdf.php?itemtype=' . rawurlencode($item::getType()) . '&items_id=' . (int)$item->getID() . '&document_type=' . $type) . "'>" . self::esc(__('Visualizar PDF gerado','projectplus')) . "</a><button class='btn btn-primary' name='publish_generated' value='1'>" . self::esc(__('Enviar para aceite','projectplus')) . '</button></form>';
   }

   private static function showPublishForm(CommonDBTM $item, string $type): void
   {
      echo "<form method='post' action='" . self::esc(self::url()) . "'>" . Html::hidden('_glpi_csrf_token',['value'=>Session::getNewCSRFToken()]) . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('document_type',['value'=>$type]);
      $options = [];
      foreach (DocumentTemplate::get((int)$item->getEntityID()) as $model) { if ((string)$model['document_type'] === $type && (int)$model['is_active']) { $options[(int)$model['id']] = (string)$model['name'] . ((string)$model['version'] !== '' ? ' — v' . (string)$model['version'] : ''); } }
      echo "<div class='projectplus-acceptance__publish'><div><label>" . self::esc(__('Modelo PDF', 'projectplus')) . '</label>';
      Dropdown::showFromArray('documents_id',$options);
      echo '</div><button class="btn btn-primary" type="submit" name="publish_document" value="1"><i class="ti ti-send"></i> ' . self::esc(__('Publicar para aceite', 'projectplus')) . '</button></div></form>';
   }

   /** @param array<string,mixed> $row */
   private static function showAcceptance(CommonDBTM $item, array $row, ?string $actionUrl = null): void
   {
      global $CFG_GLPI;
      $actionUrl = $actionUrl ?? self::url();
      if ((int)$row['documents_id'] === 0) {
         $src = rtrim((string)$CFG_GLPI['root_doc'],'/') . '/plugins/projectplus/front/documentdraft.pdf.php?itemtype=' . rawurlencode($item::getType()) . '&items_id=' . (int)$item->getID() . '&document_type=' . rawurlencode((string)$row['document_type']);
         echo "<div class='projectplus-acceptance__document'><div><strong>" . self::esc(strtoupper((string)$row['document_type'])) . "</strong><a target='_blank' rel='noopener' href='" . self::esc($src) . "'>" . self::esc(__('Abrir documento', 'projectplus')) . "</a></div><iframe title='Documento gerado' src='" . self::esc($src) . "'></iframe></div>";
         if ((string)$row['status'] === 'accepted') { echo "<div class='projectplus-acceptance__accepted'>" . self::esc(__('Aceite registrado.', 'projectplus')) . '</div>'; return; }
         if (self::canAccept($item)) echo "<form method='post' action='" . self::esc($actionUrl) . "' class='projectplus-acceptance__form'>" . Html::hidden('_glpi_csrf_token',['value'=>Session::getNewCSRFToken()]) . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('acceptance_id',['value'=>(int)$row['id']]) . "<label><input required type='checkbox' name='accepted' value='1'> " . self::esc(__('Li e aceito os termos deste documento.', 'projectplus')) . "</label><button class='btn btn-primary' type='submit' name='accept_document' value='1'>" . self::esc(__('Aceitar e continuar', 'projectplus')) . '</button></form>';
         return;
      }
      $document = new Document(); $document->getFromDB((int)$row['documents_id']);
      $src = rtrim((string)$CFG_GLPI['root_doc'],'/') . '/plugins/projectplus/front/documentacceptance.view.php?itemtype=' . rawurlencode($item::getType()) . '&items_id=' . (int)$item->getID() . '&acceptance_id=' . (int)$row['id'];
      echo "<div class='projectplus-acceptance__document'><div><strong>" . self::esc((string)$document->getName()) . '</strong><a target="_blank" rel="noopener" href="' . self::esc($src) . '">' . self::esc(__('Abrir documento', 'projectplus')) . '</a></div><iframe title="' . self::esc((string)$document->getName()) . '" src="' . self::esc($src) . '"></iframe></div>';
      if ((string)$row['status'] === 'accepted') { echo "<div class='projectplus-acceptance__accepted'><i class='ti ti-circle-check'></i> " . self::esc(__('Aceito por', 'projectplus')) . ' ' . self::esc(User::getFriendlyNameById((int)$row['accepted_users_id'])) . ' — ' . self::esc((string)$row['date_accepted']) . '</div>'; return; }
      if (self::canAccept($item)) {
         echo "<form method='post' action='" . self::esc($actionUrl) . "' class='projectplus-acceptance__form'>" . Html::hidden('_glpi_csrf_token',['value'=>Session::getNewCSRFToken()]) . Html::hidden('itemtype',['value'=>$item::getType()]) . Html::hidden('items_id',['value'=>(int)$item->getID()]) . Html::hidden('acceptance_id',['value'=>(int)$row['id']]) . "<label><input required type='checkbox' name='accepted' value='1'> " . self::esc(__('Li e aceito os termos deste documento.', 'projectplus')) . "</label><button class='btn btn-primary' type='submit' name='accept_document' value='1'><i class='ti ti-check'></i> " . self::esc(__('Aceitar e continuar', 'projectplus')) . '</button></form>';
      }
   }

   private static function url(): string { global $CFG_GLPI; return rtrim((string)$CFG_GLPI['root_doc'],'/') . '/plugins/projectplus/front/documentacceptance.form.php'; }
   private static function portalUrl(): string { global $CFG_GLPI; return rtrim((string)$CFG_GLPI['root_doc'],'/') . '/plugins/projectplus/front/myprojectacceptance.form.php'; }
   private static function addFollowup(CommonDBTM $item, string $type, string $message): void
   {
      global $DB;
      $content = $message;
      if ($item instanceof Ticket) { $f = new ITILFollowup(); $f->add(['itemtype'=>Ticket::class,'items_id'=>(int)$item->getID(),'users_id'=>(int)Session::getLoginUserID(),'content'=>$content,'is_private'=>0]); return; }
      if ($item instanceof Project && $DB->tableExists(ProjectInteraction::getTable())) $DB->insert(ProjectInteraction::getTable(), ['projects_id'=>(int)$item->getID(),'entities_id'=>(int)$item->getEntityID(),'users_id'=>(int)Session::getLoginUserID(),'content'=>$content,'is_internal'=>0,'notify_participants'=>1,'date_creation'=>date('Y-m-d H:i:s')]);
   }
   private static function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
