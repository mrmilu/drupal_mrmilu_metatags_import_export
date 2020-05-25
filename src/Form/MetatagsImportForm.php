<?php

namespace Drupal\mrmilu_metatags_import_export\Form;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mrmilu_metatags_import_export\MetatagsImportExportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that allows to export an excel file with existing metatags.
 */
class MetatagsImportForm extends FormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity_type.bundle.info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\mrmilu_metatags_import_export\metatagsImportExportManager
   */
  protected $metatagsImportExportManager;

  /**
   * MetatagsExportForm constructor.
   *
   * @param MessengerInterface $messenger
   * @param EntityTypeBundleInfo $entity_bundle_info
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param MetatagsImportExportManager $metatags_import_export_manager
   */
  public function __construct(MessengerInterface $messenger, EntityTypeBundleInfo $entity_bundle_info, EntityTypeManagerInterface $entity_type_manager, MetatagsImportExportManager $metatags_import_export_manager) {
    $this->messenger = $messenger;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->metatagsImportExportManager = $metatags_import_export_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('mrmilu_metatags_import_export.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'metatags_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $form['langcode'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Language'),
      '#languages' => LanguageInterface::STATE_CONFIGURABLE,
      '#default_value' => 'es'
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Excel file'),
      '#upload_location' => 'public://mrmilu_metatags',
      '#upload_validators' => [
        'file_validate_extensions' => ['xlsx'],
      ]
    ];
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    );
    $form_state->disableCache();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $fileId = $form_state->getValue('file')[0];
      $file = $this->entityTypeManager->getStorage('file')->load($fileId);
      $dataArray = $this->metatagsImportExportManager->excelToArray($file);
      $this->metatagsImportExportManager->overrideEntitiesMetatags($dataArray, $form_state->getValue('langcode'));
      $this->messenger->addMessage('Metatags imported successfully');
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
    }
  }
}
