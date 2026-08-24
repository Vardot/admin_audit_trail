<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailReportView;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the module exposes its table to Views.
 *
 * Coverage for issue #3618801: hook_views_data() moved from
 * admin_audit_trail.views.inc into a hook class, but the class was never
 * registered as a service, so the implementation disappeared. Views then had
 * no data for the admin_audit_trail base table and the report died with
 * "A valid cache entry key is required" from ViewsData::get().
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class ViewsDataTest extends KernelTestBase {

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
  }

  /**
   * The table is exposed to Views with its base definition.
   */
  public function testTableIsExposedToViews(): void {
    $data = $this->container->get('views.views_data')->get('admin_audit_trail');

    $this->assertIsArray($data);
    $this->assertArrayHasKey('table', $data, 'The audit trail table is exposed to Views.');
    $this->assertSame('lid', $data['table']['base']['field']);
    $this->assertSame('admin_audit_trail', $data['table']['wizard_id']);
  }

  /**
   * Every column the report shows is exposed as a Views field.
   */
  public function testReportColumnsAreExposed(): void {
    $data = $this->container->get('views.views_data')->get('admin_audit_trail');

    foreach (['lid', 'type', 'operation', 'path', 'description', 'uid', 'ip', 'created', 'ref_char', 'ref_numeric'] as $column) {
      $this->assertArrayHasKey($column, $data, sprintf('The %s column is exposed to Views.', $column));
      $this->assertArrayHasKey('field', $data[$column], sprintf('The %s column can be used as a field.', $column));
    }
  }

  /**
   * The user relationship the report uses is exposed.
   */
  public function testUserRelationshipIsExposed(): void {
    $data = $this->container->get('views.views_data')->get('admin_audit_trail');

    $this->assertArrayHasKey('relationship', $data['uid']);
    $this->assertSame('users_field_data', $data['uid']['relationship']['base']);
  }

  /**
   * The shipped report view executes.
   *
   * Without the Views data this throws, which is the crash reported on an
   * upgrade to 1.0.11.
   */
  public function testShippedReportViewExecutes(): void {
    $this->container->get('module_handler')->loadInclude('admin_audit_trail', 'install');
    AdminAuditTrailReportView::ensure();

    $view = Views::getView('admin_audit_trail');
    $this->assertNotNull($view, 'The report view exists.');
    $view->setDisplay('page_1');
    $view->execute();

    $this->assertIsArray($view->result);
  }

  /**
   * The legacy procedural wrapper exists and returns the same data.
   *
   * Object-oriented hook discovery only exists in Drupal 11.1 and later, and
   * the module supports ^10.1 || ^11 || ^12. On the older cores the wrapper in
   * the .module file is the only implementation Views can find, so its absence
   * is what broke the report (issue #3618801).
   */
  public function testLegacyWrapperReturnsTheData(): void {
    $this->container->get('module_handler')->loadInclude('admin_audit_trail', 'module');
    $this->assertTrue(function_exists('admin_audit_trail_views_data'), 'The procedural hook_views_data() wrapper exists for cores without hook discovery.');

    $data = admin_audit_trail_views_data();
    $this->assertArrayHasKey('table', $data['admin_audit_trail']);
    $this->assertSame('lid', $data['admin_audit_trail']['table']['base']['field']);
  }

  /**
   * Every object-oriented hook of the module has a procedural wrapper.
   *
   * Guards the whole class of defect rather than the one occurrence.
   */
  public function testEveryHookHasLegacyWrapper(): void {
    $this->container->get('module_handler')->loadInclude('admin_audit_trail', 'module');
    $module_path = \Drupal::service('extension.list.module')->getPath('admin_audit_trail');

    $files = glob($module_path . '/src/Hook/*.php') ?: [];
    $this->assertNotEmpty($files, 'The module ships hook classes.');
    foreach ($files as $file) {
      preg_match_all("/#\\[Hook\\('([a-z_]+)'\\)\\]/", (string) file_get_contents($file), $matches);
      foreach ($matches[1] as $hook) {
        $function = 'admin_audit_trail_' . $hook;
        $this->assertTrue(function_exists($function), sprintf('%s() wraps the %s hook implementation.', $function, $hook));
      }
    }
  }

}
