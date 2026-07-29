<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Entity;
use Glpi\RichText\RichText;
use Html;
use Project;
use Session;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Project+ discussion timeline and its controlled project actions. */
class ProjectInteraction extends CommonDBTM
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_projectinteractions';
   }

   private static function isManager(): bool
   {
      return Session::haveRight('project', UPDATE);
   }

   /**
    * Internal interactions belong to the management workspace. A native
    * Project UPDATE right alone is not enough: focal points can legitimately
    * have that right without being portfolio/project managers.
    */
   private static function canViewInternal(Project $project): bool
   {
      return Session::haveRight(ProjectProfile::RIGHT_PORTFOLIO, READ)
         || Session::haveRight(ProjectProfile::RIGHT_CONFIG, READ);
   }

   private static function isAllowed(Project $project): bool
   {
      $data = ProjectData::getForProject((int) $project->getID());
      $userId = (int) Session::getLoginUserID();

      return self::isManager() || in_array($userId, [
         (int) ($data['requester_users_id'] ?? 0),
         (int) ($data['focal_point_users_id'] ?? 0),
      ], true);
   }

   /** A profile can remove a button without weakening the native Project right. */
   public static function canAction(Project $project, string $right, bool $managerOnly = false): bool
   {
      return self::isAllowed($project)
         && Session::haveRight($right, READ)
         && (!$managerOnly || self::isManager());
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
   {
      if ($item instanceof Project && !$withtemplate && self::isAllowed($item)) {
         return self::createTabEntry(__('Interações', 'projectplus'), 0, $item::getType(), 'ti ti-message-circle');
      }
      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
   {
      if ($item instanceof Project) {
         self::show($item);
      }
      return true;
   }

   public static function addInteraction(Project $project, array $input): void
   {
      if (!self::canAction($project, ProjectProfile::RIGHT_INTERACTION_REPLY)) {
         throw new \RuntimeException(__('Sem permissão para publicar neste projeto.', 'projectplus'));
      }

      $action = (string) ($input['interaction_action'] ?? 'reply');
      if (!in_array($action, ['reply', 'solution'], true)) {
         throw new \RuntimeException(__('Ação de interação inválida.', 'projectplus'));
      }
      if ($action === 'solution') {
         if (!self::canAction($project, ProjectProfile::RIGHT_INTERACTION_SOLUTION, true)) {
            throw new \RuntimeException(__('Sem permissão para solucionar este projeto.', 'projectplus'));
         }
         $tep = DocumentDraft::get(Project::class, (int) $project->getID(), 'tep');
         if (!$tep || (string) $tep['status'] !== 'generated') {
            throw new \RuntimeException(__('Configure, preencha e gere o TEP antes de solucionar e enviá-lo ao cliente.', 'projectplus'));
         }
      }

      $content = trim((string) ($input['content'] ?? ''));
      if ($content === '') {
         throw new \RuntimeException(__('Informe a mensagem da interação.', 'projectplus'));
      }

      $isInternal = (int) ($input['is_internal'] ?? 0);
      if ($isInternal && !self::canViewInternal($project)) {
         throw new \RuntimeException(__('Apenas gerentes podem criar interações internas.', 'projectplus'));
      }

      self::insertInteraction($project, $content, $isInternal, (int) ($input['notify_participants'] ?? 0));

      if ($action === 'solution') {
         DocumentAcceptance::publishGenerated($project, 'tep');
      }
   }

   /** Returns an editable template body after checking its entity scope. */
   public static function getTemplateContent(Project $project, string $type, int $templateId): string
   {
      $right = $type === 'solution'
         ? ProjectProfile::RIGHT_INTERACTION_SOLUTION
         : ProjectProfile::RIGHT_INTERACTION_REPLY;
      if (!in_array($type, ['followup', 'solution'], true) || !self::canAction($project, $right, $type === 'solution')) {
         throw new \RuntimeException(__('Sem permissão para usar este modelo.', 'projectplus'));
      }
      if ($templateId <= 0) {
         return '';
      }
      $class = $type === 'solution' ? SolutionTemplate::class : FollowupTemplate::class;
      $template = new $class();
      if (!$template->getFromDB($templateId) || !(int) ($template->fields['is_active'] ?? 0)
         || !Session::haveAccessToEntity((int) ($template->fields['entities_id'] ?? 0))) {
         throw new \RuntimeException(__('Modelo não encontrado ou sem acesso para a entidade atual.', 'projectplus'));
      }
      return (string) ($template->fields['comment'] ?? '');
   }

   /** Transfers the Project to a target entity and records the auditable event. */
   public static function escalate(Project $project, array $input): void
   {
      if (!self::canAction($project, ProjectProfile::RIGHT_INTERACTION_ESCALATE, true)) {
         throw new \RuntimeException(__('Sem permissão para escalar este projeto.', 'projectplus'));
      }
      $targetEntityId = (int) ($input['entities_id'] ?? 0);
      if ($targetEntityId <= 0 || !Session::haveAccessToEntity($targetEntityId)) {
         throw new \RuntimeException(__('Selecione uma entidade de destino válida.', 'projectplus'));
      }
      if ($targetEntityId === (int) $project->getEntityID()) {
         throw new \RuntimeException(__('Selecione uma entidade diferente da entidade atual.', 'projectplus'));
      }

      $targetName = Entity::getFriendlyNameById($targetEntityId);
      if (!$project->update(['id' => (int) $project->getID(), 'entities_id' => $targetEntityId])) {
         throw new \RuntimeException(__('Não foi possível transferir o projeto para a entidade selecionada.', 'projectplus'));
      }
      self::insertInteraction(
         $project,
         sprintf(__('Projeto escalado para a entidade %s.', 'projectplus'), $targetName),
         0,
         1,
         $targetEntityId
      );
   }

   private static function insertInteraction(Project $project, string $content, int $isInternal, int $notify, ?int $entityId = null): void
   {
      global $DB;
      $DB->insert(self::getTable(), [
         'projects_id' => (int) $project->getID(),
         'entities_id' => $entityId ?? (int) $project->getEntityID(),
         'users_id' => (int) Session::getLoginUserID(),
         'content' => $content,
         'is_internal' => $isInternal,
         'notify_participants' => $notify,
         'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
      ]);
   }

   public static function show(Project $project): void
   {
      if (!self::isAllowed($project)) {
         return;
      }
      global $CFG_GLPI;

      $rand = mt_rand();
      $editorId = 'projectplus_interaction_content_' . $rand;
      $composerId = 'projectplus-interaction-composer-' . $rand;
      $toolbarId = 'projectplus-interaction-toolbar-' . $rand;
      $escalateId = 'projectplus-interaction-escalate-' . $rand;
      $followupTemplateId = 'projectplus-followup-template-' . $rand;
      $solutionTemplateId = 'projectplus-solution-template-' . $rand;
      $formUrl = self::url('projectinteraction.form.php');
      $escalateUrl = self::url('projectinteraction.escalate.php');
      $editor = Html::textarea([
         'name' => 'content', 'editor_id' => $editorId, 'value' => '', 'display' => false,
         'enable_richtext' => true, 'enable_fileupload' => true, 'enable_images' => true,
         'rows' => 8, 'required' => false, 'rand' => $rand,
      ]);

      echo "<section class='projectplus-followups'>";
      self::showTimeline($project);

      echo "<form id='{$composerId}' method='post' enctype='multipart/form-data' action='{$formUrl}' class='projectplus-followups__composer' data-track-changes='true'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('projects_id', ['value' => (int) $project->getID()]);
      echo Html::hidden('interaction_action', ['id' => $composerId . '-action', 'value' => 'reply']);
      echo "<button class='projectplus-followups__close' type='button' title='Fechar'><i class='ti ti-x'></i></button>";
      echo "<div class='projectplus-followups__composer-main'>{$editor}</div>";
      echo "<aside class='projectplus-followups__composer-options'>";
      echo "<div class='projectplus-followups__template-selector projectplus-followups__template-selector--followup'><i class='ti ti-file-text'></i><div>";
      FollowupTemplate::dropdown(['name' => 'followup_template_id', 'id' => $followupTemplateId, 'value' => 0, 'entity' => (int) $project->getEntityID()]);
      echo '</div></div>';
      echo "<div class='projectplus-followups__template-selector projectplus-followups__template-selector--solution'><i class='ti ti-file-check'></i><div>";
      SolutionTemplate::dropdown(['name' => 'solution_template_id', 'id' => $solutionTemplateId, 'value' => 0, 'entity' => (int) $project->getEntityID()]);
      echo '</div></div>';
      if (self::canViewInternal($project)) {
         echo "<label class='projectplus-followups__icon-toggle' title='" . htmlspecialchars(__('Privado', 'projectplus'), ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-lock'></i><input type='checkbox' name='is_internal' value='1'><span class='projectplus-followups__toggle-slider'></span></label>";
      }
      echo "<label class='projectplus-followups__icon-toggle' title='" . htmlspecialchars(__('Notificar participantes', 'projectplus'), ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-bell'></i><input type='checkbox' name='notify_participants' value='1' checked><span class='projectplus-followups__toggle-slider'></span></label>";
      echo '</aside>';
      echo "<footer class='projectplus-followups__composer-footer'><button id='{$composerId}-submit' class='btn btn-warning' type='submit' name='add_interaction' value='1'><i class='ti ti-plus'></i> " . __('Adicionar', 'projectplus') . '</button></footer>';
      echo '</form>';

      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_ESCALATE, true)) {
         echo "<form id='{$escalateId}' method='post' action='{$escalateUrl}' class='projectplus-followups__escalate'>";
         echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
         echo Html::hidden('projects_id', ['value' => (int) $project->getID()]);
         echo '<div><strong>' . __('Escalar projeto', 'projectplus') . '</strong><p>' . __('Transfira o projeto para outra entidade.', 'projectplus') . '</p></div><div class="projectplus-followups__escalate-control">';
         $targets = self::getEscalationTargets($project);
         if ($targets) {
            Dropdown::showFromArray('entities_id', $targets, ['display_emptychoice' => true, 'value' => 0]);
            echo "<button class='btn btn-warning' type='submit'><i class='ti ti-arrow-up-right'></i> " . __('Escalar', 'projectplus') . '</button>';
         } else {
            echo "<span class='text-muted'>" . __('Nenhuma entidade de destino disponível.', 'projectplus') . '</span>';
         }
         echo '</div></form>';
      }

      echo "<footer class='projectplus-followups__toolbar' id='{$toolbarId}'>";
      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_REPLY)) {
         echo "<button class='btn btn-warning projectplus-followups__button--reply' type='button' data-projectplus-action='reply'><i class='ti ti-message-circle'></i> " . __('Responder', 'projectplus') . '</button>';
      }
      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_TASK, true)) {
         $taskUrl = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/front/projecttask.form.php?projects_id=' . (int) $project->getID();
         echo "<a class='btn btn-warning projectplus-followups__button--task' href='" . htmlspecialchars($taskUrl, ENT_QUOTES, 'UTF-8') . "'><i class='ti ti-checkbox'></i> " . __('Tarefa', 'projectplus') . '</a>';
      }
      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_SOLUTION, true)) {
         echo "<button class='btn btn-warning projectplus-followups__button--solution' type='button' data-projectplus-action='solution'><i class='ti ti-circle-check'></i> " . __('Solução', 'projectplus') . '</button>';
      }
      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_DOCUMENT, true)) {
         echo "<button class='btn btn-warning projectplus-followups__button--document' type='button' data-projectplus-action='document'><i class='ti ti-file'></i> " . __('Documento', 'projectplus') . '</button>';
      }
      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_VALIDATION, true)) {
         echo "<button class='btn btn-warning projectplus-followups__button--validation' type='button' data-projectplus-action='validation'><i class='ti ti-file-check'></i> " . __('Validação', 'projectplus') . '</button>';
      }
      if (self::canAction($project, ProjectProfile::RIGHT_INTERACTION_ESCALATE, true)) {
         echo "<button class='btn btn-warning projectplus-followups__button--escalate' type='button' data-projectplus-action='escalate'><i class='ti ti-arrow-up-right'></i> " . __('Escalar', 'projectplus') . '</button>';
      }
      echo '</footer>';

      echo Html::scriptBlock("$(function () {
         var composer = $('#{$composerId}');
         function openComposer(action) {
            composer.addClass('is-open');
            composer.toggleClass('projectplus-followups__composer--solution', action === 'solution');
            $('#{$composerId}-action').val(action);
         $('#{$composerId}-submit')
            .toggleClass('projectplus-followups__submit--solution', action === 'solution')
            .html(action === 'solution' ? '<i class=\"ti ti-circle-check\"></i> Solucionar e enviar o TEP' : '<i class=\"ti ti-plus\"></i> Adicionar');
            var editor = window.tinymce ? window.tinymce.get('{$editorId}') : null;
            if (editor) { editor.focus(); }
         }
         $('#{$toolbarId} [data-projectplus-action=reply]').on('click', function () { openComposer('reply'); });
         $('#{$toolbarId} [data-projectplus-action=solution]').on('click', function () { openComposer('solution'); });
         composer.find('.projectplus-followups__close').on('click', function () { composer.removeClass('is-open'); });
         $('#{$toolbarId} [data-projectplus-action=escalate]').on('click', function () { $('#{$escalateId}').toggleClass('is-open'); });
         $('#{$toolbarId} [data-projectplus-action=document]').on('click', function () { var tab = $('a[href*=Document_Item]').first(); if (tab.length) { tab.trigger('click'); } });
         $('#{$toolbarId} [data-projectplus-action=validation]').on('click', function () { var tab = $('a[href*=DocumentAcceptance]').first(); if (tab.length) { tab.trigger('click'); } });
         function applyTemplate(type, templateId) {
            if (!templateId || String(templateId) === '0') { return; }
            $.getJSON('" . self::url('projectinteraction.template.php') . "', {
               projects_id: " . (int) $project->getID() . ", template_type: type, template_id: templateId
            }).done(function (data) {
               if (!data || !data.success) { return; }
               var editor = window.tinymce ? window.tinymce.get('{$editorId}') : null;
               if (editor) { editor.setContent(data.content || ''); };
            });
         }
         $('#{$followupTemplateId}').on('change', function () { applyTemplate('followup', $(this).val()); });
         $('#{$solutionTemplateId}').on('change', function () { applyTemplate('solution', $(this).val()); });
      });");
      echo '</section>';
   }

   private static function showTimeline(Project $project): void
   {
      global $DB;
      $data = ProjectData::getForProject((int) $project->getID());
      $participantIds = [(int) ($data['requester_users_id'] ?? 0), (int) ($data['focal_point_users_id'] ?? 0)];
      echo "<div class='projectplus-followups__timeline'>";
      foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['projects_id' => (int) $project->getID()], 'ORDER' => ['date_creation ASC', 'id ASC']]) as $interaction) {
         if ((int) $interaction['is_internal'] && !self::canViewInternal($project)) { continue; }
         $authorId = (int) $interaction['users_id'];
         $author = User::getFriendlyNameById($authorId);
         $participant = in_array($authorId, $participantIds, true);
         $position = $participant ? 'left' : 'right';
         $kind = (int) $interaction['is_internal'] ? 'internal' : ($participant ? 'participant' : 'manager');
         $date = Html::convDateTime((string) $interaction['date_creation']);
         $content = RichText::getEnhancedHtml((string) $interaction['content'], ['images_gallery' => true]);
         echo "<article class='projectplus-followup projectplus-followup--{$position} projectplus-followup--{$kind}'>";
         echo "<div class='projectplus-followup__avatar'>" . htmlspecialchars(self::getInitials($author), ENT_QUOTES, 'UTF-8') . '</div>';
         echo "<div class='projectplus-followup__card'><header class='projectplus-followup__meta'>";
         echo "<span><i class='ti ti-calendar-event'></i> " . __('Criado em:', 'projectplus') . " <strong>{$date}</strong></span>";
         echo "<span><i class='ti ti-user'></i> " . __('por', 'projectplus') . ' <strong>' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '</strong></span>';
         if ((int) $interaction['is_internal']) { echo "<span class='projectplus-followup__private'><i class='ti ti-lock'></i> " . __('Interna', 'projectplus') . '</span>'; }
         echo "</header><div class='projectplus-followup__content rich_text_container'>{$content}</div></div></article>";
      }
      echo '</div>';
   }

   /** @return array<int, string> */
   private static function getEscalationTargets(Project $project): array
   {
      global $DB;
      $targets = [];
      $currentEntityId = (int) $project->getEntityID();
      foreach ($DB->request([
         'FROM' => Entity::getTable(),
         'ORDER' => ['completename ASC', 'name ASC'],
      ]) as $entity) {
         $entityId = (int) $entity['id'];
         if ($entityId === $currentEntityId || !Session::haveAccessToEntity($entityId)) {
            continue;
         }
         $targets[$entityId] = Entity::getFriendlyNameById($entityId);
      }
      return $targets;
   }

   private static function getInitials(string $name): string
   {
      $words = preg_split('/\s+/', trim($name)) ?: [];
      if (count($words) === 1) { return mb_strtoupper(mb_substr($words[0], 0, 2)); }
      $initials = '';
      foreach (array_slice($words, 0, 2) as $word) { $initials .= mb_strtoupper(mb_substr($word, 0, 1)); }
      return $initials !== '' ? $initials : '?';
   }

   private static function url(string $file): string
   {
      global $CFG_GLPI;
      return htmlspecialchars(rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/' . $file, ENT_QUOTES, 'UTF-8');
   }
}
