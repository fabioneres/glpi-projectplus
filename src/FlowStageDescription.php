<?php

namespace GlpiPlugin\Projectplus;

use Config;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/** Centralizes the administrator-maintained explanatory text for each flow step. */
class FlowStageDescription
{
   private const CONTEXT = 'plugin:projectplus';

   /** @return array<string, array{label:string, active:string, pending:string}> */
   public static function getStages(): array
   {
      return [
         'tip' => [
            'label' => __('TIP - Termo de Intenção de Projeto', 'projectplus'),
            'active' => __('O solicitante preenche o TIP. Em seguida, o Gerente de Projetos analisa e registra o aceite ou a recusa.', 'projectplus'),
            'pending' => __('Aguarde a solicitação de preenchimento do TIP pela área responsável.', 'projectplus'),
         ],
         'tip_dt' => [
            'label' => __('TIP - Detalhamento Técnico', 'projectplus'),
            'active' => __('O Gerente de Projetos preenche o Detalhamento Técnico após o aceite do TIP.', 'projectplus'),
            'pending' => __('Aguarde o solicitante preencher o TIP - Termo de Intenção de Projeto e realize o aceite para liberar esta etapa.', 'projectplus'),
         ],
         'technical_priority' => [
            'label' => __('Priorização técnica', 'projectplus'),
            'active' => __('Preencha os critérios técnicos para subsidiar a decisão de transformar esta solicitação em projeto.', 'projectplus'),
            'pending' => __('Gere o TIP - Detalhamento Técnico para liberar esta etapa.', 'projectplus'),
         ],
         'strategic_priority' => [
            'label' => __('Priorização estratégica', 'projectplus'),
            'active' => __('Defina a prioridade estratégica da solicitação após a conclusão da análise técnica.', 'projectplus'),
            'pending' => __('Conclua a priorização técnica para liberar esta etapa.', 'projectplus'),
         ],
         'authorization_opening' => [
            'label' => __('Autorização de abertura do projeto', 'projectplus'),
            'active' => __('O Gestor de Portfólio autoriza ou nega a abertura do projeto, com justificativa quando houver recusa.', 'projectplus'),
            'pending' => __('Conclua a priorização estratégica para liberar a autorização de abertura.', 'projectplus'),
         ],
         'tap' => [
            'label' => __('TAP - Termo de Abertura do Projeto', 'projectplus'),
            'active' => __('Após a autorização de abertura, o responsável preenche o TAP para formalizar o projeto.', 'projectplus'),
            'pending' => __('O TAP será liberado após a autorização de abertura do projeto.', 'projectplus'),
         ],
         'routing_handoff' => [
            'label' => __('Encaminhamento para outra entidade', 'projectplus'),
            'active' => __('O chamado #{chamado_id} foi enviado para a entidade {entidade} e está agora na fase {fase}. Você poderá voltar a acessá-lo quando o fluxo retornar para a entidade do Gerente de Projetos.', 'projectplus'),
            'pending' => '',
         ],
      ];
   }

   /**
    * Display phases shown in the ticket header and history. Their triggers are
    * the stable internal workflow states; administrators may group more than
    * one internal state under the same public phase name.
    *
    * @return array<string, array{label:string, triggers:array<int,string>}>
    */
   public static function getDisplayPhases(): array
   {
      $phases = TicketPhase::getPhases();
      $result = [];
      foreach ($phases as $phase => $label) {
         $result[$phase] = ['label' => $label, 'triggers' => [$phase]];
      }
      return $result;
   }

