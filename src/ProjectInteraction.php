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
   private const SYSTEM_SOLUTION_PREFIX = '[projectplus:solution]';

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_projectinteractions';
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
      return self::canViewProjectplus($project);
   }

   /** Indicates whether the current user may see the project interaction workspace. */
   public static function canViewProjectplus(Project $project): bool
   {
      foreach ([
         ProjectProfile::RIGHT_INTERACTION_REPLY,
         ProjectProfile::RIGHT_INTERACTION_TASK,
         ProjectProfile::RIGHT_INTERACTION_SOLUTION,
         ProjectProfile::RIGHT_INTERACTION_DOCUMENT,
         ProjectProfile::RIGHT_INTERACTION_VALIDATION,
         ProjectProfile::RIGHT_INTERACTION_ESCALATE,
      ] as $right) {
         if (Session::haveRight($right, READ)) {
            return true;
         }
      }
      return false;
   }

   /** A profile can remove a button without weakening the native Project right. */
   public static function canAction(Project $project, string $right, bool $managerOnly = false): bool
   {
      return self::isAllowed($project) && Session::haveRight($right, CREATE);
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
      return (string) ($template->fields['content'] ?? $template->fields['comment'] ?? '');
   }

   /** Transfers the Project to a target entity and records the auditable event. */
   public static function escalate(Project $project, array $input): void
   {
      if (!self::canAction($project, ProjectProfile::RIGHT_INTERACTION_ESCALATE, true)) {
         throw new \RuntimeException(__('Sem permissão para transferir este projeto.', 'projectplus'));
      }
      $targetEntityId = (int) ($input['entities_id'] ?? 0);
      if ($targetEntityId <= 0 || !Session::haveAccessToEntity($targetEntityId)) {
         throw new \RuntimeException(__('Selecione uma entidade de destino válida.', 'projectplus'));
      }
      if ($targetEntityId === (int) $project->getEntityID()) {
         throw new \RuntimeException(__('Selecione uma entidade diferente da entidade atual.', 'projectplus'));
      }
      // A regra do servidor, e nao a lista da tela, e quem decide: esconder a
      // opcao no dropdown nao impede um POST montado a mao.
      if (!TransferRule::isAllowed((int) $project->getEntityID(), $targetEntityId)) {
         throw new \RuntimeException(__('A entidade atual do projeto não está autorizada a transferir para o destino informado. Ajuste as regras em Configurar > Project+ > Transferência de projetos.', 'projectplus'));
      }

      $targetName = Entity::getFriendlyNameById($targetEntityId);
      if (!$project->update(['id' => (int) $project->getID(), 'entities_id' => $targetEntityId])) {
         throw new \RuntimeException(__('Não foi possível transferir o projeto para a entidade selecionada.', 'projectplus'));
      }
      self::insertInteraction(
         $project,
         sprintf(__('Projeto transferido para a entidade %s.', 'projectplus'), $targetName),
         0,
         1,
         $targetEntityId,
         null,
         ProjectNotification::EVENT_ESCALATION
      );
   }

   /**
    * Grava a interacao e, quando ela pede notificacao, enfileira o e-mail.
    *
    * O evento e declarado por quem chama porque a mesma tabela guarda coisas
    * com significados diferentes - uma mensagem escrita por alguem, uma
    * solucao do fluxo, um escalonamento de entidade - e cada uma pode precisar
    * de destinatario e texto proprios.
    */
   private static function insertInteraction(Project $project, string $content, int $isInternal, int $notify, ?int $entityId = null, ?int $authorId = null, string $event = ProjectNotification::EVENT_INTERACTION): void
   {
      global $DB;
      $linha = [
         'projects_id' => (int) $project->getID(),
         'entities_id' => $entityId ?? (int) $project->getEntityID(),
         'users_id' => $authorId ?? (int) Session::getLoginUserID(),
         'content' => $content,
         'is_internal' => $isInternal,
         'notify_participants' => $notify,
         'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
      ];
      $DB->insert(self::getTable(), $linha);

      // O prefixo e marcador interno de renderizacao: nao vai para o e-mail.
      $linha['content'] = strpos($content, self::SYSTEM_SOLUTION_PREFIX) === 0
         ? substr($content, strlen(self::SYSTEM_SOLUTION_PREFIX))
         : $content;
      ProjectNotification::raise($project, $event, $linha);
   }

   /**
    * Aviso do fluxo sobre um termo do projeto (aceite, recusa, devolucao).
    *
    * Existe para que esses avisos passem pela mesma porta das demais
    * interacoes, e nao por um INSERT proprio: e o que garante que eles tambem
    * notifiquem, com evento proprio para quem quiser separar destinatario.
    */
   public static function addDocumentNotice(Project $project, string $content): void
   {
      self::insertInteraction($project, $content, 0, 1, null, null, ProjectNotification::EVENT_DOCUMENT);
   }

   /** Records a workflow event using the same visual treatment as a solution. */
   public static function addSystemSolution(Project $project, string $content, int $authorId): void
   {
      self::insertInteraction(
         $project,
         self::SYSTEM_SOLUTION_PREFIX . $content,
         0,
         1,
         null,
         $authorId > 0 ? $authorId : null,
         ProjectNotification::EVENT_SOLUTION
      );
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
         echo '<div><strong>' . __('Transferir projeto', 'projectplus') . '</strong><p>' . __('Transfira o projeto para outra entidade.', 'projectplus') . '</p></div><div class="projectplus-followups__escalate-control">';
         if (self::hasEscalationTargets($project)) {
            // Dropdown nativo de Entity, e nao uma lista montada a mao: ele e o
            // unico que mostra a arvore com o caminho completo. Com o nome da
            // folha, "Sao Paulo" aparecia duas vezes sem nada que distinguisse
            // uma da outra - e transferir para a entidade errada nao tem desfazer.
            // A restricao as entidades acessiveis ja vem do proprio dropdown.
            Dropdown::show(Entity::class, [
               'name' => 'entities_id',
               // -1, e nao 0: a entidade raiz existe com id 0, entao o campo
               // nascia mostrando "UNIFESP" como se ja houvesse escolha feita.
               'value' => -1,
               'display_emptychoice' => true,
               'emptylabel' => __('Selecione a entidade de destino', 'projectplus'),
               // A entidade atual nao e destino. A raiz sai junto porque a opcao
               // vazia do GLPI tambem vale 0: com ela na lista, "nada escolhido"
               // e "entidade raiz" chegariam identicos no POST. E por isso que o
               // proprio GLPI desliga a opcao vazia para Entity.
               'used' => [(int) $project->getEntityID(), 0],
               // Alem do alcance da sessao, o destino precisa estar nas regras
               // cadastradas para esta origem. Sem regra a lista fica vazia, e
               // o bloco nem chega a ser renderizado.
               'condition' => ['id' => TransferRule::getAllowedTargets((int) $project->getEntityID())],
            ]);
            echo "<button class='btn btn-warning' type='submit'><i class='ti ti-arrow-up-right'></i> " . __('Transferir projeto', 'projectplus') . '</button>';
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
         echo "<button class='btn btn-warning projectplus-followups__button--escalate' type='button' data-projectplus-action='escalate'><i class='ti ti-arrow-up-right'></i> " . __('Transferir projeto', 'projectplus') . '</button>';
      }
      echo '</footer>';

      echo Html::scriptBlock("$(function () {
         var composer = $('#{$composerId}');
         // GLPI exposes the native TinyMCE API as tinyMCE in some builds and
         // as tinymce in others. Supporting both keeps the template loader
         // aligned with the editor rendered by the core.
         function getRichTextEditor() {
            var editorApi = window.tinymce || window.tinyMCE;
            return editorApi && editorApi.get ? editorApi.get('{$editorId}') : null;
         }
         function openComposer(action) {
            composer.addClass('is-open');
            composer.toggleClass('projectplus-followups__composer--solution', action === 'solution');
            $('#{$composerId}-action').val(action);
         $('#{$composerId}-submit')
            .toggleClass('projectplus-followups__submit--solution', action === 'solution')
            .html(action === 'solution' ? '<i class=\"ti ti-circle-check\"></i> Solucionar e enviar o TEP' : '<i class=\"ti ti-plus\"></i> Adicionar');
            var editor = getRichTextEditor();
            if (editor) { editor.focus(); }
            // O compositor abre no fim da linha do tempo, quase sempre fora da
            // area visivel: sem rolar ate ele, o clique em Responder parece nao
            // ter feito nada. O timeout deixa o foco do editor acontecer antes,
            // senao ele desfaz a rolagem com um salto proprio.
            window.setTimeout(function () {
               if (composer[0] && composer[0].scrollIntoView) {
                  composer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
               }
            }, 60);
         }
         $('#{$toolbarId} [data-projectplus-action=reply]').on('click', function () { openComposer('reply'); });
         $('#{$toolbarId} [data-projectplus-action=solution]').on('click', function () { openComposer('solution'); });
         composer.find('.projectplus-followups__close').on('click', function () { composer.removeClass('is-open'); });
         $('#{$toolbarId} [data-projectplus-action=escalate]').on('click', function () { $('#{$escalateId}').toggleClass('is-open'); });
         $('#{$toolbarId} [data-projectplus-action=document], #{$toolbarId} [data-projectplus-action=validation]').on('click', function () { var tab = $('[data-projectplus-workspace-tab=documents]').first(); if (tab.length) { tab.trigger('click'); } });
         function applyTemplate(type, templateId) {
            if (!templateId || String(templateId) === '0') { return; }
            $.getJSON('" . self::url('projectinteraction.template.php') . "', {
               projects_id: " . (int) $project->getID() . ", template_type: type, template_id: templateId
            }).done(function (data) {
               if (!data || !data.success) { return; }
               var editor = getRichTextEditor();
               if (editor) {
                  editor.setContent(data.content || '');
                  editor.save();
               } else {
                  // Keep selection useful while the native rich-text editor
                  // is still loading (or when a browser temporarily blocks it).
                  $('#{$editorId}').val(data.content || '');
               }
            });
         }
         // GLPI generates the concrete dropdown DOM id itself. Bind by the
         // native field name, scoped to this composer, rather than assuming a
         // custom id supplied to Dropdown::show() will be kept unchanged.
         composer.find('select[name=followup_template_id]').on('change', function () {
            applyTemplate('followup', $(this).val());
         });
         composer.find('select[name=solution_template_id]').on('change', function () {
            applyTemplate('solution', $(this).val());
         });
      });");
      echo '</section>';
   }

   private static function showTimeline(Project $project): void
   {
      global $DB;
      $data = ProjectData::getForProject((int) $project->getID());
      $participantIds = [(int) ($data['requester_users_id'] ?? 0), (int) ($data['focal_point_users_id'] ?? 0)];
      // Follow the native GLPI followup convention: the authenticated user's
      // own messages are on the right, while every other participant is on
      // the left. The visual side must not depend on the author's role.
      $currentUserId = (int) Session::getLoginUserID();
      echo "<div class='projectplus-followups__timeline'>";
      foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['projects_id' => (int) $project->getID()], 'ORDER' => ['date_creation ASC', 'id ASC']]) as $interaction) {
         if ((int) $interaction['is_internal'] && !self::canViewInternal($project)) { continue; }
         $authorId = (int) $interaction['users_id'];
         $author = User::getFriendlyNameById($authorId);
         $participant = in_array($authorId, $participantIds, true);
         $position = $authorId === $currentUserId ? 'right' : 'left';
         $kind = (int) $interaction['is_internal'] ? 'internal' : ($participant ? 'participant' : 'manager');
         $isSolution = strpos((string) $interaction['content'], self::SYSTEM_SOLUTION_PREFIX) === 0;
         $date = Html::convDateTime((string) $interaction['date_creation']);
         $content = RichText::getEnhancedHtml(
            $isSolution
               ? substr((string) $interaction['content'], strlen(self::SYSTEM_SOLUTION_PREFIX))
               : (string) $interaction['content'],
            ['images_gallery' => true]
         );
         echo "<article class='projectplus-followup projectplus-followup--{$position} projectplus-followup--{$kind}" . ($isSolution ? ' projectplus-followup--solution' : '') . "'>";
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
   /**
    * Existe alguma entidade de destino possivel?
    *
    * So decide se o bloco de transferencia aparece; quem lista e restringe de
    * fato e o dropdown nativo. A versao anterior percorria a tabela inteira de
    * entidades e chamava `getFriendlyNameById()` linha a linha, o que e uma
    * consulta por entidade, para montar uma lista que o nativo ja monta.
    */
   private static function hasEscalationTargets(Project $project): bool
   {
      $ativas = $_SESSION['glpiactiveentities'] ?? [];
      if (!is_array($ativas)) {
         return false;
      }
      $origem = (int) $project->getEntityID();
      // Os destinos saem das regras cadastradas para esta origem; sem regra,
      // nao ha transferencia. Depois disso a sessao ainda precisa alcancar o
      // destino - uma condicao nao substitui a outra.
      $permitidos = array_diff(TransferRule::getAllowedTargets($origem), [$origem, 0]);
      return array_intersect($permitidos, array_map('intval', $ativas)) !== [];
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
