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
      $documentType = self::documentType($documentType);
      if (!TicketConfig::isEnabledForTicket($ticket)
         || !TipConfig::isActiveForEntity((int) $ticket->getEntityID(), $documentType)
         || !Session::haveRight(ProjectProfile::getIntentRequestRight($documentType), UPDATE)) {
         return false;
      }

      $phase = TicketPhase::getForTicket((int) $ticket->getID());
      if ((string) ($phase['phase'] ?? '') !== 'classification') {
         return false;
      }
      if ($documentType === TipDocumentType::DETAIL) {
         $tip = self::getForTicket((int) $ticket->getID(), TipDocumentType::TIP);
         return $tip !== null && (string) $tip['status'] === 'accepted';
      }
      return true;
   }

   public static function canSubmitForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      $documentType = self::documentType($documentType);
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      if (!$intent || !in_array((string) $intent['status'], ['requested', 'returned'], true)
         || !TicketConfig::isEnabledForTicket($ticket) || !TipConfig::isActiveForEntity((int) $ticket->getEntityID(), $documentType)) {
         return false;
      }

      $right = $documentType === TipDocumentType::TIP
         ? ProjectProfile::RIGHT_FILL_PRETIP
         : ProjectProfile::RIGHT_FILL_TIP;
      if (!Session::haveRight($right, UPDATE)) {
         return false;
      }

      // TIP is completed by the requester. TIP-DT is an internal technical
      // detail completed by a manager who can update the native ticket.
      if ($documentType === TipDocumentType::TIP) {
         return self::isRequester($ticket, (int) Session::getLoginUserID());
      }
      return true;
   }

   public static function canReviewForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      // O TIP-DT é um documento técnico interno: depois de gerado, é apenas
      // disponibilizado para consulta e não passa por aceite ou recusa.
      if (self::documentType($documentType) === TipDocumentType::DETAIL) {
         return false;
      }
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      return $intent !== null && (string) $intent['status'] === 'submitted'
         && TicketConfig::isEnabledForTicket($ticket)
         && Session::haveRight(ProjectProfile::getIntentValidationRight(self::documentType($documentType)), UPDATE);
   }

   public static function canViewForTicket(Ticket $ticket, string $documentType = 'tip'): bool
   {
      $documentType = self::documentType($documentType);
      // The Project+ right authorizes the feature; the ticket itself still
      // decides who may read this document. Without this, the right alone
      // would expose the TIP of any ticket in the entity.
      if (!ItemAccess::canView($ticket)) {
         return false;
      }
      // The "Ler" column is the persistent consultation permission for both
      // TIP documents. It must be enough for requesters to consult a document
      // after it has been submitted, accepted or rejected.
      if (Session::haveRight(ProjectProfile::getIntentRequestRight($documentType), READ)) {
         return true;
      }

      // A manager who has only the validation action still needs temporary
      // access to the submitted document in order to accept or reject it.
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      return $intent !== null
         && (string) $intent['status'] === 'submitted'
         && self::canReviewForTicket($ticket, $documentType);
   }

   public static function requestForTicket(Ticket $ticket, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      TicketPhase::assertCurrentPhase(
         $ticket,
         'classification',
         __('Conclua a etapa atual antes de solicitar o próximo documento.', 'projectplus')
      );
      if ($documentType === TipDocumentType::DETAIL) {
         $tip = self::getForTicket((int) $ticket->getID(), TipDocumentType::TIP);
         if (!$tip || (string) $tip['status'] !== 'accepted') {
            throw new \RuntimeException(__('O TIP deve ser preenchido e aceito antes de liberar o TIP - Detalhamento Técnico.', 'projectplus'));
         }
      }
      TicketRouting::assertActor(
         $ticket,
         'project',
         $documentType === TipDocumentType::TIP ? ProjectProfile::RIGHT_REQUEST_PRETIP : ProjectProfile::RIGHT_REQUEST_TIP
      );
      $name = self::documentName($documentType);
      if (!self::canRequestForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para solicitar este %s.', 'projectplus'), $name));
      }
      if (self::getForTicket((int) $ticket->getID(), $documentType) !== null) {
         throw new \RuntimeException(sprintf(__('Já existe um %s solicitado ou enviado para este chamado.', 'projectplus'), $name));
      }
      TipField::ensureDefaults((int) $ticket->getEntityID(), $documentType);
      global $DB;
      $now = date('Y-m-d H:i:s');
      $DB->insert(self::getTable(), [
         'tickets_id' => (int) $ticket->getID(), 'document_type' => $documentType,
         'entities_id' => (int) $ticket->getEntityID(), 'status' => 'requested',
         'requested_users_id' => (int) Session::getLoginUserID(),
         'date_requested' => $now, 'date_creation' => $now, 'date_mod' => $now,
      ]);
      if ($documentType === TipDocumentType::DETAIL) {
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'requested'), __('TIP liberado para preenchimento pelo Gestor de Portfólio.', 'projectplus'));
      } else {
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'requested'), sprintf(__('%s solicitado ao solicitante.', 'projectplus'), $name));
      }
   }

   /** @param array<string,mixed> $input */
   public static function saveForTicket(Ticket $ticket, array $input, string $documentType = 'tip', bool $generate = false): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      TicketPhase::assertCurrentPhase(
         $ticket,
         $documentType === TipDocumentType::TIP ? 'awaiting_pretip' : 'awaiting_tip',
         __('Este documento ainda não está liberado para preenchimento nesta etapa do fluxo.', 'projectplus')
      );
      if ($documentType === TipDocumentType::DETAIL) {
         TicketRouting::assertActor($ticket, 'project', ProjectProfile::RIGHT_FILL_TIP);
      }
      if ($documentType === TipDocumentType::DETAIL && !$generate) {
         throw new \RuntimeException(__('O TIP - Detalhamento Técnico deve ser gerado para ser disponibilizado.', 'projectplus'));
      }
      if (!self::canSubmitForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para enviar este %s.', 'projectplus'), $name));
      }
      if ($documentType === TipDocumentType::DETAIL && !Session::haveRight(ProjectProfile::RIGHT_GENERATE_TIP, UPDATE)) {
         throw new \RuntimeException(__('Você não possui permissão para gerar o TIP - Detalhamento Técnico.', 'projectplus'));
      }
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      if ($intent === null) {
         throw new \RuntimeException(sprintf(__('O %s precisa ser solicitado pela área responsável antes do preenchimento.', 'projectplus'), $name));
      }
      $fields = TipField::getForEntity((int) $ticket->getEntityID(), $documentType);
      $answers = is_array($input['tip_fields'] ?? null) ? $input['tip_fields'] : [];
      $validated = [];
      foreach ($fields as $field) {
         if (FieldValue::isDisplayOnly((string) $field['field_type'])) {
            continue;
         }
         $value = FieldValue::normalize($field, $answers[(int) $field['id']] ?? '');
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
      $status = $documentType === TipDocumentType::DETAIL && $generate ? 'generated' : 'submitted';
      $DB->update(self::getTable(), [
         'status' => $status, 'users_id' => $userId, 'submitter_name' => User::getFriendlyNameById($userId),
         'submitter_email' => $email, 'date_submitted' => $now, 'date_mod' => $now,
      ], ['id' => (int) $intent['id']]);
      if ($documentType === TipDocumentType::DETAIL) {
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'generated'), __('TIP - Detalhamento Técnico gerado e disponibilizado para consulta.', 'projectplus'));
      } else {
         TicketPhase::setPhase($ticket, self::phaseFor($documentType, 'submitted'), sprintf(__('%s preenchido e enviado para validação do gestor.', 'projectplus'), $name));
      }
   }

   public static function acceptForTicket(Ticket $ticket, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      TicketPhase::assertCurrentPhase(
         $ticket,
         'awaiting_pretip_review',
         __('O TIP ainda não está disponível para validação.', 'projectplus')
      );
      if ($documentType === TipDocumentType::DETAIL) {
         throw new \RuntimeException(__('O TIP - Detalhamento Técnico não exige aceite.', 'projectplus'));
      }
      TicketRouting::assertActor($ticket, 'project', ProjectProfile::RIGHT_VALIDATE_PRETIP);
      if (!self::canReviewForTicket($ticket, $documentType)) {
         throw new \RuntimeException(sprintf(__('Você não possui permissão para validar este %s.', 'projectplus'), $name));
      }
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      $now = date('Y-m-d H:i:s');
      global $DB;
      $DB->update(self::getTable(), ['status' => 'accepted', 'review_users_id' => (int) Session::getLoginUserID(), 'review_justification' => '', 'date_reviewed' => $now, 'date_mod' => $now], ['id' => (int) $intent['id']]);
      TicketPhase::setPhase($ticket, 'classification', sprintf(__('%s aceito pelo gestor.', 'projectplus'), $name));
   }

   public static function rejectForTicket(Ticket $ticket, string $justification, string $documentType = 'tip'): void
   {
      $documentType = self::documentType($documentType);
      $name = self::documentName($documentType);
      TicketPhase::assertCurrentPhase(
         $ticket,
         'awaiting_pretip_review',
         __('O TIP ainda não está disponível para validação.', 'projectplus')
      );
      if ($documentType === TipDocumentType::DETAIL) {
         throw new \RuntimeException(__('O TIP - Detalhamento Técnico não pode ser recusado.', 'projectplus'));
      }
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
      if ($documentType === TipDocumentType::DETAIL) {
         $tip = self::getForTicket((int) $ticket->getID(), TipDocumentType::TIP);
         if ($tip === null || (string) $tip['status'] !== 'accepted') {
            echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>"
               . self::escape(__('TIP - Detalhamento Técnico ainda não liberado.', 'projectplus'))
               . '</strong><br>'
               . self::escape(FlowStageDescription::get('tip_dt', 'pending'))
               . '</div>';
            return;
         }
      }
      if ($intent !== null && (string) $intent['status'] === 'generated') {
         if (self::canViewForTicket($ticket, $documentType)) {
            self::showGeneratedReadOnly($ticket, $documentType);
         }
      } elseif ($intent !== null && (string) $intent['status'] === 'submitted') {
         if (self::canReviewForTicket($ticket, $documentType)) {
            self::showReviewForm($ticket, $documentType);
         } elseif (self::canViewForTicket($ticket, $documentType)) {
            self::showSubmittedReadOnly($ticket, $documentType);
         } else {
            echo "<div class='alert alert-info'>" . self::escape(__('O documento foi enviado e aguarda validação.', 'projectplus')) . '</div>';
         }
      } elseif ($intent !== null && (string) $intent['status'] === 'accepted') {
         echo "<div class='alert alert-success'><i class='ti ti-circle-check'></i> " . self::escape(sprintf(__('%s aceito pelo gestor. A solicitação está disponível para classificação da necessidade.', 'projectplus'), self::documentName($documentType))) . '</div>';
         if (self::canViewForTicket($ticket, $documentType)) {
            self::showSubmittedReadOnly($ticket, $documentType);
         }
      } elseif ($intent !== null && (string) $intent['status'] === 'rejected') {
         echo "<div class='alert alert-danger'><i class='ti ti-circle-x'></i> " . self::escape(sprintf(__('%s recusado. O chamado foi solucionado com a justificativa informada.', 'projectplus'), self::documentName($documentType))) . '</div>';
         if (self::canViewForTicket($ticket, $documentType)) {
            self::showSubmittedReadOnly($ticket, $documentType);
         }
      } elseif ($intent !== null && self::canSubmitForTicket($ticket, $documentType)) {
         self::showForm($ticket, $documentType);
      } elseif ($intent !== null) {
         $message = $documentType === TipDocumentType::DETAIL
            ? __('O TIP foi liberado e aguarda o preenchimento pelo Gestor de Portfólio.', 'projectplus')
            : sprintf(__('O %s foi solicitado e aguarda o preenchimento pelo solicitante.', 'projectplus'), self::documentName($documentType));
         echo "<div class='alert alert-info'>" . self::escape($message) . '</div>';
      } elseif (self::canRequestForTicket($ticket, $documentType)) {
         self::showRequestButton($ticket, $documentType);
      } else {
         echo "<div class='alert alert-info'>" . self::escape(FlowStageDescription::get($documentType === TipDocumentType::DETAIL ? 'tip_dt' : 'tip', 'pending')) . '</div>';
      }
   }

   private static function showRequestButton(Ticket $ticket, string $documentType): void
   {
      $name = self::documentName($documentType);
      $isTip = $documentType === TipDocumentType::DETAIL;
      $message = FlowStageDescription::get($isTip ? 'tip_dt' : 'tip');
      $button = $isTip ? __('Preencher Detalhamento Técnico', 'projectplus') : sprintf(__('Solicitar preenchimento do %s', 'projectplus'), $name);
      echo "<div class='center'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]) . Html::hidden('document_type', ['value' => $documentType]);
      echo "<div class='alert alert-info'>" . self::escape($message) . "</div><button class='btn btn-primary' type='submit' name='request_intent' value='1'>" . self::escape($button) . '</button></form></div>';
   }

   private static function showForm(Ticket $ticket, string $documentType): void
   {
      $config = TipConfig::getForEntity((int) $ticket->getEntityID(), $documentType);
      $userId = (int) Session::getLoginUserID();
      $requesterId = self::getRequesterId($ticket);
      $intent = self::getForTicket((int) $ticket->getID(), $documentType);
      // For TIP-DT, the identification must reflect the manager who released
      // the document for completion, rather than the requester of the ticket.
      $releasedBy = (int) ($intent['requested_users_id'] ?? 0);
      $displayUserId = $documentType === TipDocumentType::DETAIL
         ? ($releasedBy > 0 ? $releasedBy : $userId)
         : ($requesterId > 0 ? $requesterId : $userId);
      $name = self::documentName($documentType);
      $title = (string) $config['title'];
      echo "<div class='projectplus-tip'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) . Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]) . Html::hidden('document_type', ['value' => $documentType]);
      echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-file-text'></i></div><div><span class='projectplus-tip__eyebrow'>" . self::escape(__('Solicitação de projeto', 'projectplus')) . "</span><h2>" . self::escape($title) . '</h2><p>' . nl2br(self::escape((string) $config['introduction'])) . '</p></div></header>';
      echo "<section class='projectplus-tip__card'><div class='projectplus-tip__section-title'><i class='ti ti-info-circle'></i><div><h3>" . self::escape(__('Identificação do preenchimento', 'projectplus')) . '</h3><p>' . self::escape(__('Confira os dados antes de enviar o formulário.', 'projectplus')) . '</p></div></div><div class="projectplus-tip__summary"><div><span>' . self::escape(__('Ticket', 'projectplus')) . '</span><strong>#' . (int) $ticket->getID() . '</strong></div><div><span>' . self::escape(__('Nome de quem preenche', 'projectplus')) . '</span><strong>' . self::escape(User::getFriendlyNameById($displayUserId)) . '</strong></div><div><span>' . self::escape(__('E-mail de quem preenche', 'projectplus')) . '</span><strong>' . self::escape(UserEmail::getDefaultForUser($displayUserId)) . '</strong></div></div></section>';
      echo "<section class='projectplus-tip__card projectplus-tip__fields'><div class='projectplus-tip__grid'>";
      foreach (TipField::getForEntity((int) $ticket->getEntityID(), $documentType) as $field) {
         self::showField($field, FieldSource::getValue($ticket, (string) ($field['source'] ?? '')));
      }
      $isTechnicalDetail = $documentType === TipDocumentType::DETAIL;
      $action = $isTechnicalDetail ? 'generate_intent' : 'save_intent';
      $button = $isTechnicalDetail ? __('Gerar TIP - Detalhamento Técnico', 'projectplus') : sprintf(__('Enviar %s para análise', 'projectplus'), $name);
      $icon = $isTechnicalDetail ? 'ti ti-file-export' : 'ti ti-send';
      echo "</div></section><footer class='projectplus-tip__footer'><span><i class='ti ti-asterisk'></i> " . self::escape(__('Campos obrigatórios', 'projectplus')) . "</span><button class='btn btn-primary' type='submit' name='" . self::escape($action) . "' value='1'><i class='" . self::escape($icon) . "'></i> " . self::escape($button) . '</button></footer></form></div>';
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

   private static function isRequester(Ticket $ticket, int $userId): bool
   {
      if ($userId <= 0) {
         return false;
      }
      global $DB;
      return (int) ($DB->request([
         'COUNT' => 'count',
         'FROM' => Ticket_User::getTable(),
         'WHERE' => [
            'tickets_id' => (int) $ticket->getID(),
            'users_id' => $userId,
            'type' => CommonITILActor::REQUESTER,
         ],
      ])->current()['count'] ?? 0) > 0;
   }

   /** @param array<string,mixed> $field */
   private static function showField(array $field, string $value): void
   {
      $id = (int) $field['id']; $type = (string) $field['field_type'];
      if ($type === 'section') { echo "</div><div class='projectplus-tip__section-title projectplus-tip__section-title--divider'><i class='ti ti-list-details'></i><div><h3>" . self::escape((string) $field['label']) . '</h3></div></div><div class="projectplus-tip__grid">'; return; }
      if ($type === 'info') { echo "<div class='projectplus-tip__field projectplus-tip__field--wide'>" . FieldValue::render('tip_fields', $field, '') . '</div>'; return; }
      $required = (int) $field['is_required'] === 1; $name = 'tip_fields[' . $id . ']';
      $help = (string) ($field['description'] ?? '');
      $helpIcon = $help !== '' ? " <i class='ti ti-help-circle text-muted' title='" . self::escape($help) . "' aria-label='" . self::escape(__('Ajuda', 'projectplus')) . "'></i>" : '';
      echo "<div class='projectplus-tip__field" . ($type === 'textarea' ? ' projectplus-tip__field--wide' : '') . "'><label for='tip-field-" . $id . "'>" . self::escape((string) $field['label'] . ($required ? ' *' : '')) . $helpIcon . '</label>' . ($help !== '' ? '<small>' . self::escape($help) . '</small>' : '') . '<div>';
      echo FieldValue::render('tip_fields', $field, $value);
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

   private static function showGeneratedReadOnly(Ticket $ticket, string $documentType): void
   {
      global $CFG_GLPI;
      $name = self::documentName($documentType);
      $pdfUrl = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.pdf.php?tickets_id=' . (int) $ticket->getID() . '&document_type=' . rawurlencode($documentType);
      echo "<section class='projectplus-tip__card projectplus-acceptance__card'><div class='alert alert-success'><i class='ti ti-circle-check'></i> "
         . self::escape(sprintf(__('%s gerado e disponível para consulta pelos atores autorizados.', 'projectplus'), $name))
         . "</div><div class='projectplus-acceptance__document'><div><strong>" . self::escape($name)
         . "</strong><a target='_blank' rel='noopener' href='" . self::escape($pdfUrl) . "'>"
         . self::escape(__('Abrir documento', 'projectplus')) . "</a></div><iframe title='Documento gerado' src='"
         . self::escape($pdfUrl) . "'></iframe></div></section>";
   }

   private static function documentType(string $documentType): string { return $documentType === TipDocumentType::DETAIL ? TipDocumentType::DETAIL : TipDocumentType::TIP; }
   private static function documentName(string $documentType): string { return $documentType === TipDocumentType::TIP ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : __('TIP - Detalhamento Técnico', 'projectplus'); }
   private static function documentLongName(string $documentType): string { return $documentType === TipDocumentType::TIP ? __('TIP - Termo de Intenção de Projeto', 'projectplus') : __('TIP - Detalhamento Técnico', 'projectplus'); }
   private static function phaseFor(string $documentType, string $status): string { return $documentType === TipDocumentType::TIP ? ($status === 'requested' ? 'awaiting_pretip' : 'awaiting_pretip_review') : ($status === 'requested' ? 'awaiting_tip' : 'portfolio_analysis'); }
   private static function getActionUrl(): string { global $CFG_GLPI; return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.form.php'; }
   private static function text($value): string { $value = trim((string) $value); if (strlen($value) > 65535) { throw new \RuntimeException(__('Um dos campos do documento excede o tamanho permitido.', 'projectplus')); } return $value; }
   private static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
