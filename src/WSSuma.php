<?php

namespace Drupal\clc_create_plan;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class WSSuma consume the web service provided by SUMA.
 */
class WSSuma {
  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The token cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  private $wsURL;
  private $wsNs;
  private $certificate;
  private $privateKey;
  private $passphrase = '6fR432dA8l';
  private $userName;
  private $userPass;
  private $brandID;
  private $redirectOK;
  private $redirectNOK;
  private $userNameChargue;
  private $userPassChargue;
  private $wsSessionID = NULL;
  public $subscriptionID = NULL;
  public $phoneNumber = NULL;
  public $triggeringElement = NULL;
  public $balanceValue = NULL;
  public $balanceExpiration = NULL;
  public $orderTicket = NULL;
  public $orderPrice = NULL;
  public $error = FALSE;
  public $resultCode = NULL;

  /**
   * Construct a new WSSuma object.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, CacheBackendInterface $cache) {
    // Validate information to consume WS.
    $this->validateConfiguration();
    if (!$this->error) {
      $this->cache = $cache;

      // Get the certificates.
      $module_path = $moduleHandler->getModule('clc_create_plan')->getPath() . '/assets/';
      $cert_path = $module_path . 'wsapp.kalleymovil.com.co_cert_client.pem';
      $private_key_path = $module_path . 'wsapp.kalleymovil.com.co_cert_client_key.pem';
      $this->certificate = realpath($cert_path);
      $this->privateKey = realpath($private_key_path);

      // Assign the configuration module.
      $config = \Drupal::state()->get('suma_ws_config', []);
      $this->wsURL = $config['ws_url'];
      $this->wsNs = $config['ws_namespace'];
      $this->userName = $config['ws_user'];
      $this->userPass = $config['ws_password'];
      $this->brandID = $config['ws_brand_id'];
      $this->redirectOK = $config['ws_redirect_ok'];
      $this->redirectNOK = $config['ws_redirect_nok'];
      $this->userNameChargue = $config['ws_user_charge'];
      $this->userPassChargue = $config['ws_password_charge'];
    }
  }

  /**
   * Login in the WS.
   */
  public function sumaLogin($phone_number, $triggering_element = NULL) {
    // Get cache information.
    $this->phoneNumber = $phone_number;
    $this->triggeringElement = $triggering_element;
    $cache_id = "SUMA-session-id-{$phone_number}-{$triggering_element}";
    $cache = $this->cache->get($cache_id);
    if ($cache) {
      // Load informaction from cache.
      $this->wsSessionID = $cache->data;
    }
    else {
      // Load information from Web Service SUMA.
      $endpoint = $this->wsURL . 'access/v7.0?wsdl';

      // Structure to send.
      $params = [
        'userName' => $this->userName,
        'pass' => $this->userPass,
        'brandID' => $this->brandID,
      ];
      if ($triggering_element == 'btn_charge') {
        $params['userName'] = $this->userNameChargue;
        $params['pass'] = $this->userPassChargue;
      }
      $structure = $this->createStructureParams($params);
      $params = "<ws7:login>{$structure}</ws7:login>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, 'login', $params);

      if ($result['sessionResult']['resultCode'] == 'OK' && isset($result['sessionResult']['wsSessionId'])) {
        $sessionResult = $result['sessionResult'];
        $this->wsSessionID = $sessionResult['wsSessionId'];
        $expirationDate = strtotime($sessionResult['expirationDate']);

        // Assign the cache for 3 hours.
        $this->cache->set($cache_id, $this->wsSessionID, $expirationDate);
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error('Error en sumaLogin parámetros enviados: ' . htmlspecialchars($params));
      }
    }
  }

