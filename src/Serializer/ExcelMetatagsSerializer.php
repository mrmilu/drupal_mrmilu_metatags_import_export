<?php

namespace Drupal\mrmilu_metatags_import_export\Serializer;

use Drupal\file\FileInterface;
use Drupal\metatag\MetatagToken;
use Drupal\mrmilu_metatags_import_export\MetatagsImportExportManager;
use PhpOffice\PhpSpreadsheet\IOFactory;


class ExcelMetatagsSerializer{

  /**
   * The Metatag token.
   *
   * @var \Drupal\metatag\MetatagToken
   */
  protected $tokenService;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\mrmilu_metatags_import_export\metatagsImportExportManager
   */
  protected $metatagsImportExportManager;

  /**
   * Constructor.
   *
   * @param MetatagToken $token
   */
  public function __construct(MetatagToken $token, MetatagsImportExportManager $metatags_import_export_manager) {
    $this->tokenService = $token;
    $this->metatagsImportExportManager = $metatags_import_export_manager;
  }

  public function toExcelRow($entity, $langcode) {
    // Create basic fields
    $row = [
      'id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'url' => $entity->toUrl()->toString(),
      'h1' => $entity->label()
    ];
    // Get metatags values and merge it with basic ones
    $entityMetatags = \Drupal::service('metatag.manager')->tagsFromEntityWithDefaults($entity);
    $tokenReplacements = [$entity->getEntityTypeId() => $entity];
    foreach ($this->metatagsImportExportManager->getAllowedTags() as $tag) {
      $row[$tag] = empty($entityMetatags[$tag]) ? '' : $this->tokenService->replace($entityMetatags[$tag], $tokenReplacements, ['langcode' => $langcode]);
    }
    return $row;
  }
}