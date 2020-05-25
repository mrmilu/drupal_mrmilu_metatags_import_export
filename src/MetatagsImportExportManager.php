<?php

namespace Drupal\mrmilu_metatags_import_export;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\metatag\MetatagManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MetatagsImportExportManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The metatag.manager service.
   *
   * @var \Drupal\metatag\MetatagManagerInterface
   */
  protected $metatagManager;

  /**
   * MetatagsImportExportManager constructor.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MetatagManagerInterface $metatag_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->metatagManager = $metatag_manager;
  }

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

  public function overrideEntitiesMetatags($data, $langcode) {
    foreach ($data as $row) {
      if (!empty($row['id']) && !empty($row['entity_type'])) {
        $entity = $this->entityTypeManager->getStorage($row['entity_type'])->load($row['id']);
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
  }
}