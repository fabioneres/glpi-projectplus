<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use Entity;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Para onde cada entidade pode transferir projetos.
 *
 * Uma linha por par origem-destino. A regra vale para a **entidade exata**: nao
 * ha heranca para as filhas. Com heranca, a regra efetiva de uma entidade
 * passaria a depender de onde ela esta na arvore, e a flag recursiva ja rendeu
 * duas falhas de isolamento neste plugin (AP-018, AP-019) - a decisao foi
 * manter explicito.
 *
 * **Sem regra cadastrada, a entidade nao transfere para lugar nenhum.** E o
 * padrao fechado, escolhido deliberadamente: quem nao foi configurado nao
 * transfere, em vez de transferir para qualquer lugar. Isso significa que, na
 * atualizacao, toda transferencia para de funcionar ate alguem cadastrar as
 * regras - a tela avisa isso em destaque.
 */
class TransferRule extends CommonDBTM
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_transferrules';
   }

   /**
    * Destinos permitidos para uma entidade de origem.
    *
    * @return array<int, int> ids das entidades de destino
    */
   public static function getAllowedTargets(int $originEntityId): array
   {
      /** @var \DBmysql $DB */
      global $DB;

      if ($originEntityId < 0 || !$DB->tableExists(self::getTable())) {
         return [];
      }
      $out = [];
      foreach ($DB->request([
         'SELECT' => 'target_entities_id',
         'FROM' => self::getTable(),
         'WHERE' => ['entities_id' => $originEntityId],
      ]) as $row) {
         $out[] = (int) $row['target_entities_id'];
      }
      return $out;
   }

   /** O destino e permitido, e a sessao alcanca as duas pontas? */
   public static function isAllowed(int $originEntityId, int $targetEntityId): bool
   {
      if ($targetEntityId <= 0 || $targetEntityId === $originEntityId) {
         return false;
      }
      if (!in_array($targetEntityId, self::getAllowedTargets($originEntityId), true)) {
         return false;
      }
      // A regra diz o que a operacao permite; a sessao ainda precisa alcancar
      // a entidade. Uma nao substitui a outra.
      return Session::haveAccessToEntity($targetEntityId);
   }

   /**
    * Todas as regras, agrupadas pela origem, para a tela de configuracao.
    *
    * @return array<int, array<int, int>> origem => ids de destino
    */
   public static function getRulesByOrigin(): array
   {
      /** @var \DBmysql $DB */
      global $DB;

      if (!$DB->tableExists(self::getTable())) {
         return [];
      }
      $out = [];
      foreach ($DB->request([
         'FROM' => self::getTable(),
         'ORDER' => ['entities_id ASC', 'target_entities_id ASC'],
      ]) as $row) {
         $out[(int) $row['entities_id']][] = (int) $row['target_entities_id'];
      }
      return $out;
   }

   /**
    * Substitui os destinos de uma origem pelos informados.
    *
    * @param array<int, mixed> $targets
    */
   public static function saveForOrigin(int $originEntityId, array $targets): void
   {
      /** @var \DBmysql $DB */
      global $DB;

      if ($originEntityId < 0) {
         throw new \RuntimeException(__('Selecione uma entidade de origem válida.', 'projectplus'));
      }
      self::assertEntityReachable($originEntityId, __('Você não possui acesso à entidade de origem informada.', 'projectplus'));

      $limpos = [];
      foreach ($targets as $target) {
         $target = (int) $target;
         // A raiz fica de fora: no dropdown do GLPI a opcao vazia tambem vale 0,
         // entao ela nunca chega a ser um destino selecionavel de verdade.
         if ($target <= 0 || $target === $originEntityId) {
            continue;
         }
         self::assertEntityReachable($target, __('Você não possui acesso a uma das entidades de destino informadas.', 'projectplus'));
         $limpos[$target] = $target;
      }

      $DB->delete(self::getTable(), ['entities_id' => $originEntityId]);
      foreach ($limpos as $target) {
         $DB->insert(self::getTable(), [
            'entities_id' => $originEntityId,
            'target_entities_id' => $target,
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
         ]);
      }
   }

   /** Remove a regra inteira de uma origem. */
   public static function removeOrigin(int $originEntityId): void
   {
      /** @var \DBmysql $DB */
      global $DB;

      if ($originEntityId < 0) {
         return;
      }
      self::assertEntityReachable($originEntityId, __('Você não possui acesso à entidade de origem informada.', 'projectplus'));
      $DB->delete(self::getTable(), ['entities_id' => $originEntityId]);
   }

   /**
    * Direito de configuracao nao substitui alcance de entidade.
    *
    * Mesmo padrao de LA-086 e AP-019: um perfil administrativo restrito a uma
    * subentidade nao deve gravar regra sobre entidade que ele nao alcanca.
    */
   private static function assertEntityReachable(int $entityId, string $message): void
   {
      if (!Session::haveAccessToEntity($entityId)) {
         throw new \RuntimeException($message);
      }
   }

   /**
    * Destinos permitidos e alcancaveis, com o caminho completo, prontos para
    * o seletor da tela de transferencia.
    *
    * @return array<int, string> id => nome completo, em ordem alfabetica
    */
   public static function getAllowedTargetNames(int $originEntityId): array
   {
      /** @var \DBmysql $DB */
      global $DB;

      $permitidos = array_values(array_diff(
         self::getAllowedTargets($originEntityId),
         [$originEntityId, 0]
      ));
      if ($permitidos === []) {
         return [];
      }
      $out = [];
      foreach ($DB->request([
         'SELECT' => ['id', 'completename', 'name'],
         'FROM' => Entity::getTable(),
         'WHERE' => ['id' => $permitidos],
         'ORDER' => ['completename ASC'],
      ]) as $row) {
         $id = (int) $row['id'];
         // A regra diz o que a operacao permite; a sessao ainda precisa
         // alcancar a entidade. Uma nao substitui a outra.
         if (!Session::haveAccessToEntity($id)) {
            continue;
         }
         $nome = trim((string) $row['completename']);
         $out[$id] = $nome !== '' ? $nome : (string) $row['name'];
      }
      return $out;
   }

   /**
    * Entidades que podem ser marcadas como destino de uma origem.
    *
    * Fora a propria origem e a raiz - a raiz porque, no dropdown do GLPI, a
    * opcao vazia tambem vale 0 e as duas ficariam indistinguiveis na tela de
    * transferencia.
    *
    * @return array<int, string> id => nome completo, em ordem de arvore
    */
   public static function selectableEntities(int $originEntityId): array
   {
      /** @var \DBmysql $DB */
      global $DB;

      $out = [];
      foreach ($DB->request([
         'SELECT' => ['id', 'completename', 'name'],
         'FROM' => Entity::getTable(),
         'WHERE' => ['NOT' => ['id' => [0, $originEntityId]]],
         'ORDER' => ['completename ASC'],
      ]) as $row) {
         $id = (int) $row['id'];
         if (!Session::haveAccessToEntity($id)) {
            continue;
         }
         $nome = trim((string) $row['completename']);
         $out[$id] = $nome !== '' ? $nome : (string) $row['name'];
      }
      return $out;
   }

   public static function entityName(int $entityId): string
   {
      $entity = new Entity();
      if (!$entity->getFromDB($entityId)) {
         return sprintf(__('Entidade %d (removida)', 'projectplus'), $entityId);
      }
      $nome = trim((string) ($entity->fields['completename'] ?? ''));
      return $nome !== '' ? $nome : (string) ($entity->fields['name'] ?? $entityId);
   }
}
