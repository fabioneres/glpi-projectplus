<?php

namespace GlpiPlugin\Projectplus;

use Html;
use Session;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Rodape de decisao aceitar/recusar, usado nas etapas do fluxo.
 *
 * Aceitar e recusar sao a mesma decisao: ficam lado a lado, alinhados a direita.
 * A justificativa pertence somente a recusa, entao so aparece depois que ela e
 * escolhida - com foco no campo e um Cancelar que volta ao estado anterior.
 *
 * O padrao vive aqui para que TIP e documentos nao divirjam com o tempo; cada
 * chamador informa apenas os rotulos e os nomes dos campos que ja usava.
 */
class DecisionFooter
{
   /**
    * @param string               $actionUrl destino dos dois formularios
    * @param string               $hidden    campos ocultos comuns, ja renderizados
    * @param array<string,string> $accept    name, label
    * @param array<string,string> $refuse    name, label, confirm, field, legend
    */
   public static function render(string $actionUrl, string $hidden, array $accept, array $refuse): void
   {
      $rand = mt_rand();
      $refuseId = 'projectplus-refuse-' . $rand;
      $fieldId = 'projectplus-refuse-field-' . $rand;
      $url = self::esc($actionUrl);

      echo "<div class='projectplus-decision' data-projectplus-decision='{$rand}'>";
      echo "<form method='post' action='{$url}' class='projectplus-decision__actions'>" . $hidden
         . "<button class='btn btn-success' type='submit' name='" . self::esc($accept['name']) . "' value='1'>"
         . "<i class='ti ti-check'></i> " . self::esc($accept['label']) . '</button>'
         . "<button class='btn btn-outline-danger' type='button' data-projectplus-refuse-toggle='{$refuseId}'>"
         . "<i class='ti ti-x'></i> " . self::esc($refuse['label']) . '</button></form>';
      echo "<form method='post' action='{$url}' class='projectplus-decision__refuse' id='{$refuseId}' hidden>" . $hidden
         . "<label for='{$fieldId}'>" . self::esc($refuse['legend']) . '</label>'
         . "<textarea class='form-control' id='{$fieldId}' name='" . self::esc($refuse['field']) . "' rows='4' required></textarea>"
         . "<div class='projectplus-decision__refuse-actions'>"
         . "<button class='btn btn-danger' type='submit' name='" . self::esc($refuse['name']) . "' value='1'>"
         . "<i class='ti ti-x'></i> " . self::esc($refuse['confirm']) . '</button>'
         . "<button class='btn btn-outline-secondary' type='button' data-projectplus-refuse-cancel='{$refuseId}'>"
         . self::esc(__('Cancelar', 'projectplus')) . '</button></div></form>';
      echo '</div>';
      self::script();
   }

   /** Revela a justificativa somente depois que a recusa e escolhida. */
   private static function script(): void
   {
      echo "<script>(function () { document.querySelectorAll('[data-projectplus-decision]').forEach(function (bloco) { if (bloco.dataset.projectplusDecisionReady) { return; } bloco.dataset.projectplusDecisionReady = '1'; var abrir = bloco.querySelector('[data-projectplus-refuse-toggle]'); var recusa = abrir ? document.getElementById(abrir.dataset.projectplusRefuseToggle) : null; if (!abrir || !recusa) { return; } var campo = recusa.querySelector('textarea'); var cancelar = recusa.querySelector('[data-projectplus-refuse-cancel]'); function mostrar(visivel) { recusa.hidden = !visivel; abrir.hidden = visivel; if (visivel && campo) { campo.focus(); } if (!visivel && campo) { campo.value = ''; } } abrir.addEventListener('click', function () { mostrar(true); }); if (cancelar) { cancelar.addEventListener('click', function () { mostrar(false); }); } }); })();</script>";
   }

   private static function esc(string $value): string
   {
      return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
}
