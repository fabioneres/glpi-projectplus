<?php

if (!defined('GLPI_ROOT')) {
   require_once dirname(__DIR__, 3) . '/inc/includes.php';
}
require_once dirname(__DIR__) . '/bootstrap.php';

use GlpiPlugin\Projectplus\ConfigPage;
use GlpiPlugin\Projectplus\RoutingNotice;
use GlpiPlugin\Projectplus\FrontUrl;

Session::checkLoginUser();
$notice = RoutingNotice::consume();
if ($notice === null) {
   Html::redirect(Ticket::getSearchURL());
}

Html::header(__('Solicitação encaminhada', 'projectplus'), FrontUrl::to(basename(__FILE__)), 'helpdesk', 'ticket');
echo "<div class='container-xl'><div class='card'><div class='card-header'><h3 class='card-title'><i class='ti ti-route'></i> "
   . ConfigPage::escape(__('Solicitação encaminhada', 'projectplus'))
   . "</h3></div><div class='card-body'><div class='alert alert-info mb-3'>"
   . nl2br(ConfigPage::escape(RoutingNotice::format($notice)))
   . "</div><dl class='row'><dt class='col-sm-3'>" . __('Entidade de destino', 'projectplus') . "</dt><dd class='col-sm-9'>"
   . ConfigPage::escape($notice['entity']) . "</dd><dt class='col-sm-3'>" . __('Fase atual', 'projectplus') . "</dt><dd class='col-sm-9'>"
   . ConfigPage::escape($notice['phase']) . "</dd></dl><a class='btn btn-primary' href='"
   . ConfigPage::escape(Ticket::getSearchURL()) . "'><i class='ti ti-list'></i> " . __('Voltar para chamados', 'projectplus')
   . '</a></div></div></div>';
Html::footer();
