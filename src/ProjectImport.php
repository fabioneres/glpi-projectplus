<?php

namespace GlpiPlugin\Projectplus;

use CommonITILObject;
use DBmysql;
use Entity;
use Group;
use Html;
use Profile_User;
use Project;
use ProjectState;
use ProjectType;
use Session;
use User;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class ProjectImport
{
   private const SESSION_KEY = 'plugin_projectplus_import_preview';

   private const HEADERS = [
      'ID da entidade*', 'Entidade*', 'Código*', 'Nome*', 'Data de criação', 'Prioridade', 'Status', 'Tipo',
      'Código do projeto-pai', 'Percentual finalizado', 'Cesta Portfólio', 'Ação PDTIC',
      'Login do gerente', 'Grupo gerente', 'Área do solicitante', 'Login do solicitante',
      'Login do ponto focal', 'Data planejada para começo', 'Data planejada para fim',
      'Data real do começo', 'Data real de fim', 'Descrição', 'Comentários', 'Objetivo',
      'Produto/Resultado esperado',
   ];

   public static function canManage(): bool
   {
      return Session::haveRight('config', UPDATE);
   }

   public static function getFormURL(bool $full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/import.php';
   }

   public static function getTemplateURL(bool $full = true): string
   {
      global $CFG_GLPI;
      return ($full ? rtrim($CFG_GLPI['root_doc'], '/') : '') . '/plugins/projectplus/front/import_template.php';
   }

   public static function downloadTemplate(): void
   {
      header('Content-Type: text/csv; charset=UTF-8');
      header('Content-Disposition: attachment; filename="modelo_importacao_projetos.csv"');
      header('Cache-Control: no-store, no-cache, must-revalidate');

      echo "\xEF\xBB\xBF";
      $output = fopen('php://output', 'wb');
      fputcsv($output, self::HEADERS, ';');
      fputcsv($output, array_fill(0, count(self::HEADERS), ''), ';');
      fclose($output);
      exit;
   }

   public static function preview(array $file): array
   {
      self::assertUpload($file);
      $rows = self::readCsv($file['tmp_name']);
      if (count($rows) < 2) {
         throw new \RuntimeException(__('O arquivo deve conter cabeçalho e ao menos uma linha de projeto.', 'projectplus'));
      }

      $headers = array_map([self::class, 'headerKey'], array_shift($rows));
      $expectedHeaders = array_map([self::class, 'headerKey'], self::HEADERS);
      if ($headers !== $expectedHeaders) {
         throw new \RuntimeException(__('Os cabeçalhos não correspondem ao modelo oficial de importação.', 'projectplus'));
      }

      $preview = [];
      $seen = [];
      foreach ($rows as $index => $row) {
         if (!array_filter($row, static fn($value) => self::cleanValue($value) !== '')) {
            continue;
         }
         $line = $index + 2;
         $record = array_combine(self::HEADERS, array_pad($row, count(self::HEADERS), ''));
         $result = self::validateRecord($record, $line, $seen);
         if ($result['valid']) {
            $key = $result['entity_id'] . ':' . self::cleanValue($record['Código*']);
            $seen[$key] = true;
         }
         $preview[] = $result;
      }

      if (!$preview) {
         throw new \RuntimeException(__('Nenhuma linha de projeto foi encontrada no arquivo.', 'projectplus'));
      }
      $_SESSION[self::SESSION_KEY] = ['created_at' => time(), 'rows' => $preview];
      return $_SESSION[self::SESSION_KEY];
   }

   public static function importPreview(): array
   {
      $preview = $_SESSION[self::SESSION_KEY] ?? [];
      if (!is_array($preview) || empty($preview['rows'])) {
         throw new \RuntimeException(__('Nenhuma pré-validação disponível. Envie o arquivo novamente.', 'projectplus'));
      }
      if (($preview['created_at'] ?? 0) < time() - 3600) {
         unset($_SESSION[self::SESSION_KEY]);
         throw new \RuntimeException(__('A pré-validação expirou. Envie o arquivo novamente.', 'projectplus'));
      }

      /** @var \DBmysql $DB */
      global $DB;

      $created = [];
      $errors = [];
      // The whole file is one operation: a partial load would leave projects
      // created and parent links missing, with no way to tell how far it got.
      $DB->beginTransaction();
      try {
         foreach ($preview['rows'] as $row) {
            if (!$row['valid']) {
               continue;
            }
            $project = new Project();
            $id = $project->add(self::projectInput($row));
            if ($id <= 0) {
               throw new \RuntimeException(sprintf(
                  __('Falha ao criar o Projeto da linha %s. Nenhum projeto foi importado.', 'projectplus'),
                  (string) $row['line']
               ));
            }
            $key = $row['entity_id'] . ':' . self::cleanValue($row['record']['Código*']);
            $created[$key] = $id;
         }

         foreach ($preview['rows'] as $row) {
            if (!$row['valid']) {
               continue;
            }
            $parentCode = self::cleanValue($row['record']['Código do projeto-pai']);
            if ($parentCode === '') {
               continue;
            }
            $key = $row['entity_id'] . ':' . self::cleanValue($row['record']['Código*']);
            $parentKey = $row['entity_id'] . ':' . $parentCode;
            $projectId = $created[$key] ?? 0;
            $parentId = $created[$parentKey] ?? self::findProjectByCode($row['entity_id'], $parentCode);
            if (!$projectId || !$parentId) {
               // A missing parent is a data problem in the file, not a failure
               // of the row itself: the project stays, the link is reported.
               $errors[] = ['line' => $row['line'], 'message' => __('Projeto-pai não localizado para vínculo.', 'projectplus')];
               continue;
            }
            $project = new Project();
            $project->update(['id' => $projectId, 'projects_id' => $parentId, '_disablenotif' => 1]);
         }
         $DB->commit();
      } catch (\Throwable $exception) {
         $DB->rollBack();
         throw $exception;
      }

      unset($_SESSION[self::SESSION_KEY]);
      return ['created' => $created, 'errors' => $errors];
   }

   public static function renderUploadForm(): void
   {
      echo "<section class='projectplus-import__upload'><div class='projectplus-import__section-title'><i class='ti ti-file-spreadsheet'></i><div><h3>"
         . __('Enviar planilha CSV', 'projectplus') . '</h3><p>'
         . __('Use o modelo oficial para manter os campos e cabeçalhos esperados pela importação.', 'projectplus')
         . "</p></div></div><form method='post' enctype='multipart/form-data' action='" . htmlescape(FrontUrl::to('import.php')) . "'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo "<table class='projectplus-import__legacy-table'><tr><th colspan='2'>" . __('Importar projetos existentes', 'projectplus') . '</th></tr>';
      echo "<tr class='tab_bg_1'><td>" . __('Planilha modelo', 'projectplus') . "</td><td><a class='btn btn-outline-secondary' href='"
         . htmlescape(self::getTemplateURL()) . "'>" . __('Baixar modelo CSV', 'projectplus') . '</a></td></tr>';
      echo "<tr class='tab_bg_1'><td>" . __('Arquivo CSV UTF-8', 'projectplus') . "</td><td><input type='file' name='import_file' accept='.csv,text/csv' required></td></tr>";
      echo "<tr class='tab_bg_1'><td colspan='2' class='text-muted'>" . __('Use o modelo oficial. Informe o ID da entidade e seu nome curto ou caminho completo (por exemplo, Entidade pai > Entidade filha). A operação primeiro mostra uma pré-validação; nenhum projeto é criado nesta etapa.', 'projectplus') . '</td></tr>';
      echo "<tr class='tab_bg_2'><td colspan='2' class='center'><button class='btn btn-primary' type='submit' name='action' value='preview'>" . __('Validar arquivo', 'projectplus') . '</button></td></tr>';
      echo '</table></form></section>';
   }

   public static function renderPreview(array $preview): void
   {
      $valid = count(array_filter($preview['rows'], static fn($row) => $row['valid']));
      $invalid = count($preview['rows']) - $valid;
      echo "<section class='projectplus-import__preview'><div class='projectplus-import__section-title'><i class='ti ti-list-check'></i><div><h3>"
         . __('Pré-validação da importação', 'projectplus') . '</h3><p>'
         . __('Revise o resultado antes de confirmar a criação dos projetos válidos.', 'projectplus')
         . '</p></div></div>';
      echo "<div class='center'><table class='tab_cadre_fixe'><tr><th colspan='3'>" . __('Pré-validação da importação', 'projectplus') . '</th></tr>';
      echo "<tr class='tab_bg_1'><td>" . __('Linhas válidas', 'projectplus') . "</td><td>$valid</td><td>" . __('Linhas com erro', 'projectplus') . "</td><td>$invalid</td></tr>";
      echo "<tr><th>" . __('Linha', 'projectplus') . '</th><th>' . __('Projeto', 'projectplus') . '</th><th colspan="2">' . __('Resultado', 'projectplus') . '</th></tr>';
      foreach ($preview['rows'] as $row) {
         $messages = $row['errors'] ? implode('<br>', array_map('htmlescape', $row['errors'])) : __('Pronto para importar', 'projectplus');
         echo "<tr class='tab_bg_" . ($row['valid'] ? '1' : '2') . "'><td>" . $row['line'] . '</td><td>'
            . htmlescape($row['record']['Nome*']) . '</td><td colspan="2">' . $messages . '</td></tr>';
      }
      echo '</table>';
      if ($valid) {
         echo "<form method='post' action='" . htmlescape(FrontUrl::to('import.php')) . "'>";
         echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
         echo "<button class='btn btn-success' type='submit' name='action' value='import'>" . __('Confirmar importação das linhas válidas', 'projectplus') . '</button></form>';
      }
      echo '</div></section>';
   }

   public static function renderResult(array $result): void
   {
      echo "<section class='projectplus-import__result'><div class='projectplus-import__section-title'><i class='ti ti-circle-check'></i><div><h3>"
         . __('Resultado da importação', 'projectplus') . '</h3><p>'
         . __('A importação foi concluída. Consulte abaixo o resumo do processamento.', 'projectplus')
         . '</p></div></div>';
      echo "<div class='center'><table class='tab_cadre_fixe'><tr><th colspan='2'>" . __('Resultado da importação', 'projectplus') . '</th></tr>';
      echo "<tr class='tab_bg_1'><td>" . __('Projetos criados', 'projectplus') . '</td><td>' . count($result['created']) . '</td></tr>';
      foreach ($result['errors'] as $error) {
         echo "<tr class='tab_bg_2'><td>" . sprintf(__('Linha %s', 'projectplus'), $error['line']) . '</td><td>' . htmlescape($error['message']) . '</td></tr>';
      }
      echo '</table></div></section>';
   }

   private static function validateRecord(array $record, int $line, array $seen): array
   {
      $errors = [];
      foreach (['ID da entidade*', 'Entidade*', 'Código*', 'Nome*'] as $field) {
         if (self::cleanValue($record[$field]) === '') {
            $errors[] = sprintf(__('Campo obrigatório: %s', 'projectplus'), $field);
         }
      }
      $entityId = null;
      $entityIdValue = self::cleanValue($record['ID da entidade*']);
      if (!ctype_digit($entityIdValue)) {
         $errors[] = __('ID da entidade inválido.', 'projectplus');
      } else {
         $entityId = (int) $entityIdValue;
         $entityName = self::getEntityNameById($entityId);
         if ($entityName === null) {
            $errors[] = __('ID da entidade não encontrado.', 'projectplus');
         } elseif (!self::entityNameMatches($entityId, $entityName, $record['Entidade*'])) {
            $errors[] = __('O nome da entidade não corresponde ao ID informado.', 'projectplus');
         } elseif (!Session::haveAccessToEntity($entityId)) {
            $errors[] = __('Sem acesso à entidade informada.', 'projectplus');
         }
      }
      $key = $entityId . ':' . self::cleanValue($record['Código*']);
      if ($entityId !== null && (isset($seen[$key]) || self::findProjectByCode($entityId, $record['Código*']))) {
         $errors[] = __('Já existe um Projeto com este código na entidade.', 'projectplus');
      }
      $entityForValidation = $entityId ?? 0;
      $resolved = [
         'projecttypes_id' => self::findOptional(ProjectType::getTable(), $record['Tipo'], $entityForValidation, $errors, __('Tipo', 'projectplus')),
         'projectstates_id' => self::findOptional(ProjectState::getTable(), $record['Status'], $entityForValidation, $errors, __('Status', 'projectplus')),
         'plugin_projectplus_portfoliobaskets_id' => self::findOptional(PortfolioBasket::getTable(), $record['Cesta Portfólio'], $entityForValidation, $errors, __('Cesta Portfólio', 'projectplus')),
         'plugin_projectplus_pdticactions_id' => self::findOptional(PdticAction::getTable(), $record['Ação PDTIC'], $entityForValidation, $errors, __('Ação PDTIC', 'projectplus')),
      ];
      $resolved['priority'] = self::priority($record['Prioridade'], $errors);
      $resolved['users_id'] = self::userId($record['Login do gerente'], $entityForValidation, $errors, __('Gerente', 'projectplus'));
      $resolved['groups_id'] = self::findOptional(Group::getTable(), $record['Grupo gerente'], $entityForValidation, $errors, __('Grupo gerente', 'projectplus'));
      $resolved['requester_users_id'] = self::userId($record['Login do solicitante'], $entityForValidation, $errors, __('Solicitante', 'projectplus'));
      $resolved['focal_point_users_id'] = self::userId($record['Login do ponto focal'], $entityForValidation, $errors, __('Ponto focal', 'projectplus'));
      $percent = self::percentValue($record['Percentual finalizado']);
      if ($percent !== '' && (!ctype_digit($percent) || (int) $percent > 100)) {
         $errors[] = __('Percentual finalizado deve ser um número entre 0 e 100.', 'projectplus');
      }
      foreach (['Data de criação', 'Data planejada para começo', 'Data planejada para fim', 'Data real do começo', 'Data real de fim'] as $field) {
         if (self::cleanValue($record[$field]) !== '' && !self::isDate($record[$field])) {
            $errors[] = sprintf(__('Data inválida em %s. Use AAAA-MM-DD HH:MM.', 'projectplus'), $field);
         }
      }
      return ['line' => $line, 'record' => $record, 'entity_id' => $entityId, 'resolved' => $resolved, 'errors' => $errors, 'valid' => !$errors];
   }

   private static function projectInput(array $row): array
   {
      $r = $row['record'];
      $v = $row['resolved'];
      return [
         'entities_id' => $row['entity_id'], 'name' => self::cleanValue($r['Nome*']), 'code' => self::cleanValue($r['Código*']),
         'date' => self::normalizeDate($r['Data de criação']) ?: $_SESSION['glpi_currenttime'], 'priority' => $v['priority'] ?: 3,
         'projecttypes_id' => $v['projecttypes_id'], 'projectstates_id' => $v['projectstates_id'],
         'percent_done' => (int) (self::percentValue($r['Percentual finalizado']) ?: 0), 'auto_percent_done' => 0,
         'users_id' => $v['users_id'], 'groups_id' => $v['groups_id'],
         'plan_start_date' => self::normalizeDate($r['Data planejada para começo']), 'plan_end_date' => self::normalizeDate($r['Data planejada para fim']),
         'real_start_date' => self::normalizeDate($r['Data real do começo']), 'real_end_date' => self::normalizeDate($r['Data real de fim']),
         'content' => self::cleanValue($r['Descrição']), 'comment' => self::cleanValue($r['Comentários']),
         'plugin_projectplus_portfoliobaskets_id' => $v['plugin_projectplus_portfoliobaskets_id'],
         'plugin_projectplus_pdticactions_id' => $v['plugin_projectplus_pdticactions_id'],
         'objective' => self::cleanValue($r['Objetivo']), 'expected_result' => self::cleanValue($r['Produto/Resultado esperado']),
         'requester_area' => self::cleanValue($r['Área do solicitante']), 'requester_users_id' => $v['requester_users_id'],
         'focal_point_users_id' => $v['focal_point_users_id'], '_disablenotif' => 1,
      ];
   }

   private static function assertUpload(array $file): void
   {
      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
         throw new \RuntimeException(__('Falha no envio do arquivo.', 'projectplus'));
      }
      if (($file['size'] ?? 0) > 5 * 1024 * 1024 || strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'csv') {
         throw new \RuntimeException(__('Envie um arquivo CSV UTF-8 de até 5 MB.', 'projectplus'));
      }
   }

   private static function readCsv(string $path): array
   {
      $content = (string) file_get_contents($path);
      if ($content === '') {
         return [];
      }
      if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
         $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $content);
         if ($converted !== false) {
            $content = $converted;
         }
      }

      $first = strtok($content, "\r\n");
      $delimiter = ';';
      $maxCount = -1;
      foreach ([';', ',', "\t"] as $candidate) {
         $count = substr_count((string) $first, $candidate);
         if ($count > $maxCount) {
            $delimiter = $candidate;
            $maxCount = $count;
         }
      }

      $handle = fopen('php://temp', 'r+');
      fwrite($handle, $content);
      rewind($handle);
      $rows = [];
      while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
         $rows[] = array_map([self::class, 'cleanValue'], $row);
      }
      fclose($handle);
      return $rows;
   }

   private static function cleanValue($value): string
   {
      $value = (string) $value;
      $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
      return trim($value);
   }

   private static function headerKey($value): string
   {
      $value = self::cleanValue($value);
      $value = str_replace(["\xC2\xA0", "\xEF\xBB\xBF"], ' ', $value);
      if (function_exists('iconv')) {
         $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
         if ($ascii !== false) {
            $value = $ascii;
         }
      }
      return preg_replace('/[^a-z0-9]+/i', '', strtolower($value));
   }

   private static function findOptional(string $table, $value, int $entityId, array &$errors, string $label): int
   {
      $value = self::cleanValue($value);
      if ($value === '') {
         return 0;
      }
      if ($table === ProjectState::getTable()) {
         $value = self::projectStateName($value);
      }
      $id = self::findByName($table, $value, $entityId);
      if ($id === null) {
         $errors[] = sprintf(__('%s não encontrado.', 'projectplus'), $label);
         return 0;
      }
      return $id;
   }

   private static function findByName(string $table, $name, ?int $entityId = null): ?int
   {
      /** @var DBmysql $DB */
      global $DB;
      $name = self::cleanValue($name);
      if ($name === '') {
         return 0;
      }
      $where = [];
      $entityAwareTables = [
         Group::getTable(),
         PortfolioBasket::getTable(),
         PdticAction::getTable(),
      ];
      if ($entityId !== null && $entityId > 0 && in_array($table, $entityAwareTables, true)) {
         $where['entities_id'] = [$entityId, 0];
      }
      $matches = [];
      foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => $table, 'WHERE' => $where]) as $row) {
         if (self::headerKey($row['name']) === self::headerKey($name)) {
            $matches[] = (int) $row['id'];
         }
      }
      if (count($matches) !== 1) {
         return null;
      }
      return $matches[0];
   }

   private static function getEntityNameById(int $entityId): ?string
   {
      /** @var DBmysql $DB */
      global $DB;

      foreach ($DB->request([
         'SELECT' => ['name'],
         'FROM'   => Entity::getTable(),
         'WHERE'  => ['id' => $entityId],
         'LIMIT'  => 1,
      ]) as $entity) {
         return (string) $entity['name'];
      }
      return null;
   }

   private static function entityNameMatches(int $entityId, string $shortName, $providedName): bool
   {
      $providedKey = self::headerKey($providedName);
      if ($providedKey === self::headerKey($shortName)) {
         return true;
      }

      $fullName = Entity::getFriendlyNameById($entityId);
      return $providedKey !== '' && $providedKey === self::headerKey($fullName);
   }

   private static function userId($login, int $entityId, array &$errors, string $label): int
   {
      $login = self::cleanValue($login);
      if ($login === '') {
         return 0;
      }
      $id = (int) User::getIdByName($login);
      $user = new User();
      if (!$id || !$user->getFromDB($id) || empty($user->fields['is_active']) || !in_array($entityId, Profile_User::getUserEntities($id, true))) {
         $errors[] = sprintf(__('%s inválido, inativo ou sem acesso à entidade.', 'projectplus'), $label);
         return 0;
      }
      return $id;
   }

   private static function priority($value, array &$errors): int
   {
      $value = self::cleanValue($value);
      if ($value === '') {
         return 0;
      }
      $map = ['muito baixa' => 1, 'baixa' => 2, 'média' => 3, 'media' => 3, 'alta' => 4, 'muito alta' => 5];
      $key = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
      if (!isset($map[$key])) {
         $errors[] = __('Prioridade inválida.', 'projectplus');
         return 0;
      }
      return $map[$key];
   }

   private static function isDate($value): bool
   {
      return self::normalizeDate($value) !== '';
   }

   private static function normalizeDate($value): string
   {
      $value = self::cleanValue($value);
      if ($value === '') {
         return '';
      }
      foreach (['Y-m-d H:i', 'Y-m-d H:i:s', 'Y/m/d H:i', 'Y/m/d H:i:s', 'd/m/Y H:i', 'd/m/Y H:i:s'] as $format) {
         $date = \DateTime::createFromFormat($format, $value);
         if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d H:i:s');
         }
      }
      return '';
   }

   private static function percentValue($value): string
   {
      return rtrim(self::cleanValue($value), "% \t");
   }

   private static function projectStateName(string $value): string
   {
      $aliases = [
         'novo' => 'New',
         'new' => 'New',
         'executando' => 'Processing',
         'emprocessamento' => 'Processing',
         'processing' => 'Processing',
         'encerrado' => 'Closed',
         'fechado' => 'Closed',
         'closed' => 'Closed',
      ];
      return $aliases[self::headerKey($value)] ?? $value;
   }

   private static function findProjectByCode(int $entityId, string $code): int
   {
      $project = new Project();
      return $project->getFromDBByCrit(['entities_id' => $entityId, 'code' => self::cleanValue($code), 'is_deleted' => 0]) ? (int) $project->getID() : 0;
   }
}
