<?php

namespace GlpiPlugin\Projectplus;

use CommonDBTM;
use DBmysql;
use Document;
use Document_Item;
use Html;
use Project;
use Session;
use Ticket;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Campo de anexo dos formularios configuraveis.
 *
 * O arquivo nunca trafega pelo plugin: o upload usa o mesmo pipeline nativo do
 * GLPI (ajax/fileupload.php -> GLPI_TMP_DIR -> Document), de modo que a lista de
 * extensoes permitidas, o limite de tamanho, o caminho de armazenamento e o
 * controle de download continuam sendo os do nucleo. A resposta gravada guarda
 * apenas os ids dos documentos criados.
 */
final class FieldAttachment
{
   public const TYPE = 'attachment';

   /** Limite por campo, para que um formulario nao vire um deposito de arquivos. */
   private const MAX_FILES = 10;

   /** Nome do campo de upload nativo associado a um campo do formulario. */
   public static function inputName(string $prefix, int $fieldId): string
   {
      return $prefix . '_file_' . $fieldId;
   }

   /** Nome do grupo de caixas que mantem os anexos ja gravados. */
   public static function keepName(string $prefix, int $fieldId): string
   {
      return $prefix . '_keep[' . $fieldId . '][]';
   }

   /** @param array<string,mixed> $field */
   public static function render(string $prefix, array $field, string $value): string
   {
      $id = (int) $field['id'];
      $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
      $documents = self::documents($value);

      $html = "<div class='projectplus-attachment'>";
      if ($documents !== []) {
         $html .= "<ul class='projectplus-attachment__list'>";
         foreach ($documents as $document) {
            $html .= "<li class='projectplus-attachment__item'>"
               . "<label class='form-check'>"
               . "<input class='form-check-input' type='checkbox' name='" . $esc(self::keepName($prefix, $id))
               . "' value='" . (int) $document['id'] . "' checked>"
               . "<span class='form-check-label'>" . $esc(__('Manter', 'projectplus')) . '</span></label>'
               . "<a target='_blank' rel='noopener' href='" . $esc($document['url']) . "'>"
               . "<i class='ti ti-paperclip'></i> " . $esc($document['name']) . '</a></li>';
         }
         $html .= '</ul><small>' . $esc(__('Desmarque um arquivo para removê-lo deste campo ao salvar.', 'projectplus')) . '</small>';
      }

      // O asterisco de obrigatorio so vale enquanto nao existe nenhum anexo:
      // depois disso o campo ja esta preenchido e exigir novo upload travaria o envio.
      $html .= Html::file([
         'name'     => self::inputName($prefix, $id),
         'multiple' => true,
         'display'  => false,
         'required' => (int) ($field['is_required'] ?? 0) === 1 && $documents === [],
      ]);

      return $html . '</div>';
   }

   /**
    * Consolida o valor do campo a partir dos anexos mantidos e dos novos uploads.
    *
    * @param array<string,mixed> $field
    * @param array<string,mixed> $post
    */
   public static function collect(string $prefix, array $field, string $current, array $post, CommonDBTM $item): string
   {
      $id = (int) $field['id'];
      $label = (string) ($field['label'] ?? '');

      // Somente ids que ja estavam gravados podem ser mantidos: assim ninguem
      // anexa um documento arbitrario do GLPI apenas forjando o formulario.
      $requested = $post[$prefix . '_keep'][$id] ?? [];
      $requested = array_map('intval', is_array($requested) ? $requested : [$requested]);
      $kept = array_values(array_intersect(self::ids($current), $requested));

      $name = self::inputName($prefix, $id);
      $uploads = $post['_' . $name] ?? [];
      $uploads = is_array($uploads) ? array_values($uploads) : [];
      $prefixes = $post['_prefix_' . $name] ?? [];
      $prefixes = is_array($prefixes) ? array_values($prefixes) : [];
      $tags = $post['_tag_' . $name] ?? [];
      $tags = is_array($tags) ? array_values($tags) : [];

      $added = [];
      foreach ($uploads as $index => $filename) {
         if (trim((string) $filename) === '') {
            continue;
         }
         if (count($kept) + count($added) >= self::MAX_FILES) {
            throw new \RuntimeException(sprintf(
               __('O campo "%1$s" aceita no máximo %2$d arquivos.', 'projectplus'),
               $label,
               self::MAX_FILES
            ));
         }
         $documentId = self::createDocument(
            (string) $filename,
            (string) ($prefixes[$index] ?? ''),
            (string) ($tags[$index] ?? ''),
            $label,
            $item
         );
         if ($documentId <= 0) {
            throw new \RuntimeException(sprintf(
               __('Não foi possível anexar o arquivo enviado no campo "%s".', 'projectplus'),
               $label
            ));
         }
         $added[] = $documentId;
      }

      return implode(',', array_merge($kept, $added));
   }

