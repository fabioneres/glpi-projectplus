<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\TransferRule;
use GlpiPlugin\Projectplus\FrontUrl;

Session::checkRight('config', UPDATE);

if (isset($_POST['save_transfer_rule'])) {
   $origem = (int) ($_POST['entities_id'] ?? -1);
   try {
      $alvos = $_POST['target_entities_id'] ?? [];
      TransferRule::saveForOrigin($origem, is_array($alvos) ? array_keys($alvos) : []);
      Session::addMessageAfterRedirect(__('Destinos atualizados.', 'projectplus'));
   } catch (RuntimeException $exception) {
      Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
   }
   Html::redirect(FrontUrl::to(basename(__FILE__)) . '?entities_id=' . $origem);
}

if (isset($_POST['remove_transfer_rule'])) {
   try {
      TransferRule::removeOrigin((int) ($_POST['entities_id'] ?? -1));
      Session::addMessageAfterRedirect(__('Regra de transferência removida.', 'projectplus'));
   } catch (RuntimeException $exception) {
      Session::addMessageAfterRedirect(htmlescape($exception->getMessage()), false, ERROR);
   }
   Html::redirect(FrontUrl::to(basename(__FILE__)));
}

$origemSelecionada = isset($_GET['entities_id']) ? (int) $_GET['entities_id'] : -1;
if ($origemSelecionada >= 0 && !Session::haveAccessToEntity($origemSelecionada)) {
   $origemSelecionada = -1;
}

Html::header(__('Transferência de projetos', 'projectplus'), FrontUrl::to(basename(__FILE__)), 'config', 'plugins');
ConfigPage::begin('overview');

$regras = TransferRule::getRulesByOrigin(true);
$url = htmlescape(FrontUrl::to(basename(__FILE__)));

echo "<section class='projectplus-tip projectplus-flowcfg'>";
echo "<header class='projectplus-tip__hero'><div class='projectplus-tip__hero-icon'><i class='ti ti-arrow-up-right'></i></div><div><span class='projectplus-tip__eyebrow'>"
   . ConfigPage::escape(__('Configuração do Project+', 'projectplus')) . '</span><h2>'
   . ConfigPage::escape(__('Transferência de projetos', 'projectplus')) . '</h2><p>'
   . ConfigPage::escape(__('Defina, para cada entidade de origem, quais entidades podem receber os projetos dela.', 'projectplus'))
   . '</p></div></header>';

echo "<div class='projectplus-tip__card projectplus-flowcfg__body'>";

// O padrao e fechado. Isso precisa estar em destaque, e nao numa nota de
// rodape: e a diferenca entre "nada mudou" e "a transferencia parou".
echo "<aside class='projectplus-flowcfg__vars' style='background:#FCF3E3;border-color:#E6CFA5;'>"
   . "<span class='projectplus-flowcfg__vars-title' style='color:#854F0B;'><i class='ti ti-alert-triangle' style='color:#854F0B;'></i> "
   . ConfigPage::escape(__('Entidade sem destino ativo não transfere', 'projectplus')) . '</span>'
   . "<p style='margin:0;color:#6B5535;'>"
   . ConfigPage::escape(__('Só transfere projetos a entidade que tiver ao menos um destino ativo aqui, e apenas para os destinos marcados. A regra vale para a entidade exata: as entidades filhas não herdam.', 'projectplus'))
   . '</p></aside>';

// ---------- 1. escolha da origem ----------
echo "<div class='projectplus-tip__section-title projectplus-flowcfg__section'><i class='ti ti-arrow-right-circle'></i><div><h3>"
   . ConfigPage::escape(__('Selecione a entidade de origem', 'projectplus')) . '</h3><p>'
   . ConfigPage::escape(__('Onde o projeto está hoje. Os destinos aparecem logo abaixo.', 'projectplus')) . '</p></div></div>';

