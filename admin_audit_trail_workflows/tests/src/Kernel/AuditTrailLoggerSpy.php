<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_workflows\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;

/**
 * Logger spy: captures log records instead of writing rows.
 *
 * The real logger skips inserts under PHP_SAPI 'cli' (which is what PHPUnit
 * runs under), so kernel tests replace it with this spy and assert on the
 * captured records.
 */
class AuditTrailLoggerSpy extends AdminAuditTrailLogger {

  /**
   * The captured log records.
   *
   * @var array[]
   */
  public array $captured = [];

  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function insert(array &$log): void {
    $this->captured[] = $log;
  }

}
