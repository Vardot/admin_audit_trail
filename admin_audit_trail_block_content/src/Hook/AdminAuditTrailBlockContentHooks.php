<?php

namespace Drupal\admin_audit_trail_block_content\Hook;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_block_content.
 */
class AdminAuditTrailBlockContentHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Block content event log handler.
    $handlers = [];
    $handlers['block_content'] = [
      'title' => $this->t('Block Content'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('block_content_insert')]
  public function blockContentInsert(BlockContentInterface $entity) {
    $log = [
      'type' => 'block_content',
      'operation' => 'insert',
      'description' => $this->t('%bundle: %title', [
        '%bundle' => $entity->bundle(),
        '%title' => $entity->label(),
      ]),
      'ref_numeric' => $entity->id(),
      'ref_char' => $entity->label(),
      'ref_entity' => $entity,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('block_content_update')]
  public function blockContentUpdate(BlockContentInterface $entity) {
    $log = [
      'type' => 'block_content',
      'operation' => 'update',
      'description' => $this->t('%bundle: %title', [
        '%bundle' => $entity->bundle(),
        '%title' => $entity->label(),
      ]),
      'ref_numeric' => $entity->id(),
      'ref_char' => $entity->label(),
      'ref_entity' => $entity,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('block_content_delete')]
  public function blockContentDelete(BlockContentInterface $entity) {
    $log = [
      'type' => 'block_content',
      'operation' => 'delete',
      'description' => $this->t('%bundle: %title', [
        '%bundle' => $entity->bundle(),
        '%title' => $entity->label(),
      ]),
      'ref_numeric' => $entity->id(),
      'ref_char' => $entity->label(),
      'ref_entity' => $entity,
    ];
    admin_audit_trail_insert($log);
  }

}
