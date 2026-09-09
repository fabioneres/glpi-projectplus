<?php

namespace GlpiPlugin\Projectplus;

use DBmysql;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class TipConfig
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_tipconfigs';
   }

   /** @return array<string, mixed> */
   public static function getForEntity(int $entityId, string $documentType = 'tip'): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $documentType = self::normalizeDocumentType($documentType);
      $defaults = self::getDefaults($documentType);
      $default = [
         'id' => 0,
         'entities_id' => $entityId,
         'document_type' => $documentType,
         'is_active' => 1,
         'title' => $defaults['title'],
         'introduction' => $defaults['introduction'],
      ];
      if (!$DB->tableExists(self::getTable())) {
         return $default;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $entityId, 'document_type' => $documentType],
         'LIMIT' => 1,
      ])->current();
      return $row ? array_merge($default, $row) : $default;
   }

   public static function isActiveForEntity(int $entityId, string $documentType = 'tip'): bool
   {
      return (int) self::getForEntity($entityId, $documentType)['is_active'] === 1;
   }

   public static function saveForEntity(int $entityId, string $documentType, array $input): bool
   {
      /** @var DBmysql $DB */
      global $DB;

      if ($entityId < 0 || !Session::haveAccessToEntity($entityId, true)) {
         return false;
      }
      $documentType = self::normalizeDocumentType($documentType);
      $title = trim((string) ($input['title'] ?? ''));
      if ($title === '') {
         return false;
      }
      $now = date('Y-m-d H:i:s');
      $data = [
         'is_active' => (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0,
         'title' => substr($title, 0, 255),
         'introduction' => trim((string) ($input['introduction'] ?? '')),
         'users_id' => (int) Session::getLoginUserID(),
         'date_mod' => $now,
      ];
      $current = self::getForEntity($entityId, $documentType);
      if ((int) $current['id'] > 0) {
         return (bool) $DB->update(self::getTable(), $data, ['id' => (int) $current['id']]);
      }
      return (bool) $DB->insert(self::getTable(), $data + [
         'entities_id' => $entityId,
         'document_type' => $documentType,
         'date_creation' => $now,
      ]);
   }

   private static function normalizeDocumentType(string $documentType): string
   {
      return in_array($documentType, ['tip', 'tip_dt', 'tap', 'tep', 'ta'], true)
         ? $documentType
         : TipDocumentType::TIP;
   }

   /** @return array{title:string,introduction:string} */
   private static function getDefaults(string $documentType): array
   {
      $defaults = [
         'tip' => [
            'title' => __('TIP - Termo de Intenção de Projeto', 'projectplus'),
            'introduction' => __('Preencha as informações para análise da demanda de intenção de projeto.', 'projectplus'),
         ],
         'tip_dt' => [
            'title' => __('TIP - Detalhamento Técnico', 'projectplus'),
            'introduction' => __('Preencha as informações técnicas para detalhamento do projeto.', 'projectplus'),
         ],
         'tap' => [
            'title' => __('TAP - Termo de Abertura do Projeto', 'projectplus'),
            'introduction' => __('Preencha as informações necessárias para formalizar a abertura do projeto.', 'projectplus'),
         ],
         'tep' => [
            'title' => __('TEP - Relatório de Entrega do Projeto', 'projectplus'),
            'introduction' => __('Registre as informações de entrega e encerramento do projeto.', 'projectplus'),
         ],
         'ta' => [
            'title' => __('TA - Termo de Aceite', 'projectplus'),
            'introduction' => __('Registre as informações necessárias para o aceite do projeto.', 'projectplus'),
         ],
      ];
      return $defaults[$documentType];
   }
}