   /**
    * Eventos do fluxo que publicam acompanhamento proprio.
    *
    * O acompanhamento automatico e indexado pela fase de destino, o que basta
    * enquanto a fase identifica o que aconteceu. Nao basta quando a mesma fase
    * e alcancada por caminhos diferentes: `awaiting_pretip` vale tanto para a
    * primeira solicitacao de preenchimento quanto para uma devolucao, e so a
    * segunda tem justificativa. O evento carrega o texto do que aconteceu, em
    * vez do texto de onde se chegou.
    *
    * @return array<string, array{label:string, default:string}>
    */
   public static function getFlowEvents(): array
   {
      return [
         'tip_returned' => [
            'label' => __('TIP devolvido para ajuste', 'projectplus'),
            'default' => __('O TIP - Termo de Intenção de Projeto foi devolvido para ajuste pelo Gerente de Projetos. Justificativa: {justificativa}', 'projectplus'),
         ],
         'document_returned' => [
            'label' => __('Documento devolvido pelo cliente (TAP, TEP e TA)', 'projectplus'),
            'default' => __('Documento recusado pelo cliente. Justificativa: {justificativa}', 'projectplus'),
         ],
      ];
   }

   public static function getEventFollowup(string $event): string
   {
      $events = self::getFlowEvents();
      if (!array_key_exists($event, $events)) {
         return '';
      }
      $configured = Config::getConfigurationValue(self::CONTEXT, self::eventFollowupKey($event));
      if ($configured === null || $configured === false) {
         return (string) $events[$event]['default'];
      }
      return trim((string) $configured);
   }

   /** @return array<string, string> */
   public static function getDefaultPhaseFollowups(): array
   {
      return [
         'classification' => __('A solicitação está na fase {fase}.', 'projectplus'),
         'awaiting_pretip' => __('O preenchimento do TIP - Termo de Intenção de Projeto foi solicitado. Acesse a aba Project+ para preencher as informações.', 'projectplus'),
         'awaiting_pretip_review' => __('O TIP - Termo de Intenção de Projeto foi preenchido e enviado para aceite do Gerente de Projetos.', 'projectplus'),
         'awaiting_tip' => __('O preenchimento do TIP - Detalhamento Técnico foi liberado para o Gerente de Projetos.', 'projectplus'),
         'awaiting_tip_review' => __('O TIP - Detalhamento Técnico foi enviado para validação.', 'projectplus'),
         'portfolio_analysis' => __('O TIP - Detalhamento Técnico foi concluído. A solicitação está disponível para priorização.', 'projectplus'),
         'awaiting_authorization' => __('A priorização estratégica foi concluída. A solicitação aguarda autorização de abertura do projeto.', 'projectplus'),
         'authorized_opening' => __('A abertura do projeto foi autorizada. O TAP pode ser preenchido.', 'projectplus'),
         'awaiting_tap_acceptance' => __('O TAP foi enviado e aguarda aceite do solicitante.', 'projectplus'),
         'project_opened' => __('O projeto foi criado e vinculado a esta solicitação.', 'projectplus'),
         'rejected_cancelled' => __('A solicitação foi recusada ou cancelada. Justificativa: {justificativa}', 'projectplus'),
      ];
   }

   public static function getPhaseName(string $displayPhase): string
   {
      $phases = self::getDisplayPhases();
      if (!isset($phases[$displayPhase])) {
         return $displayPhase;
      }
      $configured = Config::getConfigurationValue(self::CONTEXT, self::phaseNameKey($displayPhase));
      $name = trim((string) (($configured === null || $configured === false) ? '' : $configured));
      return $name !== '' ? $name : $phases[$displayPhase]['label'];
   }

   /** @return array<int, string> */
   public static function getPhaseTriggers(string $displayPhase): array
   {
      $phases = self::getDisplayPhases();
      if (!isset($phases[$displayPhase])) {
         return [];
      }
      $configured = Config::getConfigurationValue(self::CONTEXT, self::phaseTriggersKey($displayPhase));
      if ($configured === null || $configured === false) {
         return $phases[$displayPhase]['triggers'];
      }
      $decoded = json_decode((string) $configured, true);
      if (!is_array($decoded)) {
         return $phases[$displayPhase]['triggers'];
      }
      $known = array_keys(TicketPhase::getPhases());
      return array_values(array_unique(array_intersect(array_map('strval', $decoded), $known)));
   }

