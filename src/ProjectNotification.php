<?php

namespace GlpiPlugin\Projectplus;

use Notification;
use Notification_NotificationTemplate;
use NotificationEvent;
use NotificationTarget;
use NotificationTemplate;
use NotificationTemplateTranslation;
use Project;
use Session;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Envio por e-mail das interacoes de projeto.
 *
 * Segue o caminho dos chamados. No GLPI o acompanhamento nao e um itemtype de
 * notificacao proprio: `ITILFollowup::post_addItem()` dispara `add_followup`
 * sobre o **Ticket**, e o conteudo do acompanhamento entra como tag do texto.
 * Aqui e igual - os eventos sao disparados sobre o **Project**.
 *
 * A vantagem e reaproveitar `NotificationTargetProject`, que o GLPI ja sabe
 * resolver: gerente, grupo do gerente, usuarios e grupos da equipe, contatos e
 * fornecedores ficam disponiveis como destinatarios sem o plugin manter a sua
 * propria lista, e a configuracao acontece na tela nativa de Notificacoes.
 *
 * Os eventos entram na lista do alvo nativo pelo hook `item_get_events` e as
 * tags do texto pelo `item_get_datas` - os dois pontos de extensao que o
 * proprio `NotificationTarget` oferece a plugins.
 *
 * O disparo fica condicionado a "Notificar participantes" e o envio, em si, e
 * feito pela acao automatica nativa `queuednotification`: o plugin apenas
 * enfileira.
 */
class ProjectNotification
{
   public const EVENT_INTERACTION = 'projectplus_interaction';
   public const EVENT_SOLUTION = 'projectplus_solution';
   public const EVENT_ESCALATION = 'projectplus_escalation';
   public const EVENT_DOCUMENT = 'projectplus_document';

   /** Chave usada para levar a interacao de `raiseEvent` ate o hook das tags. */
   private const OPTION_KEY = 'projectplus_interaction';

   /** @return array<string, string> */
   public static function getEvents(): array
   {
      return [
         self::EVENT_INTERACTION => __('Project+ - Nova interação no projeto', 'projectplus'),
         self::EVENT_SOLUTION => __('Project+ - Solução registrada no projeto', 'projectplus'),
         self::EVENT_ESCALATION => __('Project+ - Projeto transferido para outra entidade', 'projectplus'),
         self::EVENT_DOCUMENT => __('Project+ - Aviso sobre um termo do projeto', 'projectplus'),
      ];
   }

   /**
    * Hook `item_get_events`: acrescenta os eventos do plugin aos do Project.
    *
    * @param NotificationTarget $target
    */
   public static function addEvents($target): void
   {
      if (!($target instanceof NotificationTarget) || !($target->obj instanceof Project)) {
         return;
      }
      foreach (self::getEvents() as $event => $label) {
         $target->events[$event] = $label;
      }
   }

   /**
    * Hook `item_get_datas`: publica as tags do texto para os eventos do plugin.
    *
    * @param NotificationTarget $target
    */
   public static function addData($target): void
   {
      if (!($target instanceof NotificationTarget) || !($target->obj instanceof Project)) {
         return;
      }
      if (!array_key_exists((string) $target->raiseevent, self::getEvents())) {
         return;
      }

      $interaction = $target->options[self::OPTION_KEY] ?? [];
      $interaction = is_array($interaction) ? $interaction : [];

      $authorId = (int) ($interaction['users_id'] ?? 0);
      $author = $authorId > 0 ? User::getFriendlyNameById($authorId) : __('Sistema', 'projectplus');
      $date = (string) ($interaction['date_creation'] ?? '');

      // O conteudo e entregue como esta gravado, do mesmo jeito que o GLPI faz
      // com ##followup.description##: quem converte para texto puro na versao
      // sem HTML do e-mail e o proprio motor de template.
      $target->data['##projectplus.interaction##'] = (string) ($interaction['content'] ?? '');
      $target->data['##projectplus.author##'] = $author;
      $target->data['##projectplus.date##'] = $date !== '' ? \Html::convDateTime($date) : '';
      $target->data['##projectplus.event##'] = self::getEvents()[(string) $target->raiseevent];
   }

   /**
    * Enfileira o e-mail de uma interacao.
    *
    * Interacao interna nunca notifica: ela existe justamente para o registro
    * que o cliente nao deve ver, e a lista de destinatarios do projeto inclui
    * gente de fora da equipe.
    *
    * @param array<string, mixed> $interaction
    */
   public static function raise(Project $project, string $event, array $interaction): void
   {
      if (!array_key_exists($event, self::getEvents())) {
         return;
      }
      if ((int) ($interaction['notify_participants'] ?? 0) !== 1) {
         return;
      }
      if ((int) ($interaction['is_internal'] ?? 0) === 1) {
         return;
      }
      NotificationEvent::raiseEvent($event, $project, [self::OPTION_KEY => $interaction]);
   }

