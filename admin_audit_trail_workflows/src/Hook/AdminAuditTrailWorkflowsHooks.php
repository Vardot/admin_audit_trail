<?php

namespace Drupal\admin_audit_trail_workflows\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for admin_audit_trail_workflows.
 */
class AdminAuditTrailWorkflowsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Workflows event log handler.
    $handlers = [];
    $handlers['workflows'] = [
      'title' => $this->t('Workflows'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_node_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert($node) {
    /** @var \Drupal\node\NodeInterface $node */
    if (!$node->hasField('moderation_state')) {
      return;
    }
    $new_state = $node->get("moderation_state")->getString();
    $log = [
      'type' => 'workflows',
      'operation' => 'insert',
      'description' => $this->t('%type: %title - New node created with workflow state %new_state', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
        '%new_state' => $new_state,
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
    /** @var \Drupal\node\NodeInterface $node */
    if (!$node->hasField('moderation_state')) {
      return;
    }
    $new_state = $node->get("moderation_state")->getString();
    $original = method_exists($node, 'getOriginal') ? $node->getOriginal() : ($node->original ?? NULL);
    if (!$original instanceof NodeInterface) {
      return;
    }
    $old_state = $original->get("moderation_state")->getString();
    if ($old_state != $new_state) {
      $log = [
        'type' => 'workflows',
        'operation' => 'update',
        'description' => $this->t('%type: %title - Workflow state changed from %old_state to %new_state', [
          '%type' => $node->getType(),
          '%title' => $node->getTitle(),
          '%old_state' => $old_state,
          '%new_state' => $new_state,
        ]),
        'ref_numeric' => $node->id(),
        'ref_char' => $node->getTitle(),
      ];
      admin_audit_trail_insert($log);
    }
  }

}
