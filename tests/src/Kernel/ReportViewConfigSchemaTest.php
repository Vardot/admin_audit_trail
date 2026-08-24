<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shipped report view configuration against the config schema.
 *
 * Coverage for issue #3608941: the optional view carried a
 * style.options.class key that only exists in the views.style.table schema
 * of newer cores, so installing the view on the oldest supported core threw
 * a SchemaIncompleteException.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class ReportViewConfigSchemaTest extends KernelTestBase {

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
   * The shipped view saves cleanly under strict config schema checking.
   *
   * KernelTestBase enables the ConfigSchemaChecker, so any key missing from
   * the views schema makes this save throw a SchemaIncompleteException.
   */
  public function testShippedViewPassesConfigSchema(): void {
    $this->installEntitySchema('user');
    $this->installSchema('admin_audit_trail', ['admin_audit_trail']);

    $view = View::create($this->loadReportViewConfig());
    $view->save();

    $this->assertNotNull(View::load('admin_audit_trail'), 'The report view was created from the shipped config.');
  }

  /**
   * The table style options carry no "class" key.
   *
   * The key only exists in the views.style.table schema of newer cores, so
   * shipping it breaks installs on the oldest supported core (^10.1). Guards
   * against the key sneaking back in through a config re-export from a newer
   * core.
   */
  public function testStyleOptionsCarryNoClassKey(): void {
    $config = $this->loadReportViewConfig();
    foreach ($config['display'] as $display_id => $display) {
      $style_options = $display['display_options']['style']['options'] ?? [];
      $this->assertArrayNotHasKey('class', $style_options, sprintf('The %s display style options carry no "class" key.', $display_id));
    }
  }

}
