<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_logger\Unit;

use Drupal\admin_audit_trail_logger\Hook\AdminAuditTrailLoggerHooks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the PSR-3 forwarding alter hook.
 *
 * @coversDefaultClass \Drupal\admin_audit_trail_logger\Hook\AdminAuditTrailLoggerHooks
 * @group admin_audit_trail
 */
class AdminAuditTrailLoggerHooksTest extends UnitTestCase {

  /**
   * Builds the hook service with the given settings, capturing log calls.
   *
   * @param array $settings
   *   The admin_audit_trail_logger.settings values.
   * @param array $calls
   *   Receives one [channel, level, message, context] entry per log call.
   */
  protected function buildHooks(array $settings, array &$calls): AdminAuditTrailLoggerHooks {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn(string $key) => $settings[$key] ?? NULL,
    );
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('admin_audit_trail_logger.settings')
      ->willReturn($config);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturnCallback(
      function (string $channel) use (&$calls) {
        $logger = $this->createMock(LoggerChannelInterface::class);
        $logger->method('log')->willReturnCallback(
          static function ($level, $message, array $context = []) use (&$calls, $channel): void {
            $calls[] = [$channel, $level, (string) $message, $context];
          },
        );
        return $logger;
      },
    );

    return new AdminAuditTrailLoggerHooks($config_factory, $logger_factory);
  }

  /**
   * Runs the alter hook on a sample record and returns it.
   */
  protected function alter(AdminAuditTrailLoggerHooks $hooks, string $operation = 'insert'): array {
    $log = [
      'type' => 'node',
      'operation' => $operation,
      'description' => 'Test event',
      'uid' => 3,
      'ip' => '203.0.113.7',
      'path' => 'node/1/edit',
    ];
    $hooks->logAlter($log);
    return $log;
  }

  /**
   * Hybrid defaults: notice level, audit_trail channel, no skip_db.
   *
   * @covers ::logAlter
   */
  public function testDefaultsForwardAtNotice(): void {
    $calls = [];
    $log = $this->alter($this->buildHooks([], $calls));

    $this->assertCount(1, $calls);
    [$channel, $level, $message, $context] = $calls[0];
    $this->assertSame('audit_trail', $channel);
    $this->assertSame('notice', $level);
    $this->assertStringContainsString('{operation}', $message);
    $this->assertSame('node', $context['type']);
    $this->assertSame(3, $context['uid']);
    $this->assertArrayNotHasKey('skip_db', $log);
  }

  /**
   * PSR-3-only mode sets skip_db after forwarding.
   *
   * @covers ::logAlter
   */
  public function testPsr3OnlySetsSkipDb(): void {
    $calls = [];
    $log = $this->alter($this->buildHooks(['mode' => 'psr3_only'], $calls));

    $this->assertCount(1, $calls);
    $this->assertTrue($log['skip_db']);
  }

  /**
   * The configured channel is used.
   *
   * @covers ::logAlter
   */
  public function testChannelOverride(): void {
    $calls = [];
    $this->alter($this->buildHooks(['channel' => 'security'], $calls));

    $this->assertSame('security', $calls[0][0]);
  }

  /**
   * Severity map: exact match, default fallback, invalid level fallback.
   *
   * @covers ::logAlter
   */
  public function testSeverityMap(): void {
    $calls = [];
    $hooks = $this->buildHooks([
      'severity_map' => [
        'default' => 'warning',
        'link delete' => 'alert',
        'insert' => 'bogus_level',
      ],
    ], $calls);

    $this->alter($hooks, 'link delete');
    $this->alter($hooks, 'update');
    $this->alter($hooks, 'insert');

    $this->assertSame(['alert', 'warning', 'notice'], array_column($calls, 1));
  }

}
