<?php

namespace Drupal\admin_audit_trail_logger\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LogLevel;

/**
 * Hook implementations for admin_audit_trail_logger.
 */
class AdminAuditTrailLoggerHooks {

  /**
   * The valid PSR-3 severity levels.
   */
  protected const VALID_LEVELS = [
    LogLevel::EMERGENCY,
    LogLevel::ALERT,
    LogLevel::CRITICAL,
    LogLevel::ERROR,
    LogLevel::WARNING,
    LogLevel::NOTICE,
    LogLevel::INFO,
    LogLevel::DEBUG,
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
  }

  /**
   * Implements hook_admin_audit_trail_log_alter().
   *
   * Forwards every audit record to the configured PSR-3 channel, and in
   * "PSR-3 only" mode asks the parent module to skip the database write.
   */
  #[Hook('admin_audit_trail_log_alter')]
  public function logAlter(array &$log): void {
    $config = $this->configFactory->get('admin_audit_trail_logger.settings');

    $mode = $config->get('mode') ?: 'hybrid';
    $channel = $config->get('channel') ?: 'audit_trail';

    // All events are logged at "notice" severity by default. Severities per
    // operation come from the severity_map setting (exact match on
    // $log['operation'] - compound operations such as "link delete" need the
    // full string as key - with a "default" fallback key).
    $severity_map = $config->get('severity_map') ?: [];
    $severity = $severity_map[$log['operation']] ?? $severity_map['default'] ?? LogLevel::NOTICE;

    // Validate against PSR-3 levels to prevent fatal errors from typos.
    if (!in_array($severity, self::VALID_LEVELS, TRUE)) {
      $severity = LogLevel::NOTICE;
    }

    $this->loggerFactory->get($channel)->log(
      $severity,
      '[{type}] {operation}: {description} (uid={uid}, ip={ip}, path={path})',
      [
        'type' => $log['type'] ?? '',
        'operation' => $log['operation'] ?? '',
        'description' => $log['description'] ?? '',
        'uid' => $log['uid'] ?? 0,
        'ip' => $log['ip'] ?? '',
        'path' => $log['path'] ?? '',
      ]
    );

    // In psr3_only mode, prevent the parent module from writing to the
    // database.
    if ($mode === 'psr3_only') {
      $log['skip_db'] = TRUE;
    }
  }

}
