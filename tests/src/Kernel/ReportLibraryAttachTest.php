<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the report stylesheet is attached to the audit trail view.
 *
 * Coverage for issue #3583295: long path/description values should wrap instead
 * of stretching the table. The wrapping is delivered by a CSS library that
 * hook_views_pre_render() attaches only to the admin_audit_trail view.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class ReportLibraryAttachTest extends KernelTestBase {

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

    // The report view ships as optional config; create it explicitly.
    View::create($this->loadReportViewConfig())->save();
  }

  /**
   * Loads the optional report view configuration as an array.
   */
  private function loadReportViewConfig(): array {
    $path = \Drupal::service('extension.list.module')->getPath('admin_audit_trail')
      . '/config/optional/views.view.admin_audit_trail.yml';
    $contents = file_get_contents($path);
    $this->assertIsString($contents, 'The report view config file is readable.');
    return Yaml::decode($contents);
  }

  /**
   * The report view gets the report library attached.
   */
  public function testReportViewGetsLibrary(): void {
    $view = Views::getView('admin_audit_trail');
    $this->assertNotNull($view, 'The admin_audit_trail view exists.');
    $view->setDisplay('page_1');

    admin_audit_trail_views_pre_render($view);

    $libraries = $view->element['#attached']['library'] ?? [];
    $this->assertContains('admin_audit_trail/report', $libraries);
  }

  /**
   * A different view does not get the report library attached.
   */
  public function testOtherViewIsUntouched(): void {
    // Reuse the report config under a different id to get a real executable
    // whose id does not match the guard.
    $data = $this->loadReportViewConfig();
    $data['id'] = 'some_other_view';
    unset($data['uuid']);
    View::create($data)->save();

    $view = Views::getView('some_other_view');
    $view->setDisplay('page_1');

    admin_audit_trail_views_pre_render($view);

    $libraries = $view->element['#attached']['library'] ?? [];
    $this->assertNotContains('admin_audit_trail/report', $libraries);
  }

}