echo "<form method='get' action='{$url}' style='display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;'>";
echo "<div class='projectplus-tip__field' style='min-width:340px;'>";
Dropdown::show(Entity::class, [
   'name' => 'entities_id',
   'value' => $origemSelecionada >= 0 ? $origemSelecionada : -1,
   'display_emptychoice' => true,
   'emptylabel' => __('Selecione a entidade de origem', 'projectplus'),
]);
echo '</div>';
echo "<button class='btn btn-primary' type='submit' style='margin-bottom:2px;'><i class='ti ti-list-search'></i> "
   . ConfigPage::escape(__('Carregar destinos', 'projectplus')) . '</button></form>';

// ---------- 2. tabela de destinos ----------
if ($origemSelecionada >= 0) {
   $ativos = array_flip(TransferRule::getAllowedTargets($origemSelecionada));
   $entidades = TransferRule::selectableEntities($origemSelecionada);

   echo "<div class='projectplus-tip__section-title projectplus-flowcfg__section'><i class='ti ti-checkbox'></i><div><h3>"
      . ConfigPage::escape(sprintf(__('Entidades de destino para %s', 'projectplus'), TransferRule::entityName($origemSelecionada)))
      . '</h3><p>'
      . ConfigPage::escape(sprintf(
         _n('%d destino ativo de %d entidade disponível.', '%d destinos ativos de %d entidades disponíveis.', count($entidades), 'projectplus'),
         count($ativos),
         count($entidades)
      ))
      . '</p></div></div>';

   if ($entidades === []) {
      echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'>"
         . ConfigPage::escape(__('Não há outra entidade acessível para ser destino.', 'projectplus')) . '</div>';
   } else {
      echo "<form method='post' action='{$url}'>" . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('entities_id', ['value' => $origemSelecionada]);

      echo "<div style='display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;'>"
         . "<label for='projectplus-filtro-destino' style='font-size:.88rem;color:#5B6570;'>"
         . ConfigPage::escape(__('Filtrar', 'projectplus')) . '</label>'
         . "<input id='projectplus-filtro-destino' type='search' class='form-control' style='max-width:300px;' placeholder='"
         . ConfigPage::escape(__('parte do nome da entidade', 'projectplus')) . "'>"
         . "<button type='button' class='btn btn-sm btn-outline-secondary' data-projectplus-marca='1'>"
         . ConfigPage::escape(__('Marcar os desta página', 'projectplus')) . '</button>'
         . "<button type='button' class='btn btn-sm btn-outline-secondary' data-projectplus-marca='0'>"
         . ConfigPage::escape(__('Desmarcar os desta página', 'projectplus')) . '</button>'
         . "<span style='flex-grow:1;'></span>"
         . "<span id='projectplus-contagem' style='font-size:.86rem;font-weight:500;color:#17613d;'></span></div>";

      echo "<div style='max-height:420px;overflow-y:auto;border:1px solid #dce5e1;border-radius:9px;'>";
      echo "<table class='tab_cadre_fixe' id='projectplus-destinos' style='margin:0;border:0;box-shadow:none;'>";
      echo "<thead><tr><th style='width:80px;'>" . ConfigPage::escape(__('Ativo', 'projectplus')) . '</th><th>'
         . ConfigPage::escape(__('Entidade', 'projectplus')) . "</th><th style='width:110px;'>"
         . ConfigPage::escape(__('Identificador', 'projectplus')) . '</th></tr></thead><tbody>';
      foreach ($entidades as $id => $nome) {
         $marcado = isset($ativos[$id]) ? " checked='checked'" : '';
         echo "<tr data-projectplus-nome='" . ConfigPage::escape(mb_strtolower($nome)) . "'>"
            . "<td class='center'><input type='checkbox' name='target_entities_id[" . (int) $id . "]' value='1'{$marcado}></td>"
            . '<td>' . ConfigPage::escape($nome) . '</td>'
            . "<td class='center'><code style='font-size:.8rem;color:#7c8b83;'>#" . (int) $id . '</code></td></tr>';
      }
      echo '</tbody></table></div>';

      echo "<nav style='display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap;' aria-label='"
         . ConfigPage::escape(__('Paginação dos destinos', 'projectplus')) . "'>"
         . "<label for='projectplus-porpagina' style='font-size:.86rem;color:#5B6570;'>"
         . ConfigPage::escape(__('Por página', 'projectplus')) . '</label>'
         . "<select id='projectplus-porpagina' class='form-select' style='width:auto;'>"
         . "<option value='25'>25</option><option value='50'>50</option><option value='100'>100</option>"
         . "<option value='0'>" . ConfigPage::escape(__('Todas', 'projectplus')) . '</option></select>'
         . "<button type='button' class='btn btn-sm btn-outline-secondary' id='projectplus-anterior'><i class='ti ti-chevron-left'></i> "
         . ConfigPage::escape(__('Anterior', 'projectplus')) . '</button>'
         . "<span id='projectplus-pagina' style='font-size:.86rem;color:#5B6570;'></span>"
         . "<button type='button' class='btn btn-sm btn-outline-secondary' id='projectplus-proxima'>"
         . ConfigPage::escape(__('Próxima', 'projectplus')) . " <i class='ti ti-chevron-right'></i></button></nav>";

      echo "<footer class='projectplus-flowcfg__actions' style='position:static;border:0;box-shadow:none;padding:16px 0 0;'><span><i class='ti ti-info-circle'></i> "
         . ConfigPage::escape(__('Salvar substitui a lista de destinos desta origem. As marcações de todas as páginas são enviadas juntas.', 'projectplus'))
         . "</span><button class='btn btn-primary' type='submit' name='save_transfer_rule' value='1'><i class='ti ti-device-floppy'></i> "
         . ConfigPage::escape(__('Salvar destinos', 'projectplus')) . '</button></footer>';
      echo '</form>';

      // A paginacao e do cliente de proposito: todas as linhas continuam no
      // formulario, entao marcar na pagina 1, ir para a 2 e salvar envia as
      // duas. Paginando no servidor, o POST traria so a pagina aberta e o
      // salvamento - que substitui a lista - apagaria o resto sem avisar.
      echo Html::scriptBlock("$(function () {
         var tabela = document.getElementById('projectplus-destinos');
         var filtro = document.getElementById('projectplus-filtro-destino');
         var porPagina = document.getElementById('projectplus-porpagina');
         var rotuloPagina = document.getElementById('projectplus-pagina');
         var contagem = document.getElementById('projectplus-contagem');
         var linhas = Array.prototype.slice.call(tabela.tBodies[0].rows);
         var filtradas = linhas;
         var pagina = 1;

         function tamanho() { var n = parseInt(porPagina.value, 10); return n > 0 ? n : filtradas.length || 1; }
         function paginas() { return Math.max(1, Math.ceil(filtradas.length / tamanho())); }

         function contar() {
            var marcados = linhas.filter(function (l) {
               var c = l.querySelector('input[type=checkbox]');
               return c && c.checked;
            }).length;
            contagem.textContent = marcados + ' de ' + linhas.length + ' marcadas';
         }

         function desenhar() {
            if (pagina > paginas()) { pagina = paginas(); }
            if (pagina < 1) { pagina = 1; }
            linhas.forEach(function (l) { l.style.display = 'none'; });
            var inicio = (pagina - 1) * tamanho();
            filtradas.slice(inicio, inicio + tamanho()).forEach(function (l) { l.style.display = ''; });
            rotuloPagina.textContent = filtradas.length === 0
               ? 'nenhuma entidade'
               : 'pagina ' + pagina + ' de ' + paginas() + ' (' + filtradas.length + ' entidades)';
            document.getElementById('projectplus-anterior').disabled = (pagina <= 1);
            document.getElementById('projectplus-proxima').disabled = (pagina >= paginas());
            contar();
         }

         filtro.addEventListener('input', function () {
            var termo = filtro.value.trim().toLowerCase();
            filtradas = termo === '' ? linhas : linhas.filter(function (l) {
               return l.dataset.projectplusNome.indexOf(termo) !== -1;
            });
            pagina = 1;
            desenhar();
         });
         porPagina.addEventListener('change', function () { pagina = 1; desenhar(); });
         document.getElementById('projectplus-anterior').addEventListener('click', function () { pagina--; desenhar(); });
         document.getElementById('projectplus-proxima').addEventListener('click', function () { pagina++; desenhar(); });
         tabela.addEventListener('change', function (ev) {
            if (ev.target && ev.target.type === 'checkbox') { contar(); }
         });
         document.querySelectorAll('[data-projectplus-marca]').forEach(function (b) {
            b.addEventListener('click', function () {
               var marcar = b.dataset.projectplusMarca === '1';
               linhas.filter(function (l) { return l.style.display !== 'none'; })
                  .forEach(function (l) {
                     var c = l.querySelector('input[type=checkbox]');
                     if (c) { c.checked = marcar; }
                  });
               contar();
            });
         });
         desenhar();
      });");
   }
}

