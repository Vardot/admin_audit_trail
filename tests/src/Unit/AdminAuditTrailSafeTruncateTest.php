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

  /**
   * A percent-encoded non-ASCII source decodes to a short, readable ref_char.
   *
   * Mirrors the redirect handler fix for issue #3584163: getSourceUrl() returns
   * an RFC 3986 percent-encoded path (each non-ASCII character ~9 chars), which
   * overflows the varchar(255) ref_char column. rawurldecode() shrinks it and
   * admin_audit_trail_safe_truncate() guarantees it fits.
   */
  public function testDecodedNonAsciiSourceFitsRefChar(): void {
    // 40x "시" percent-encoded ("%EC%8B%9C") => 360 characters, over the limit.
    $encoded = str_repeat('%EC%8B%9C', 40);
    $this->assertGreaterThan(255, strlen($encoded));

    $ref_char = admin_audit_trail_safe_truncate(rawurldecode($encoded), 255);

    $this->assertLessThanOrEqual(255, mb_strlen($ref_char));
    $this->assertTrue(mb_check_encoding($ref_char, 'UTF-8'));
    // The stored value is readable (decoded), not percent-encoded.
    $this->assertStringContainsString("\u{C2DC}", $ref_char);
    $this->assertStringNotContainsString('%EC', $ref_char);
  }

}
