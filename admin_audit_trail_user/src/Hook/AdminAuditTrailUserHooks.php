<?php

namespace Drupal\admin_audit_trail_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_user.
 */
class AdminAuditTrailUserHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // User event log handler.
    $handlers = [];
    $handlers['user'] = [
      'title' => $this->t('User'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_user_insert().
   */
  #[Hook('user_insert')]
  public function userInsert($account) {
    $log = [
      'type' => 'user',
      'operation' => 'insert',
      'description' => $this->t('%name (uid %uid)', [
        '%name' => $account->getDisplayName(),
        '%uid' => $account->id(),
      ]),
      'ref_numeric' => $account->id(),
      'ref_char' => $account->getDisplayName(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_user_update().
   */
  #[Hook('user_update')]
  public function userUpdate($account) {
    $log = [
      'type' => 'user',
      'operation' => 'update',
      'description' => $this->t('%name (uid %uid)', [
        '%name' => $account->original->getDisplayName(),
        '%uid' => $account->original->id(),
      ]),
      'ref_numeric' => $account->original->id(),
      'ref_char' => $account->getDisplayName(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_user_delete().
   */
  #[Hook('user_delete')]
  public function userDelete($account) {
    $log = [
      'type' => 'user',
      'operation' => 'delete',
      'description' => $this->t('%name (uid %uid)', [
        '%name' => $account->getDisplayName(),
        '%uid' => $account->id(),
      ]),
      'ref_numeric' => $account->id(),
      'ref_char' => $account->getDisplayName(),
    ];
    admin_audit_trail_insert($log);
  }

}
