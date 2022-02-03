<?php

namespace Drupal\clc_create_plan;

use Drupal\Core\Database\Connection;

/**
 * Class OrderManager.
 *
 * Manager orders functionalities.
 */
class OrderManager {
  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Construc a new OrderManager class.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Filter list of orders.
   *
   * @param array $filters
   *   The filters.
   */
  public function filterOrders(array $filters) {
    $query = $this->database->select('clc_create_plan_order', 'o')
      ->fields('o');

    // Filter by order ID.
    if (!empty($filters['order_id'])) {
      $query->condition('id', $filters['order_id']);
    }

    // Filter by created date from.
    if (!empty($filters['from_date'])) {
      $from_created = $filters['from_date'];
      $from_created = strtotime($from_created . ' 00:00:01');
      $query->condition('created', $from_created, '>');
    }

    // Filter by created date to.
    if (!empty($filters['to_date'])) {
      $to_created = $filters['to_date'];
      $to_created = strtotime($to_created . ' 23:59:59');
      $query->condition('created', $to_created, '<');
    }

    $result = $query
      ->orderBy('created', 'DESC')
      ->execute()->fetchAll();

    return $result;
  }

}
