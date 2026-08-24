<?php

declare(strict_types=1);

namespace Drupal\admin_audit_trail;

/**
 * Installs the report view from the shipped optional configuration.
 *
 * Some install-profile (distribution) installs skip the module's optional
 * configuration (issue #3618734), and a fresh install stamps the schema at
 * the newest update, so the repair must be callable from any update hook.
 * Called statically because update hooks run before the module's services
 * are guaranteed to be usable.
 */
final class AdminAuditTrailReportView {

  /**
   * Creates the report view when it is missing.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A status message describing the outcome.
   */
  public static function ensure() {
    // Check if Views module is enabled.
    if (!\Drupal::moduleHandler()->moduleExists('views')) {
      return t('Views module is not enabled. The Admin Audit Trail view was not installed.');
    }

    // Check if the view already exists.
    $view = \Drupal::entityTypeManager()
      ->getStorage('view')
      ->load('admin_audit_trail');

    if ($view) {
      return t('Admin Audit Trail view already exists.');
    }

    // Get the module path and read the view configuration.
    $module_path = \Drupal::service('extension.list.module')->getPath('admin_audit_trail');
    $config_path = $module_path . '/config/optional/views.view.admin_audit_trail.yml';

    if (!file_exists($config_path)) {
      return t('View configuration file not found at @path', ['@path' => $config_path]);
    }

    // Load and parse the YAML configuration.
    $yaml_content = file_get_contents($config_path);
    if ($yaml_content === FALSE) {
      return t('Unable to read the view configuration file at @path', ['@path' => $config_path]);
    }
    $config_data = \Drupal::service('serialization.yaml')->decode($yaml_content);

    // Create the view from configuration.
    \Drupal::entityTypeManager()
      ->getStorage('view')
      ->create($config_data)
      ->save();

    return t('Admin Audit Trail view has been successfully installed.');
  }

}
