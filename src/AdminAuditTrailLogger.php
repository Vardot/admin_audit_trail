<?php

declare(strict_types=1);

namespace Drupal\admin_audit_trail;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Records audit trail events and resolves the registered event handlers.
 *
 * Holds the logic that used to live in the procedural helpers in
 * admin_audit_trail.module. Those functions are kept as thin backwards
 * compatible wrappers that delegate here.
 */
class AdminAuditTrailLogger {

  /**
   * Statically cached event handlers for the current request.
   *
   * @var array|null
   */
  protected ?array $handlers = NULL;

  /**
   * Constructs an AdminAuditTrailLogger object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected TimeInterface $time,
    protected AccountProxyInterface $currentUser,
    protected RequestStack $requestStack,
    protected Connection $database,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Inserts the log record in the admin audit trail. Sets the lid.
   *
   * @param array $log
   *   The log record to be saved. This record contains the following fields:
   *   - {string} type
   *     The event type. This is usually the object type that is described by
   *     this event. Example: 'node' or 'user'. Required.
   *   - {string} operation
   *     The operation being performed. Example: 'insert'. Required.
   *   - {string} description
   *     A textual description of the event. Required.
   *   - {string} ref_numeric
   *     Reference to numeric id. Optional.
   *   - {string} ref_char
   *     Reference to alphabetical id. Optional.
   */
  public function insert(array &$log): void {
    if ($this->isCliRequest()) {
      if (empty($this->configFactory->get('admin_audit_trail.settings')->get('log_cli'))) {
        // Ignore CLI requests unless CLI logging is enabled (issue #3263615).
        return;
      }
      // Flag the record as CLI-originated: the report's IP column reads
      // "CLI" and the path carries the command line.
      $log['ip'] = 'CLI';
      if (empty($log['path'])) {
        $log['path'] = 'cli: ' . implode(' ', $_SERVER['argv'] ?? []);
      }
    }

    if (empty($log['created'])) {
      $log['created'] = $this->time->getRequestTime();
    }

    if (empty($log['uid'])) {
      $log['uid'] = $this->currentUser->id();
    }

    $ip = $this->requestStack->getCurrentRequest()?->getClientIp();
    if (empty($log['ip']) && !empty($ip)) {
      $log['ip'] = $ip;
    }

    if (empty($log['path'])) {
      $log['path'] = Url::fromRoute('<current>')->getInternalPath();
    }

    if (empty($log['ref_numeric'])) {
      $log['ref_numeric'] = NULL;
    }

    // Allow the altering of the log array.
    $this->moduleHandler->invokeAll('admin_audit_trail_log_alter', ['log' => &$log]);

    // An implementation may set 'skip_db' to prevent the database write -
    // e.g. the Logger submodule's PSR-3-only mode, which has already
    // forwarded the record during the alter above.
    if (!empty($log['skip_db'])) {
      return;
    }

    $fields = [
      'type' => $log['type'],
      'operation' => $log['operation'],
      'description' => $log['description'],
      'created' => $log['created'],
      'uid' => $log['uid'],
      'ip' => $log['ip'] ?? '',
      // Trim the path to the column limit (varchar 255). Long values - e.g. S3
      // file paths added by s3fs_cors - would otherwise throw "Data too long
      // for column 'path'". self::safeTruncate() cuts by characters so
      // multibyte paths are never split mid-sequence.
      'path' => self::safeTruncate($log['path'], 255),
      'ref_char' => $log['ref_char'],
      'ref_numeric' => $log['ref_numeric'],
    ];

    if (isset($log['lid'])) {
      $this->database->update('admin_audit_trail')
        ->fields($fields)
        ->condition('lid', $log['lid'])
        ->execute();
    }
    else {
      $this->database->insert('admin_audit_trail')
        ->fields($fields)
        ->execute();
    }
    Cache::invalidateTags(['config:views.view.admin_audit_trail']);
  }

