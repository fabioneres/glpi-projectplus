<?php

namespace GlpiPlugin\Projectplus;

use CommonITILActor;
use DBmysql;
use Html;
use Session;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** TIP submitted by the requester for a project-request ticket. */
class TicketIntent
{
   private const REQUIRED_FIELDS = [
      'executor_area',
      'requester_name',
      'requester_area',
      'project_name',
      'project_description',
      'specific_objective',
      'metrics',
      'relevance',
      'benefits',
      'resources',
   ];

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
      $row = $DB->request([
         'FROM' => self::getTable(),
         'WHERE' => ['tickets_id' => $ticketId],
         'LIMIT' => 1,
      ])->current();
      return $row ?: null;
   }

   public static function canSubmitForTicket(Ticket $ticket): bool
   {
      if (!TicketConfig::isEnabledForTicket($ticket)) {
         return false;
      }
      $userId = (int) Session::getLoginUserID();
      if ($userId <= 0) {
         return false;
      }
      if (Session::haveRight('ticket', UPDATE)) {
         return true;
      }

      /** @var DBmysql $DB */
      global $DB;
      return $DB->request([
         'COUNT' => 'count',
         'FROM' => Ticket_User::getTable(),
         'WHERE' => [
            'tickets_id' => (int) $ticket->getID(),
            'users_id' => $userId,
            'type' => CommonITILActor::REQUESTER,
         ],
      ])->current()['count'] > 0;
   }

   public static function saveForTicket(Ticket $ticket, array $input): void
   {
      if (!self::canSubmitForTicket($ticket)) {
         throw new \RuntimeException(__('Você não possui permissão para enviar este TIP.', 'projectplus'));
      }
      if (self::getForTicket((int) $ticket->getID()) !== null) {
         throw new \RuntimeException(__('Este TIP já foi enviado e está disponível somente para consulta.', 'projectplus'));
      }

      $data = [];
      foreach (self::REQUIRED_FIELDS as $field) {
         $data[$field] = self::text($input[$field] ?? '');
         if ($data[$field] === '') {
            throw new \RuntimeException(__('Preencha todos os campos obrigatórios do TIP.', 'projectplus'));
         }
      }
      $data['observations'] = self::text($input['observations'] ?? '');

      $userId = (int) Session::getLoginUserID();
      $email = UserEmail::getDefaultForUser($userId);
      if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
         throw new \RuntimeException(__('Seu usuário não possui e-mail válido cadastrado.', 'projectplus'));
      }

      /** @var DBmysql $DB */
      global $DB;
      $now = date('Y-m-d H:i:s');
      $DB->insert(self::getTable(), array_merge($data, [
         'tickets_id' => (int) $ticket->getID(),
         'entities_id' => (int) $ticket->getEntityID(),
         'users_id' => $userId,
         'submitter_name' => User::getFriendlyNameById($userId),
         'submitter_email' => $email,
         'date_creation' => $now,
         'date_mod' => $now,
      ]));
   }

   public static function showForTicket(Ticket $ticket): void
   {
      $intent = self::getForTicket((int) $ticket->getID());
      if ($intent !== null) {
         self::showSubmitted($ticket, $intent);
         return;
      }
      if (self::canSubmitForTicket($ticket)) {
         self::showForm($ticket);
         return;
      }
      echo "<div class='alert alert-info'>" . self::escape(__('Aguardando o preenchimento do Termo de Intenção do Projeto pelo solicitante.', 'projectplus')) . '</div>';
   }

   private static function showForm(Ticket $ticket): void
   {
      $userId = (int) Session::getLoginUserID();
      $name = User::getFriendlyNameById($userId);
      $email = UserEmail::getDefaultForUser($userId);
      echo "<div class='center'><form method='post' action='" . self::escape(self::getActionUrl()) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('tickets_id', ['value' => (int) $ticket->getID()]);
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('Termo de Intenção do Projeto - TIP', 'projectplus')) . '</th></tr>';
      echo "<tr class='tab_bg_1'><td colspan='2'>" . self::escape(__('Preencha as informações para análise da demanda de intenção de projeto.', 'projectplus')) . '</td></tr>';
      self::readonlyRow(__('Número do ticket', 'projectplus'), '#' . (int) $ticket->getID());
      self::readonlyRow(__('Nome de quem preenche', 'projectplus'), $name);
      self::readonlyRow(__('E-mail de quem preenche', 'projectplus'), $email);
      self::inputRow('executor_area', __('Área que pretende executar o projeto', 'projectplus'), '', true);
      echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('Dados do solicitante', 'projectplus')) . '</th></tr>';
      self::inputRow('requester_name', __('Nome do solicitante', 'projectplus'), $name, true);
      self::inputRow('requester_area', __('Área solicitante', 'projectplus'), '', true);
      self::inputRow('project_name', __('Nome do projeto', 'projectplus'), (string) ($ticket->fields['name'] ?? ''), true);
      echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('Informações adicionais', 'projectplus')) . '</th></tr>';
      self::textareaRow('project_description', __('Descrição do projeto', 'projectplus'), __('Explique de forma resumida o que o projeto pretende realizar e qual problema ou necessidade busca resolver.', 'projectplus'), true);
      self::textareaRow('specific_objective', __('Objetivo específico', 'projectplus'), __('Defina o resultado concreto desejado, preferencialmente no modelo SMART.', 'projectplus'), true);
      self::textareaRow('metrics', __('Métricas', 'projectplus'), __('Indique como será possível medir o sucesso do projeto.', 'projectplus'), true);
      self::textareaRow('relevance', __('Motivo/Relevância', 'projectplus'), __('Descreva a importância do projeto para a área solicitante.', 'projectplus'), true);
      self::textareaRow('benefits', __('Benefícios', 'projectplus'), __('Liste os ganhos esperados com a realização do projeto.', 'projectplus'), true);
      self::textareaRow('resources', __('Lista dos prováveis recursos envolvidos', 'projectplus'), __('Informe os membros da equipe que participarão da execução, caso o projeto seja aprovado.', 'projectplus'), true);
      self::textareaRow('observations', __('Observações', 'projectplus'), __('Campo livre para informações complementares.', 'projectplus'), false);
      echo "<tr class='tab_bg_2'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='save_tip' value='1'>" . self::escape(__('Enviar TIP para análise', 'projectplus')) . '</button></td></tr>';
      echo '</table></form></div>';
   }

   /** @param array<string, mixed> $intent */
   private static function showSubmitted(Ticket $ticket, array $intent): void
   {
      echo "<div class='center'><table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='2'>" . self::escape(__('Termo de Intenção do Projeto - TIP enviado', 'projectplus')) . '</th></tr>';
      self::readonlyRow(__('Ticket', 'projectplus'), '#' . (int) $ticket->getID());
      self::readonlyRow(__('Enviado por', 'projectplus'), (string) $intent['submitter_name'] . ' - ' . (string) $intent['submitter_email']);
      self::readonlyRow(__('Data de envio', 'projectplus'), (string) $intent['date_creation']);
      self::readonlyRow(__('Área executora', 'projectplus'), (string) $intent['executor_area']);
      self::readonlyRow(__('Solicitante', 'projectplus'), (string) $intent['requester_name']);
      self::readonlyRow(__('Área solicitante', 'projectplus'), (string) $intent['requester_area']);
      self::readonlyRow(__('Nome do projeto', 'projectplus'), (string) $intent['project_name']);
      self::textRow(__('Descrição do projeto', 'projectplus'), (string) $intent['project_description']);
      self::textRow(__('Objetivo específico', 'projectplus'), (string) $intent['specific_objective']);
      self::textRow(__('Métricas', 'projectplus'), (string) $intent['metrics']);
      self::textRow(__('Motivo/Relevância', 'projectplus'), (string) $intent['relevance']);
      self::textRow(__('Benefícios', 'projectplus'), (string) $intent['benefits']);
      self::textRow(__('Recursos envolvidos', 'projectplus'), (string) $intent['resources']);
      if ((string) $intent['observations'] !== '') {
         self::textRow(__('Observações', 'projectplus'), (string) $intent['observations']);
      }
      echo '</table></div>';
   }

   private static function inputRow(string $field, string $label, string $value, bool $required): void
   {
      echo "<tr class='tab_bg_1'><td>" . self::escape($label . ($required ? ' *' : '')) . "</td><td><input class='form-control' type='text' name='"
         . self::escape($field) . "' value='" . self::escape($value) . "'" . ($required ? ' required' : '') . '></td></tr>';
   }

   private static function textareaRow(string $field, string $label, string $help, bool $required): void
   {
      echo "<tr class='tab_bg_1'><td>" . self::escape($label . ($required ? ' *' : '')) . "<br><small>" . self::escape($help) . "</small></td><td><textarea class='form-control' name='"
         . self::escape($field) . "' rows='4'" . ($required ? ' required' : '') . '></textarea></td></tr>';
   }

   private static function readonlyRow(string $label, string $value): void
   {
      echo "<tr class='tab_bg_1'><td>" . self::escape($label) . '</td><td>' . self::escape($value) . '</td></tr>';
   }

   private static function textRow(string $label, string $value): void
   {
      echo "<tr class='tab_bg_1'><td>" . self::escape($label) . '</td><td>' . nl2br(self::escape($value)) . '</td></tr>';
   }

   private static function getActionUrl(): string
   {
      global $CFG_GLPI;
      return rtrim((string) $CFG_GLPI['root_doc'], '/') . '/plugins/projectplus/front/tip.form.php';
   }

   private static function text($value): string
   {
      $value = trim((string) $value);
      if (strlen($value) > 65535) {
         throw new \RuntimeException(__('Um dos campos do TIP excede o tamanho permitido.', 'projectplus'));
      }
      return $value;
   }

   private static function escape(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
