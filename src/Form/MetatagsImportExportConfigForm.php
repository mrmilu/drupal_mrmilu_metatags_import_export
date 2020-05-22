<?php

namespace Drupal\mrmilu_metatags_import_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\metatag\MetatagManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for Paragraphs library item settings.
 */
class MetatagsImportExportConfigForm extends FormBase {

  /**
   * The Metatag manager.
   *
   * @var \Drupal\metatag\MetatagManagerInterface
   */
  protected $metatagManager;

  /**
   * ToolbarSettingsForm constructor.
   *
   * @param MetatagManagerInterface $metatagManager
   */
  public function __construct(MetatagManagerInterface $metatagManager) {
    $this->metatagManager = $metatagManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('metatag.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mrmilu_metatags_import_export_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state()->get('mrmilu_metatags_import_export_allowed_tags');
    $groupsAndTags = $this->metatagManager->sortedGroupsWithTags();
    foreach ($groupsAndTags as $groupId => $group) {
      if ($groupId == 'advanced') {
        continue;
      }
      $form[$groupId] = [
        '#type' => 'details',
        '#title' => $group['label'],
        '#description' => $group['description'],
        '#open' => FALSE
      ];
      if (!empty($group['tags'])) {
        foreach ($group['tags'] as $tagId => $tag) {
          $form[$groupId][$tagId] = [
            '#type' => 'checkbox',
            '#title' => $tagId,
            '#default_value' => $config[$tagId],
          ];
        }
      }
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    \Drupal::state()->set('mrmilu_metatags_import_export_allowed_tags', $form_state->getValues());
  }
}