  /**
   * Get subscription ID.
   */
  public function getSubscriptionID() {
    if (is_null($this->subscriptionID) && !is_null($this->wsSessionID)) {
      // Load information from WS SUMA.
      $endpoint = $this->wsURL . 'subscriptionbasic/v7.4?wsdl';

      // Structure to send.
      $params = [
        'wsSessionId' => $this->wsSessionID,
        'msisdn' => $this->phoneNumber,
      ];
      $structure = $this->createStructureParams($params);
      $params = "<ws7:searchSubscriptions>{$structure}</ws7:searchSubscriptions>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, 'searchSubscriptions', $params);
      $searchSubscriptionsResult = $result['searchSubscriptionsResult'];
      if ($searchSubscriptionsResult['resultCode'] == 'OK' && isset($searchSubscriptionsResult['itemList']['subscriptionID'])) {
        $this->subscriptionID = $searchSubscriptionsResult['itemList']['subscriptionID'];
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error('Error en getSubscriptionID parámetros enviados: ' . htmlspecialchars($params));
      }
    }
  }

  /**
   * Get subscription ID.
   */
  public function getSubscriptionBalance($order_value = NULL) {
    if ($this->subscriptionID && $this->wsSessionID) {
      // Create order in WS SUMA.
      $endpoint = $this->wsURL . 'subscriptionbasic/v7.4?wsdl';
      $method = 'getSubscriptionBalance';

      // Structure to send.
      $params = [
        'wsSessionId' => $this->wsSessionID,
        'subscriptionID' => $this->subscriptionID
      ];
      $structure = $this->createStructureParams($params);
      $params = "<ws7:{$method}>{$structure}</ws7:{$method}>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, $method, $params);
      $this->resultCode = $result['balanceResult']['resultCode'];
      if ($result['balanceResult']['resultCode'] == 'OK' && isset($result['balanceResult']['item'])) {
        $item = $result['balanceResult']['item'];
        $this->balanceValue = $item['balance'];
        $this->balanceExpiration = $item['expirationDate'];

        if ($order_value && $order_value > $this->balanceValue) {
          // An error has occurred.
          $this->error = TRUE;

          $this->resultCode = 'INSUFFICIENT_BALANCE';
        }
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error("Error en {$method} parámetros enviados: " . htmlspecialchars($params));
      }
    }
  }


  /**
   * Create order.
   */
  public function createOrderDistribution($package_id) {
    // Structure to send.
    $params = [
      'wsSessionId' => $this->wsSessionID,
      'subscriptionID' => $this->subscriptionID,
      'package' => $package_id,
      'distributionStatus' => 1,
      'activationPrice' => 0,
    ];

    if ($this->triggeringElement == 'btn_charge') {
      $params['distributionStatus'] = NULL;
      $params['paidPrice'] = NULL;
      $params['activationPrice'] = NULL;
    }

    if (is_null($this->orderTicket) && !is_null($this->subscriptionID) && !is_null($this->wsSessionID)) {
      // Create order in WS SUMA.
      $endpoint = $this->wsURL . 'subscriptionorder/v7.0?wsdl';

      $structure = $this->createStructureParams($params);
      $params = "<ws7:createOrderDistribution>{$structure}</ws7:createOrderDistribution>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, 'createOrderDistribution', $params);
      $this->resultCode = $result['callOrderResult']['resultCode'];
      if ($result['callOrderResult']['resultCode'] == 'OK' && isset($result['callOrderResult']['ticket'])) {
        $this->orderTicket = $result['callOrderResult']['ticket'];
        $this->orderPrice = $result['callOrderResult']['price'];
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error('Error en createOrderDistribution parámetros enviados: ' . htmlspecialchars($params));
      }
    }
    elseif (!is_null($this->orderTicket) && !is_null($this->wsSessionID)) {
      $this->addPackageOrderDistribution($package_id, $params);
    }
  }

  /**
   * Add package in order.
   */
  public function addPackageOrderDistribution($package_id, $params = NULL) {
    if (!is_null($this->orderTicket) && !is_null($this->wsSessionID)) {
      // Add package in order SUMA.
      $endpoint = $this->wsURL . 'subscriptionorder/v7.0?wsdl';

      // Structure to send.
      if (!isset($params)) {
        $params = [
          'wsSessionId' => $this->wsSessionID,
          'activationPrice' => 0,
        ];
      }
      $params['orderTicket'] = $this->orderTicket;
      $params['packageID'] = $package_id;
      $structure = $this->createStructureParams($params);
      $params = "<ws7:addPackageOrderDistribution>{$structure}</ws7:addPackageOrderDistribution>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, 'addPackageOrderDistribution', $params);
      if ($result['callOrderResult']['resultCode'] == 'OK' && isset($result['callOrderResult']['price'])) {
        $this->orderPrice = $result['callOrderResult']['price'];
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error('Error en addPackageOrderDistribution parámetros enviados: ' . htmlspecialchars($params));
      }
    }
  }

