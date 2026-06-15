<?php

namespace Drupal\admin_audit_trail_media\Hook;

use Drupal\media\Entity\Media;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_media.
 */
class AdminAuditTrailMediaHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    $handlers = [];
    // Media event log handler.
    $handlers['media'] = [
      'title' => $this->t('Media'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('media_insert')]
  public function mediaInsert(Media $media) {
    $log = [
      'type' => 'media',
      'operation' => 'insert',
      'description' => $this->t('%title (%type)%revision_log', [
        '%type' => $media->bundle(),
        '%title' => $media->getName(),
        '%revision_log' => $media->getRevisionLogMessage() ? ': ' . $media->getRevisionLogMessage() : '',
      ]),
      'ref_numeric' => $media->id(),
      'ref_char' => $media->label(),
    ];
    // Insert log.
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('media_update')]
  public function mediaUpdate(Media $media) {
    $log = [
      'type' => 'media',
      'operation' => 'update',
      'description' => $this->t('%title (%type)%revision_log', [
        '%type' => $media->bundle(),
        '%title' => $media->getName(),
        '%revision_log' => $media->getRevisionLogMessage() ? ': ' . $media->getRevisionLogMessage() : '',
      ]),
      'ref_numeric' => $media->id(),
      'ref_char' => $media->label(),
    ];
    // Insert log.
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('media_delete')]
  public function mediaDelete(Media $media) {
    $log = [
      'type' => 'media',
      'operation' => 'delete',
      'description' => $this->t('%title (%type)%revision_log', [
        '%type' => $media->bundle(),
        '%title' => $media->getName(),
        '%revision_log' => $media->getRevisionLogMessage() ? ': ' . $media->getRevisionLogMessage() : '',
      ]),
      'ref_numeric' => $media->id(),
      'ref_char' => $media->label(),
    ];
    // Insert log.
    admin_audit_trail_insert($log);
  }

}
