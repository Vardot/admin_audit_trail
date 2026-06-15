<?php

namespace Drupal\admin_audit_trail_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_config.
 */
class AdminAuditTrailConfigHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    $handlers = [];
    $handlers['config'] = [
      'title' => $this->t('Configuration'),
    ];
    return $handlers;
  }

}
