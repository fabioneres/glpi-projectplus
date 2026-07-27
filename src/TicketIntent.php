<?php

namespace GlpiPlugin\Projectplus;

use CommonITILActor;
use DBmysql;
use Dropdown;
use Html;
use Session;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Controls the request, submission and consultation of a configurable TIP. */
class TicketIntent
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketintents';
   }

   /** @return array<string, mixed>|null */
   public static function getForTicket(int $ticketId): ?array
   {
      /** @var DBmysql $DB */
      global $DB;
      if ($ticketId <= 0 || !$DB->tableExists(self::getTable())) {
         return null;
      }
      $row = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['tickets_id' => $ticketId], 'LIMIT' => 1])->current();
      return $row ?: null;
   }

   public static function canRequestForTicket(Ticket $ticket): bool
   {
      return TicketConfig::isEnabledForTicket($ticket)
         && TipConfig::isActiveForEntity((int) $ticket->getEntityID())
         && Session::haveRight('ticket', UPDATE);
   }

   public static function canSubmitForTicket(Ticket $ticket): bool
   {
      $intent = self::getForTicket((int) $ticket->getID());
      if (!$intent || !in_array((string) $intent['status'], ['requested', 'returned'], true)
         || !TicketConfig::isEnabledForTicket($ticket) || !TipConfig::isActiveForEntity((int) $ticket->getEntityID())) {
         return false;
      }
      $userId = (int) Session::getLoginUserID();
      if ($userId <= 0) {
         return false;
      }
      /** @var DBmysql $DB */
      global $DB;
      return (int) ($DB->request([
         'COUNT' => 'count', 'FROM' => Ticket_User::getTable(),
         'WHERE' => ['tickets_id' => (int) $ticket->getID(), 'users_id' => $userId, 'type' => CommonITILActor::REQUESTER],
      ])->current()['count'] ?? 0) > 0;
   }

   public static function requestForTicket(Ticket $ticket): void
   {
      if (!self::canRequestForTicket($ticket)) {
         throw new \RuntimeException(__('Você não possui permissão para solicitar este TIP.', 'projectplus'));
      }
      if (self::getForTicket((int) $ticket->getID()) !== null) {
         throw new \RuntimeException(__('Já existe um TIP solicitado ou enviado para este chamado.', 'projectplus'));
      }
      TipField::ensureDefaults((int) $ticket->getEntityID());
      /** @var DBmysql $DB */
      global $DB;
      $now = date('Y-m-d H:i:s');
      $DB->insert(self::getTable(), [
         'tickets_id' => (int) $ticket->getID(), 'entities_id' => (int) $ticket->getEntityID(),
         'status' => 'requested', 'requested_users_id' => (int) Session::getLoginUserID(),
         'date_requested' => $now, 'date_creation' => $now, 'date_mod' => $now,
      ]);
      TicketPhase::setPhase($ticket, 'awaiting_tip', __('TIP solicitado ao solicitante.', 'projectplus'));
   }

   public static function saveForTicket(Ticket $ticket, array $input): void
   {
      if (!self::canSubmitForTicket($ticket)) {
         throw new \RuntimeException(__('Você não possui permissão para enviar este TIP.', 'projectplus'));
      }
      $intent = self::getForTicket((int) $ticket->getID());
      if ($intent === null) {
         throw new \RuntimeException(__('O TIP precisa ser solicitado pela área responsável antes do preenchimento.', 'projectplus'));
      }
      $fields = TipField::getForEntity((int) $ticket->getEntityID());
      $answers = is_array($input['tip_fields'] ?? null) ? $input['tip_fields'] : [];
      $validated = [];
      foreach ($fields as $field) {
         if ((string) $field['field_type'] === 'section') {
            continue;
         }
         $value = self::text($answers[(int) $field['id']] ?? '');
         if ((int) $field['is_required'] === 1 && $value === '') {
            throw new \RuntimeException(__('Preencha todos os campos obrigatórios do TIP.', 'projectplus'));
         }
         $validated[] = ['field' => $field, 'value' => $value];
      }
      $userId = (int) Session::getLoginUserID();
      $email = UserEmail::getDefaultForUser($userId);
      if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
         throw new \RuntimeException(__('Seu usuário não possui e-mail válido cadastrado.', 'projectplus'));
      }
      /** @var DBmysql $DB */
      global $DB;
      $now = date('Y-m-d H:i:s');
      $DB->delete(TicketIntentAnswer::getTable(), ['plugin_projectplus_ticketintents_id' => (int) $intent['id']]);
      foreach ($validated as $answer) {
         $DB->insert(TicketIntentAnswer::getTable(), [
            'plugin_projectplus_ticketintents_id' => (int) $intent['id'],
            'plugin_projectplus_tipfields_id' => (int) $answer['field']['id'],
            'label' => (string) $answer['field']['label'], 'value' => $answer['value'], 'date_creation' => $now,
         ]);
      }
      $DB->update(self::getTable(), [
         'status' => 'submitted', 'users_id' => $userId, 'submitter_name' => User::getFriendlyNameById($userId),
         'submitter_email' => $email, 'date_submitted' => $now, 'date_mod' => $now,
      ], ['id' => (int) $intent['id']]);
      TicketPhase::setPhase($ticket, 'classification', __('TIP preenchido e enviado para análise.', 'projectplus'));
   }

   public static function showForTicket(Ticket $ticket): void
   {
      $intent = self::getForTicket((int) $ticket->getID());
      if ($intent !== null && (string) $intent['status'] === 'submitted') {
         self::showSubmitted($ticket, $intent);
      } elseif ($intent !== null && self::canSubmitForTicket($ticket)) {
         self::showForm($ticket);
      } elseif ($intent !== null) {
         echo "<div class='alert alert-info'>" . self::escape(__('O TIP foi solicitado e aguarda o preenchimento pelo solicitante.', 'projectplus')) . '</div>';
      } elseif (self::canRequestForTicket($ticket)) {
         self::showRequestButton($ticket);
      } else {
         echo "<div class='alert alert-info'>" . self::escape(__('Aguardando a solicitação de preenchimento do TIP pela área responsável.', 'projectplus')) . '</div>';
      }
   }

   private static function showRequestButton(Ticket $ticket): void
   {
      echo "<div class='center'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo "<div class='alert alert-info'>" . self::escape(__('Após solicitar, o solicitante receberá acesso ao formulário TIP neste chamado.', 'projectplus')) . '</div>';
      echo "<button class='btn btn-primary' type='submit' name='request_tip' value='1'>" . self::escape(__('Solicitar preenchimento do TIP', 'projectplus')) . '</button></form></div>';
   }

   private static function showForm(Ticket $ticket): void
   {
      $config = TipConfig::getForEntity((int) $ticket->getEntityID());
      $userId = (int) Session::getLoginUserID();
      echo "<div class='center'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'><th colspan='2'>" . self::escape((string) $config['title']) . '</th></tr>';
      echo "<tr class='tab_bg_1'><td colspan='2'>" . nl2br(self::escape((string) $config['introduction'])) . '</td></tr>';
      self::readonlyRow(__('Número do ticket', 'projectplus'), '#' . (int) $ticket->getID());
      self::readonlyRow(__('Nome de quem preenche', 'projectplus'), User::getFriendlyNameById($userId));
      self::readonlyRow(__('E-mail de quem preenche', 'projectplus'), UserEmail::getDefaultForUser($userId));
      foreach (TipField::getForEntity((int) $ticket->getEntityID()) as $field) {
         self::showField($field, (string) ($ticket->fields['name'] ?? ''));
      }
      echo "<tr class='tab_bg_2'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_tip' value='1'>" . self::escape(__('Enviar TIP para análise', 'projectplus')) . '</button></td></tr></table></form></div>';
   }

   /** @param array<string,mixed> $field */
   private static function showField(array $field, string $ticketName): void
   {
      $id = (int) $field['id']; $type = (string) $field['field_type'];
      if ($type === 'section') {
         echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape((string) $field['label']) . '</th></tr>';
         return;
      }
      $required = (int) $field['is_required'] === 1;
      $label = (string) $field['label'] . ($required ? ' *' : '');
      $name = 'tip_fields[' . $id . ']';
      $value = (string) $field['name'] === 'project_name' ? $ticketName : '';
      echo "<tr class='tab_bg_1'><td>" . self::escape($label) . ((string) $field['description'] !== '' ? '<br><small>' . self::escape((string) $field['description']) . '</small>' : '') . '</td><td>';
      if ($type === 'textarea') {
         echo "<textarea class='form-control' name='" . self::escape($name) . "' rows='4'" . ($required ? ' required' : '') . '>' . self::escape($value) . '</textarea>';
      } elseif ($type === 'select') {
         $values = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $field['values'])));
         Dropdown::showFromArray($name, array_combine($values, $values) ?: [], ['value' => $value]);
      } else {
         $htmlType = in_array($type, ['number', 'date'], true) ? $type : 'text';
         echo "<input class='form-control' type='" . $htmlType . "' name='" . self::escape($name) . "' value='" . self::escape($value) . "'" . ($required ? ' required' : '') . '>';
      }
      echo '</td></tr>';
   }

   /** @param array<string,mixed> $intent */
   private static function showSubmitted(Ticket $ticket, array $intent): void
   {
      echo "<div class='center'><table class='tab_cadre_fixe'><tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('TIP enviado', 'projectplus')) . '</th></tr>';
      self::readonlyRow(__('Ticket', 'projectplus'), '#' . (int) $ticket->getID());
      self::readonlyRow(__('Enviado por', 'projectplus'), (string) $intent['submitter_name'] . ' - ' . (string) $intent['submitter_email']);
      self::readonlyRow(__('Data de envio', 'projectplus'), (string) $intent['date_submitted']);
      foreach (TicketIntentAnswer::getForIntent((int) $intent['id']) as $answer) {
         self::textRow((string) $answer['label'], (string) $answer['value']);
      }
      echo '</table></div>';
   }

   private static function readonlyRow(string $label, string $value): void { echo "<tr class='tab_bg_1'><td>" . self::escape($label) . '</td><td>' . self::escape($value) . '</td></tr>'; }
   private static function textRow(string $label, string $value): void { echo "<tr class='tab_bg_1'><td>" . self::escape($label) . '</td><td>' . nl2br(self::escape($value)) . '</td></tr>'; }
   private static function getActionUrl(): string { global $CFG_GLPI; return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.form.php'; }
   private static function text($value): string { $value = trim((string) $value); if (strlen($value) > 65535) { throw new \RuntimeException(__('Um dos campos do TIP excede o tamanho permitido.', 'projectplus')); } return $value; }
   private static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
