<?php

declare(strict_types=1);

namespace Drupal\admin_audit_trail\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes audit trail operations to Views.
 */
#[ViewsFilter("audit_trail_operations")]
class AuditTrailOperations extends InOperator {

  /**
   * The cache id for the computed operation options.
   */
  protected const CID = 'admin_audit_trail:operations';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The default (non-render) cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->cache = $container->get('cache.default');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): ?array {
    if (!isset($this->valueOptions)) {
      // The distinct operations change only when a new operation is first
      // logged, but the exposed filter renders on every report request. Cache
      // the result and let the logger's existing cache-tag invalidation refresh
      // it, so the SELECT DISTINCT does not scan the whole (potentially large)
      // log table on each page load.
      $cached = $this->cache->get(self::CID);
      if ($cached) {
        $this->valueOptions = $cached->data;
      }
      else {
        $this->valueOptions = [];
        $result = $this->database->select('admin_audit_trail', 'aat')
          ->fields('aat', ['operation'])
          ->distinct()
          ->orderBy('operation', 'ASC')
          ->execute();
        foreach ($result as $row) {
          if (!empty($row->operation)) {
            $this->valueOptions[$row->operation] = ucfirst($row->operation);
          }
        }
        $this->cache->set(self::CID, $this->valueOptions, Cache::PERMANENT, ['config:views.view.admin_audit_trail']);
      }
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    parent::valueForm($form, $form_state);
    $form['value']['#access'] = !empty($form['value']['#options']);
  }

}
