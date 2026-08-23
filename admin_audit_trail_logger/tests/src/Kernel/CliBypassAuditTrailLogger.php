<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_logger\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;

/**
 * The real audit trail logger with the CLI guard disabled.
 *
 * PHPUnit runs under the CLI SAPI, where insert() returns before invoking
 * the alter hooks; disabling the guard lets kernel tests exercise the real
 * insert flow (alter dispatch, skip_db handling, database write).
 */
class CliBypassAuditTrailLogger extends AdminAuditTrailLogger {

  /**
   * {@inheritdoc}
   */
  protected function isCliRequest(): bool {
    return FALSE;
  }

}
