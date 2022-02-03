<?php

namespace Drupal\clc_create_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\clc_create_plan\ProductsManager;

/**
 * Class ConfigCreatePlanForm to configure the packages.
 */
class ConfigCreatePlanForm extends FormBase {
  /**
   * Messenger.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The products manager.
   *
   * @var Drupal\clc_create_plan\ProductsManager
   */
  protected $productManager;

  /**
   * Construc a new ConfigCreatePlanForm.
   *
   * @param Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   * @param Drupal\clc_create_plan\ProductsManager $products_manager
   *   The products manager.
   */
  public function __construct(Messenger $messenger, ProductsManager $products_manager) {
    $this->messenger = $messenger;
    $this->productManager = $products_manager;
  }

  /**
   * Create function.
   *
   * @param Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('clc_create_plan.products_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_create_plan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Routes to redirect for edit product.
    $routes = [
      'voice' => 'config_voice_create_plan_form',
      'special' => 'config_special_packs_create_plan_form',
      'product' => 'config_product_create_plan_form',
      'internet' => 'config_data_create_plan_form',
    ];
    $header = [
      'edit' => $this->t('Options'),
      'id' => $this->t('Package ID'),
      'name' => $this->t('Name'),
      'status' => $this->t('Status'),
      'minuts' => $this->t('Minuts'),
      'internet' => $this->t('Internet'),
      'duration' => $this->t('Duration'),
      'value' => $this->t('Value'),
    ];
    $rows = [];

    $products = $this->productManager->getAllProducts();
    if (!empty($products)) {
      foreach ($products as $value) {
        $status = 'Deshabilitado';
        if ($value->status) {
          $status = 'Activo';
        }

        $label_minuts = '';
        if ($value->minuts) {
          $label_minuts = $value->minuts . ' Min';

          if ($value->minuts >= 20000) {
            $label_minuts = 'ILIMITADO';
          }
        }

        // Create link to configuration.
        $url = Url::fromRoute('clc_create_plan.' . $routes[$value->type], ['pack_id' => $value->package_id]);
        $link = Link::fromTextAndUrl($this->t('Edit'), $url)->toRenderable();
        $rows[$value->id] = [
          'edit' => render($link),
          'id' => $value->package_id,
          'name' => $value->name ? $value->name : '',
          'status' => $status,
          'minuts' => $label_minuts,
          'internet' => $value->internet ? $value->internet . ' MB' : '',
          'duration' => $value->duration ? $value->duration : '',
          'value' => $value->value ? $value->value : '',
        ];
      }
    }

    $form['table'] = [
      '#type' => 'tableselect',
      '#title' => $this->t('Users'),
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('Without information'),
    ];

    $form['options'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a option'),
      '#options' => [
        0 => $this->t('Disable'),
        1 => $this->t('Activate'),
        2 => $this->t('Delete'),
      ],
    ];
    $form['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
      '#name' => 'btn_execute',
    ];

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
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();

    if ($triggering['#name'] == 'btn_execute') {
      $status = $form_state->getValue('options');
      $value_table = $form_state->getValue('table');
      $packs = array_filter($value_table);

      // Option to delete products.
      if ($status == 2) {
        $this->productManager->deleteMultipleProducts($packs);
      }
      // Option to update products.
      else {
        $this->productManager->updateStatusMultipleProducts($packs, $status);
      }

      // Message success.
      $this->messenger()->addMessage($this->t('The configuration has been updating successfully.'));
    }
  }

}
