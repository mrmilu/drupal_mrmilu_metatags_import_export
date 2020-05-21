<?php

namespace Drupal\mrmilu_metatags_import_export\Form;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mrmilu_metatags_import_export\Serializer\ExcelMetatagsSerializer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that allows to export an excel file with existing metatags.
 */
class MetatagsExportForm extends FormBase {

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
   * The Excel metatags serializer service.
   *
   * @var \Drupal\mrmilu_metatags_import_export\Serializer\ExcelMetatagsSerializer
   */
  protected $excelMetatagsSerializer;

  /**
   * MetatagsExportForm constructor.
   *
   * @param MessengerInterface $messenger
   * @param EntityTypeBundleInfo $entity_bundle_info
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param ExcelMetatagsSerializer $excel_metatags_serializer
   */
  public function __construct(MessengerInterface $messenger, EntityTypeBundleInfo $entity_bundle_info, EntityTypeManagerInterface $entity_type_manager, ExcelMetatagsSerializer $excel_metatags_serializer) {
    $this->messenger = $messenger;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->excelMetatagsSerializer = $excel_metatags_serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('mrmilu_metatags_import_export.excel_metatags_serializer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'metatags_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $entity_types = [
      'node' => 'Nodes',
      'taxonomy_term' => 'Taxonomy terms'
    ];
    foreach ($entity_types as $entity_type => $entity_label) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      $form[$entity_type] = [
        '#type' => 'checkboxes',
        '#title' => $entity_label,
        '#options' => []
      ];
      foreach ($bundles as $bundle_id => $bundle_info) {
        $form[$entity_type]['#options'][$bundle_id] = $bundle_info['label'];
      }
      // Select all checkboxes by default
      $form[$entity_type]['#default_value'] = array_keys($form[$entity_type]['#options']);
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $data = [];
      $data[] = $this->getExcelHeader();
      // Add nodes
      foreach ($form_state->getValue('node') as $bundle) {
        if (!empty($bundle)) {
          $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => $bundle]);
          foreach ($nodes as $node) {
            $data[] = $this->excelMetatagsSerializer->export($node);
          }
        }
      }
      $spread = new Spreadsheet();
      $spread->getProperties()
        ->setCreator("Mr. Milú")
        ->setTitle(\Drupal::config('system.site')->get('name') . ' metatags');

      $sheet = $spread->getActiveSheet();
      $sheet->freezePane('B2');
      $sheet->getStyle('A1:Z1')->getFont()->setBold(true);
      $sheet->getStyle('A1:Z1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('41bdf2');

      // Fill data array
      $sheet->fromArray($data);

      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment;filename="metatags.xlsx"');
      header('Cache-Control: max-age=0');
      $writer = IOFactory::createWriter($spread, 'Xlsx');
      $writer->save('php://output');
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
    }
  }

  private function getExcelHeader() {
    // @TODO Get available tags from settings
    return [
      'A1' => 'id',
      'B1' => 'entity_type',
      'C1' => 'bundle',
      'D1' => 'url',
      'E1' => 'h1',
      'F1' => 'title',
      'G1' => 'description',
      'H1' => 'og_title'
    ];
  }
}