   public static function getNameForTrigger(string $trigger, string $fallback = ''): string
   {
      foreach (self::getDisplayPhases() as $displayPhase => $definition) {
         if (in_array($trigger, self::getPhaseTriggers($displayPhase), true)) {
            return self::getPhaseName($displayPhase);
         }
      }
      return $fallback !== '' ? $fallback : $trigger;
   }

   public static function getPhaseFollowup(string $phase): string
   {
      if (!array_key_exists($phase, TicketPhase::getPhases())) {
         return '';
      }
      $configured = Config::getConfigurationValue(self::CONTEXT, self::phaseFollowupKey($phase));
      if ($configured === null || $configured === false) {
         $defaults = self::getDefaultPhaseFollowups();
         return $defaults[$phase] ?? '';
      }
      return trim((string) $configured);
   }

   public static function get(string $stage, string $state = 'active'): string
   {
      $stages = self::getStages();
      if (!isset($stages[$stage]) || !in_array($state, ['active', 'pending'], true)) {
         return '';
      }
      $configured = Config::getConfigurationValue(self::CONTEXT, self::key($stage, $state));
      // The active key used before pending descriptions were introduced is
      // retained as a fallback, preserving an administrator's prior wording.
      if (($configured === null || $configured === false) && $state === 'active') {
         $configured = Config::getConfigurationValue(self::CONTEXT, self::key($stage));
      }
      return ($configured === null || $configured === false) ? $stages[$stage][$state] : trim((string) $configured);
   }

   /** @param array<string, mixed> $input */
   public static function save(array $input): void
   {
      $values = [];
      $knownTriggers = array_keys(TicketPhase::getPhases());
      $usedTriggers = [];
      foreach (self::getDisplayPhases() as $displayPhase => $definition) {
         $name = trim((string) ($input['phase_names'][$displayPhase] ?? ''));
         $triggers = $input['phase_triggers'][$displayPhase] ?? [];
         $triggers = is_array($triggers) ? array_values(array_unique(array_map('strval', $triggers))) : [];
         if (array_diff($triggers, $knownTriggers) !== []) {
            throw new \RuntimeException(__('Foi informado um gatilho de fase inválido.', 'projectplus'));
         }
         foreach ($triggers as $trigger) {
            if (isset($usedTriggers[$trigger])) {
               throw new \RuntimeException(sprintf(
                  __('O gatilho "%1$s" foi selecionado em mais de uma fase.', 'projectplus'),
                  TicketPhase::getPhaseLabelRaw($trigger)
               ));
            }
            $usedTriggers[$trigger] = $displayPhase;
         }
         $values[self::phaseNameKey($displayPhase)] = substr($name, 0, 255);
         $values[self::phaseTriggersKey($displayPhase)] = json_encode($triggers);
         $followup = trim((string) ($input['phase_followups'][$displayPhase] ?? ''));
         $values[self::phaseFollowupKey($displayPhase)] = substr($followup, 0, 65535);
      }
      foreach (array_keys(self::getFlowEvents()) as $event) {
         $followup = trim((string) ($input['event_followups'][$event] ?? ''));
         $values[self::eventFollowupKey($event)] = substr($followup, 0, 65535);
      }
      foreach (self::getStages() as $stage => $definition) {
         foreach (['active', 'pending'] as $state) {
            $value = trim((string) ($input['descriptions'][$stage][$state] ?? ''));
            $values[self::key($stage, $state)] = substr($value, 0, 65535);
         }
      }
      Config::setConfigurationValues(self::CONTEXT, $values);
   }

   private static function key(string $stage, string $state = ''): string
   {
      return 'flow_stage_description_' . $stage . ($state === '' ? '' : '_' . $state);
   }

   private static function phaseNameKey(string $displayPhase): string
   {
      return 'flow_phase_name_' . $displayPhase;
   }

   private static function phaseTriggersKey(string $displayPhase): string
   {
      return 'flow_phase_triggers_' . $displayPhase;
   }

   private static function phaseFollowupKey(string $phase): string
   {
      return 'flow_phase_followup_' . $phase;
   }

   private static function eventFollowupKey(string $event): string
   {
      return 'flow_event_followup_' . $event;
   }
}
