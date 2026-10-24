<?php

namespace GlpiPlugin\Projectplus;

use Glpi\RichText\RichText;
use CommonDBTM;
use CommonITILActor;
use Entity;
use Html;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }

/** Explicit, administrator-configured source for document field suggestions. */
class FieldSource
{
   private const ORDER = ['tip', 'tip_dt', 'tap', 'tep', 'ta', 'project'];

   /** @return array<string,string> */
   public static function getOptions(int $entityId, string $targetType): array
   {
      $options = ['' => __('Preenchimento manual', 'projectplus')];
      foreach (self::ticketOptions() as $key => $label) {
         $options['ticket:' . $key] = __('Chamado', 'projectplus') . ' › ' . $label;
      }

      $targetPosition = array_search($targetType, self::ORDER, true);
      if ($targetPosition === false) {
         return $options;
      }
      $sourceTypes = $targetType === 'project'
         ? ['tip', 'tip_dt', 'tap']
         : array_slice(self::ORDER, 0, $targetPosition);
      foreach ($sourceTypes as $sourceType) {
         $fields = in_array($sourceType, ['tip', 'tip_dt'], true)
            ? TipField::getForEntity($entityId, $sourceType)
            : DocumentField::get($entityId, $sourceType, true);
         $sourceLabel = self::documentLabel($sourceType);
         foreach ($fields as $field) {
            $fieldType = (string) $field['field_type'];
            // Anexo nao alimenta outro campo: o valor gravado e uma lista de ids
            // de documentos, que nao faz sentido copiar para um campo de texto.
            if ($fieldType !== 'section' && !FieldValue::isFileType($fieldType)) {
               $options['document:' . $sourceType . ':' . (int) $field['id']]
                  = $sourceLabel . ' › ' . (string) $field['label'];
            }
         }
      }
      return $options;
   }

   /** @return array<string,string> Document fields available to populate a Project. */
   public static function getProjectDocumentOptions(int $entityId): array
   {
      $options = ['' => __('Preenchimento manual', 'projectplus')];
      foreach (self::getOptions($entityId, 'project') as $key => $label) {
         if (strpos((string) $key, 'document:') === 0) {
            $options[(string) $key] = $label;
         }
      }
      return $options;
   }

   /** Resets mappings that point to a field removed from a document model. */
   public static function clearFieldReferences(string $documentType, int $fieldId): void
   {
      global $DB;
      if ($fieldId <= 0 || !in_array($documentType, ['tip', 'tip_dt', 'tap', 'tep', 'ta'], true)) {
         return;
      }
      $source = 'document:' . $documentType . ':' . $fieldId;
      foreach ([TipField::getTable(), DocumentField::getTable(), ProjectFieldConfig::getTable()] as $table) {
         if ($DB->tableExists($table) && $DB->fieldExists($table, 'source')) {
            if (!$DB->update($table, ['source' => ''], ['source' => $source])) {
               throw new \RuntimeException(__('Não foi possível limpar as referências do campo.', 'projectplus'));
            }
         }
      }
   }

   /** @param array<int,array<string,mixed>> $fields @return array<int,array{label:string,value:string}> */
   public static function getPrefill(CommonDBTM $item, string $targetType, array $fields): array
   {
      $values = [];
      foreach ($fields as $field) {
         $fieldType = (string) $field['field_type'];
         if ($fieldType === 'section' || FieldValue::isFileType($fieldType)) {
            continue;
         }
         $value = self::getPrefillValue($item, (string) ($field['source'] ?? ''));
         if ($value !== '') {
            $values[(int) $field['id']] = ['label' => (string) $field['label'], 'value' => $value];
         }
      }
      return $values;
   }

   /**
    * Valor para pre-preencher um campo de formulario: igual a getValue(), mas a descricao do
    * chamado (HTML do editor de texto) vira texto puro, porque o campo e texto e as tags e
    * entidades apareciam literais para o solicitante. O Projeto converte por conta propria.
    */
   public static function getPrefillValue(CommonDBTM $item, string $source): string
   {
      $value = self::getValue($item, $source);
      if ($value !== '' && $source === 'ticket:content') {
         $value = trim(RichText::getTextFromHtml($value, false, false, false, false, true));
      }
      return $value;
   }

