<?php

namespace GlpiPlugin\Projectplus;

use Html;
use GlpiPlugin\Projectplus\PortfolioBasket;
use GlpiPlugin\Projectplus\PdticAction;
use Project;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class ProjectForm
{
   public static function postItemForm(array $params): void
   {
      $project = $params['item'] ?? null;
      if (!$project instanceof Project) {
         return;
      }

      $data = ProjectData::getForProject((int) $project->getID());
      echo '<table id="projectplus-fields" class="d-none"><tbody>';
      self::renderProjectDataRows($data);
      self::renderRequesterRowsV2($data, (int) $project->fields['entities_id']);
      self::renderPlanningRows($data);
      echo '</tbody></table>';
      // GLPI 11 draws the Project form with Twig: a single "row flex-row" of
      // "form-field" blocks, with sections as "card > card-header > h4". It has no
      // table.tab_cadre_fixe any more, so the fields are rebuilt as native blocks.
      // When the layout is not recognised the fields are shown, never kept hidden.
      echo Html::scriptBlock(<<<'JAVASCRIPT'
$(function () {
   var source = $('#projectplus-fields');
   if (!source.length) {
      return;
   }
   var typeField = $('select[name="projecttypes_id"]').closest('.form-field');
   if (!typeField.length) {
      source.removeClass('d-none');
      return;
   }
   var container = typeField.parent();

   function section(title) {
      var card = $('<div class="card border-0 shadow-none p-0 m-0 mt-2 mb-2 projectplus-section"></div>');
      var head = $('<div class="card-header mb-1 p-0 ps-3 py-1"></div>');
      head.append($('<h4 class="card-subtitle"></h4>').append($('<span class="ms-2"></span>').text(title)));
      return card.append(head);
   }

   function block(labelCell, fieldCell, text) {
      var label = $('<label class="col-form-label text-xxl-end"></label>')
         .addClass(text ? 'col-xxl-3' : 'col-xxl-5')
         .append(labelCell.contents());
      var field = $('<div class="field-container"></div>')
         .addClass(text ? 'col-xxl-9' : 'col-xxl-7')
         .append(fieldCell.contents());
      field.find('textarea, input[type="text"], input[type="email"]').addClass('form-control');
      return $('<div class="form-field row align-items-center col-12 col-sm-6 mb-2 projectplus-field"></div>')
         .append(label, field);
   }

   // Descricao e Comentarios nativos passam a dividir a linha com Objetivo e
   // Produto/Resultado esperado, como no formulario do GLPI 10.
   function halfWidth(field) {
      field.removeClass('glpi-full-width').addClass('col-sm-6');
      field.children('label').removeClass('col-xxl-5').addClass('col-xxl-3');
      field.children('.field-container').removeClass('col-xxl-7').addClass('col-xxl-9');
   }

   function pairs(row, text) {
      var cells = row.children('td');
      var blocks = [];
      for (var i = 0; i + 1 < cells.length; i += 2) {
         var label = cells.eq(i);
         var field = cells.eq(i + 1);
         if (!$.trim(label.text()) && !field.children().length) {
            continue;
         }
         blocks.push(block(label, field, text));
      }
      return blocks;
   }

   function insertAfter(anchor, nodes) {
      var cursor = anchor;
      $.each(nodes, function (index, node) {
         cursor.after(node);
         cursor = node;
      });
   }

   var projectTitle = $.trim(source.find('.projectplus-project-header td').text());
   var projectBlocks = [];
   source.find('tr.projectplus-project-data').each(function () {
      projectBlocks = projectBlocks.concat(pairs($(this), false));
   });
   var requesterNodes = [];
   source.find('tr.projectplus-requester-data').each(function () {
      var row = $(this);
      var head = row.children('td[colspan]');
      if (head.length) {
         requesterNodes.push(section($.trim(head.text())));
      } else {
         requesterNodes = requesterNodes.concat(pairs(row, false));
      }
   });
   var planningBlocks = [];
   source.find('tr.projectplus-planning-data').each(function () {
      planningBlocks = planningBlocks.concat(pairs($(this), true));
   });

   if (projectTitle) {
      container.children().first().before(section(projectTitle));
   }

   // "Tipo" is followed by an empty block that completes its row.
   var afterType = typeField;
   var spacer = typeField.next('.form-field');
   if (spacer.length && !spacer.find('input, select, textarea').length) {
      afterType = spacer;
   }
   insertAfter(afterType, projectBlocks);

   var groupField = $('select[name="groups_id"]').closest('.form-field');
   if (!groupField.length) {
      groupField = $('select[name="users_id"]').closest('.form-field');
   }
   insertAfter(groupField.length ? groupField : afterType, requesterNodes);

   var contentField = $('[name="content"]').closest('.form-field');
   var commentField = $('[name="comment"]').closest('.form-field');
   if (planningBlocks.length > 1 && contentField.length && commentField.length) {
      halfWidth(contentField);
      halfWidth(commentField);
      insertAfter(contentField, [planningBlocks[0]]);
      insertAfter(commentField, [planningBlocks[1]]);
   } else if (planningBlocks.length) {
      insertAfter(commentField.length ? commentField : container.children().last(), planningBlocks);
   }

   source.remove();
});
JAVASCRIPT
      );
   }

   private static function renderProjectDataRows(array $data): void
   {
      echo '<tr class="projectplus-project-header"><td colspan="4" class="subheader">'
         . __('Dados do Projeto', 'projectplus') . '</td></tr>';
      self::row(
         'projectplus-project-data',
         __('Cesta Portfólio', 'projectplus'),
         PortfolioBasket::dropdown([
            'name'    => 'plugin_projectplus_portfoliobaskets_id',
            'value'   => (int) ($data['plugin_projectplus_portfoliobaskets_id'] ?? 0),
            'display' => false,
         ]),
         __('Ação PDTIC', 'projectplus'),
         PdticAction::dropdown([
            'name'    => 'plugin_projectplus_pdticactions_id',
            'value'   => (int) ($data['plugin_projectplus_pdticactions_id'] ?? 0),
            'display' => false,
         ])
      );
   }

   private static function renderRequesterRowsV2(array $data, int $entityId): void
   {
      echo '<tr class="projectplus-requester-data"><td colspan="4" class="subheader">'
         . __('Dados do solicitante (Cliente)', 'projectplus') . '</td></tr>';
      self::row(
         'projectplus-requester-data',
         __('Área do Solicitante', 'projectplus'),
         Html::input('requester_area', [
            'value'       => $data['requester_area'],
            'placeholder' => __('Pró-reitoria, campus, diretoria, departamento ou grande área', 'projectplus'),
         ]),
         __('Solicitante', 'projectplus'),
         self::userDropdown('requester_users_id', (int) ($data['requester_users_id'] ?? 0), $entityId)
      );
      self::row(
         'projectplus-requester-data',
         __('E-mail do Solicitante', 'projectplus'),
         Html::input('requester_email', [
            'value'    => $data['requester_email'],
            'type'     => 'email',
            'readonly' => true,
         ]),
         __('Ponto focal', 'projectplus'),
         self::userDropdown('focal_point_users_id', (int) ($data['focal_point_users_id'] ?? 0), $entityId)
      );
      self::row(
         'projectplus-requester-data',
         __('E-mail do Ponto focal', 'projectplus'),
         Html::input('focal_point_email', [
            'value'    => $data['focal_point_email'],
            'type'     => 'email',
            'readonly' => true,
         ]),
         '',
         ''
      );
   }

   private static function userDropdown(string $name, int $value, int $entityId): string
   {
      return User::dropdown([
         'name'          => $name,
         'value'         => $value,
         'entity'        => $entityId,
         'right'         => 'all',
         'display'       => false,
         'specific_tags' => [
            'title' => __('Selecione um usuário ativo da entidade do projeto.', 'projectplus'),
         ],
      ]);
   }

   private static function renderRequesterRows(array $data): void
   {
      echo '<tr class="projectplus-requester-data"><td colspan="4" class="subheader">'
         . __('Dados do solicitante (Cliente)', 'projectplus') . '</td></tr>';
      self::row(
         'projectplus-requester-data',
         __('Área do Solicitante', 'projectplus'),
         Html::input('requester_area', [
            'value'       => $data['requester_area'],
            'placeholder' => __('Pró-reitoria, campus, diretoria, departamento ou grande área', 'projectplus'),
         ]),
         __('Nome do Solicitante', 'projectplus'),
         Html::input('requester_name', ['value' => $data['requester_name']])
      );
      self::row(
         'projectplus-requester-data',
         __('E-mail do Solicitante', 'projectplus'),
         Html::input('requester_email', ['value' => $data['requester_email'], 'type' => 'email']),
         __('Ponto focal', 'projectplus'),
         Html::input('focal_point', [
            'value' => $data['focal_point'],
            'title' => __('Indique um ponto focal para acompanhar tecnicamente a execução do projeto.', 'projectplus'),
         ])
      );
      self::row(
         'projectplus-requester-data',
         __('E-mail do Ponto focal', 'projectplus'),
         Html::input('focal_point_email', ['value' => $data['focal_point_email'], 'type' => 'email']),
         '',
         ''
      );
   }

   private static function renderPlanningRows(array $data): void
   {
      self::row(
         'projectplus-planning-data',
         __('Objetivo', 'projectplus'),
         self::nativeTextarea('objective', $data['objective']),
         __('Produto/Resultado esperado', 'projectplus'),
         self::nativeTextarea('expected_result', $data['expected_result'])
      );
   }

   private static function row(string $class, string $firstLabel, string $firstField, string $secondLabel, string $secondField): void
   {
      echo '<tr class="tab_bg_1 ' . $class . '">';
      echo '<td>' . $firstLabel . '</td><td>' . $firstField . '</td>';
      echo '<td>' . $secondLabel . '</td><td>' . $secondField . '</td>';
      echo '</tr>';
   }

   private static function nativeTextarea(string $name, string $value): string
   {
      return '<textarea id="' . Html::cleanInputText($name) . '" name="'
         . Html::cleanInputText($name) . '" cols="90" rows="6">'
         . Html::cleanInputText($value) . '</textarea>';
   }
}
