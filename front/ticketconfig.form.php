<?php

include('../../../inc/includes.php');

use GlpiPlugin\Projectplus\ProjectProfile;
use GlpiPlugin\Projectplus\TicketCategoryConfig;

Session::checkRight('config', UPDATE);

$id = (int) ($_REQUEST['id'] ?? 0);
$config = new TicketCategoryConfig();
if ($id <= 0 || !$config->getFromDB($id)) {
   Html::displayNotFoundError();
}

$entityId = (int) $config->fields['entities_id'];
$configId = (int) $config->getID();
if (!Session::haveAccessToEntity($entityId, true)) {
   Html::displayRightError();
}

/** Validate the submitted actor against the same right used by the GLPI dropdown. */
$validateActor = static function (int $userId, string $right, string $label) use ($entityId): void {
   global $DB;

   if ($userId <= 0) {
      return;
   }

   $user = new User();
   $allowed = false;
   if ($user->getFromDB($userId)) {
      $targetAncestors = array_flip(array_map('intval', getAncestorsOf(Entity::getTable(), $entityId)));
      $targetAncestors[$entityId] = true;
      $iterator = $DB->request([
         'SELECT' => ['glpi_profiles_users.entities_id', 'glpi_profiles_users.is_recursive'],
         'FROM'   => 'glpi_profiles_users',
         'INNER JOIN' => [
            'glpi_profilerights' => [
               'ON' => [
                  'glpi_profiles_users' => 'profiles_id',
                  'glpi_profilerights' => 'profiles_id',
               ],
            ],
         ],
         'WHERE' => [
            'glpi_profiles_users.users_id' => $userId,
            'glpi_profilerights.name'      => $right,
            ['glpi_profilerights.rights' => ['>=', READ]],
         ],
      ]);
      foreach ($iterator as $profile) {
         $profileEntity = (int) $profile['entities_id'];
         if ($profileEntity === $entityId
            || ((int) $profile['is_recursive'] === 1 && isset($targetAncestors[$profileEntity]))) {
            $allowed = true;
            break;
         }
      }
   }

   if (!$allowed) {
      Session::addMessageAfterRedirect(
         sprintf(__('O usuário selecionado não possui a permissão Project+ necessária para atuar como %s.', 'projectplus'), $label),
         false,
         ERROR
      );
      Html::back();
   }
};

// This endpoint has a single write operation.  Some GLPI theme/browser
// combinations submit the form without serializing the submit button name,
// therefore the request method is the reliable indicator of a save.
// GLPI validates the CSRF token during includes.php bootstrap.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $projectManagerId = (int) ($_POST['project_manager_users_id'] ?? 0);
   $portfolioManagerId = (int) ($_POST['portfolio_manager_users_id'] ?? 0);
   $validateActor($projectManagerId, ProjectProfile::RIGHT_FILL_TIP, __('Gerente de projetos', 'projectplus'));
   $validateActor($portfolioManagerId, ProjectProfile::RIGHT_PRIORITY_STRATEGIC, __('Gestor de portfólio', 'projectplus'));
   $config->update([
      'id' => $id,
      'project_manager_users_id' => $projectManagerId,
      'portfolio_manager_users_id' => $portfolioManagerId,
      'is_recursive' => (int) ($_POST['is_recursive'] ?? 0),
      'is_active' => (int) ($_POST['is_active'] ?? 0),
      'users_id' => (int) Session::getLoginUserID(),
   ]);
   Html::redirect('ticketconfig.php');
}

Html::header(__('Configuração do fluxo de projeto', 'projectplus'), $_SERVER['PHP_SELF'], 'config', 'plugins');

$entityName = htmlspecialchars((string) Dropdown::getDropdownName(Entity::getTable(), $entityId), ENT_QUOTES, 'UTF-8');
$categoryName = htmlspecialchars((string) Dropdown::getDropdownName(ITILCategory::getTable(), (int) $config->fields['itilcategories_id']), ENT_QUOTES, 'UTF-8');

echo "<form method='post' action='" . TicketCategoryConfig::getFormURL() . "' class='mb-0'>";
// Keep these values explicit: the form URL is intentionally clean and GLPI
// must receive the loaded configuration id again on POST.  Html::hidden()
// also keeps this form aligned with GLPI's CSRF field rendering.
echo Html::hidden('id', ['value' => $configId]);
// A standalone token prevents unrelated POST/AJAX components rendered on the
// same GLPI page from consuming this form token before the administrator
// submits the configuration.
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
echo "<section class='card'>";
echo "<header class='card-header'><h3 class='card-title mb-0'><i class='ti ti-settings me-2'></i>"
   . __('Configuração da categoria do fluxo', 'projectplus') . '</h3></header>';
echo "<div class='card-body'>";
echo "<p class='text-secondary mb-4'>"
   . __('Defina os responsáveis habilitados para esta entidade e categoria. A pesquisa de usuários respeita as permissões do Project+.', 'projectplus')
   . '</p>';
echo "<div class='row g-3'>";

echo "<div class='col-md-6'><label class='form-label'>" . Entity::getTypeName(1) . "</label>"
   . "<div class='form-control bg-body-tertiary'>" . $entityName . '</div></div>';
echo "<div class='col-md-6'><label class='form-label'>" . __('Categoria ITIL', 'projectplus') . "</label>"
   . "<div class='form-control bg-body-tertiary'>" . $categoryName . '</div></div>';

echo "<div class='col-md-6'><label class='form-label' for='dropdown_project_manager_users_id'>"
   . __('Gerente de projetos', 'projectplus') . '</label>';
User::dropdown([
   'name' => 'project_manager_users_id',
   'value' => (int) ($config->fields['project_manager_users_id'] ?? 0),
   'right' => ProjectProfile::RIGHT_FILL_TIP,
   'entity' => $entityId,
   'entity_sons' => true,
   'width' => '100%',
]);
echo '</div>';

echo "<div class='col-md-6'><label class='form-label' for='dropdown_portfolio_manager_users_id'>"
   . __('Gestor de portfólio', 'projectplus') . '</label>';
User::dropdown([
   'name' => 'portfolio_manager_users_id',
   'value' => (int) ($config->fields['portfolio_manager_users_id'] ?? 0),
   'right' => ProjectProfile::RIGHT_PRIORITY_STRATEGIC,
   'entity' => $entityId,
   'entity_sons' => true,
   'width' => '100%',
]);
echo '</div>';

echo "<div class='col-md-6'><label class='form-label' for='dropdown_is_recursive'>" . __('Recursivo', 'projectplus') . '</label>';
Dropdown::showYesNo('is_recursive', (int) ($config->fields['is_recursive'] ?? 0));
echo '</div>';
echo "<div class='col-md-6'><label class='form-label' for='dropdown_is_active'>" . __('Ativo', 'projectplus') . '</label>';
Dropdown::showYesNo('is_active', (int) ($config->fields['is_active'] ?? 0));
echo '</div>';
echo '</div></div>';
echo "<footer class='card-footer d-flex justify-content-end gap-2'>"
   . Html::link(__('Cancelar'), TicketCategoryConfig::getSearchURL(), ['class' => 'btn btn-outline-secondary'])
   . "<button type='submit' class='btn btn-primary' name='save' value='1'><i class='ti ti-device-floppy me-1'></i>"
   . __('Salvar') . '</button></footer>';
echo '</section></form>';

Html::footer();
