<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests admin_audit_trail_safe_truncate(), used to trim the logged path.
 *
 * @group admin_audit_trail
 */
class AdminAuditTrailSafeTruncateTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once __DIR__ . '/../../../admin_audit_trail.module';
  }

  /**
   * A value within the limit is returned unchanged.
   */
  public function testShortValueUnchanged(): void {
    $this->assertSame(
      'node/add/article',
      admin_audit_trail_safe_truncate('node/add/article', 255)
    );
  }

  /**
   * A path longer than the column limit is trimmed to at most 255 characters.
   *
   * This is the trim that prevents the "Data too long for column 'path'"
   * SQL error (issue #3588241).
   */
  public function testLongPathTrimmedToLimit(): void {
    $long = 's3/cors/' . str_repeat('a', 400);
    $result = admin_audit_trail_safe_truncate($long, 255);
    $this->assertLessThanOrEqual(255, mb_strlen($result));
  }

  /**
   * Trimming a multibyte value never produces invalid UTF-8.
   */
  public function testMultibyteValueRemainsValid(): void {
    $long = str_repeat("\u{0634}", 400);
    $result = admin_audit_trail_safe_truncate($long, 255);
    $this->assertLessThanOrEqual(255, mb_strlen($result));
    $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
  }

  /**
   * A non-string value is converted to an empty string.
   */
  public function testNonStringReturnsEmptyString(): void {
    $this->assertSame('', admin_audit_trail_safe_truncate(NULL, 255));
  }

}
