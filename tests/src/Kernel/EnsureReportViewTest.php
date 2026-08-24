<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailReportView;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the report view repair used by the update hooks.
 *
 * Coverage for issue #3618734: some install-profile (distribution) installs
 * skip the module's optional configuration, leaving /admin/reports/audit-trail
 * as a 404 with the schema already stamped at the newest update. The repair
 * has to create the view from the shipped optional config when it is missing,
 * and leave an existing view untouched.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class EnsureReportViewTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'views',
    'admin_audit_trail',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('admin_audit_trail', ['admin_audit_trail']);
    $this->container->get('module_handler')
      ->loadInclude('admin_audit_trail', 'install');
  }

  /**
   * The repair creates the view when it is missing.
   */
  public function testCreatesMissingView(): void {
    $this->assertNull(View::load('admin_audit_trail'));

    $message = AdminAuditTrailReportView::ensure();

    $this->assertNotNull(View::load('admin_audit_trail'), 'The report view was created.');
    $this->assertStringContainsString('successfully installed', (string) $message);
  }

  /**
   * The repair leaves an existing view untouched.
   */
  public function testKeepsExistingView(): void {
    AdminAuditTrailReportView::ensure();
    $view = View::load('admin_audit_trail');
    $this->assertNotNull($view);
    $view->set('label', 'Customized label')->save();

    $message = AdminAuditTrailReportView::ensure();

    $this->assertStringContainsString('already exists', (string) $message);
    $this->assertSame('Customized label', View::load('admin_audit_trail')->label());
  }

  /**
   * Both update hooks delegate to the same repair.
   */
  public function testUpdateHooksRepairTheView(): void {
    $this->assertNull(View::load('admin_audit_trail'));
    admin_audit_trail_update_10003();
    $this->assertNotNull(View::load('admin_audit_trail'));
  }

}
