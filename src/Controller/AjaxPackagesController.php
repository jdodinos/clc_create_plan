<?php

namespace Drupal\clc_create_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Define a route controller for entity autocomplete form elements.
 */
class AjaxPackagesController extends ControllerBase {

  /**
   * Handler for contents request.
   */
  public function handleProducts(Request $request) {
    $response = [];
    // Get the INTERNET by MINUTE.
    if (isset($_POST['minut_selected']) && !empty($_POST['minut_selected'])) {
      $minut_selected = $_POST['minut_selected'];

      $conn = \Drupal::database();
      $query = $conn->select('clc_create_plan_data', 'ccpd')
        ->fields('ccpd', ['internet', 'value'])
        ->condition('status', TRUE, '=')
        ->condition('type', 'product', '=')
        ->condition('minuts', $minut_selected, '=')
        ->orderBy('internet')
        ->distinct();
      $response = (array) $query->execute()->fetchAll();
    }

    return new Jsonresponse($response);
  }

  /**
   * Handler for id product request.
   */
  public function handleProductPackageId(Request $request) {
    $response = '';
    // Get package_id value by INTERNET and MINUT selected.
    if (!empty($_POST['minut_selected']) && !empty($_POST['internet_selected'])) {
      $minut_selected = $_POST['minut_selected'];
      $internet_selected = $_POST['internet_selected'];

      $conn = \Drupal::database();
      $query = $conn->select('clc_create_plan_data', 'ccpd')
        ->fields('ccpd', ['package_id'])
        ->condition('status', TRUE, '=')
        ->condition('type', 'product', '=')
        ->condition('minuts', $minut_selected, '=')
        ->condition('internet', $internet_selected, '=')
        ->distinct();
      $response = $query->execute()->fetchField();
    }

    return new Jsonresponse($response);
  }

  /**
   * Handler for price product request.
   */
  public function handleProductPrice(Request $request) {
    $response = '';
    // Get product value by INTERNET and MINUT selected.
    if (!empty($_POST['minut_selected']) && !empty($_POST['internet_selected'])) {
      $minut_selected = $_POST['minut_selected'];
      $internet_selected = $_POST['internet_selected'];

      $conn = \Drupal::database();
      $query = $conn->select('clc_create_plan_data', 'ccpd')
        ->fields('ccpd', ['value'])
        ->condition('status', TRUE, '=')
        ->condition('type', 'product', '=')
        ->condition('minuts', $minut_selected, '=')
        ->condition('internet', $internet_selected, '=')
        ->distinct();
      $response = (array) $query->execute()->fetchField();
    }

    return new Jsonresponse($response);
  }

  /**
   * Handler for price Special Package request.
   */
  public function handlePackSpecialPrice(Request $request) {
    $response = 0;
    // Get special pack value.
    if (!empty($_POST['packs'])) {
      $packs_selected = $_POST['packs'];

      $conn = \Drupal::database();
      $query = $conn->select('clc_create_plan_data', 'ccpd')
        ->fields('ccpd', ['value'])
        ->condition('status', TRUE, '=')
        ->condition('type', 'special', '=')
        ->condition('package_id', $packs_selected, 'IN')
        ->distinct();
      $result = (array) $query->execute()->fetchAll();

      if (!empty($result)) {
        foreach ($result as $key => $info) {
          $response += $info->value;
        }
      }
    }

    return new Jsonresponse($response);
  }

  /**
   * Handler for price Package request.
   */
  public function handlePackagePrice(Request $request) {
    $response = 0;
    // Get special pack value.
    if (!empty($_POST['pack_id'])) {
      $package_id = $_POST['pack_id'];

      $conn = \Drupal::database();
      $query = $conn->select('clc_create_plan_data', 'ccpd')
        ->fields('ccpd', ['value'])
        ->condition('status', TRUE, '=')
        ->condition('package_id', $package_id, '=')
        ->distinct();
      $response = (array) $query->execute()->fetchField();
    }

    return new Jsonresponse($response);
  }

  /**
   * Handler to consume web service in SUMA.
   */
  public function handleSumaWs(Request $request) {
    $response = [
      'error' => TRUE,
      'msg_error' => '¡No se pudo completar tu pago! - Por favor inténtelo de nuevo.',
    ];

    if (!empty($_POST['cellphone']) && !empty($_POST['packages_id'])) {
      $cellphone = $_POST['cellphone'];
      $packages_id = $_POST['packages_id'];
      $triggering_element = $_POST['name_btn'];

      $wsSuma = \Drupal::service('clc_create_plan.ws_suma');
      // Validate configuration to consume Web Service.
      if (!$wsSuma->error) {
        $wsSuma->sumaLogin($cellphone, $triggering_element);

        // Validate the service consumed upon login.
        if (!$wsSuma->error) {
          $wsSuma->getSubscriptionID();

          // Validate the service consumed upon get subscription.
          if (!$wsSuma->error) {
            switch ($triggering_element) {
              case 'btn_send':
                $packages_selected = explode('|', $packages_id);
                foreach ($packages_selected as $key => $package_id) {
                  $wsSuma->createOrderDistribution($package_id);
                }

                // Validate the service consumed upon create order distribution.
                if (!$wsSuma->error) {
                  $result = $wsSuma->payOrder();

                  if (isset($result['url_redirect']) && isset($result['body'])) {
                    $response['error'] = FALSE;
                    $response['msg_error'] = NULL;
                    $response = array_merge($response, $result);
                  }
                }
                break;

              case 'btn_charge':
                // $wsSuma->getSubscriptionBalance($_POST['order_value']);
                // if (!$wsSuma->error) {
                  $response['error'] = FALSE;
                  $response['msg_error'] = NULL;
                // }
                // elseif (isset($wsSuma->resultCode) && $wsSuma->resultCode == 'INSUFFICIENT_BALANCE') {
                //   $response['error'] = TRUE;
                //   $response['msg_error'] = 'No tienes fondos suficientes. Intentar recargar con PSE.';
                // }
                break;
            }
          }
          else {
            $response['msg_error'] = 'El número ingresado no está activo en Kalley Movil. Verificalo e intenta de nuevo';
          }
        }
        else {
          $response['msg_error'] = 'No exite la configuración mínima para conexión con la pasarela. El login en SUMA ha fallado. Contacta al administrador del sitio.';
        }
      }
      else {
        $response['msg_error'] = 'No exite la configuración mínima para conexión con la pasarela. Contacta al administrador del sitio.';
      }
    }

    return new Jsonresponse($response);
  }

}
