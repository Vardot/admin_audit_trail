<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail\Kernel;

use Drupal\admin_audit_trail\Plugin\views\filter\AuditTrailOperations;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the exposed "Operation" filter on the audit trail report.
 *
 * Coverage for issue #3319320: the Operations dropdown must be populated from
 * the logged operations. The result is cached and refreshed when the audit log
 * cache tag is invalidated, so the query does not run on every render.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class OperationsFilterTest extends KernelTestBase {

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

    $path = \Drupal::service('extension.list.module')->getPath('admin_audit_trail')
      . '/config/optional/views.view.admin_audit_trail.yml';
    $contents = file_get_contents($path);
    $this->assertIsString($contents);
    View::create(Yaml::decode($contents))->save();
  }

  /**
   * Writes a minimal audit row with the given operation.
   */
  private function logOperation(string $operation): void {
    $this->container->get('database')->insert('admin_audit_trail')->fields([
      'type' => 'node',
      'operation' => $operation,
      'description' => 'test',
      'created' => 1,
      'uid' => 0,
      'ip' => '',
      'path' => 'test',
      'ref_char' => '',
      'ref_numeric' => 0,
    ])->execute();
  }

  /**
   * Returns the operations filter handler from a fresh view instance.
   */
  private function operationsFilter(): AuditTrailOperations {
    $view = Views::getView('admin_audit_trail');
    $view->setDisplay('page_1');
    $view->initHandlers();
    $filter = $view->filter['operation'];
    $this->assertInstanceOf(AuditTrailOperations::class, $filter);
    return $filter;
  }

  /**
   * The dropdown lists the logged operations, cached and tag-invalidated.
   */
  public function testOperationsArePopulatedAndCached(): void {
    $this->logOperation('insert');
    $this->logOperation('update');

    $options = $this->operationsFilter()->getValueOptions();
    $this->assertSame(['insert' => 'Insert', 'update' => 'Update'], $options);

    // The computed options are cached.
    $this->assertNotFalse($this->container->get('cache.default')->get('admin_audit_trail:operations'));

    // A new operation only shows up once the log cache tag is invalidated.
    $this->logOperation('delete');
    $stale = $this->operationsFilter()->getValueOptions();
    $this->assertArrayNotHasKey('delete', $stale, 'Cached options are reused until invalidated.');

    Cache::invalidateTags(['config:views.view.admin_audit_trail']);
    $fresh = $this->operationsFilter()->getValueOptions();
    $this->assertArrayHasKey('delete', $fresh);
    $this->assertSame('Delete', $fresh['delete']);
  }

}