// ---------- 3. resumo das regras ----------
echo "<div class='projectplus-tip__section-title projectplus-flowcfg__section'><i class='ti ti-list-check'></i><div><h3>"
   . ConfigPage::escape(__('Origens configuradas', 'projectplus')) . '</h3><p>'
   . ConfigPage::escape(sprintf(
      _n('%d entidade transfere projetos hoje.', '%d entidades transferem projetos hoje.', count($regras), 'projectplus'),
      count($regras)
   )) . '</p></div></div>';

if ($regras === []) {
   echo "<div class='projectplus-flow-alert projectplus-flow-alert--warning'><strong>"
      . ConfigPage::escape(__('Nenhuma origem configurada.', 'projectplus')) . '</strong><br>'
      . ConfigPage::escape(__('Enquanto esta lista estiver vazia, nenhum projeto pode ser transferido de entidade.', 'projectplus'))
      . '</div>';
} else {
   echo "<div class='projectplus-flowcfg__list'>";
   foreach ($regras as $origem => $destinos) {
      echo "<article class='projectplus-flowcfg__item'>";
      echo "<div class='projectplus-flowcfg__rail'><span class='projectplus-flowcfg__num'>" . count($destinos)
         . '</span><strong>' . ConfigPage::escape(TransferRule::entityName((int) $origem)) . "</strong>"
         . "<a href='{$url}?entities_id=" . (int) $origem . "'>" . ConfigPage::escape(__('editar destinos', 'projectplus')) . '</a></div>';
      echo "<div class='projectplus-flowcfg__fields'>";
      echo "<div class='projectplus-tip__field projectplus-tip__field--wide'><label>"
         . ConfigPage::escape(__('Pode transferir para', 'projectplus')) . '</label><small>'
         . ConfigPage::escape(__('Apenas estes destinos aparecem na tela de transferência.', 'projectplus')) . '</small>'
         . "<div style='display:flex;flex-wrap:wrap;gap:7px;align-items:center;'>";
      foreach ($destinos as $destino) {
         echo "<span style='display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border:1px solid #cfe0d6;border-radius:6px;background:#f3f7f4;color:#1c4430;font-size:.86rem;'>"
            . "<i class='ti ti-arrow-right' style='font-size:.85rem;color:#17613d;'></i>"
            . ConfigPage::escape(TransferRule::entityName((int) $destino)) . '</span>';
      }
      echo "<form method='post' action='{$url}' style='display:inline;margin-left:4px;'>"
         . Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()])
         . Html::hidden('entities_id', ['value' => (int) $origem])
         . "<button class='btn btn-sm btn-outline-danger' type='submit' name='remove_transfer_rule' value='1'><i class='ti ti-trash'></i> "
         . ConfigPage::escape(__('Remover origem', 'projectplus')) . '</button></form>';
      echo '</div></div></div></article>';
   }
   echo '</div>';
}

echo '</div></section>';
ConfigPage::end();
Html::footer();
