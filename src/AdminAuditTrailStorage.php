<?php

namespace Drupal\admin_audit_trail;

/**
 * Controller class for admin audit trail required special handling for events.
 */
class AdminAuditTrailStorage {

  /**
   * Display event data listing.
   *
   * @param array $getData
   *   Filter to display data.
   * @param array $header
   *   Data sorting type.
   * @param int $limit
   *   Limit to display data.
   *
   * @return array
   *   The result to display.
   */
  public static function getSearchData(array $getData, array $header, $limit = NULL) {

    $db = \Drupal::database();
    $query = $db->select('admin_audit_trail', 'e');
    $query->fields('e');

    $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header);
    $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($limit);

    // Apply filters.
    if (!empty($getData['type'])) {
      $query->condition('type', $getData['type']);
      if (!empty($getData['operation'])) {
        $query->condition('operation', $getData['operation']);
      }
    }
    if (!empty($getData['id'])) {
      $query->condition('ref_numeric', $getData['id']);
    }
    if (!empty($getData['ip'])) {
      $query->condition('ip', $getData['ip']);
    }
    if (!empty($getData['name'])) {
      $query->condition('ref_char', $getData['name']);
    }
    if (!empty($getData['path'])) {
      $query->condition('path', '%' . \Drupal::database()->escapeLike($getData['path']) . '%', 'LIKE');
    }
    if (!empty($getData['keyword'])) {
      $query->condition('description', '%' . \Drupal::database()->escapeLike($getData['keyword']) . '%', 'LIKE');
    }
    if (!empty($getData['user'])) {
      $query->condition('uid', $getData['user']);
    }
    $result = $pager->execute();

    return $result;
  }

  /**
   * Returns the form element for the operations based on the event log type.
   *
   * @param string $type
   *   Event type.
   *
   * @return array
   *   A form element.
   */
  public static function formGetOperations($type) {
    $element = [
      '#type' => 'select',
      '#name' => 'operation',
      '#title' => t('Operation'),
      '#description' => t('The entity operation.'),
      '#options' => ['' => t('Choose an operation')],
      '#prefix' => '<div id="operation-dropdown-replace">',
      '#suffix' => '</div>',
    ];
    if ($type) {
      $db = \Drupal::database();
      $query = $db->select('admin_audit_trail', 'e')
        ->fields('e', ['operation'])
        ->condition('type', $type)
        ->groupBy('operation');
      $query->addExpression('COUNT(e.lid)', 'c');
      $query->distinct(TRUE);
      $results = $query->execute()->fetchAllKeyed(0);

      $operations = [];
      foreach ($results as $name => $count) {
        $operations[$name] = $name . ' (' . $count . ')';
      }
      $element['#options'] += $operations;
    }

    return $element;
  }

}
