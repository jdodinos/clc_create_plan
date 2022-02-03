<?php

namespace Drupal\clc_create_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\clc_create_plan\WSSuma;

/**
 * Class CheckBallancePhoneForm to check the balance.
 */
class CheckBallancePhoneForm extends FormBase {
  /**
   * Messenger.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The WS Suma.
   *
   * @var Drupal\clc_create_plan\WSSuma
   */
  protected $wsSuma;

  /**
   * Construc a new CheckBallancePhoneForm.
   *
   * @param Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   * @param Drupal\clc_create_plan\WSSuma $ws_suma
   *   The WS Suma.
   */
  public function __construct(Messenger $messenger, WSSuma $ws_suma) {
    $this->messenger = $messenger;
    $this->wsSuma = $ws_suma;
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
      $container->get('clc_create_plan.ws_suma')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'check_ballance_phone_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['cellphone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
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
    $cellphone = $form_state->getValue('cellphone');
    $this->wsSuma->sumaLogin($cellphone);

    if (!$this->wsSuma->error) {
      $this->wsSuma->getSubscriptionID();

      if (!$this->wsSuma->error) {
        $this->wsSuma->getSubscriptionBalance();

        if (!$this->wsSuma->error) {
          $message = "El saldo actual de {$cellphone} es $" . $this->wsSuma->balanceValue;
        }
      }
      else {
        $message = 'El número ingresado no esta suscrito a SUMA';
      }
    }
    else {
      $message = 'Error en Suma Login';
    }

    $this->messenger()->addMessage($message);
  }

}
