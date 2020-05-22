<?php

namespace Drupal\mrmilu_metatags_import_export;


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
}