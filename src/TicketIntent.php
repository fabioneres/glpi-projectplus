<?php

namespace GlpiPlugin\Projectplus;

use CommonITILActor;
use DBmysql;
use Dropdown;
use Html;
use ITILFollowup;
use Session;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Controls configurable pre-intent and intent forms attached to a Ticket. */
class TicketIntent
{
   public static function getTable($classname = null): string
   {
      return 'glpi_plugin_projectplus_ticketintents';
   }

   /** @return array<string, mixed>|null */
   public static function getForTicket(int $ticketId, string $documentType = 'tip'): ?array
   {
      global $DB;
      if ($ticketId <= 0 || !$DB->tableExists(self::getTable())) {
         return null;
      }
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['tickets_id' => $ticketId, 'document_type' => self::documentType($documentType)],
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function canRequestForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      return TicketConfig::isEnabledForTicket($ticket)
         && TipConfig::isActiveForEntity((int) $ticket->getEntityID())
         && Session::haveRight('ticket', UPDATE)
         && Session::haveRight(ProjectProfile::getIntentRequestRight(self::documentType($documentType)), UPDATE);
   }

   public static function canSubmitForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      $documentType = self::documentType($documentType);
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      if (!$intent || !in_array((string) $intent['status'], ['requested', 'returned'], true)
         || !TicketConfig::isEnabledForTicket($ticket) || !TipConfig::isActiveForEntity((int) $ticket->getEntityID())) {
         return false;
      }

