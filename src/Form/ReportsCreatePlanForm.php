<?php

namespace Drupal\clc_create_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\clc_create_plan\OrderManager;

/**
 * Class ReportsCreatePlanForm to show reports.
 */
class ReportsCreatePlanForm extends FormBase {
  /**
   * Request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The order manager.
   *
   * @var Drupal\clc_create_plan\OrderManager
   */
  protected $orderManager;

  /**
   * Constructs a new ReportsCreatePlanForm.
   *
   * @param Symfony\Component\HttpFoundation\RequestStack $request
   *   Request stack.
   * @param Drupal\clc_create_plan\OrderManager $order_manager
   *   The products manager.
   */
  public function __construct(RequestStack $request, OrderManager $order_manager) {
    $this->request = $request;
    $this->orderManager = $order_manager;
  }

  /**
   * Create function.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('clc_create_plan.orders_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'reports_create_plan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Filter order.
    $form['order_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order ID'),
      '#description' => $this->t('Filter by order ID'),
    ];

    // Filter created.
    $form['created'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Created date'),
      '#description' => $this->t('Filter by created date'),
      '#tree' => TRUE,
      'from_date' => [
        '#type' => 'date',
        '#title' => $this->t('Date from'),
      ],
      'to_date' => [
        '#type' => 'date',
        '#title' => $this->t('Date to'),
      ],
    ];

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#name' => 'filter_report',
    ];

    $order_id = $this->request->getCurrentRequest()->get('order_id');
    $created = $this->request->getCurrentRequest()->get('created');
    if ($order_id || isset($created['to_date']) || isset($created['from_date'])) {
      $filters = [
        'order_id' => $order_id,
        'to_date' => $created['to_date'],
        'from_date' => $created['from_date'],
      ];
      $orders = $this->orderManager->filterOrders($filters);

      $header = [
        'id' => $this->t('Order ID'),
        'type' => $this->t('Type'),
        'created' => $this->t('Created'),
        'buyer_email' => $this->t('Email'),
        'buyer_cellphone' => $this->t('Cellphone'),
        'terms_kalley' => $this->t('Terms Kalley Móvil'),
        'terms_suma' => $this->t('Terms SUMA'),
        'value' => $this->t('Value'),
      ];
      $rows = [];

      foreach ($orders as $value) {
        $created = date('Y, M j', $value->created);

        $rows[] = [
          'id' => $value->id,
          'type' => $value->type == 'purchase' ? 'Pago por PSE' : 'Recarga tu saldo',
          'created' => $created,
          'buyer_email' => $value->buyer_email,
          'buyer_cellphone' => $value->buyer_cellphone,
          'terms_kalley' => $value->term_kalley ? 'Aceptado' : 'Rechazado',
          'terms_suma' => $value->term_suma ? 'Aceptado' : 'Rechazado',
          'value' => $value->value,
        ];
      }
      $data = array_merge([$header], $rows);
      $form_state->setStorage($data);

      $steps_download = 'Para ver el reporte correctamente, 1. Abres Excel. 2. En la opcion Data o Datos, escoges la opcion datos externos desde un archivo o texto. 3. Seleccionas el archivo y en la ventana emergente escoges la opción delimitado. 4. En la siguiente ventana escoges Semicolon o Punto y Coma. 4. Terminar.';
      $form['download'] = [
        '#prefix' => '<div class="download-report">',
        '#suffix' => '<div>' . $steps_download . '</div></div>',
        '#type' => 'submit',
        '#value' => $this->t('Download Report'),
        '#name' => 'download_report',
      ];

      $form['table'] = [
        '#type' => 'table',
        '#title' => $this->t('Customers'),
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('Without information'),
      ];
    }

    $form['#attached'] = [
      'library' => [
        'clc_create_plan/config',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    if ($triggering_element['#name'] == 'filter_report') {
      $form_state->setRebuild(TRUE);
    }
    elseif ($triggering_element['#name'] == 'download_report') {
      $data = $form_state->getStorage();
      $this->reportsGenerateCsv($data);
    }
  }

  /**
   * Implements function ms_reports_generate.
   *
   * @param array $results
   *   The Query results.
   */
  protected function reportsGenerateCsv(array $results) {
    // Generate CSV.
    $name_report = "report_" . time();
    $tmpName = tempnam("/tmp", $name_report);
    $file = fopen($tmpName, "w");
    $count = 0;
    foreach ($results as $value) {
      $value = (array) $value;
      $value = array_map('utf8_decode', $value);

      $value = array_values($value);

      $count++;
      fputcsv($file, $value, ';');
    }
    fclose($file);
    // To Download Report.
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$tmpName}.csv");
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tmpName));
    ob_clean();
    flush();
    readfile($tmpName);
    unlink($tmpName);
    die();
  }

}
