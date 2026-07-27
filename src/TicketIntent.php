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
      echo "<div class='projectplus-tip'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-text'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::escape(__('Solicitação de projeto', 'projectplus')) . "</span><h2>" . self::escape((string) $config['title']) . '</h2><p>' . nl2br(self::escape((string) $config['introduction'])) . '</p></div></header>';
      echo "<section class='projectplus-tip__card'><div class='projectplus-tip__section-title'><i class='ti ti-info-circle'></i><div><h3>" . self::escape(__('Identificação do preenchimento', 'projectplus')) . '</h3><p>' . self::escape(__('Confira os dados antes de enviar o formulário.', 'projectplus')) . '</p></div></div>';
      echo "<div class='projectplus-tip__summary'><div><span>" . self::escape(__('Ticket', 'projectplus')) . '</span><strong>#' . (int) $ticket->getID() . '</strong></div><div><span>' . self::escape(__('Nome de quem preenche', 'projectplus')) . '</span><strong>' . self::escape(User::getFriendlyNameById($userId)) . '</strong></div><div><span>' . self::escape(__('E-mail de quem preenche', 'projectplus')) . '</span><strong>' . self::escape(UserEmail::getDefaultForUser($userId)) . '</strong></div></div></section>';
      echo "<section class='projectplus-tip__card projectplus-tip__fields'><div class='projectplus-tip__grid'>";
      foreach (TipField::getForEntity((int) $ticket->getEntityID()) as $field) {
         self::showField($field, (string) ($ticket->fields['name'] ?? ''));
      }
      echo "</div></section><footer class='projectplus-tip__footer'><span><i class='ti ti-asterisk'></i> " . self::escape(__('Campos obrigatórios', 'projectplus')) . "</span><button class='btn btn-primary' type='submit' name='save_tip' value='1'><i class='ti ti-send'></i> " . self::escape(__('Enviar TIP para análise', 'projectplus')) . '</button></footer></form></div>';
   }

   /** @param array<string,mixed> $field */
   private static function showField(array $field, string $ticketName): void
   {
      $id = (int) $field['id']; $type = (string) $field['field_type'];
      if ($type === 'section') {
         echo "</div><div class='projectplus-tip__section-title projectplus-tip__section-title--divider'><i class='ti ti-list-details'></i><div><h3>" . self::escape((string) $field['label']) . '</h3></div></div><div class="projectplus-tip__grid">';
         return;
      }
      $required = (int) $field['is_required'] === 1;
      $label = (string) $field['label'] . ($required ? ' *' : '');
      $name = 'tip_fields[' . $id . ']';
      $value = (string) $field['name'] === 'project_name' ? $ticketName : '';
      echo "<div class='projectplus-tip__field" . ($type === 'textarea' ? ' projectplus-tip__field--wide' : '') . "'><label for='tip-field-" . $id . "'>" . self::escape($label) . '</label>' . ((string) $field['description'] !== '' ? "<small>" . self::escape((string) $field['description']) . '</small>' : '') . '<div>';
      if ($type === 'textarea') {
         echo "<textarea id='tip-field-" . $id . "' class='form-control' name='" . self::escape($name) . "' rows='5'" . ($required ? ' required' : '') . '>' . self::escape($value) . '</textarea>';
      } elseif ($type === 'select') {
         $values = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $field['values'])));
         Dropdown::showFromArray($name, array_combine($values, $values) ?: [], ['value' => $value, 'id' => 'tip-field-' . $id]);
      } else {
         $htmlType = in_array($type, ['number', 'date'], true) ? $type : 'text';
         echo "<input id='tip-field-" . $id . "' class='form-control' type='" . $htmlType . "' name='" . self::escape($name) . "' value='" . self::escape($value) . "'" . ($required ? ' required' : '') . '>';
      }
      echo '</div></div>';
   }

   /** @param array<string,mixed> $intent */
   private static function showSubmitted(Ticket $ticket, array $intent): void
   {
      echo "<div class='projectplus-tip projectplus-tip--submitted'><header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-check'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::escape(__('Solicitação de projeto', 'projectplus')) . '</span><h2>' . self::escape(__('Termo de Intenção do Projeto - TIP', 'projectplus')) . '</h2><p>' . self::escape(__('Formulário preenchido pelo solicitante e disponível para análise.', 'projectplus')) . '</p></div></header>';
      echo "<section class='projectplus-tip__card'><div class='projectplus-tip__section-title'><i class='ti ti-circle-check'></i><div><h3>" . self::escape(__('TIP enviado', 'projectplus')) . '</h3><p>' . self::escape(__('Dados de identificação e registro do envio.', 'projectplus')) . '</p></div></div>';
      echo "<div class='projectplus-tip__summary'><div><span>" . self::escape(__('Ticket', 'projectplus')) . '</span><strong>#' . (int) $ticket->getID() . '</strong></div><div><span>' . self::escape(__('Enviado em', 'projectplus')) . '</span><strong>' . self::escape((string) $intent['date_submitted']) . '</strong></div><div><span>' . self::escape(__('Enviado por', 'projectplus')) . '</span><strong>' . self::escape((string) $intent['submitter_name']) . '</strong><em>' . self::escape((string) $intent['submitter_email']) . '</em></div></div></section>';
      echo "<section class='projectplus-tip__card projectplus-tip__answers'><div class='projectplus-tip__section-title'><i class='ti ti-clipboard-text'></i><div><h3>" . self::escape(__('Respostas do formulário', 'projectplus')) . '</h3><p>' . self::escape(__('Informações declaradas no Termo de Intenção do Projeto.', 'projectplus')) . '</p></div></div><div class="projectplus-tip__answer-list">';
      foreach (TicketIntentAnswer::getForIntent((int) $intent['id']) as $answer) {
         echo "<article class='projectplus-tip__answer'><h4>" . self::escape((string) $answer['label']) . '</h4><div>' . nl2br(self::escape((string) $answer['value'])) . '</div></article>';
      }
      echo '</div></section></div>';
   }

   private static function readonlyRow(string $label, string $value): void { echo "<tr class='tab_bg_1'><td>" . self::escape($label) . '</td><td>' . self::escape($value) . '</td></tr>'; }
   private static function textRow(string $label, string $value): void { echo "<tr class='tab_bg_1'><td>" . self::escape($label) . '</td><td>' . nl2br(self::escape($value)) . '</td></tr>'; }
   private static function getActionUrl(): string { global $CFG_GLPI; return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.form.php'; }
   private static function text($value): string { $value = trim((string) $value); if (strlen($value) > 65535) { throw new \RuntimeException(__('Um dos campos do TIP excede o tamanho permitido.', 'projectplus')); } return $value; }
   private static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
