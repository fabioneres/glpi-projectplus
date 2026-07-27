<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TipField
{
   private const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'section'];

   private const DEFAULTS = [
      ['executor_area', 'Área que pretende executar o projeto', 'Informe a área da STI responsável pela execução do projeto.', 'text', 1],
      ['requester_name', 'Nome do solicitante', 'Informe o nome completo do solicitante.', 'text', 1],
      ['requester_area', 'Área solicitante', 'Informe a área responsável pela demanda.', 'text', 1],
      ['project_name', 'Nome do projeto', 'Escolha um nome claro e objetivo para o projeto.', 'text', 1],
      ['information', 'Informações adicionais', '', 'section', 0],
      ['project_description', 'Descrição do projeto', 'Explique o que o projeto pretende realizar e qual necessidade busca resolver.', 'textarea', 1],
      ['specific_objective', 'Objetivo específico', 'Defina o resultado concreto desejado, preferencialmente no modelo SMART.', 'textarea', 1],
      ['metrics', 'Métricas', 'Indique como será possível medir o sucesso do projeto.', 'textarea', 1],
      ['relevance', 'Motivo/Relevância', 'Descreva a importância do projeto para a área solicitante.', 'textarea', 1],
      ['benefits', 'Benefícios', 'Liste os ganhos esperados com a realização do projeto.', 'textarea', 1],
      ['resources', 'Lista dos prováveis recursos envolvidos', 'Informe os membros da equipe que participarão da execução, caso seja aprovado.', 'textarea', 1],
      ['observations', 'Observações', 'Campo livre para informações complementares.', 'textarea', 0],
   ];

   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_tipfields';
   }

   /** @return array<string, string> */
   public static function getTypes(): array
   {
      return [
         'text' => __('Texto curto', 'projectplus'),
         'textarea' => __('Texto longo', 'projectplus'),
         'number' => __('Número', 'projectplus'),
         'date' => __('Data', 'projectplus'),
         'select' => __('Lista suspensa', 'projectplus'),
         'section' => __('Título de seção', 'projectplus'),
      ];
   }

   public static function ensureDefaults(int $entityId): void
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!$DB->tableExists(self::getTable()) || $DB->request([
         'COUNT' => 'count', 'FROM' => self::getTable(), 'WHERE' => ['entities_id' => $entityId],
      ])->current()['count'] > 0) {
         return;
      }
      $now = date('Y-m-d H:i:s');
      foreach (self::DEFAULTS as $index => $field) {
         $DB->insert(self::getTable(), [
            'entities_id' => $entityId,
            'name' => $field[0],
            'label' => $field[1],
            'description' => $field[2],
            'field_type' => $field[3],
            'is_required' => $field[4],
            'is_active' => 1,
            'sort_order' => ($index + 1) * 10,
            'date_creation' => $now,
            'date_mod' => $now,
         ]);
      }
   }

   /** @return array<int, array<string, mixed>> */
   public static function getForEntity(int $entityId, bool $activeOnly = true): array
   {
      /** @var DBmysql $DB */
      global $DB;

      self::ensureDefaults($entityId);
      $where = ['entities_id' => $entityId];
      if ($activeOnly) {
         $where['is_active'] = 1;
      }
      return iterator_to_array($DB->request([
         'FROM' => self::getTable(),
         'WHERE' => $where,
         'ORDER' => ['sort_order ASC', 'id ASC'],
      ]), false);
   }

   /** @return array<string, mixed>|null */
   public static function getById(int $id, int $entityId): ?array
   {
      /** @var DBmysql $DB */
      global $DB;
      $row = $DB->request([
         'FROM' => self::getTable(), 'WHERE' => ['id' => $id, 'entities_id' => $entityId], 'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function save(int $entityId, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if (!Session::haveAccessToEntity($entityId, true)) {
         return false;
      }
      $type = (string) ($input['field_type'] ?? 'text');
      $label = trim((string) ($input['label'] ?? ''));
      if ($label === '' || !in_array($type, self::TYPES, true)) {
         return false;
      }
      $now = date('Y-m-d H:i:s');
      $data = [
         'label' => substr($label, 0, 255),
         'description' => trim((string) ($input['description'] ?? '')),
         'field_type' => $type,
         'values' => trim((string) ($input['values'] ?? '')),
         'is_required' => $type === 'section' ? 0 : ((int) ($input['is_required'] ?? 0) === 1 ? 1 : 0),
         'is_active' => (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0,
         'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
         'date_mod' => $now,
      ];
      $id = (int) ($input['id'] ?? 0);
      if ($id > 0 && self::getById($id, $entityId) !== null) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => $id]);
      }
      $data += [
         'entities_id' => $entityId,
         'name' => '',
         'date_creation' => $now,
      ];
      if (!$DB->insert(self::getTable(), $data)) {
         return false;
      }
      $id = (int) $DB->insertId();
      return (bool) $DB->update(self::getTable(), ['name' => 'field_' . $id], ['id' => $id]);
   }

   public static function delete(int $id, int $entityId): bool
   {
      /** @var DBmysql $DB */
      global $DB;
      if (!Session::haveAccessToEntity($entityId, true) || self::getById($id, $entityId) === null) {
         return false;
      }
      return (bool) $DB->delete(self::getTable(), ['id' => $id]);
   }
}
