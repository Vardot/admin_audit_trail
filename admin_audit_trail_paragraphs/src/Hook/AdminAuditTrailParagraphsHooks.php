<?php

namespace Drupal\admin_audit_trail_paragraphs\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_paragraphs.
 */
class AdminAuditTrailParagraphsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Paragraphs event log handler.
    $handlers = [];
    $handlers['paragraph'] = [
      'title' => $this->t('Paragraphs'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_paragraph_insert().
   */
  #[Hook('paragraph_insert')]
  public function paragraphInsert($paragraph) {
    $log = [
      'type' => 'paragraph',
      'operation' => 'insert',
      'description' => $this->t('%type: %title', [
        '%type' => $paragraph->getType(),
        '%title' => '(parent: ' . $paragraph->parent_type->value . '-' . $paragraph->parent_id->value . ')',
      ]),
      'ref_numeric' => $paragraph->id(),
      'ref_char' => '',
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_paragraph_update().
   */
  #[Hook('paragraph_update')]
  public function paragraphUpdate($paragraph) {
    $log = [
      'type' => 'paragraph',
      'operation' => 'update',
      'description' => $this->t('%type: %title', [
        '%type' => $paragraph->getType(),
        '%title' => '(parent: ' . $paragraph->parent_type->value . '-' . $paragraph->parent_id->value . ')',
      ]),
      'ref_numeric' => $paragraph->id(),
      'ref_char' => '',
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_paragraph_delete().
   */
  #[Hook('paragraph_delete')]
  public function paragraphDelete($paragraph) {
    $log = [
      'type' => 'paragraph',
      'operation' => 'delete',
      'description' => $this->t('%type: %title', [
        '%type' => $paragraph->getType(),
        '%title' => '(parent: ' . $paragraph->parent_type->value . '-' . $paragraph->parent_id->value . ')',
      ]),
      'ref_numeric' => $paragraph->id(),
      'ref_char' => '',
    ];
    admin_audit_trail_insert($log);
  }

}
