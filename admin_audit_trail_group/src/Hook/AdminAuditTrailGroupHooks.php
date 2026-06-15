<?php

namespace Drupal\admin_audit_trail_group\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_group.
 */
class AdminAuditTrailGroupHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // User event log handler.
    $handlers = [];
    $handlers['group'] = [
      'title' => $this->t('Group'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_group_insert().
   */
  #[Hook('group_insert')]
  public function groupInsert($group) {
    $log = [
      'type' => 'group',
      'operation' => 'insert',
      'description' => $this->t('%bundle: %title', [
        '%bundle' => $group->bundle(),
        '%title' => $group->label(),
      ]),
      'ref_numeric' => $group->id(),
      'ref_char' => $group->label(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_group_update().
   */
  #[Hook('group_update')]
  public function groupUpdate($group) {
    $log = [
      'type' => 'group',
      'operation' => 'update',
      'description' => $this->t('%bundle: %title', [
        '%bundle' => $group->bundle(),
        '%title' => $group->label(),
      ]),
      'ref_numeric' => $group->id(),
      'ref_char' => $group->label(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_group_delete().
   */
  #[Hook('group_delete')]
  public function groupDelete($group) {
    $log = [
      'type' => 'group',
      'operation' => 'delete',
      'description' => $this->t('%bundle: %title', [
        '%bundle' => $group->bundle(),
        '%title' => $group->label(),
      ]),
      'ref_numeric' => $group->id(),
      'ref_char' => $group->label(),
    ];
    admin_audit_trail_insert($log);
  }

}
