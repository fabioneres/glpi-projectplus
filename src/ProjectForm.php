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
      echo Html::scriptBlock(<<<'JAVASCRIPT'
$(function () {
   var source = $('#projectplus-fields');
   var target = $('select[name="projecttypes_id"]').closest('table.tab_cadre_fixe');
   if (!source.length || !target.length) {
      return;
   }

   var projectRows = source.find('.projectplus-project-data').detach();
   var projectHeader = source.find('.projectplus-project-header').detach();
   var requesterRows = source.find('.projectplus-requester-data').detach();
   var planningRows = source.find('.projectplus-planning-data').detach();

   var creationRow = target.find('input[name="date"]').closest('tr');
   var typeRow = target.find('select[name="projecttypes_id"]').closest('tr');
   var managerRow = target.find('select[name="groups_id"]').closest('tr');
   var descriptionRow = target.find('textarea[name="content"]').closest('tr');
   var commentRow = target.find('textarea[name="comment"]').closest('tr');

   if (creationRow.length) {
      creationRow.before(projectHeader);
   } else if (typeRow.length) {
      typeRow.before(projectHeader);
   }
   if (typeRow.length) {
      typeRow.after(projectRows);
   }
   if (managerRow.length) {
      managerRow.after(requesterRows);
   }
   if (descriptionRow.length && commentRow.length && planningRows.length) {
      var pluginCells = planningRows.children().detach();
      var descriptionLabel = descriptionRow.children().eq(0).detach();
      var descriptionField = descriptionRow.children().eq(0).detach();
      var commentLabel = commentRow.children().eq(0).detach();
      var commentField = commentRow.children().eq(0).detach();
      var objectiveLabel = pluginCells.eq(0);
      var objectiveField = pluginCells.eq(1);
      var expectedResultLabel = pluginCells.eq(2);
      var expectedResultField = pluginCells.eq(3);

      descriptionField.attr('colspan', 1).find('textarea').css('width', '100%');
      commentField.attr('colspan', 1).find('textarea').css('width', '100%');
      objectiveField.find('textarea').css('width', '100%');
      expectedResultField.find('textarea').css('width', '100%');

      $('<tr class="tab_bg_1 projectplus-native-textareas"></tr>')
         .append(descriptionLabel, descriptionField, objectiveLabel, objectiveField)
         .insertBefore(descriptionRow);
      $('<tr class="tab_bg_1 projectplus-native-textareas"></tr>')
         .append(commentLabel, commentField, expectedResultLabel, expectedResultField)
         .insertBefore(descriptionRow);
      descriptionRow.remove();
      commentRow.remove();
   } else if (descriptionRow.length) {
      descriptionRow.before(planningRows);
   }
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
