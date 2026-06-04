<?php

namespace Drupal\admin_audit_trail_config\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to config events for audit trail logging.
 */
class ConfigEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Tracks processed config saves during the current request.
   *
   * @var array<string, bool>
   */
  protected static array $processed = [];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onConfigSave',
      ConfigEvents::DELETE => 'onConfigDelete',
    ];
  }

  /**
   * Logs config insert and update.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration CRUD event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();

    // Prevent duplicate processing of the same config data in one request.
    $key = $config->getName() . ':' . md5(serialize($config->getRawData()));
    if (isset(self::$processed[$key])) {
      return;
    }
    self::$processed[$key] = TRUE;

    // Empty original data == the config did not exist before this save.
    // (Config::save() sets isNew = FALSE before dispatching SAVE, so
    // $config->isNew() is always FALSE here.)
    $operation = empty($config->getOriginal('')) ? 'insert' : 'update';
    $this->logEvent($config->getName(), $operation);
  }

  /**
   * Logs config delete.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration CRUD event.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $this->logEvent($event->getConfig()->getName(), 'delete');
  }

  /**
   * Writes a configuration event to the admin audit trail.
   *
   * @param string $name
   *   The configuration object name (for example "system.site").
   * @param string $operation
   *   The operation performed: "insert", "update" or "delete".
   */
  protected function logEvent(string $name, string $operation): void {
    $log = [
      'type' => 'config',
      'operation' => $operation,
      'description' => $this->t('Config: %name', ['%name' => $name]),
      'ref_char' => $name,
    ];
    admin_audit_trail_insert($log);
  }

}
