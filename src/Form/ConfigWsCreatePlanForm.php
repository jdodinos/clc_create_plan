<?php

namespace Drupal\clc_create_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\State;
use Drupal\Core\Messenger\Messenger;

/**
 * Class ConfigWsCreatePlanForm to configure the packages.
 */
class ConfigWsCreatePlanForm extends FormBase {
  /**
   * State.
   *
   * @var Drupal\Core\State\State
   */
  protected $state;

  /**
   * Messenger.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Construc a new ConfigWsCreatePlanForm.
   *
   * @param Drupal\Core\State\State $state
   *   The state.
   * @param Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   */
  public function __construct(State $state, Messenger $messenger) {
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * Create function.
   *
   * @param Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_ws_create_plan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->state->get('suma_ws_config', []);

    // WS Configuraction.
    $form['ws_config'] = [
      '#type' => 'details',
      '#title' => $this->t('SUMA Web Service PSE'),
      '#open' => FALSE,
      '#tree' => TRUE,
      'wsurl' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service URL'),
        '#description' => $this->t('Enter the main URL'),
        '#default_value' => isset($config['ws_url']) ? $config['ws_url'] : NULL,
        '#required' => TRUE,
      ],
      'wsnamespace' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service NameSpace'),
        '#description' => $this->t('Enter the NameSpace URL'),
        '#default_value' => isset($config['ws_namespace']) ? $config['ws_namespace'] : NULL,
        '#required' => TRUE,
      ],
      'wsuser' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service User'),
        '#description' => $this->t('Enter the User Web Service'),
        '#default_value' => isset($config['ws_user']) ? $config['ws_user'] : NULL,
        '#required' => TRUE,
      ],
      'wspass' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service User Password'),
        '#description' => $this->t('Enter the User Password in Web Service'),
        '#default_value' => isset($config['ws_password']) ? $config['ws_password'] : NULL,
        '#required' => TRUE,
      ],
      'wsbrand_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service Brand ID'),
        '#description' => $this->t('Enter the Brand ID Web Service'),
        '#default_value' => isset($config['ws_brand_id']) ? $config['ws_brand_id'] : NULL,
        '#required' => TRUE,
      ],
      'wsredirect_ok' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL To redirect OK'),
        '#description' => $this->t('URL when the service responde successfully'),
        '#default_value' => isset($config['ws_redirect_ok']) ? $config['ws_redirect_ok'] : NULL,
        '#required' => TRUE,
      ],
      'wsredirect_nok' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL To redirect Not OK'),
        '#description' => $this->t('URL when the service responde with error'),
        '#default_value' => isset($config['ws_redirect_nok']) ? $config['ws_redirect_nok'] : NULL,
        '#required' => TRUE,
      ],
      'terms_suma' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL Terms and conditions SUMA'),
        '#default_value' => isset($config['terms_suma']) ? $config['terms_suma'] : NULL,
        '#required' => TRUE,
      ],
      'policy_suma' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL de políticas y protección de datos de SUMA'),
        '#default_value' => isset($config['policy_suma']) ? $config['policy_suma'] : NULL,
        '#required' => TRUE,
      ],
      'terms_kalley' => [
        '#type' => 'textfield',
        '#title' => $this->t('Terms and conditions Kalley Móvil'),
        '#autocomplete_route_name' => 'clc_habeas_data.autocomplete_contents',
        '#placeholder' => $this->t('Enter the content name of terms and conditions'),
        '#description' => $this->t('Just accept contents of type basic page'),
        '#default_value' => isset($config['kalley_terms']) ? $config['kalley_terms'] : NULL,
      ],
    ];

    // WS Configuraction.
    $form['ws_config_charge'] = [
      '#type' => 'details',
      '#title' => $this->t('SUMA Web Service Recargas'),
      '#open' => FALSE,
      '#tree' => TRUE,
      'wsuser_charger' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service User'),
        '#description' => $this->t('Enter the User Web Service'),
        '#default_value' => isset($config['ws_user_charge']) ? $config['ws_user_charge'] : NULL,
        '#required' => TRUE,
      ],
      'wspass_charger' => [
        '#type' => 'textfield',
        '#title' => $this->t('Web Service User Password'),
        '#description' => $this->t('Enter the User Password in Web Service'),
        '#default_value' => isset($config['ws_password_charge']) ? $config['ws_password_charge'] : NULL,
        '#required' => TRUE,
      ],
      'wsredirect_ok_charge' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL To redirect OK') . ' ' . $this->t('charge'),
        '#autocomplete_route_name' => 'clc_habeas_data.autocomplete_contents',
        '#placeholder' => $this->t('Enter the content name'),
        '#description' => $this->t('URL when the charge is successfully'),
        '#default_value' => isset($config['ws_redirect_ok_charge']) ? $config['ws_redirect_ok_charge'] : NULL,
      ],
      'wsredirect_nok_charge' => [
        '#type' => 'textfield',
        '#title' => $this->t('URL To redirect Not OK') . ' ' . $this->t('charge'),
        '#autocomplete_route_name' => 'clc_habeas_data.autocomplete_contents',
        '#placeholder' => $this->t('Enter the content name'),
        '#description' => $this->t('URL when the charge is successfully'),
        '#default_value' => isset($config['ws_redirect_nok_charge']) ? $config['ws_redirect_nok_charge'] : NULL,
      ],
    ];

    $form['save'] = [
      '#prefix' => '<div class="container-submit">',
      '#suffix' => '</div>',
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save_ws_config',
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
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();

    if ($triggering['#name'] == 'save_ws_config') {
      $values = $form_state->getValue('ws_config');
      $values_charge = $form_state->getValue('ws_config_charge');
      $ws_config = [
        'ws_url' => $values['wsurl'],
        'ws_namespace' => $values['wsnamespace'],
        'ws_user' => $values['wsuser'],
        'ws_password' => $values['wspass'],
        'ws_brand_id' => $values['wsbrand_id'],
        'ws_redirect_ok' => $values['wsredirect_ok'],
        'ws_redirect_nok' => $values['wsredirect_nok'],
        'terms_suma' => $values['terms_suma'],
        'policy_suma' => $values['policy_suma'],
        'kalley_terms' => $values['terms_kalley'],
        'ws_user_charge' => $values_charge['wsuser_charger'],
        'ws_password_charge' => $values_charge['wspass_charger'],
        'ws_redirect_ok_charge' => $values_charge['wsredirect_ok_charge'],
        'ws_redirect_nok_charge' => $values_charge['wsredirect_nok_charge'],
      ];
      $this->state->set('suma_ws_config', $ws_config);

      // Message success.
      $this->messenger()->addMessage($this->t('The configuration has been updated successfully'));
    }
  }

}
