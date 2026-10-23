<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectPriority;
use GlpiPlugin\Projectplus\FrontUrl;
use Glpi\Exception\Http\AccessDeniedHttpException;

Session::checkLoginUser();
if (!ProjectPriority::canViewPriority()) {
   throw new AccessDeniedHttpException();
}
Html::header(__('Visão do portfólio', 'projectplus'), FrontUrl::to(basename(__FILE__)), 'tools', 'project');
$projects = ProjectPriority::getPortfolioProjects();
echo "<div class='projectplus-portfolio'><header><div><h2>" . __('Visão do portfólio', 'projectplus') . '</h2><p>' . __('Projetos ordenados pelo ranking institucional.', 'projectplus') . "</p></div></header><div class='table-responsive'><table class='table table-hover table-striped'><thead><tr><th>" . __('Ranking', 'projectplus') . '</th><th>' . __('Faixa institucional', 'projectplus') . '</th><th>' . __('Projeto', 'projectplus') . '</th><th>' . __('Entidade', 'projectplus') . '</th><th>' . __('Prioridade geral GLPI', 'projectplus') . '</th><th>' . __('Concluído', 'projectplus') . '</th></tr></thead><tbody>';
foreach ($projects as $project) {
   $ranking = (int) ($project['ranking'] ?? 0);
   echo '<tr><td><strong>' . ($ranking ?: '—') . '</strong></td><td>' . htmlspecialchars(ProjectPriority::getInstitutionalRange($ranking), ENT_QUOTES, 'UTF-8') . '</td><td><a href="' . htmlspecialchars(Project::getFormURLWithID((int) $project['id']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8') . '</a></td><td>' . htmlspecialchars(Entity::getFriendlyNameById((int) $project['entities_id']), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars(CommonITILObject::getPriorityName((int) $project['priority']), ENT_QUOTES, 'UTF-8') . '</td><td>' . (int) $project['percent_done'] . '%</td></tr>';
}
if (!$projects) echo '<tr><td colspan="6">' . __('Nenhum projeto encontrado.', 'projectplus') . '</td></tr>';
echo '</tbody></table></div></div>';
Html::footer();
