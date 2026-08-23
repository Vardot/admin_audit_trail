<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_logger\Kernel;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that collects every record for assertions.
 */
class TestLogCollector extends AbstractLogger {

  /**
   * The collected records: level, message, context.
   *
   * @var array[]
   */
  public array $records = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->records[] = [
      'level' => $level,
      'message' => (string) $message,
      'context' => $context,
    ];
  }

}
