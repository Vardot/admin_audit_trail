<?php

namespace Drupal\admin_audit_trail_node\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_node.
 */
class AdminAuditTrailNodeHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Node event log handler.
    $handlers = [];
    $handlers['node'] = [
      'title' => $this->t('Node'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_node_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert($node) {
    $log = [
      'type' => 'node',
      'operation' => 'insert',
      'description' => $this->t('%type: %title', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
      ]),
      'ref_numeric' => $node->id(),
      'ref_char' => $node->getTitle(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_node_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate($node) {
    $log = [
      'type' => 'node',
      'operation' => 'update',
      'description' => $this->t('%type: %title', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
      ]),
      'ref_numeric' => $node->id(),
      'ref_char' => $node->getTitle(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_node_delete().
   */
  #[Hook('node_delete')]
  public function nodeDelete($node) {
    $log = [
      'type' => 'node',
      'operation' => 'delete',
      'description' => $this->t('%type: %title', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
      ]),
      'ref_numeric' => $node->id(),
      'ref_char' => $node->getTitle(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_translation_insert() for node entities.
   */
  #[Hook('node_translation_insert')]
  public function nodeTranslationInsert($node) {
    $log = [
      'type' => 'node',
      'operation' => 'translation insert',
      'description' => $this->t('%type: %title', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
      ]),
      'ref_numeric' => $node->id(),
      'ref_char' => $node->getTitle(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_translation_delete() for node entities.
   */
  #[Hook('node_translation_delete')]
  public function nodeTranslationDelete($node) {
    $log = [
      'type' => 'node',
      'operation' => 'translation delete',
      'description' => $this->t('%type: %title', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
      ]),
      'ref_numeric' => $node->id(),
      'ref_char' => $node->getTitle(),
    ];
    admin_audit_trail_insert($log);
  }

}
