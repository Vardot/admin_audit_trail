<?php

namespace Drupal\admin_audit_trail_comment\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_comment.
 */
class AdminAuditTrailCommentHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Comment event log handler.
    $handlers = [];
    $handlers['comment'] = [
      'title' => $this->t('Comment'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_comment_insert().
   */
  #[Hook('comment_insert')]
  public function commentInsert($comment) {
    $log = [
      'type' => 'comment',
      'operation' => 'insert',
      'description' => $this->t('%type: %title', [
        '%type' => $comment->getTypeId(),
        '%title' => $comment->getSubject(),
      ]),
      'ref_numeric' => $comment->id(),
      'ref_char' => $comment->getSubject(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_comment_update().
   */
  #[Hook('comment_update')]
  public function commentUpdate($comment) {
    $log = [
      'type' => 'comment',
      'operation' => 'update',
      'description' => $this->t('%type: %title', [
        '%type' => $comment->getTypeId(),
        '%title' => $comment->getSubject(),
      ]),
      'ref_numeric' => $comment->id(),
      'ref_char' => $comment->getSubject(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_comment_delete().
   */
  #[Hook('comment_delete')]
  public function commentDelete($comment) {
    $log = [
      'type' => 'comment',
      'operation' => 'delete',
      'description' => $this->t('%type: %title', [
        '%type' => $comment->getTypeId(),
        '%title' => $comment->getSubject(),
      ]),
      'ref_numeric' => $comment->id(),
      'ref_char' => $comment->getSubject(),
    ];
    admin_audit_trail_insert($log);
  }

}
