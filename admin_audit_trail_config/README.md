# Admin Audit Trail Config

Admin Audit Trail Config is a Drupal module that extends the Admin Audit Trail module by logging all configuration changes.

Drupal configuration (site settings, content types, fields, views, vocabularies, menus and any other simple or configuration-entity data) cannot be tracked through entity CUD hooks, so this sub-module listens to the configuration system's events instead, giving administrators a detailed record of who changed which configuration object and when.

## Features

* **Config Creation Tracking**: Logs when a configuration object is created (first save) via `ConfigEvents::SAVE`
* **Config Update Tracking**: Logs when an existing configuration object is modified via `ConfigEvents::SAVE`
* **Config Deletion Tracking**: Logs when a configuration object is removed via `ConfigEvents::DELETE`
* **Config Name Tracking**: Records the configuration object name (e.g. `system.site`, `taxonomy.vocabulary.tags`) for categorization and searching
* **Per-request De-duplication**: Skips logging the same configuration object with identical data twice within a single request
* **Detailed Event Descriptions**: Provides human-readable descriptions of every configuration operation
* **Integration with Admin Audit Trail**: Seamlessly integrates with the Admin Audit Trail module for centralized audit logging

## Requirements

* Drupal
* Admin Audit Trail module (`admin_audit_trail`)

## Installation

1. Download or clone this module into your Drupal `modules` directory

2. Enable the module via the Drupal admin interface or using Drush:

```bash
drush en admin_audit_trail_config
```

3. Ensure the Admin Audit Trail module is enabled

4. Clear the Drupal cache

## Configuration

This module requires no additional configuration. Once enabled, it automatically begins logging configuration events through the Admin Audit Trail system.

As with the rest of Admin Audit Trail, only changes triggered by a real web request (for example, saving an administration form) are recorded; configuration written from the command line (Drush, cron) is intentionally not logged.

## Logged Events

The module logs configuration changes through the configuration system's events:

* **insert**: Triggered the first time a configuration object is saved (it did not previously exist) via `ConfigEvents::SAVE`
  * Logs the configuration object name
  * Example: "Config: taxonomy.vocabulary.tags"

* **update**: Triggered when an existing configuration object is saved via `ConfigEvents::SAVE`
  * Logs the configuration object name
  * Example: "Config: system.site"

* **delete**: Triggered when a configuration object is removed via `ConfigEvents::DELETE`
  * Logs the configuration object name
  * Provides a permanent record of deleted configuration for compliance

The insert/update distinction is determined from the configuration's original data: an object with no original data did not exist before the save and is therefore recorded as an `insert`.

## Log Entry Details

Each audit trail entry includes:

* **Type**: Always "config"
* **Operation**: The specific operation performed (insert, update, delete)
* **Description**: Format "Config: %name" (e.g., "Config: system.site")
* **Reference (char)**: The configuration object name for easy searching and filtering

## Usage

All configuration events are automatically logged. To view the audit trail:

1. Navigate to Administration > Reports > Audit Trail (admin/reports/audit-trail)
2. Filter by the "Configuration" log type to view only configuration-related events
3. View detailed information about each configuration operation

## Use Cases

* **Change Management**: Track every change to site settings, content types, fields, views and other configuration
* **Accountability**: Monitor which administrators changed specific configuration objects
* **Incident Investigation**: Review configuration change history when diagnosing a regression or outage
* **Compliance Requirements**: Maintain detailed records of configuration activity for regulatory compliance (HIPAA, GDPR, SOC 2, etc.)
* **Data Governance**: Keep historical records of the configuration lifecycle for archival and audit purposes