  /**
   * Whether the current request runs under the CLI.
   *
   * Separated out so tests can exercise insert() (PHPUnit itself runs under
   * the CLI SAPI).
   */
  protected function isCliRequest(): bool {
    return PHP_SAPI === 'cli';
  }

  /**
   * Returns all existing event handlers.
   *
   * @return array
   *   An array with the event log handlers.
   */
  public function getEventHandlers(): array {
    if ($this->handlers === NULL) {
      $this->handlers = $this->moduleHandler->invokeAll('admin_audit_trail_handlers');
      $this->moduleHandler->alter('admin_audit_trail_handlers', $this->handlers);
    }
    return $this->handlers;
  }

  /**
   * Returns all registered event types for Views filters.
   *
   * @return array
   *   An associative array of event types keyed by machine name with labels.
   */
  public function getEventTypes(): array {
    $types = [];
    foreach ($this->getEventHandlers() as $type => $handler) {
      $types[$type] = !empty($handler['title']) ? $handler['title'] : ucfirst($type);
    }
    asort($types);
    return $types;
  }

  /**
   * Form submission callback.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function formSubmit(array &$form, FormStateInterface $form_state): void {
    if ($form_state->has('admin_audit_trail_logged')
      && $form_state->get('admin_audit_trail_logged') == TRUE) {
      // Some forms are submitted twice, for instance the node_form.
      // We will only call the submit callback once.
      return;
    }

    $form_state->set('admin_audit_trail_logged', TRUE);

    // Get form id.
    $form_id = $form['#form_id'];

    // Dispatch the submission to the correct event handler.
    foreach ($this->getEventHandlers() as $type => $handler) {
      $dispatch = FALSE;
      if (!empty($handler['form_ids']) && in_array($form_id, $handler['form_ids'])) {
        $dispatch = TRUE;
      }
      elseif (!empty($handler['form_ids_regexp'])) {
        foreach ($handler['form_ids_regexp'] as $regexp) {
          if (preg_match($regexp, $form_id)) {
            $dispatch = TRUE;
            break;
          }
        }
      }

      if ($dispatch) {
        // Dispatch!
        $function = $handler['form_submit_callback'];
        if (is_callable($function)) {
          $log = $function($form, $form_state, $form_id);
          if (!empty($log)) {
            // Log the event.
            $log['type'] = $type;
            $this->insert($log);
          }
        }
      }
    }
  }

  /**
   * Adds a submit handler to all submit hooks in the form tree.
   *
   * @param array $element
   *   A form element or the form itself.
   * @param string|array $callback
   *   The callback to be added.
   */
  public function addSubmitHandler(array &$element, string|array $callback): void {
    if (array_key_exists("#submit", $element)) {
      if ((!empty($element['#type']) && $element['#type'] == 'form') || count($element["#submit"])) {
        $element["#submit"][] = $callback;
      }
    }
    $keys = Element::children($element);
    foreach ($keys as $key) {
      if (is_array($element[$key])) {
        $this->addSubmitHandler($element[$key], $callback);
      }
    }
  }

  /**
   * Truncates a string to a safe DB length.
   *
   * Counts and cuts by characters (not bytes) so multibyte UTF-8 text - e.g.
   * Arabic node titles or usernames - is never split mid-sequence, which would
   * otherwise corrupt the stored value and the rendered report. The reference
   * columns are varchar(255), i.e. 255 characters under utf8mb4.
   *
   * Kept static so the value-only logic stays usable without the container
   * (e.g. from unit tests).
   *
   * @param mixed $value
   *   The value to truncate.
   * @param int $max_length
   *   The maximum number of characters to keep.
   *
   * @return string
   *   The truncated string, or an empty string when $value is not a string.
   */
  public static function safeTruncate(mixed $value, int $max_length = 255): string {
    if (!is_string($value)) {
      return '';
    }
    if (mb_strlen($value) <= $max_length) {
      return $value;
    }
    // Unicode::truncate() trims to whole characters and appends the ellipsis
    // without ever exceeding $max_length characters.
    return Unicode::truncate($value, $max_length, FALSE, TRUE);
  }

}
