<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_logger\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests forwarding audit records to the PSR-3 logger.
 *
 * Coverage for issue #3594471: hybrid mode forwards and stores, PSR-3-only
 * mode skips the database write, the channel is configurable, and the
 * severity map resolves operations with a default and a typo-safe fallback.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class LoggerForwardingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'admin_audit_trail',
    'admin_audit_trail_logger',
  ];

  /**
   * Collects every record the logger channels receive.
   */
  protected TestLogCollector $collector;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('admin_audit_trail', ['admin_audit_trail']);
    $this->installConfig(['admin_audit_trail_logger']);

    $this->collector = new TestLogCollector();
    $this->container->get('logger.factory')->addLogger($this->collector);

    // The real logger skips inserts under the CLI SAPI (which PHPUnit runs
    // under); replace it with a subclass that only disables that guard so
    // the genuine insert flow runs.
    $this->container->set(AdminAuditTrailLogger::class, new CliBypassAuditTrailLogger(
      $this->container->get('datetime.time'),
      $this->container->get('current_user'),
      $this->container->get('request_stack'),
      $this->container->get('database'),
      $this->container->get('module_handler'),
      $this->container->get('config.factory'),
    ));
  }

  /**
   * Inserts a sample record through the real audit trail logger.
   */
  protected function insertSample(string $operation = 'insert'): void {
    $log = [
      'type' => 'node',
      'operation' => $operation,
      'description' => 'Test event',
      'path' => 'test/path',
      'ref_char' => '',
      'ref_numeric' => 1,
    ];
    $this->container->get(AdminAuditTrailLogger::class)->insert($log);
  }

  /**
   * Returns the number of rows in the audit trail table.
   */
  protected function rowCount(): int {
    return (int) $this->container->get('database')
      ->select('admin_audit_trail')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Returns the collected records on the audit channels.
   *
   * @return array[]
   *   Records whose channel context is not a core channel (filters out
   *   unrelated cron/system noise).
   */
  protected function auditRecords(): array {
    return array_values(array_filter(
      $this->collector->records,
      static fn(array $r): bool => str_contains($r['message'], '{operation}'),
    ));
  }

  /**
   * Hybrid mode (default) forwards to PSR-3 AND stores in the database.
   */
  public function testHybridModeForwardsAndStores(): void {
    $this->insertSample();

    $records = $this->auditRecords();
    $this->assertCount(1, $records);
    // Drupal's LoggerChannel translates PSR-3 levels to RFC 5424 integers
    // before delegating to the registered loggers.
    $this->assertSame(RfcLogLevel::NOTICE, $records[0]['level']);
    $this->assertSame('audit_trail', $records[0]['context']['channel']);
    $this->assertSame('node', $records[0]['context']['type']);
    $this->assertSame('insert', $records[0]['context']['operation']);
    $this->assertSame(1, $this->rowCount());
  }

  /**
   * PSR-3-only mode forwards but skips the database write.
   */
  public function testPsr3OnlyModeSkipsDatabase(): void {
    $this->config('admin_audit_trail_logger.settings')
      ->set('mode', 'psr3_only')
      ->save();

    $this->insertSample();

    $this->assertCount(1, $this->auditRecords());
    $this->assertSame(0, $this->rowCount());
  }

  /**
   * The PSR-3 channel is configurable.
   */
  public function testChannelOverride(): void {
    $this->config('admin_audit_trail_logger.settings')
      ->set('channel', 'security')
      ->save();

    $this->insertSample();

    $records = $this->auditRecords();
    $this->assertSame('security', $records[0]['context']['channel']);
  }

  /**
   * The severity map resolves operations, defaults, and invalid levels.
   */
  public function testSeverityMap(): void {
    $this->config('admin_audit_trail_logger.settings')
      ->set('severity_map', [
        'default' => 'warning',
        'link delete' => 'alert',
        'insert' => 'bogus_level',
      ])
      ->save();

    // Exact operation match.
    $this->insertSample('link delete');
    // Unmapped operation falls back to the "default" key.
    $this->insertSample('update');
    // Invalid level degrades to notice instead of a fatal.
    $this->insertSample('insert');

    $levels = array_column($this->auditRecords(), 'level');
    $this->assertSame([RfcLogLevel::ALERT, RfcLogLevel::WARNING, RfcLogLevel::NOTICE], $levels);
  }

}