   /** @param array<string,mixed> $field */
   public static function getValue(CommonDBTM $item, string $source): string
   {
      if ($source === '') {
         return '';
      }
      $ticket = self::getTicket($item);
      if (!$ticket) {
         return '';
      }
      if (strpos($source, 'ticket:') === 0) {
         return self::getTicketValue($ticket, substr($source, 7));
      }
      if (!preg_match('/^document:(tip|tip_dt|tap|tep):([1-9][0-9]*)$/', $source, $matches)) {
         return '';
      }
      $type = $matches[1];
      $fieldId = (int) $matches[2];
      if (in_array($type, ['tip', 'tip_dt'], true)) {
         $intent = TicketIntent::getForTicket((int) $ticket->getID(), $type);
         if (!$intent) {
            return '';
         }
         foreach (TicketIntentAnswer::getForIntent((int) $intent['id']) as $answer) {
            if ((int) $answer['plugin_projectplus_tipfields_id'] === $fieldId) {
               return trim((string) $answer['value']);
            }
         }
         return '';
      }

      $sourceItem = $type === 'tap' ? $ticket : $item;
      $draft = DocumentDraft::get($sourceItem::getType(), (int) $sourceItem->getID(), $type);
      $answers = $draft ? (json_decode((string) $draft['content'], true) ?: []) : [];
      return trim((string) ($answers[$fieldId]['value'] ?? ''));
   }

   /** @return array<string,string> */
   private static function ticketOptions(): array
   {
      return [
         'number'          => __('Número do chamado', 'projectplus'),
         'date'            => __('Data de abertura', 'projectplus'),
         'requester_name'  => __('Requerente', 'projectplus'),
         'requester_email' => __('E-mail do requerente', 'projectplus'),
         'name'            => __('Título', 'projectplus'),
         'content'         => __('Descrição', 'projectplus'),
         'category'        => __('Categoria ITIL', 'projectplus'),
         'entity'          => __('Entidade', 'projectplus'),
      ];
   }

   private static function getTicket(CommonDBTM $item): ?Ticket
   {
      if ($item instanceof Ticket) {
         return $item;
      }
      $ticketId = TicketProject::getTicketIdForProject((int) $item->getID());
      if ($ticketId <= 0) {
         return null;
      }
      $ticket = new Ticket();
      return $ticket->getFromDB($ticketId) ? $ticket : null;
   }

   private static function getTicketValue(Ticket $ticket, string $key): string
   {
      if ($key === 'name' || $key === 'content') {
         return trim((string) ($ticket->fields[$key] ?? ''));
      }
      if ($key === 'number') {
         return (string) (int) $ticket->getID();
      }
      if ($key === 'date') {
         // 'date' e a data de abertura do chamado; 'date_creation' registra a
         // criacao do registro e pode divergir em importacoes.
         $openingDate = trim((string) ($ticket->fields['date'] ?? ''));
         return $openingDate === '' ? '' : Html::convDateTime($openingDate);
      }
      if ($key === 'category') {
         return \ITILCategory::getFriendlyNameById((int) ($ticket->fields['itilcategories_id'] ?? 0));
      }
      if ($key === 'entity') {
         return Entity::getFriendlyNameById((int) $ticket->getEntityID());
      }
      $requester = $GLOBALS['DB']->request([
         'SELECT' => ['users_id'], 'FROM' => Ticket_User::getTable(),
         'WHERE' => ['tickets_id' => (int) $ticket->getID(), 'type' => CommonITILActor::REQUESTER], 'LIMIT' => 1,
      ])->current();
      $userId = (int) ($requester['users_id'] ?? 0);
      return $key === 'requester_email' ? UserEmail::getDefaultForUser($userId) : User::getFriendlyNameById($userId);
   }

   private static function documentLabel(string $type): string
   {
      return ['tip' => 'TIP', 'tip_dt' => 'TIP - DT', 'tap' => 'TAP', 'tep' => 'TEP', 'ta' => 'TA'][$type] ?? strtoupper($type);
   }
}
