<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the CLI logging option.
 *
 * Coverage for issue #3263615: by default events triggered under the CLI SAPI
 * are ignored; with the log_cli setting enabled they are recorded and flagged
 * as CLI-originated. PHPUnit itself runs under the CLI SAPI, so these tests
 * exercise the real code path without any test double.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
class CliLoggingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'admin_audit_trail',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('admin_audit_trail', ['admin_audit_trail']);
    $this->installConfig(['admin_audit_trail']);
  }

  /**
   * Inserts a sample record through the real audit trail logger.
   */
  protected function insertSample(): void {
    $log = [
      'type' => 'node',
      'operation' => 'insert',
      'description' => 'Test event',
      'ref_char' => '',
      'ref_numeric' => 1,
    ];
    $this->container->get(AdminAuditTrailLogger::class)->insert($log);
  }

  /**
   * Returns all rows from the audit trail table.
   *
   * @return array[]
   *   The audit trail rows as associative arrays.
   */
  protected function rows(): array {
    $rows = $this->container->get('database')
      ->select('admin_audit_trail', 'a')
      ->fields('a')
      ->execute()
      ->fetchAll();
    // Cast to arrays: FetchAs only exists in newer cores, and static analysis
    // rejects property access on the default stdClass rows.
    return array_map(static fn(object $row): array => (array) $row, $rows);
  }

  /**
   * By default, CLI events are not recorded.
   */
  public function testCliIgnoredByDefault(): void {
    $this->insertSample();
    $this->assertSame([], $this->rows());
  }

  /**
   * With log_cli enabled, CLI events are recorded and flagged.
   */
  public function testCliLoggedAndFlaggedWhenEnabled(): void {
    $this->config('admin_audit_trail.settings')
      ->set('log_cli', TRUE)
      ->save();

    $this->insertSample();

    $rows = $this->rows();
    $this->assertCount(1, $rows);
    $this->assertSame('CLI', $rows[0]['ip']);
    $this->assertStringStartsWith('cli: ', $rows[0]['path']);
    $this->assertSame('node', $rows[0]['type']);
  }

  /**
   * A caller-provided path is preserved; only the IP flag is forced.
   */
  public function testCliKeepsProvidedPath(): void {
    $this->config('admin_audit_trail.settings')
      ->set('log_cli', TRUE)
      ->save();

    $log = [
      'type' => 'node',
      'operation' => 'insert',
      'description' => 'Test event',
      'path' => 'custom/source',
      'ref_char' => '',
      'ref_numeric' => 1,
    ];
    $this->container->get(AdminAuditTrailLogger::class)->insert($log);

    $rows = $this->rows();
    $this->assertCount(1, $rows);
    $this->assertSame('custom/source', $rows[0]['path']);
    $this->assertSame('CLI', $rows[0]['ip']);
  }

}