   /** @return array<int, array{id:int, name:string, url:string}> */
   public static function documents(string $value): array
   {
      global $CFG_GLPI;

      $rows = [];
      foreach (self::ids($value) as $documentId) {
         $document = new Document();
         if (!$document->getFromDB($documentId)) {
            continue;
         }
         $name = trim((string) ($document->fields['filename'] ?? ''));
         if ($name === '') {
            $name = trim((string) ($document->fields['name'] ?? ''));
         }
         $url = rtrim((string) $CFG_GLPI['root_doc'], '/') . '/front/document.send.php?docid=' . $documentId;
         // O contexto do item permite que quem enxerga o chamado/projeto baixe o
         // arquivo, sem depender do direito global de documentos.
         $link = self::linkedItem($documentId);
         if ($link !== null) {
            $url .= '&itemtype=' . rawurlencode($link['itemtype']) . '&items_id=' . $link['items_id'];
         }
         $rows[] = ['id' => $documentId, 'name' => $name, 'url' => $url];
      }
      return $rows;
   }

   /** Nomes dos arquivos, um por linha, para exibicao em texto puro (PDF). */
   public static function names(string $value): string
   {
      $names = array_map(static fn(array $document): string => $document['name'], self::documents($value));
      return implode("\n", $names);
   }

   /** @return int[] */
   public static function ids(string $value): array
   {
      $parts = preg_split('/[^0-9]+/', trim($value)) ?: [];
      $ids = array_filter(array_map('intval', $parts), static fn(int $id): bool => $id > 0);
      return array_values(array_unique($ids));
   }

   /**
    * Ids dos campos de anexo entre os informados, para formatar respostas
    * gravadas sem precisar de uma consulta por linha.
    *
    * @param int[] $fieldIds
    * @return int[]
    */
   public static function attachmentFieldIds(string $table, array $fieldIds): array
   {
      /** @var DBmysql $DB */
      global $DB;

      $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), static fn(int $id): bool => $id > 0));
      if ($fieldIds === [] || !$DB->tableExists($table)) {
         return [];
      }
      $ids = [];
      foreach ($DB->request([
         'SELECT' => ['id'],
         'FROM'   => $table,
         'WHERE'  => ['id' => $fieldIds, 'field_type' => self::TYPE],
      ]) as $row) {
         $ids[] = (int) $row['id'];
      }
      return $ids;
   }

   private static function createDocument(
      string $filename,
      string $filePrefix,
      string $tag,
      string $label,
      CommonDBTM $item
   ): int {
      $document = new Document();
      $documentName = Html::resume_text(
         sprintf(__('%1$s - %2$s'), $label !== '' ? $label : Document::getTypeName(1), $item->getNameID()),
         200
      );
      $input = [
         'entities_id'             => (int) $item->getEntityID(),
         'is_recursive'            => 0,
         'name'                    => addslashes($documentName),
         '_filename'               => [$filename],
         '_prefix_filename'        => [$filePrefix],
         '_only_if_upload_succeed' => 1,
      ];
      if ($tag !== '') {
         $input['_tag_filename'] = [$tag];
      }
      $documentId = (int) $document->add($input);
      if ($documentId <= 0) {
         return 0;
      }

      // O vinculo nativo faz o anexo aparecer na aba Documentos do chamado/projeto
      // e e o que o Document::canViewFile() consulta para liberar o download.
      $link = new Document_Item();
      $link->add([
         'documents_id' => $documentId,
         'itemtype'     => $item::getType(),
         'items_id'     => (int) $item->getID(),
         'entities_id'  => (int) $item->getEntityID(),
         'users_id'     => (int) Session::getLoginUserID(),
      ]);

      return $documentId;
   }

   /** @return array{itemtype:string, items_id:int}|null */
   private static function linkedItem(int $documentId): ?array
   {
      /** @var DBmysql $DB */
      global $DB;

      $row = $DB->request([
         'SELECT' => ['itemtype', 'items_id'],
         'FROM'   => Document_Item::getTable(),
         'WHERE'  => ['documents_id' => $documentId, 'itemtype' => [Ticket::class, Project::class]],
         'ORDER'  => ['id ASC'],
         'LIMIT'  => 1,
      ])->current();

      return $row ? ['itemtype' => (string) $row['itemtype'], 'items_id' => (int) $row['items_id']] : null;
   }
}
