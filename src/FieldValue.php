<?php

namespace GlpiPlugin\Projectplus;

/** Native validation and normalization for configurable Project+ fields. */
final class FieldValue
{
   private const MULTIPLE = ['checkboxes', 'multiselect'];
   private const CHOICES = ['select', 'radio', 'checkboxes', 'multiselect'];
   private const DISPLAY_ONLY = ['section', 'info'];
   private const FILES = [FieldAttachment::TYPE];

   /** @return array<string,string> */
   public static function objectTypes(): array
   {
      return [
         'Entity' => __('Entidade'),
         'ITILCategory' => __('Categoria ITIL'),
         'Location' => __('Localização'),
         'Group' => __('Grupo'),
         'User' => __('Usuário'),
         'Supplier' => __('Fornecedor'),
         'Project' => __('Projeto'),
         'Ticket' => __('Chamado'),
      ];
   }

   /** @return array<string,string> */
   public static function actorTypes(): array
   {
      return ['User' => __('Usuário'), 'Group' => __('Grupo'), 'Supplier' => __('Fornecedor')];
   }

   public static function isDisplayOnly(string $type): bool
   {
      return in_array($type, self::DISPLAY_ONLY, true);
   }

   /**
    * Campos cujo valor sao arquivos: nao sao preenchidos por uma origem de texto
    * e exigem o fluxo dedicado de FieldAttachment para gravar a resposta.
    */
   public static function isFileType(string $type): bool
   {
      return in_array($type, self::FILES, true);
   }

   public static function displayRules(): array { return ['always' => __('Sempre exibido', 'projectplus'), 'conditional' => __('Exibir conforme resposta', 'projectplus')]; }
   public static function conditionOperators(): array { return ['equals'=>__('é igual a', 'projectplus'),'not_equals'=>__('é diferente de', 'projectplus'),'contains'=>__('contém', 'projectplus'),'empty'=>__('está vazio', 'projectplus'),'not_empty'=>__('está preenchido', 'projectplus')]; }

   public static function isVisible(array $field, array $answers): bool
   {
      if ((string)($field['display_rule'] ?? 'always') !== 'conditional') return true;
      $sourceId = (int)($field['condition_field_id'] ?? 0);
      $raw = $answers[$sourceId] ?? '';
      $actual = is_array($raw) ? implode("\n", array_map('strval', $raw)) : trim((string)$raw);
      $expected = trim((string)($field['condition_value'] ?? ''));
      switch ((string)($field['condition_operator'] ?? 'equals')) {
         case 'not_equals': return $actual !== $expected;
         case 'contains': return $expected !== '' && mb_stripos($actual, $expected) !== false;
         case 'empty': return $actual === '';
         case 'not_empty': return $actual !== '';
         default: return $actual === $expected;
      }
   }

   public static function conditionAttributes(array $field): string
   {
      if ((string)($field['display_rule'] ?? 'always') !== 'conditional') return '';
      $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
      return " data-projectplus-condition-field='".(int)($field['condition_field_id']??0)."' data-projectplus-condition-operator='".$esc($field['condition_operator']??'equals')."' data-projectplus-condition-value='".$esc($field['condition_value']??'')."'";
   }

   public static function conditionScript(string $prefix): string
   {
      $p = htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8');
      return "<script>(function(){const root=document.currentScript.parentElement;const update=()=>{root.querySelectorAll('[data-projectplus-condition-field]').forEach(box=>{const id=box.dataset.projectplusConditionField;const inputs=root.querySelectorAll('[name=\"{$p}['+id+']\"],[name=\"{$p}['+id+'][]\"]');let values=[];inputs.forEach(i=>{if((i.type==='checkbox'||i.type==='radio')&&!i.checked)return;if(i.multiple)values.push(...Array.from(i.selectedOptions).map(o=>o.value));else values.push(i.value||'');});const actual=values.filter(Boolean).join('\\n');const expected=box.dataset.projectplusConditionValue||'';const op=box.dataset.projectplusConditionOperator;const visible=op==='not_equals'?actual!==expected:op==='contains'?expected!==''&&actual.toLowerCase().includes(expected.toLowerCase()):op==='empty'?actual==='':op==='not_empty'?actual!=='':actual===expected;box.hidden=!visible;box.querySelectorAll('input,select,textarea').forEach(i=>i.disabled=!visible);});};root.addEventListener('input',update);root.addEventListener('change',update);update();})();</script>";
   }

   /** @return string[] */
   public static function options(array $field): array
   {
      $values = preg_split('/\r\n|\r|\n/', (string) ($field['values'] ?? '')) ?: [];
      return array_values(array_unique(array_filter(array_map('trim', $values), static fn($v) => $v !== '')));
   }

