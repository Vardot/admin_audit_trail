<?php

namespace Drupal\admin_audit_trail_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for admin_audit_trail_test.
 */
class AdminAuditTrailTestHooks {

  /**
   * Implements hook_admin_audit_trail_log_alter().
   *
   * For events whose reference name contains "LONGPATH", force a path longer
   * than the 255-character "path" column so the trim in
   * admin_audit_trail_insert() (issue #3588241) is exercised end-to-end through
   * a real web request.
   */
  #[Hook('admin_audit_trail_log_alter')]
  public static function adminAuditTrailLogAlter(array &$log) {
    if (isset($log['ref_char']) && str_contains((string) $log['ref_char'], 'LONGPATH')) {
      $log['path'] = 'longpath/' . str_repeat('a', 400);
    }
  }

}
