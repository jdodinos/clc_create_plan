<?php

namespace Drupal\clc_create_plan;

use Drupal\Core\Database\Connection;

/**
 * Class ProductsManager.
 *
 * Manager products functionalities.
 */
class ProductsManager {
  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Construc a new ProductsManager class.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Get all products.
   */
  public function getAllProducts() {
    // Get products.
    $query = $this->database->select('clc_create_plan_data', 'ccpd')
      ->fields('ccpd')
      ->orderBy('type', 'DESC')
      ->execute()->fetchAll();

    return $query;
  }

  /**
   * Get product by package id.
   *
   * @param string $package_id
   *   The package id.
   */
  public function getProductByPackageId($package_id) {
    // Get product.
    $query = $this->database->select('clc_create_plan_data', 'ccpd')
      ->fields('ccpd')
      ->condition('package_id', $package_id)
      ->execute()->fetchObject();

    return $query;
  }

  /**
   * Create new product.
   *
   * @param array $fields
   *   The data product.
   */
  public function createProduct(array $fields) {
    $this->database->insert('clc_create_plan_data')
      ->fields($fields)
      ->execute();
  }

  /**
   * Update data product by package id.
   *
   * @param string $package_id
   *   The package id.
   * @param array $fields
   *   The data product.
   */
  public function updateProductByPackageId($package_id, array $fields) {
    $this->database->update('clc_create_plan_data')
      ->fields($fields)
      ->condition('package_id', $package_id)
      ->execute();
  }

  /**
   * Delete multiple products.
   *
   * @param array $products_id
   *   The array with product ids.
   */
  public function deleteMultipleProducts(array $products_id) {
    $this->database->delete('clc_create_plan_data')
      ->condition('id', $products_id, 'IN')
      ->execute();
  }

  /**
   * Update status for multiple products.
   *
   * @param array $products_id
   *   The array with product ids.
   * @param int $status
   *   The new status.
   */
  public function updateStatusMultipleProducts(array $products_id, $status) {
    $this->database->update('clc_create_plan_data')
      ->fields(['status' => $status])
      ->condition('id', $products_id, 'IN')
      ->execute();
  }

  /**
   * Get data product by type.
   *
   * @param string $field
   *   The field name.
   * @param string $type
   *   The product type.
   * @param string $duration
   *   The product duration.
   */
  public function getDataProduct($field, $type, $duration = NULL) {
    // Select $field.
    $query = $this->database->select('clc_create_plan_data', 'ccpd')
      ->fields('ccpd', [$field])
      ->condition('status', TRUE, '=')
      ->condition('type', $type, '=')
      ->condition($field, NULL, 'IS NOT');

    if (isset($duration)) {
      $query->condition('duration', $duration, '=');
    }

    $result = $query->orderBy($field, 'ASC')
      ->distinct()
      ->execute()->fetchAll();

    return $result;
  }

  /**
   * Get data product by type.
   *
   * @param string $type
   *   The product type.
   */
  public function getDurationByType($type) {
    // Select $field.
    $query = $this->database->select('clc_create_plan_data', 'ccpd')
      ->fields('ccpd', ['duration'])
      ->condition('status', TRUE, '=')
      ->condition('type', $type, '=')
      ->orderBy('duration', 'DESC')
      ->distinct();

    return $query->execute()->fetchAll();
  }

  /**
   * Get data packages by type.
   *
   * @param array $fields
   *   The fields.
   * @param string $type
   *   The product type.
   */
  public function getDataPackages(array $fields, $type) {
    // Select products packages.
    $query = $this->database->select('clc_create_plan_data', 'ccpd')
      ->fields('ccpd', $fields)
      ->condition('status', TRUE, '=')
      ->condition('type', $type, '=')
      ->distinct();

    return $query->execute()->fetchAll();
  }

}