   /** @param mixed $raw */
   public static function normalize(array $field, $raw): string
   {
      $type = (string) ($field['field_type'] ?? 'text');
      if (self::isDisplayOnly($type)) {
         return '';
      }
      // Anexos nunca vem do POST do proprio campo: quem grava e FieldAttachment::collect(),
      // que so aceita ids ja gravados ou arquivos recem-enviados pelo pipeline do GLPI.
      if (self::isFileType($type)) {
         return '';
      }
      $allowed = self::options($field);
      if (in_array($type, ['glpi_object', 'actor'], true)) {
         if ($raw === '' || $raw === null || (int) $raw <= 0) return '';
         $itemtype = trim((string) ($field['values'] ?? ''));
         if ($type === 'actor' && $itemtype === '') $itemtype = 'User';
         $validTypes = $type === 'actor' ? self::actorTypes() : self::objectTypes();
         if (!isset($validTypes[$itemtype]) || !class_exists($itemtype)) {
            throw new \RuntimeException(__('O tipo de objeto GLPI configurado é inválido.', 'projectplus'));
         }
         $item = new $itemtype();
         if (!$item->getFromDB((int) $raw)) {
            throw new \RuntimeException(__('O objeto GLPI selecionado não existe.', 'projectplus'));
         }
         return (string) (int) $raw;
      }
      if (in_array($type, self::MULTIPLE, true)) {
         $values = is_array($raw) ? $raw : ($raw === '' || $raw === null ? [] : [(string) $raw]);
         $values = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), $values), static fn($v) => $v !== '')));
         if (array_diff($values, $allowed)) {
            throw new \RuntimeException(__('Uma ou mais opções selecionadas são inválidas.', 'projectplus'));
         }
         return implode("\n", $values);
      }
      $value = trim((string) $raw);
      if (in_array($type, self::CHOICES, true) && $value !== '' && !in_array($value, $allowed, true)) {
         throw new \RuntimeException(__('A opção selecionada é inválida.', 'projectplus'));
      }
      if ($value !== '' && $type === 'number' && filter_var($value, FILTER_VALIDATE_INT) === false) {
         throw new \RuntimeException(__('Informe um número inteiro válido.', 'projectplus'));
      }
      if ($value !== '' && $type === 'decimal' && !is_numeric(str_replace(',', '.', $value))) {
         throw new \RuntimeException(__('Informe um número decimal válido.', 'projectplus'));
      }
      if ($value !== '' && $type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
         throw new \RuntimeException(__('Informe um endereço de e-mail válido.', 'projectplus'));
      }
      return substr($value, 0, 65535);
   }

   public static function render(string $prefix, array $field, string $value): string
   {
      $id = (int) $field['id'];
      $type = (string) ($field['field_type'] ?? 'text');
      $name = $prefix . '[' . $id . ']';
      $htmlId = str_replace('_', '-', $prefix) . '-' . $id;
      $required = (int) ($field['is_required'] ?? 0) === 1;
      $req = $required ? ' required' : '';
      $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
      if ($type === 'info') {
         return "<div class='alert alert-info'>" . nl2br($esc((string) ($field['description'] ?? ''))) . '</div>';
      }
      if (self::isFileType($type)) {
         return FieldAttachment::render($prefix, $field, $value);
      }
      if (in_array($type, ['glpi_object', 'actor'], true)) {
         $itemtype = trim((string) ($field['values'] ?? ''));
         if ($type === 'actor' && $itemtype === '') $itemtype = 'User';
         $validTypes = $type === 'actor' ? self::actorTypes() : self::objectTypes();
         if (!isset($validTypes[$itemtype]) || !class_exists($itemtype)) {
            return "<div class='alert alert-warning'>" . $esc(__('Configure um tipo de objeto GLPI válido.', 'projectplus')) . '</div>';
         }
         ob_start();
         $itemtype::dropdown([
            'name' => $name,
            'value' => (int) $value,
            'display_emptychoice' => true,
            'entity' => $_SESSION['glpiactiveentities'] ?? [],
         ]);
         return (string) ob_get_clean();
      }
      if ($type === 'textarea') {
         return "<textarea id='{$esc($htmlId)}' class='form-control' name='{$esc($name)}' rows='5'{$req}>{$esc($value)}</textarea>";
      }
      $options = self::options($field);
      if ($type === 'select' || $type === 'multiselect') {
         $multiple = $type === 'multiselect';
         $selected = $multiple ? preg_split('/\r\n|\r|\n/', $value) : [$value];
         $html = "<select id='{$esc($htmlId)}' class='form-select' name='{$esc($name)}" . ($multiple ? "[]' multiple" : "'") . $req . '>';
         if (!$multiple) $html .= "<option value=''>-----</option>";
         foreach ($options as $option) {
            $html .= "<option value='{$esc($option)}'" . (in_array($option, $selected, true) ? ' selected' : '') . ">{$esc($option)}</option>";
         }
         return $html . '</select>';
      }
      if ($type === 'radio' || $type === 'checkboxes') {
         $selected = $type === 'checkboxes' ? preg_split('/\r\n|\r|\n/', $value) : [$value];
         $html = "<div class='projectplus-field-options'>";
         foreach ($options as $index => $option) {
            $inputId = $htmlId . '-' . $index;
            $html .= "<label class='form-check'><input class='form-check-input' id='{$esc($inputId)}' type='" . ($type === 'radio' ? 'radio' : 'checkbox') . "' name='{$esc($name)}" . ($type === 'checkboxes' ? "[]'" : "'") . " value='{$esc($option)}'" . (in_array($option, $selected, true) ? ' checked' : '') . $req . "><span class='form-check-label'>{$esc($option)}</span></label>";
         }
         return $html . '</div>';
      }
      $htmlType = ['number' => 'number', 'decimal' => 'number', 'email' => 'email', 'date' => 'date', 'datetime' => 'datetime-local', 'time' => 'time'][$type] ?? 'text';
      $step = $type === 'number' ? " step='1'" : ($type === 'decimal' ? " step='any'" : '');
      return "<input id='{$esc($htmlId)}' class='form-control' type='{$htmlType}'{$step} name='{$esc($name)}' value='{$esc($value)}'{$req}>";
   }
}
