# Admin Audit Trail Logger

Admin Audit Trail Logger is a Drupal module that extends the Admin Audit Trail module by forwarding all audit events to the Drupal PSR-3 logger.

This makes audit trail entries available in any logging backend (syslog, Monolog + ELK, Graylog, etc.) without coupling to a specific backend implementation.

## Use Case

The base `admin_audit_trail` module stores events exclusively in the database. While this is useful for the admin UI, operational teams often need audit data in their centralized logging infrastructure for:

* **SIEM integration** (security event correlation)
* **Log aggregation** (ELK, Graylog, Datadog)
* **Compliance** (immutable external log storage)
* **Alerting** (real-time notifications on sensitive operations)

This submodule bridges the gap by emitting each audit event as a structured PSR-3 log message.

## Compatible Backends

* **Syslog** (Drupal core module) — writes to `/var/log/syslog` or journald
* **Monolog** (contrib) — routes to any Monolog handler (Elasticsearch, Graylog, Slack, files, etc.)
* **Any custom logger** implementing `Psr\Log\LoggerInterface`

## Requirements

* Drupal 10.1+, 11 or 12
* Admin Audit Trail module (`admin_audit_trail`)

## Installation

1. Enable the module via the Drupal admin interface or using Drush:

```bash
drush en admin_audit_trail_logger
```

2. Ensure at least one logger backend is active (e.g., enable core `syslog` module)

3. Clear the Drupal cache

## Configuration

Navigate to **Administration > Configuration > Development > Audit Trail > Logger** (`/admin/config/development/audit-trail/logger`).

| Setting | Default | Description |
|---------|---------|-------------|
| **Logging mode** | Hybrid | `hybrid` (database + PSR-3) or `psr3_only` (skip database) |
| **Logger channel** | `audit_trail` | PSR-3 channel name used to route/filter messages in your backend |

In `psr3_only` mode the report at `/admin/reports/audit-trail` receives no new
entries; the events exist only in your logging backend.

All events are logged at **notice** severity by default. An invalid level
name in the severity map degrades to `notice` instead of causing an error.

### Overriding severity via settings.php

To assign custom PSR-3 severity levels per operation, add a `severity_map` in `settings.php`:

```php
$config['admin_audit_trail_logger.settings']['severity_map'] = [
  'default' => 'notice',
  'delete'  => 'warning',
  'fail'    => 'alert',
  'login'   => 'info',
];
```

The map uses **exact match** on `$log['operation']`. Compound operations (e.g. `link delete`, `term insert`, `vocabulary update`) require the full string as key:

```php
$config['admin_audit_trail_logger.settings']['severity_map'] = [
  'default'     => 'notice',
  'link delete' => 'warning',
  'term delete' => 'warning',
];
```

You can also override the channel:

```php
$config['admin_audit_trail_logger.settings']['channel'] = 'security';
```

## Log Output Example

With syslog backend enabled, creating a node produces:

```
Jun  9 14:32:01 web1 drupal[12345]: audit_trail|notice|[node] insert: article: My Article (uid=1, ip=192.168.1.10, path=node/add/article)
```

## How It Works

The module implements `hook_admin_audit_trail_log_alter()`, which is invoked by the base module just before writing to the database. The hook receives the full log array and emits a structured PSR-3 message with placeholders:

```
[{type}] {operation}: {description} (uid={uid}, ip={ip}, path={path})
```

The context array provides all placeholder values, allowing logger backends to index them as structured fields.

The hook is implemented with the object-oriented hook pattern
(`src/Hook/AdminAuditTrailLoggerHooks.php` with the `#[Hook]` attribute and
injected services), plus a `#[LegacyHook]` stub in the `.module` file for
older cores. In `psr3_only` mode it sets `$log['skip_db']`, which the base
module honors after the alter and before the database write.

## Limitations

* Like the base module, events are only captured during web requests: actions
  performed from the command line (Drush, CLI cron) never reach the audit
  trail, so they are not forwarded either.
* The severity map matches `$log['operation']` exactly; there is no wildcard
  matching.

## Testing

The submodule ships PHPUnit Unit tests (severity resolution, channel,
`skip_db`), Kernel tests exercising the real insert flow against a database
(hybrid stores and forwards, `psr3_only` skips the write), and browser-level
scenarios in the project's functional suite (settings form, channel
validation, dblog forwarding).
