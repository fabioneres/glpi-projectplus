<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use CommonITILActor;
use Entity;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

if (!defined('GLPI_ROOT')) { die("Sorry. You can't access directly to this file"); }

/** Explicit, administrator-configured source for document field suggestions. */
class FieldSource
{
   private const ORDER = ['pretip', 'tip', 'tap', 'tep', 'ta'];

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
      foreach (array_slice(self::ORDER, 0, $targetPosition) as $sourceType) {
         $fields = in_array($sourceType, ['pretip', 'tip'], true)
            ? TipField::getForEntity($entityId, $sourceType)
            : DocumentField::get($entityId, $sourceType, true);
         $sourceLabel = self::documentLabel($sourceType);
         foreach ($fields as $field) {
            if ((string) $field['field_type'] !== 'section') {
               $options['document:' . $sourceType . ':' . (int) $field['id']]
                  = $sourceLabel . ' › ' . (string) $field['label'];
            }
         }
      }
      return $options;
   }

   /** @param array<int,array<string,mixed>> $fields @return array<int,array{label:string,value:string}> */
   public static function getPrefill(CommonDBTM $item, string $targetType, array $fields): array
   {
      $values = [];
      foreach ($fields as $field) {
         if ((string) $field['field_type'] === 'section') {
            continue;
         }
         $value = self::getValue($item, (string) ($field['source'] ?? ''));
         if ($value !== '') {
            $values[(int) $field['id']] = ['label' => (string) $field['label'], 'value' => $value];
         }
      }
      return $values;
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
      if (!preg_match('/^document:(pretip|tip|tap|tep):([1-9][0-9]*)$/', $source, $matches)) {
         return '';
      }
      $type = $matches[1];
      $fieldId = (int) $matches[2];
      if (in_array($type, ['pretip', 'tip'], true)) {
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
      return ['pretip' => 'TIP', 'tip' => 'TIP - DT', 'tap' => 'TAP', 'tep' => 'TEP', 'ta' => 'TA'][$type] ?? strtoupper($type);
   }
}