  /**
   * Add package in order.
   */
  public function payOrder() {
    if (!is_null($this->wsSessionID)) {
      // Pay order SUMA.
      $endpoint = $this->wsURL . 'billingadvanced/v7.3?wsdl';
      $ip = \Drupal::request()->getClientIp();
      $return = [];

      // Structure to send.
      $params = [
        'wsSessionId' => $this->wsSessionID,
        'idOrder' => $this->orderTicket,
        'paymentMethodType' => 1,
        'purchaserIP' => $ip,
        'redirectOK' => $this->redirectOK,
        'redirectNOK' => $this->redirectNOK,
      ];
      $structure = $this->createStructureParams($params);
      $params = "<ws7:payOrder>{$structure}</ws7:payOrder>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, 'payOrder', $params);
      if (isset($result['callResult']) && $result['callResult']['resultCode'] == 'OK') {
        $data = $result['callResult']['item'];
        $return['url_redirect'] = $data['url'];
        $return['body'] = $data['body'];
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error('Error en payOrder parámetros enviados: ' . htmlspecialchars($params));
      }

      return $return;
    }
  }

    /**
   * Add Apply order distribution.
   */
  public function applyOrderDistribution() {
    if (!is_null($this->wsSessionID)) {
      // Pay order SUMA.
      $endpoint = $this->wsURL . 'subscriptionorder/v7.0?wsdl';
      $return = [];

      // Structure to send.
      $params = [
        'wsSessionId' => $this->wsSessionID,
        'orderTicket' => $this->orderTicket,
      ];
      $structure = $this->createStructureParams($params);
      $params = "<ws7:applyOrderDistribution>{$structure}</ws7:applyOrderDistribution>";

      // Call the Web Service.
      $result = $this->callWsSuma($endpoint, $this->wsNs, 'applyOrderDistribution', $params);
      if (isset($result['callOrderResult']) && $result['callOrderResult']['resultCode'] == 'OK') {
        $data = $result['callOrderResult'];
        $return['saleCode'] = $data['saleCode'];
      }
      else {
        // An error has occurred.
        $this->error = TRUE;

        // Send information about the error.
        \Drupal::logger('WS SUMA')->error('Error en applyOrderDistribution parámetros enviados: ' . htmlspecialchars($params));
      }

      return $return;
    }
  }

  /**
   * Validate configuration of the Web Services.
   */
  private function validateConfiguration() {
    $config = \Drupal::state()->get('suma_ws_config', []);

    foreach ($config as $ws_field => $value) {
      if (!$value) {
        // Information is missing.
        $this->error = TRUE;
        break;
      }
    }
  }

  /**
   * Create structure to consume web service SUMA.
   */
  private function createStructureParams($params) {
    $structure = '';
    if (!empty($params)) {
      foreach ($params as $key => $value) {
        $structure .= "<{$key}>{$value}</{$key}>";
      }
    }

    return $structure;
  }

  /**
   * Call the WS suma.
   */
  private function callWsSuma($endpoint, $wsNs, $method, $params) {
    try {
      // Web service Call.
      $client = new \nusoap_client($endpoint, TRUE);
      $client->soap_defencoding = 'UTF-8';
      $client->namespaces['ws7'] = $wsNs;
      $client->setCredentials('', '', 'certificate', [
        'sslcertfile' => $this->certificate,
        'sslkeyfile' => $this->privateKey,
        'passphrase' => $this->passphrase,
        'verifypeer' => FALSE,
        'verifyhost' => FALSE,
      ]);
      $result = $client->call($method, $params);
    }
    catch (Exception $e) {
      // @Watchdog
      \Drupal::logger('WSSuma')->notice($e->getMessage());
    }

    return $result;
  }

}
