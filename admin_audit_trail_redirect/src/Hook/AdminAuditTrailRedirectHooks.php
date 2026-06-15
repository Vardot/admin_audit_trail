<?php

namespace Drupal\admin_audit_trail_redirect\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_redirect.
 */
class AdminAuditTrailRedirectHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Redirect event log handler.
    $handlers = [];
    $handlers['redirect'] = [
      'title' => $this->t('Redirect'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_redirect_insert().
   */
  #[Hook('redirect_insert')]
  public function redirectInsert($redirect) {
    // getSourceUrl() returns an RFC 3986 percent-encoded path: each non-ASCII
    // character expands to ~9 characters (e.g. "시" => "%EC%8B%9C"), which can
    // overflow the varchar(255) ref_char column. Decode it for a readable,
    // shorter value and trim to the column limit as a safeguard
    // (issue #3584163).
    $source = rawurldecode($redirect->getSourceUrl());
    $log = [
      'type' => 'redirect',
      'operation' => 'insert',
      'description' => $this->t('src: %source (%status) to: %dest', [
        '%status' => $redirect->getStatusCode(),
        '%source' => $source,
        '%dest' => $redirect->getRedirectUrl()->toString(),
      ]),
      'ref_numeric' => $redirect->id(),
      'ref_char' => admin_audit_trail_safe_truncate($source, 255),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_redirect_update().
   */
  #[Hook('redirect_update')]
  public function redirectUpdate($redirect) {
    $source = rawurldecode($redirect->getSourceUrl());
    $log = [
      'type' => 'redirect',
      'operation' => 'update',
      'description' => $this->t('src: %source (%status) to: %dest', [
        '%status' => $redirect->getStatusCode(),
        '%source' => $source,
        '%dest' => $redirect->getRedirectUrl()->toString(),
      ]),
      'ref_numeric' => $redirect->id(),
      'ref_char' => admin_audit_trail_safe_truncate($source, 255),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_redirect_delete().
   */
  #[Hook('redirect_delete')]
  public function redirectDelete($redirect) {
    $source = rawurldecode($redirect->getSourceUrl());
    $log = [
      'type' => 'redirect',
      'operation' => 'delete',
      'description' => $this->t('src: %source (%status) to: %dest', [
        '%status' => $redirect->getStatusCode(),
        '%source' => $source,
        '%dest' => $redirect->getRedirectUrl()->toString(),
      ]),
      'ref_numeric' => $redirect->id(),
      'ref_char' => admin_audit_trail_safe_truncate($source, 255),
    ];
    admin_audit_trail_insert($log);
  }

}
