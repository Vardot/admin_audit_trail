<?php

namespace Drupal\admin_audit_trail_entityqueue\Hook;

use Drupal\entityqueue\Entity\EntityQueue;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_entityqueue.
 */
class AdminAuditTrailEntityqueueHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Entityqueue and Entity_subqueue event log handler.
    $handlers = [];
    $handlers = [
      "entity_queue" => [
        "title" => $this->t('Entityqueue'),
      ],
      "entity_subqueue" => [
        "title" => $this->t('Entity Subqueue'),
      ],
    ];
    return $handlers;
  }

  /**
   * Implements hook_entity_queue_insert().
   */
  #[Hook('entity_queue_insert')]
  public function entityQueueInsert($entityqueue) {
    $entityQueue = EntityQueue::load($entityqueue->id());
    $title = $entityQueue->get('label');
    $log = [
      'type' => 'entity_queue',
      'operation' => 'insert',
      'description' => $this->t('%type: %title - inserted', [
        '%type' => $entityqueue->bundle(),
        '%title' => $title,
      ]),
      'ref_char' => $title,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_entity_queue_update().
   */
  #[Hook('entity_queue_update')]
  public function entityQueueUpdate($entityqueue) {
    $entityQueue = EntityQueue::load($entityqueue->id());
    $title = $entityQueue->get('label');
    $log = [
      'type' => 'entity_queue',
      'operation' => 'update',
      'description' => $this->t('%type: %title - updated', [
        '%type' => $entityqueue->bundle(),
        '%title' => $title,
      ]),
      'ref_char' => $title,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_entity_queue_predelete().
   */
  #[Hook('entity_queue_predelete')]
  public function entityQueuePredelete($entityqueue) {
    $entityQueue = EntityQueue::load($entityqueue->id());
    $title = $entityQueue->get('label');
    $log = [
      'type' => 'entity_queue',
      'operation' => 'delete',
      'description' => $this->t('%type: %title - deleted', [
        '%type' => $entityqueue->bundle(),
        '%title' => $title,
      ]),
      'ref_char' => $title,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_entity_subqueue_insert().
   */
  #[Hook('entity_subqueue_insert')]
  public function entitySubqueueInsert($entity_subqueue) {
    $title = $entity_subqueue->get('title')->getValue()[0]['value'];
    $log = [
      'type' => 'entity_subqueue',
      'operation' => 'insert',
      'description' => $this->t('%type: %title - inserted', [
        '%type' => $entity_subqueue->bundle(),
        '%title' => $title,
      ]),
      'ref_char' => $title,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_entity_subqueue_update().
   */
  #[Hook('entity_subqueue_update')]
  public function entitySubqueueUpdate($entity_subqueue) {
    $title = $entity_subqueue->get('title')->getValue()[0]['value'];
    $log = [
      'type' => 'entity_subqueue',
      'operation' => 'update',
      'description' => $this->t('%type: %title - updated', [
        '%type' => $entity_subqueue->bundle(),
        '%title' => $title,
      ]),
      'ref_char' => $title,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_entity_subqueue_predelete().
   */
  #[Hook('entity_subqueue_predelete')]
  public function entitySubqueuePredelete($entity_subqueue) {
    $title = $entity_subqueue->get('title')->getValue()[0]['value'];
    $log = [
      'type' => 'entity_subqueue',
      'operation' => 'delete',
      'description' => $this->t('%type: %title - deleted', [
        '%type' => $entity_subqueue->bundle(),
        '%title' => $title,
      ]),
      'ref_char' => $title,
    ];
    admin_audit_trail_insert($log);
  }

}
