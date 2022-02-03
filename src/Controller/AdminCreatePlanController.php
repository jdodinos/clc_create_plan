<?php

namespace Drupal\clc_create_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Define a route controller for admin create plan list.
 */
class AdminCreatePlanController extends ControllerBase {

  /**
   * Handler for contents request.
   */
  public function handleListLinks() {
    $build = [];

    // Create link to Reports.
    $reports_link = $this->createLinkConfiguration('reports_create_plan_form', 'Reporte de órdenes');

    // Create link to WS configuration Form.
    $ws_config_link = $this->createLinkConfiguration('config_ws_create_plan_form', 'Configuración Web Service');

    // Create link to WS configuration Form.
    $voice_link = $this->createLinkConfiguration('config_voice_create_plan_form', 'Configuración Paquetes Solo Voz');

    // Create link to WS configuration Form.
    $data_link = $this->createLinkConfiguration('config_data_create_plan_form', 'Configuración Paquetes Solo datos');

    // Create link to WS configuration Form.
    $special_packs_link = $this->createLinkConfiguration('config_special_packs_create_plan_form', 'Configuración Paquetes especiales');

    // Create link to WS configuration Form.
    $products_link = $this->createLinkConfiguration('config_product_create_plan_form', 'Configuración de Productos');

    // Create link to WS configuration Form.
    $gestion_products_link = $this->createLinkConfiguration('config_create_plan_form', 'Gestión de Productos');

    // Create link to WS configuration Form.
    $check_ballance_link = $this->createLinkConfiguration('check_ballance', 'Consulta de saldo');

    $build['list'] = [
      '#prefix' => '<ul class="links-menu">',
      '#suffix' => '</ul>',
      'report' => [
        '#prefix' => '<li class="item-link item-report">',
        '#suffix' => '</li>',
        '#markup' => render($reports_link),
      ],
      'ws_config' => [
        '#prefix' => '<li class="item-link item-ws-config">',
        '#suffix' => '</li>',
        '#markup' => render($ws_config_link),
      ],
      'voice' => [
        '#prefix' => '<li class="item-link item-voice">',
        '#suffix' => '</li>',
        '#markup' => render($voice_link),
      ],
      'data' => [
        '#prefix' => '<li class="item-link item-data">',
        '#suffix' => '</li>',
        '#markup' => render($data_link),
      ],
      'special_packs' => [
        '#prefix' => '<li class="item-link item-data">',
        '#suffix' => '</li>',
        '#markup' => render($special_packs_link),
      ],
      'products' => [
        '#prefix' => '<li class="item-link item-data">',
        '#suffix' => '</li>',
        '#markup' => render($products_link),
      ],
      'gestion' => [
        '#prefix' => '<li class="item-link item-data">',
        '#suffix' => '</li>',
        '#markup' => render($gestion_products_link),
      ],
      'check_ballance' => [
        '#prefix' => '<li class="item-link item-ballance">',
        '#suffix' => '</li>',
        '#markup' => render($check_ballance_link),
      ],
    ];

    return $build;
  }

  /**
   * Create link structure.
   *
   * @param string $route
   *   The route name.
   * @param string $text
   *   The link name.
   */
  private function createLinkConfiguration($route, $text) {
    // Create link to configuration.
    $url = Url::fromRoute('clc_create_plan.' . $route);
    $link = Link::fromTextAndUrl($text, $url)->toRenderable();

    return $link;
  }

}
