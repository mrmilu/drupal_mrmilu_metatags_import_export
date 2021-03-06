<?php

namespace Drupal\mrmilu_metatags_import_export;


use Drupal\file\FileInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MetatagsImportExportManager {

  public function getAllowedTags() {
    $config = \Drupal::state()->get('mrmilu_metatags_import_export_allowed_tags');
    $allowedTags = [];
    foreach ($config as $tag => $enabled) {
      if ($enabled) {
        $allowedTags[] = $tag;
      }
    }
    return $allowedTags;
  }

  public function getExcelColumns() {
    $columns = [
      'id',
      'entity_type',
      'bundle',
      'url',
      'h1',
    ];
    foreach ($this->getAllowedTags() as $tag) {
      $columns[] = $tag;
    }
    return $columns;
  }

  public function excelToArray(FileInterface $file) {
    $fullPath = $file->get('uri')->value;
    $inputFileName = \Drupal::service('file_system')->realpath($fullPath);

    $spreadsheet = IOFactory::load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = [];
    $fieldNames = [];
    foreach ($sheet->getRowIterator() as $row) {
      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(FALSE);
      $cells = [];
      foreach ($cellIterator as $cell) {
        $cells[] = $cell->getValue();
      }
      if (empty($fieldNames)) {
        $fieldNames = $cells;
      }
      elseif(!empty($cells[0])) {
        $row =  array_combine($fieldNames, $cells);
        if (array_key_exists('', $row)) {
          unset ($row['']);
        }
        $rows[] = $row;
      }
    }
    return $rows;
  }

  public static function overrideEntityMetatags($row, $langcode, &$context) {
    if (!empty($row['id']) && !empty($row['entity_type'])) {
      $entity = \Drupal::entityTypeManager()->getStorage($row['entity_type'])->load(intval($row['id']));
      if ($entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
        // H1 is allowed to be changed although it's not a metatag
        if ($row['entity_type'] == 'node') {
          $entity->setTitle($row['h1']);
        }elseif ($row['entity_type'] == 'taxonomy_term') {
          $entity->setName($row['h1']);
        }
        // If alias changes, set automatic alias to FALSE
        if ($entity->toUrl()->toString() !== $row['url']) {
          $entity->path->pathauto = 0;
          $entity->path->alias = $row['url'];
        }
        // Delete no-metatag columns
        unset($row['id']);
        unset($row['entity_type']);
        unset($row['bundle']);
        unset($row['url']);
        unset($row['h1']);
        // Loop over entity fields to find the metatags one
        $definitions = $entity->getFieldDefinitions();
        foreach ($definitions as $fieldName => $definition) {
          if (!empty($definition->getType()) && $definition->getType() == 'metatag') {
            $entity->set($fieldName, serialize($row));
            $entity->save();
          }
        }
      }
    }
  }

  public function generateExcel($data) {
    $spread = new Spreadsheet();
    $spread->getProperties()
      ->setCreator("Mr. Milú")
      ->setTitle(\Drupal::config('system.site')->get('name') . ' metatags');
    $sheet = $spread->getActiveSheet();
    // Fill data array
    $sheet->fromArray($data);
    // Apply some styles
    $sheet->freezePane('B2');
    $highestColumn = $sheet->getHighestColumn();
    $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
    $sheet->getStyle('A1:' . $highestColumn . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('41bdf2');

    // Download file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="metatags.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = IOFactory::createWriter($spread, 'Xlsx');
    $writer->save('php://output');
  }
}