      // Both TIP steps are internal portfolio-management records. The
      // requester consults their generated content but does not edit it.
      $right = $documentType === 'pretip'
         ? ProjectProfile::RIGHT_FILL_PRETIP
         : ProjectProfile::RIGHT_FILL_TIP;
      $hasRight = Session::haveRight($right, UPDATE)
         || ($documentType === 'pretip' && Session::haveRight(ProjectProfile::RIGHT_FILL_TIP, UPDATE));
      return Session::haveRight('ticket', UPDATE) && $hasRight;
   }

   public static function canReviewForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      return $intent !== null && (string) $intent['status'] === 'submitted'
         && TicketConfig::isEnabledForTicket($ticket) && Session::haveRight('ticket', UPDATE)
         && Session::haveRight(ProjectProfile::getIntentValidationRight(self::documentType($documentType)), UPDATE);
   }

   public static function canViewForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      $documentType = self::documentType($documentType);
      if (Session::haveRight(ProjectProfile::getIntentRequestRight($documentType), READ)) {
         return true;
      }
      $userId = (int) Session::getLoginUserID();
      global $DB;
      return $userId > 0 && (int) ($DB->request([
         'COUNT' => 'count', 'FROM' => Ticket_User::getTable(),
         'WHERE' => ['tickets_id' => (int) $ticket->getID(), 'users_id' => $userId, 'type' => CommonITILActor::REQUESTER],
      ])->current()['count'] ?? 0) > 0;
   }

   public static function requestForTicket(Ticket $ticket, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      if (!self::canRequestForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para solicitar este %s.', 'projectplus'), $name));
      }
      if (self::getForTicket((int) $ticket->getID(), $documentType) !== null) {
         throw new \RuntimeException(sprintf(__('Já existe um %s solicitado ou enviado para este chamado.', 'projectplus'), $name));
      }
      TipField::ensureDefaults((int) $ticket->getEntityID());
      global $DB;
      $now = date('Y-m-d H:i:s');
      $DB->insert(self::getTable(), [
         'tickets_id' => (int) $ticket->getID(), 'document_type' => $documentType,
         'entities_id' => (int) $ticket->getEntityID(), 'status' => 'requested',
         'requested_users_id' => (int) Session::getLoginUserID(),
         'date_requested' => $now, 'date_creation' => $now, 'date_mod' => $now,
      ]);
      if ($documentType === 'tip') {
         self::followup($ticket, __('O preenchimento do TIP foi liberado para o Gestor de Portfólio.', 'projectplus'));
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'requested'), __('TIP liberado para preenchimento pelo Gestor de Portfólio.', 'projectplus'));
      } else {
         self::followup($ticket, sprintf(__('O preenchimento do %s foi solicitado. Acesse a aba Project+ para preencher as informações.', 'projectplus'), self::documentLongName($documentType)));
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'requested'), sprintf(__('%s solicitado ao solicitante.', 'projectplus'), $name));
      }
   }

   /** @param array<string,mixed> $input */
   public static function saveForTicket(Ticket $ticket, array $input, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      if (!self::canSubmitForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para enviar este %s.', 'projectplus'), $name));
      }
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      if ($intent === null) {
         throw new \RuntimeException(sprintf(__('O %s precisa ser solicitado pela área responsável antes do preenchimento.', 'projectplus'), $name));
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
            throw new \RuntimeException(sprintf(__('Preencha todos os campos obrigatórios do %s.', 'projectplus'), $name));
         }
         $validated[] = ['field' => $field, 'value' => $value];
      }
      $userId = (int) Session::getLoginUserID();
      $email = UserEmail::getDefaultForUser($userId);
      if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
         throw new \RuntimeException(__('Seu usuário não possui e-mail válido cadastrado.', 'projectplus'));
      }
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
      if ($documentType === 'tip') {
         self::followup($ticket, __('TIP preenchido pelo Gestor de Portfólio e disponibilizado para consulta.', 'projectplus'), $userId);
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'submitted'), __('TIP preenchido pelo Gestor de Portfólio.', 'projectplus'));
      } else {
         self::followup($ticket, sprintf(__('%s preenchido e enviado para análise.', 'projectplus'), $name), $userId);
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'submitted'), sprintf(__('%s preenchido e enviado para validação do gestor.', 'projectplus'), $name));
      }
   }

   public static function acceptForTicket(Ticket $ticket, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      if (!self::canReviewForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para validar este %s.', 'projectplus'), $name));
      }
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      $now = date('Y-m-d H:i:s');
      global $DB;
      $DB->update(self::getTable(), ['status' => 'accepted', 'review_users_id' => (int) Session::getLoginUserID(), 'review_justification' => '', 'date_reviewed' => $now, 'date_mod' => $now], ['id' => (int) $intent['id']]);
      self::followup($ticket, sprintf(__('%s aceito pelo gestor e encaminhado para classificação da necessidade.', 'projectplus'), $name));
      TicketPhase::setPhase($ticket, 'classification', sprintf(__('%s aceito pelo gestor.', 'projectplus'), $name));
   }

   public static function rejectForTicket(Ticket $ticket, string $justification, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      if (!self::canReviewForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para validar este %s.', 'projectplus'), $name));
      }
      $justification = trim($justification);
      if ($justification === '') {
         throw new \RuntimeException(sprintf(__('Informe a justificativa para recusar o %s.', 'projectplus'), $name));
      }
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      $now = date('Y-m-d H:i:s');
      global $DB;
      $DB->update(self::getTable(), ['status' => 'rejected', 'review_users_id' => (int) Session::getLoginUserID(), 'review_justification' => $justification, 'date_reviewed' => $now, 'date_mod' => $now], ['id' => (int) $intent['id']]);
      $ticket->update(['id' => (int) $ticket->getID(), 'status' => Ticket::SOLVED, 'solution' => $justification]);
      TicketPhase::setPhase($ticket, 'rejected_cancelled', $justification);
   }

   public static function showForTicket(Ticket $ticket, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      if ($intent !== null && (string) $intent['status'] === 'submitted') {
         if (self::canReviewForTicket($ticket, $documentType)) {
            self::showReviewForm($ticket, $documentType);
         } else {
            self::showSubmittedReadOnly($ticket, $documentType);
         }
      } elseif ($intent !== null && (string) $intent['status'] === 'accepted') {
         echo "<div class='alert alert-success'><i class='ti ti-circle-check'></i> " . self::escape(sprintf(__('%s aceito pelo gestor. A solicitação está disponível para classificação da necessidade.', 'projectplus'), self::documentName($documentType))) . '</div>';
      } elseif ($intent !== null && (string) $intent['status'] === 'rejected') {
         echo "<div class='alert alert-danger'><i class='ti ti-circle-x'></i> " . self::escape(sprintf(__('%s recusado. O chamado foi solucionado com a justificativa informada.', 'projectplus'), self::documentName($documentType))) . '</div>';
      } elseif ($intent !== null && self::canSubmitForTicket($ticket, $documentType)) {
         self::showForm($ticket, $documentType);
      } elseif ($intent !== null) {
         $message = $documentType === 'tip'
            ? __('O TIP foi liberado e aguarda o preenchimento pelo Gestor de Portfólio.', 'projectplus')
            : sprintf(__('O %s foi solicitado e aguarda o preenchimento pelo solicitante.', 'projectplus'), self::documentName($documentType));
         echo "<div class='alert alert-info'>" . self::escape($message) . '</div>';
      } elseif (self::canRequestForTicket($ticket, $documentType)) {
         self::showRequestButton($ticket, $documentType);
      } else {
         echo "<div class='alert alert-info'>" . self::escape(sprintf(__('Aguardando a solicitação de preenchimento do %s pela área responsável.', 'projectplus'), self::documentName($documentType))) . '</div>';
      }
   }

   private static function showRequestButton(Ticket $ticket, string $documentType): void
   {
      $name = self::documentName($documentType);
      $isTip = $documentType === 'tip';
      $message = $isTip
         ? __('Ao liberar, o Gestor de Portfólio poderá preencher o TIP. O solicitante terá acesso somente para consulta após o preenchimento.', 'projectplus')
         : sprintf(__('Após solicitar, o solicitante receberá acesso ao formulário %s neste chamado.', 'projectplus'), $name);
      $button = $isTip ? __('Liberar preenchimento do TIP', 'projectplus') : sprintf(__('Solicitar preenchimento do %s', 'projectplus'), $name);
      echo "<div class='center'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]) . Html::hidden('document_type', ['value' => $documentType]);
      echo "<div class='alert alert-info'>" . self::escape($message) . "</div><button class='btn btn-primary' type='submit' name='request_intent' value='1'>" . self::escape($button) . '</button></form></div>';
   }

   private static function showForm(Ticket $ticket, string $documentType): void
   {
      $config = TipConfig::getForEntity((int) $ticket->getEntityID());
      $userId = (int) Session::getLoginUserID();
      $requesterId = self::getRequesterId($ticket);
      $displayUserId = $requesterId > 0 ? $requesterId : $userId;
      $name = self::documentName($documentType);
      $title = $documentType === 'pretip' ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : (string) $config['title'];
      echo "<div class='projectplus-tip'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]) . Html::hidden('document_type', ['value' => $documentType]);
      echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-text'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::escape(__('Solicitação de projeto', 'projectplus')) . "</span><h2>" . self::escape($title) . '</h2><p>' . nl2br(self::escape((string) $config['introduction'])) . '</p></div></header>';
      echo "<section class='projectplus-tip__card'><div class='projectplus-tip__section-title'><i class='ti ti-info-circle'></i><div><h3>" . self::escape(__('Identificação do preenchimento', 'projectplus')) . '</h3><p>' . self::escape(__('Confira os dados antes de enviar o formulário.', 'projectplus')) . '</p></div></div><div class="projectplus-tip__summary"><div><span>' . self::escape(__('Ticket', 'projectplus')) . '</span><strong>#' . (int) $ticket->getID() . '</strong></div><div><span>' . self::escape(__('Nome de quem preenche', 'projectplus')) . '</span><strong>' . self::escape(User::getFriendlyNameById($displayUserId)) . '</strong></div><div><span>' . self::escape(__('E-mail de quem preenche', 'projectplus')) . '</span><strong>' . self::escape(UserEmail::getDefaultForUser($displayUserId)) . '</strong></div></div></section>';
      echo "<section class='projectplus-tip__card projectplus-tip__fields'><div class='projectplus-tip__grid'>";
      foreach (TipField::getForEntity((int) $ticket->getEntityID()) as $field) {
         self::showField($field, (string) ($ticket->fields['name'] ?? ''));
      }
      echo "</div></section><footer class='projectplus-tip__footer'><span><i class='ti ti-asterisk'></i> " . self::escape(__('Campos obrigatórios', 'projectplus')) . "</span><button class='btn btn-primary' type='submit' name='save_intent' value='1'><i class='ti ti-send'></i> " . self::escape(sprintf(__('Enviar %s para análise', 'projectplus'), $name)) . '</button></footer></form></div>';
   }

   private static function getRequesterId(Ticket $ticket): int
   {
      global $DB;
      $row = $DB->request([
         'FROM'  => Ticket_User::getTable(),
         'WHERE' => [
            'tickets_id' => (int) $ticket->getID(),
            'type'       => CommonITILActor::REQUESTER,
         ],
         'LIMIT' => 1,
      ])->current();
      return (int) ($row['users_id'] ?? 0);
   }

   /** @param array<string,mixed> $field */
   private static function showField(array $field, string $ticketName): void
   {
      $id = (int) $field['id']; $type = (string) $field['field_type'];
      if ($type === 'section') { echo "</div><div class='projectplus-tip__section-title projectplus-tip__section-title--divider'><i class='ti ti-list-details'></i><div><h3>" . self::escape((string) $field['label']) . '</h3></div></div><div class="projectplus-tip__grid">'; return; }
      $required = (int) $field['is_required'] === 1; $name = 'tip_fields[' . $id . ']';
      $value = (string) $field['name'] === 'project_name' ? $ticketName : '';
      echo "<div class='projectplus-tip__field" . ($type === 'textarea' ? ' projectplus-tip__field--wide' : '') . "'><label for='tip-field-" . $id . "'>" . self::escape((string) $field['label'] . ($required ? ' *' : '')) . '</label>' . ((string) $field['description'] !== '' ? '<small>' . self::escape((string) $field['description']) . '</small>' : '') . '<div>';
      if ($type === 'textarea') { echo "<textarea id='tip-field-" . $id . "' class='form-control' name='" . self::escape($name) . "' rows='5'" . ($required ? ' required' : '') . '>' . self::escape($value) . '</textarea>'; }
      elseif ($type === 'select') { $values = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $field['values']))); Dropdown::showFromArray($name, array_combine($values, $values) ?: [], ['value' => $value, 'id' => 'tip-field-' . $id]); }
      else { $htmlType = in_array($type, ['number', 'date'], true) ? $type : 'text'; echo "<input id='tip-field-" . $id . "' class='form-control' type='" . $htmlType . "' name='" . self::escape($name) . "' value='" . self::escape($value) . "'" . ($required ? ' required' : '') . '>'; }
      echo '</div></div>';
   }

   private static function showReviewForm(Ticket $ticket, string $documentType): void
   {
      global $CFG_GLPI;
      $name = self::documentName($documentType);
      $url = self::getActionUrl();
      $pdfUrl = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.pdf.php?tickets_id=' . (int) $ticket->getID() . '&document_type=' . rawurlencode($documentType);
      $hidden = Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]) . Html::hidden('document_type', ['value' => $documentType]);
      echo "<section class='projectplus-tip__card projectplus-acceptance__card'><div class='projectplus-tip__section-title'><i class='ti ti-file-check'></i><div><h3>" . self::escape(sprintf(__('Validação do %s', 'projectplus'), $name)) . '</h3><p>' . self::escape(__('Revise o termo preenchido antes de aceitar ou recusar a solicitação.', 'projectplus')) . "</p></div></div><div class='projectplus-acceptance__document'><div><strong>" . self::escape($name) . " preenchido</strong><a target='_blank' rel='noopener' href='" . self::escape($pdfUrl) . "'>" . self::escape(__('Abrir documento', 'projectplus')) . "</a></div><iframe title='Documento preenchido' src='" . self::escape($pdfUrl) . "'></iframe></div>";
      echo "<form method='post' action='" . self::escape($url) . "' class='projectplus-tip__footer'>" . $hidden . "<button class='btn btn-success' type='submit' name='accept_intent' value='1'><i class='ti ti-check'></i> " . self::escape(sprintf(__('Aceitar %s', 'projectplus'), $name)) . "</button></form>";
      echo "<form method='post' action='" . self::escape($url) . "' class='projectplus-tip__review-refuse'>" . $hidden . '<label>' . self::escape(__('Justificativa da recusa', 'projectplus')) . "</label><textarea class='form-control' name='justification' rows='4' required></textarea><button class='btn btn-danger' type='submit' name='reject_intent' value='1'><i class='ti ti-x'></i> " . self::escape(sprintf(__('Recusar %s e solucionar chamado', 'projectplus'), $name)) . '</button></form></section>';
   }

   private static function showSubmittedReadOnly(Ticket $ticket, string $documentType): void
   {
      global $CFG_GLPI;
      $name = self::documentName($documentType);
      $pdfUrl = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.pdf.php?tickets_id=' . (int) $ticket->getID() . '&document_type=' . rawurlencode($documentType);
      echo "<section class='projectplus-tip__card projectplus-acceptance__card'><div class='alert alert-info'>"
         . self::escape(sprintf(__('%s preenchido e aguardando validação do gestor responsável.', 'projectplus'), $name))
         . "</div><div class='projectplus-acceptance__document'><div><strong>" . self::escape($name)
         . " preenchido</strong><a target='_blank' rel='noopener' href='" . self::escape($pdfUrl) . "'>"
         . self::escape(__('Abrir documento', 'projectplus')) . "</a></div><iframe title='Documento preenchido' src='"
         . self::escape($pdfUrl) . "'></iframe></div></section>";
   }

   private static function documentType(string $documentType): string { return $documentType === 'pretip' ? 'pretip' : 'tip'; }
   private static function documentName(string $documentType): string { return $documentType === 'pretip' ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : __('TIP - Detalhamento Técnico', 'projectplus'); }
   private static function documentLongName(string $documentType): string { return $documentType === 'pretip' ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : __('TIP - Detalhamento Técnico', 'projectplus'); }
   private static function phaseFor(string $documentType, string $status): string { return $documentType === 'pretip' ? ($status === 'requested' ? 'awaiting_pretip' : 'awaiting_pretip_review') : ($status === 'requested' ? 'awaiting_tip' : 'awaiting_tip_review'); }
   private static function getActionUrl(): string { global $CFG_GLPI; return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.form.php'; }
   private static function text($value): string { $value = trim((string) $value); if (strlen($value) > 65535) { throw new \RuntimeException(__('Um dos campos do documento excede o tamanho permitido.', 'projectplus')); } return $value; }
   private static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
   private static function followup(Ticket $ticket, string $content, ?int $userId = null): void { $followup = new ITILFollowup(); $followup->add(['itemtype' => Ticket::class, 'items_id' => (int) $ticket->getID(), 'users_id' => $userId ?? (int) Session::getLoginUserID(), 'content' => $content, 'is_private' => 0]); }
}
