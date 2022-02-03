<?php

namespace Drupal\clc_create_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\State;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\clc_create_plan\ProductsManager;

/**
 * Class CreatePlanForm create the form to build packages.
 */
class CreatePlanForm extends FormBase {
  /**
   * State.
   *
   * @var Drupal\Core\State\State
   */
  protected $state;

  /**
   * The rederer interface.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  public $request;

  /**
   * The products manager.
   *
   * @var Drupal\clc_create_plan\ProductsManager
   */
  protected $productManager;

  /**
   * Construc a new CreatePlanForm.
   *
   * @param Drupal\Core\State\State $state
   *   The state.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render interface.
   * @param Symfony\Component\HttpFoundation\RequestStack $request
   *   Request stack.
   * @param Drupal\clc_create_plan\ProductsManager $products_manager
   *   The products manager.
   */
  public function __construct(State $state, RendererInterface $renderer, RequestStack $request, ProductsManager $products_manager) {
    $this->state = $state;
    $this->renderer = $renderer;
    $this->request = $request->getCurrentRequest();
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
      $container->get('state'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('clc_create_plan.products_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_plan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Structure to Create plan block.
    $this->productStructure($form, $form_state);

    // Structure to Special packages block.
    $this->specialPackagesStructure($form, $form_state);

    // Structure to Special packages block.
    $this->packagesStructure($form, $form_state);

    $create_plan_link = Url::fromRoute('clc_create_plan.create_plan_form');
    $buy_it_link = Link::fromTextAndUrl($this->t('Buy it'), $create_plan_link)->toRenderable();
    $buy_it_link['#attributes'] = ['class' => ['goto-summary']];
    $form['total'] = [
      '#type' => 'container',
      'value' => [
        '#prefix' => '<div class="order-value">',
        '#suffix' => '</div>',
        '#markup' => '<span class="currency-symbol">$</span><span class="value"></span> pesos',
      ],
      'btn_buy' => [
        '#markup' => $this->renderer->render($buy_it_link),
      ],
    ];

    // Structure to summary block.
    $this->summaryStructure($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (empty($values['redirect_url'])) {
      $triggering_element = $values['btn_triggering'];
      $msg_error = '¡No se pudo completar tu pago! - Por favor inténtelo de nuevo.';

      if ($triggering_element == 'btn_send' || $triggering_element == 'btn_charge') {
        $response = [];
        $wsSuma = \Drupal::service('clc_create_plan.ws_suma');

        // Validate configuration to consume Web Service.
        if (!$wsSuma->error) {
          $wsSuma->sumaLogin($values['cellphone'], $triggering_element);

          // Validate the service consumed upon login.
          if (!$wsSuma->error) {
            $wsSuma->getSubscriptionID();

            // Validate the service consumed upon get subscription.
            if (!$wsSuma->error) {
              if ($triggering_element == 'btn_charge') {
                $wsSuma->getSubscriptionBalance($values['order_value']);
              }

              if (!$wsSuma->error) {
                $packages_selected = explode('|', $values['packages_id']);
                foreach ($packages_selected as $package_id) {
                  $wsSuma->createOrderDistribution($package_id);
                  $response[$package_id] = $wsSuma->resultCode;
                }

                // Validate the service consumed upon create order distribution.
                if (!$wsSuma->error) {
                  switch ($triggering_element) {
                    case 'btn_send':
                      $response[] = $wsSuma->payOrder();
                      break;

                    case 'btn_charge':
                      $response[] = $wsSuma->applyOrderDistribution();
                      break;
                  }

                  if (!$wsSuma->error) {
                    $form_state->setStorage($response);
                  }
                }
              }
            }
            else {
              $msg_error = 'El número ingresado no está activo en Kalley Movil. Verificalo e intenta de nuevo';
            }
          }
          else {
            $msg_error = 'No exite la configuración mínima para conexión con la pasarela. El login en SUMA ha fallado. Contacta al administrador del sitio.';
          }
        }
        else {
          $msg_error = 'No exite la configuración mínima para conexión con la pasarela. Contacta al administrador del sitio.';
        }
      }

      // Validate errors validated in the Web Service.
      if ($wsSuma->error) {
        $form_state->setStorage(['error' => TRUE]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $config = $this->state->get('suma_ws_config', []);

    if (isset($storage['error']) && $storage['error']) {
      $redirect_nok = $config['ws_redirect_nok_charge'];
      $nid_pos = strpos($redirect_nok, ' (') + 2;
      $nid = substr($redirect_nok, $nid_pos, -1);
      $options = ['absolute' => TRUE];
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options)->toString();

      $form_state->setResponse(new TrustedRedirectResponse($url, 302));
    }
    else {
      $values = $form_state->getValues();
      $triggering_element = $values['btn_triggering'];

      if ($triggering_element == 'btn_send' || $triggering_element == 'btn_charge') {
        $time = REQUEST_TIME;
        $type = $triggering_element == 'btn_send' ? 'purchase' : 'charge';

        // Fields default.
        $fields = [
          'status' => 1,
          'buyer_cellphone' => $values['cellphone'],
          'buyer_email' => $values['email'],
          'value' => $values['order_value'],
          'term_kalley' => $values['terms_data_kalley'],
          'term_suma' => $values['terms_data_suma'],
          'created' => $time,
          'changed' => $time,
          'type' => $type,
        ];

        // Save de register.
        $conn = \Drupal::database();
        $query = $conn->insert('clc_create_plan_order')
          ->fields($fields)
          ->execute();

        // Order ID.
        $conn = \Drupal::database();
        $query = $conn->select('clc_create_plan_order', 'ccpd')
          ->fields('ccpd', ['id'])
          ->condition('status', 1, '=')
          ->condition('buyer_cellphone', $values['cellphone'], '=')
          ->condition('buyer_email', $values['email'], '=')
          ->condition('value', $values['order_value'], '=')
          ->condition('created', $time, '=')
          ->condition('changed', $time, '=');
        $order_id = $query->execute()->fetchField();

        if ($order_id) {
          $packages_selected = explode('|', $values['packages_id']);
          // Fields default.
          $fields = ['order_id' => $order_id];
          foreach ($packages_selected as $key => $package_id) {
            $fields['package_id'] = $package_id;

            // Save de register.
            $conn = \Drupal::database();
            $query = $conn->insert('clc_create_plan_lineitems')
              ->fields($fields)
              ->execute();
          }
        }

        if (!empty($values['redirect_url'])) {
          $form_state->setResponse(new TrustedRedirectResponse($values['redirect_url'], 302));
        }
        else {
          $redirect_ok = $config['ws_redirect_ok_charge'];
          $nid_pos = strpos($redirect_ok, ' (') + 2;
          $nid = substr($redirect_ok, $nid_pos, -1);
          $options = ['absolute' => TRUE];
          $url = Url::fromRoute('entity.node.canonical', ['node' => $nid], $options)->toString();

          $form_state->setResponse(new TrustedRedirectResponse($url, 302));
        }
      }
    }
  }

  /**
   * Create structure to Create plan block.
   */
  protected function productStructure(array &$form, FormStateInterface $form_state) {
    // Duration or validity of product-type products.
    $product_validity = $this->productManager->getDurationByType('product');

    if (!empty($product_validity)) {
      $activate_class = "block-enable";
      $form['products'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container-block']],
        'tab_validity' => [
          '#prefix' => '<ul class="tab-validity">',
          '#suffix' => '</ul>',
        ],
      ];

      $create_plan_link = Url::fromRoute('clc_create_plan.create_plan_form');
      foreach ($product_validity as $value) {
        $duration = $value->duration;
        $duration_class = str_replace('í', 'i', $duration);
        $duration_class = Html::getUniqueId($duration_class);
        // Create link to menu item.
        $link = Link::fromTextAndUrl($duration, $create_plan_link)->toRenderable();
        $link['#attributes']['data-validity'] = $duration_class;
        $link = $this->renderer->render($link);
        $form['products']['tab_validity'][] = [
          '#prefix' => '<li class="tab-validity__item ' . $activate_class . '">',
          '#suffix' => '</li">',
          '#markup' => $link,
        ];

        // Get minutes and internet products.
        $prod_minutes = $this->productManager->getDataProduct('minuts', 'product', $duration);
        $prod_internet = $this->productManager->getDataProduct('internet', 'product', $duration);

        // Structure to minutes.
        $options_minuts = [
          'default' => 0,
          'values' => [],
        ];
        foreach ($prod_minutes as $value) {
          $minut = $value->minuts;
          $label = $minut . ' MIN';
          $this->formatStrongLabel($label);
          if ($minut >= 20000) {
            $label = 'ILIMITADO';
          }

          // Set default value information.
          if ($options_minuts['default'] == 0 && $activate_class == 'block-enable') {
            $options_minuts['default'] = $minut;
          }
          $options_minuts['values'][$minut] = $label;
        }

        // Structure to internet.
        $options_mb = [
          'default' => 0,
          'values' => [],
        ];
        foreach ($prod_internet as $value) {
          // Get internet data.
          $info_key = $value->internet;
          $info = $value->internet;

          $label = $info . ' MB';
          $this->formatStrongLabel($label);
          // Format internet data in GB.
          if ($info >= 1024) {
            $info = $info / 1024;
            $this->formatStrongLabel($info);
            $label = $info . ' GB';
          }

          // Set values information.
          if ($options_mb['default'] == 0 && $activate_class == 'block-enable') {
            $options_mb['default'] = $info_key;
          }
          $options_mb['values'][$info_key] = $label;
        }

        $form['products'][$duration_class] = [
          '#type' => 'container',
          '#attributes' => ['class' => [$duration_class, $activate_class]],
          'minuts' => [
            '#type' => 'radios',
            '#title' => $this->t('Select your minuts'),
            '#options' => $options_minuts['values'],
            '#default_value' => $options_minuts['default'],
            '#description' => $this->t('Validity to @d', ['@d' => $duration]),
          ],
          'internet' => [
            '#type' => 'radios',
            '#title' => $this->t('Select your MB'),
            '#options' => $options_mb['values'],
            '#default_value' => $options_mb['default'],
            '#description' => $this->t('Validity to @d', ['@d' => $duration]),
          ],
        ];

        $activate_class = 'block-hide';
      }
    }
  }

  /**
   * Create structure to Special packages block.
   */
  protected function specialPackagesStructure(array &$form, FormStateInterface $form_state) {
    // Get packages.
    $fields = ['package_id', 'name', 'duration'];
    $products = $this->productManager->getDataPackages($fields, 'special');
    if (!empty($products)) {
      // Format duration in HTML.
      $this->formatStrongLabel($products[0]->duration);
      if (isset($products[1]->duration)) {
        $this->formatStrongLabel($products[1]->duration);
      }
      if (isset($products[2]->duration)) {
        $this->formatStrongLabel($products[2]->duration);
      }
      if (isset($products[3]->duration)) {
        $this->formatStrongLabel($products[3]->duration);
      }

      $form['specials'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Select your additionals'),
        'face_what' => [
          '#prefix' => '<div class="container-face-what">',
          '#suffix' => '</div>',
          'input' => [
            '#type' => 'checkbox',
            '#title' => $products[0]->name,
            '#option' => TRUE,
            '#attributes' => [
              'class' => ['check-face-what'],
              'packageid' => $products[0]->package_id,
            ],
          ],
          'duration' => [
            '#prefix' => '<div class="duration">',
            '#suffix' => '</div>',
            '#markup' => 'Vigencia hasta ' . $products[0]->duration,
          ],
        ],
        'inst_face_what' => [
          '#prefix' => '<div class="container-inst-face-what">',
          '#suffix' => '</div>',
          'input' => [
            '#type' => 'checkbox',
            '#title' => $products[1]->name,
            '#option' => TRUE,
            '#attributes' => [
              'class' => ['check-inst-face-what'],
              'packageid' => $products[2]->package_id,
            ],
          ],
          'duration' => [
            '#prefix' => '<div class="duration">',
            '#suffix' => '</div>',
            '#type' => 'radios',
            '#title' => 'Selecciona la vigencia hasta',
            '#options' => [
              $products[1]->package_id => $products[1]->duration,
              $products[2]->package_id => $products[2]->duration,
            ],
          ],
          'tooltip' => [
            '#prefix' => '<div class="pk-tooltip">',
            '#suffix' => '</div>',
            '#markup' => 'Navega en tus redes sociales sin consumir de tus datos.',
          ],
        ],
        'sms' => [
          '#prefix' => '<div class="container-sms">',
          '#suffix' => '</div>',
          'input' => [
            '#type' => 'checkbox',
            '#title' => $products[3]->name,
            '#option' => TRUE,
            '#attributes' => [
              'class' => ['check-sms'],
              'packageid' => $products[3]->package_id,
            ],
          ],
          'duration' => [
            '#prefix' => '<div class="duration">',
            '#suffix' => '</div>',
            '#markup' => 'Vigencia hasta ' . $products[3]->duration,
          ],
          'tooltip' => [
            '#prefix' => '<div class="pk-tooltip">',
            '#suffix' => '</div>',
            '#markup' => 'Valido a todo operador nacional.',
          ],
        ],
      ];
    }
  }

  /**
   * Create structure to Packages block.
   */
  protected function packagesStructure(array &$form, FormStateInterface $form_state) {
    $form['packages'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-block', 'container-disabled']],
      'wrapper_title' => [
        '#prefix' => '<div class="title-products-packages">',
        '#suffix' => '</div>',
        'title' => [
          '#prefix' => '<h4>',
          '#suffix' => '</h4>',
          '#markup' => $this->t('Select the next options if just wish data or minutes'),
        ],
        'description' => [
          '#prefix' => '<div>',
          '#suffix' => '</div>',
          '#markup' => $this->t('Don`t forget to choose any additional options'),
        ],
      ],
    ];

    // Select minuts packages activated.
    $fields = ['package_id', 'minuts', 'duration'];
    $only_minuts = $this->productManager->getDataPackages($fields, 'voice');
    if (!empty($only_minuts)) {
      $options_minuts = [];
      foreach ($only_minuts as $value) {
        $minuts_id = $value->package_id;
        $minuts_quantity = $value->minuts;
        $minuts_duration = $value->duration;
        $this->formatStrongLabel($minuts_quantity);
        $this->formatStrongLabel($minuts_duration);

        $options_minuts[$minuts_id] = "{$minuts_quantity} Min a TDN hasta por {$minuts_duration}";
      }

      $form['packages']['packages_minuts'] = [
        '#type' => 'radios',
        '#title' => $this->t('Do you want only minutes?'),
        '#options' => $options_minuts,
        '#attributes' => [
          'class' => ['container-package', 'container-package-minuts'],
        ],
      ];
    }

    // Select internet packages activated.
    $fields = ['package_id', 'internet', 'duration'];
    $only_data = $this->productManager->getDataPackages($fields, 'internet');
    if (!empty($only_data)) {
      $options_data = [];
      foreach ($only_data as $value) {
        $internet_id = $value->package_id;
        $internet_quantity = $value->internet;
        $internet_quantity = $internet_quantity / 1024;
        $internet_duration = $value->duration;
        $this->formatStrongLabel($internet_quantity);
        $this->formatStrongLabel($internet_duration);

        $options_data[$internet_id] = "{$internet_quantity} GB hasta por {$internet_duration}";
      }

      $form['packages']['packages_internet'] = [
        '#type' => 'radios',
        '#title' => $this->t('Do you want only data?'),
        '#options' => $options_data,
        '#attributes' => [
          'class' => ['container-package', 'container-package-internet'],
        ],
      ];
    }
  }

  /**
   * Create structure to Summary block.
   */
  protected function summaryStructure(array &$form, FormStateInterface $form_state) {
    $config = $this->state->get('suma_ws_config', []);
    $domain = $this->request->getSchemeAndHttpHost();

    // SUMA Terms policy.
    $suma_policy = $this->buildStructureTermsLink('Política de Protección de Datos', $config['policy_suma']);
    $suma_terms = $this->buildStructureTermsLink('términos y Condiciones', $config['terms_suma']);
    $kalleymovil_terms = $this->buildStructureTermsLink('Kalley Móvil', 'https://www.tdpcorbeta.com/', ['query' => ['page' => $domain]]);
    $pos_initial = strrpos($config['kalley_terms'], '(') + 1;
    $nid_terms = substr($config['kalley_terms'], $pos_initial, -1);
    $url = Url::fromRoute('entity.node.canonical',
      ['node' => $nid_terms],
      ['absolute' => TRUE]
    );
    $url = $url->toString();
    $offer_terms = $this->buildStructureTermsLink('términos y condiciones', $url);

    // Summary structure.
    $form['summary'] = [
      '#type' => 'container',
      'title' => [
        '#prefix' => '<h4>',
        '#suffix' => '</h4>',
        '#markup' => $this->t('Purchase summary'),
      ],
      'price' => [
        '#prefix' => '<div class="order-value">',
        '#suffix' => '</div>',
        '#markup' => '<span class="currency-symbol">$</span><span class="value"></span> pesos',
      ],
      'minuts' => [
        '#prefix' => '<div class="minuts-info field-summary field-hidden">',
        '#suffix' => '</div>',
        '#markup' => '<span class="summary-label">Cantidad de minutos:</span><span class="value"></span>',
      ],
      'data' => [
        '#prefix' => '<div class="data-info field-summary field-hidden">',
        '#suffix' => '</div>',
        '#markup' => '<span class="summary-label">Cantidad de datos:</span><span class="value"></span>',
      ],
      'plan' => [
        '#prefix' => '<div class="plan-info field-summary field-hidden">',
        '#suffix' => '</div>',
        '#markup' => '<span class="summary-label">Solo minutos:</span><span class="value"></span>',
      ],
      'additionals' => [
        '#prefix' => '<div class="additionals-info field-summary field-hidden">',
        '#suffix' => '</div>',
        '#markup' => '<span class="summary-label">Adicionales:</span><span class="value"></span>',
      ],
      'sms-msj-fixed' => [
        '#prefix' => '<div class="sms-msj-fixed">',
        '#suffix' => '</div>',
        '#markup' => 'Ahora todos tus paquetes incluyen SMS ilimitados a TON y Chat* de WhatsApp y Facebook que no consumen de tus datos*',
      ],
      'cellphone' => [
        '#type' => 'number',
        '#title' => 'Ingresa el número de tu línea para continuar',
        '#attributes' => [
          'class' => ['field-cellphone', 'field-required'],
          'maxlength' => 10,
          'data-name' => $this->t('Cellphone'),
        ],
      ],
      'email' => [
        '#type' => 'email',
        '#title' => 'Ingresa tu correo electrónico',
        '#attributes' => [
          'class' => ['field-email', 'field-required'],
          'maxlength' => 80,
          'data-name' => $this->t('Email'),
        ],
        '#required' => TRUE,
      ],
      'terms_data_suma' => [
        '#type' => 'checkbox',
        '#title' => "Acepto la {$suma_policy} y los {$suma_terms} del Servicio.",
        '#attributes' => [
          'class' => ['field-check', 'field-required'],
          'data-name' => $this->t('the terms and conditions'),
        ],
      ],
      'terms_data_kalley' => [
        '#type' => 'checkbox',
        '#title' => "Acepto {$offer_terms}, y autorizo el tratamiento de mis datos personales por parte de {$kalleymovil_terms}.",
        '#attributes' => [
          'class' => ['field-check', 'field-required'],
          'data-name' => $this->t('the terms and conditions'),
        ],
      ],
      'btn_charge' => [
        '#type' => 'submit',
        '#value' => 'Paga con tu saldo',
        '#attributes' => ['class' => ['btn-charge']],
        '#required' => TRUE,
        '#name' => 'btn_charge',
      ],
      'btn_send' => [
        '#type' => 'submit',
        '#value' => $this->t('Paga por PSE'),
        '#attributes' => ['class' => ['btn-send']],
        '#required' => TRUE,
        '#name' => 'btn_send',
      ],
      'packages_id' => [
        '#type' => 'hidden',
        '#default_value' => NULL,
        '#attributes' => ['class' => ['field-packages-id']],
      ],
      'order_value' => [
        '#type' => 'hidden',
        '#default_value' => 0,
        '#attributes' => ['class' => ['field-order-value']],
      ],
      'redirect_url' => [
        '#type' => 'hidden',
        '#default_value' => NULL,
        '#attributes' => ['class' => ['field-order-redirect-url']],
      ],
      'btn_triggering' => [
        '#type' => 'hidden',
        '#default_value' => NULL,
        '#attributes' => ['class' => ['field-btn-triggering']],
      ],
    ];
  }

  /**
   * Format time and size with strong tag.
   */
  private function formatStrongLabel(&$duration) {
    $info = explode(' ', $duration);
    $info[0] = '<strong>' . $info[0] . '</strong>';

    $duration = implode(' ', $info);
  }

  /**
   * Create terms link structure.
   *
   * @param string $text
   *   The link title.
   * @param string $url
   *   The link URL.
   * @param array $options
   *   The link options.
   */
  private function buildStructureTermsLink($text, $url, array $options = []) {
    $link = Link::fromTextAndUrl($text, Url::fromUri($url, $options))->toRenderable();
    $link['#attributes']['target'] = '_blank';
    $link = $this->renderer->render($link);

    return $link;
  }

}
