<?php

namespace Drupal\admin_audit_trail_file\Hook;

use Drupal\file\Entity\File;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_file.
 */
class AdminAuditTrailFileHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    $handlers = [];
    // File event log handler.
    $handlers['file'] = [
      'title' => $this->t('File'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('file_insert')]
  public static function fileInsert(File $file) {
    $log = [
      'type' => 'file',
      'operation' => 'insert',
      'description' => $file->getFileUri(),
      'ref_numeric' => $file->id(),
      'ref_char' => $file->getFilename(),
    ];
    // Insert log.
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('file_update')]
  public static function fileUpdate(File $file) {
    $log = [
      'type' => 'file',
      'operation' => 'update',
      'description' => $file->getFileUri(),
      'ref_numeric' => $file->id(),
      'ref_char' => $file->getFilename(),
    ];
    // Insert log.
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('file_delete')]
  public static function fileDelete(File $file) {
    $log = [
      'type' => 'file',
      'operation' => 'delete',
      'description' => $file->getFileUri(),
      'ref_numeric' => $file->id(),
      'ref_char' => $file->getFilename(),
    ];
    // Insert log.
    admin_audit_trail_insert($log);
  }

}
