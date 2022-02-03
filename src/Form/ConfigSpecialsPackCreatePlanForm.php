<?php

namespace Drupal\clc_create_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Messenger\Messenger;
use Drupal\clc_create_plan\ProductsManager;

/**
 * Class ConfigSpecialsPackCreatePlanForm to configure the packages.
 */
class ConfigSpecialsPackCreatePlanForm extends FormBase {
  /**
   * Request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

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
   * Constructs a new ConfigSpecialsPackCreatePlanForm.
   *
   * @param Symfony\Component\HttpFoundation\RequestStack $request
   *   Request stack.
   * @param Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   * @param Drupal\clc_create_plan\ProductsManager $products_manager
   *   The products manager.
   */
  public function __construct(RequestStack $request, Messenger $messenger, ProductsManager $products_manager) {
    $this->request = $request;
    $this->messenger = $messenger;
    $this->productManager = $products_manager;
  }

  /**
   * Create function.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('messenger'),
      $container->get('clc_create_plan.products_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_special_pack_create_plan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $name = NULL;
    $duration = NULL;
    $price = NULL;

    // Get values from package.
    $pack_id = $this->request->getCurrentRequest()->get('pack_id');
    if (isset($pack_id)) {
      $product = $this->productManager->getProductByPackageId($pack_id);
      if (!empty($product)) {
        $name = $product->name;
        $duration = $product->duration;
        $price = $product->value;
      }
      else {
        $this->messenger()->addMessage($this->t('Referenced package does not exist'));
        $pack_id = NULL;
      }
    }

    $durations = [
      '3 Días' => '3 Días',
      '30 Días' => '30 Días',
    ];

    $form['special'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Ingresar nuevo Paquetes Especiales'),
      '#tree' => TRUE,
      'package_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Package ID'),
        '#description' => $this->t('Enter the package ID in SUMA'),
        '#default_value' => $pack_id,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#description' => $this->t('Enter the name to package special'),
        '#default_value' => $name,
      ],
      'duration' => [
        '#type' => 'select',
        '#title' => $this->t('Duration'),
        '#description' => $this->t('Select the number of days'),
        '#options' => $durations,
        '#default_value' => $duration,
      ],
      'value' => [
        '#type' => 'number',
        '#title' => $this->t('Value'),
        '#default_value' => $price,
      ],
      'save' => [
        '#prefix' => '<div class="container-submit">',
        '#suffix' => '</div>',
        '#type' => 'submit',
        '#value' => $this->t('Save new package special'),
        '#name' => 'save_special',
      ],
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

    // Save new package voice.
    if ($triggering['#name'] == 'save_special') {
      $values = $form_state->getValue('special');

      // Save the configuration.
      $fields = [
        'type' => 'special',
        'name' => $values['name'],
        'status' => 0,
        'minuts' => NULL,
        'internet' => NULL,
        'duration' => $values['duration'],
      ];

      // Fields default.
      $fields['package_id'] = $values['package_id'];
      $fields['value'] = $values['value'];
      $fields['created'] = REQUEST_TIME;

      // Validate register.
      $validate = $this->productManager->getProductByPackageId($values['package_id']);
      if ($validate) {
        $this->productManager->updateProductByPackageId($values['package_id'], $fields);
        $message = $this->t('The package has been updated.');
      }
      else {
        $this->productManager->createProduct($fields);
        $message = $this->t('The package has been created.');
      }

      // Message success.
      $this->messenger()->addMessage($message);
    }
  }

}
