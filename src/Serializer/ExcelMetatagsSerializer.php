<?php

namespace Drupal\mrmilu_metatags_import_export\Serializer;

use Drupal\metatag\MetatagToken;


class ExcelMetatagsSerializer{

  /**
   * The Metatag token.
   *
   * @var \Drupal\metatag\MetatagToken
   */
  protected $tokenService;

  /**
   * Constructor.
   *
   * @param MetatagToken $token
   */
  public function __construct(MetatagToken $token) {
    $this->tokenService = $token;
  }

  public function export($entity) {
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
    // @TODO Get available tags from settings
    $allowedMetatags = ['title', 'description', 'og_title'];
    foreach ($allowedMetatags as $key) {
      $row[$key] = $this->tokenService->replace($entityMetatags[$key], $tokenReplacements);
    }
    return $row;
  }
}