   /**
    * Cria, na instalacao, o modelo e a notificacao de cada evento.
    *
    * Idempotente: identifica o que ja existe pelo itemtype e pelo evento, para
    * nao duplicar a cada atualizacao do plugin e para nao desfazer o que o
    * administrador tiver ajustado depois.
    */
   public static function install(): void
   {
      foreach (self::getEvents() as $event => $label) {
         $templateId = self::ensureTemplate($event, $label);
         if ($templateId > 0) {
            self::ensureNotification($event, $label, $templateId);
         }
      }
   }

   /** Remove modelos e notificacoes criados pelo plugin. */
   public static function uninstall(): void
   {
      /** @var \DBmysql $DB */
      global $DB;

      $eventos = array_keys(self::getEvents());
      foreach ($DB->request([
         'FROM' => Notification::getTable(),
         'WHERE' => ['itemtype' => Project::class, 'event' => $eventos],
      ]) as $row) {
         $DB->delete(Notification_NotificationTemplate::getTable(), ['notifications_id' => (int) $row['id']]);
         $DB->delete(Notification::getTable(), ['id' => (int) $row['id']]);
      }
      foreach ($DB->request([
         'FROM' => NotificationTemplate::getTable(),
         'WHERE' => ['itemtype' => Project::class, 'comment' => $eventos],
      ]) as $row) {
         $DB->delete(NotificationTemplateTranslation::getTable(), ['notificationtemplates_id' => (int) $row['id']]);
         $DB->delete(NotificationTemplate::getTable(), ['id' => (int) $row['id']]);
      }
   }

   /** @return int id do modelo, 0 em caso de falha */
   private static function ensureTemplate(string $event, string $label): int
   {
      /** @var \DBmysql $DB */
      global $DB;

      // O evento fica no campo `comment` porque o modelo nativo nao tem coluna
      // de evento: e por ele que a reinstalacao reconhece o que ja criou.
      $existente = $DB->request([
         'FROM' => NotificationTemplate::getTable(),
         'WHERE' => ['itemtype' => Project::class, 'comment' => $event],
         'LIMIT' => 1,
      ])->current();
      if ($existente !== null) {
         return (int) $existente['id'];
      }

      $template = new NotificationTemplate();
      $templateId = (int) $template->add([
         'name' => $label,
         'itemtype' => Project::class,
         'comment' => $event,
         'css' => '',
      ]);
      if ($templateId <= 0) {
         return 0;
      }

      $translation = new NotificationTemplateTranslation();
      $translation->add([
         'notificationtemplates_id' => $templateId,
         'language' => '',
         'subject' => '##project.name## - ##projectplus.event##',
         'content_text' => self::textBody(),
         'content_html' => self::htmlBody(),
      ]);

      return $templateId;
   }

   private static function ensureNotification(string $event, string $label, int $templateId): void
   {
      /** @var \DBmysql $DB */
      global $DB;

      $existente = $DB->request([
         'FROM' => Notification::getTable(),
         'WHERE' => ['itemtype' => Project::class, 'event' => $event],
         'LIMIT' => 1,
      ])->current();
      if ($existente !== null) {
         return;
      }

      $notification = new Notification();
      $notificationId = (int) $notification->add([
         'name' => $label,
         'entities_id' => 0,
         'itemtype' => Project::class,
         'event' => $event,
         'comment' => __('Criada pelo Project+. Ajuste destinatários e modelo conforme a sua operação.', 'projectplus'),
         'is_recursive' => 1,
         // Nasce desligada de proposito: quem instala escolhe quando comecar a
         // enviar e para quem, em vez de o plugin passar a mandar e-mail
         // sozinho na primeira atualizacao.
         'is_active' => 0,
         'allow_response' => 0,
      ]);
      if ($notificationId <= 0) {
         return;
      }

      $link = new Notification_NotificationTemplate();
      $link->add([
         'notifications_id' => $notificationId,
         'notificationtemplates_id' => $templateId,
         'mode' => Notification_NotificationTemplate::MODE_MAIL,
      ]);

      // Destinatario padrao: o gerente do projeto. E o unico que existe em
      // qualquer projeto, entao e o unico seguro para vir pronto.
      $DB->insert('glpi_notificationtargets', [
         'items_id' => Notification::MANAGER_USER,
         'type' => Notification::USER_TYPE,
         'notifications_id' => $notificationId,
      ]);
   }

   private static function textBody(): string
   {
      return "##projectplus.event##\n\n"
         . __('Projeto', 'projectplus') . ": ##project.name##\n"
         . __('Autor', 'projectplus') . ": ##projectplus.author##\n"
         . __('Data', 'projectplus') . ": ##projectplus.date##\n\n"
         . "##projectplus.interaction##\n\n"
         . __('Acesse o projeto', 'projectplus') . ": ##project.url##\n";
   }

   private static function htmlBody(): string
   {
      return '<p><strong>##projectplus.event##</strong></p>'
         . '<p>' . __('Projeto', 'projectplus') . ': ##project.name##<br />'
         . __('Autor', 'projectplus') . ': ##projectplus.author##<br />'
         . __('Data', 'projectplus') . ': ##projectplus.date##</p>'
         . '<div>##projectplus.interaction##</div>'
         . '<p><a href="##project.url##">' . __('Acessar o projeto', 'projectplus') . '</a></p>';
   }
}
