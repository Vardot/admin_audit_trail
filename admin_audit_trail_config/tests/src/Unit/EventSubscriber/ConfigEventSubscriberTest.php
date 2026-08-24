<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_config\Unit\EventSubscriber;

use Drupal\admin_audit_trail_config\EventSubscriber\ConfigEventSubscriber;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the configuration event subscriber wiring.
 *
 * @group admin_audit_trail
 *
 * @coversDefaultClass \Drupal\admin_audit_trail_config\EventSubscriber\ConfigEventSubscriber
 */
#[Group('admin_audit_trail')]
class ConfigEventSubscriberTest extends UnitTestCase {

  /**
   * The subscriber must register for both config save and delete events.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $events = ConfigEventSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(ConfigEvents::SAVE, $events);
    $this->assertSame('onConfigSave', $events[ConfigEvents::SAVE]);

    $this->assertArrayHasKey(ConfigEvents::DELETE, $events);
    $this->assertSame('onConfigDelete', $events[ConfigEvents::DELETE]);
  }

  /**
   * The save and delete handler methods must be callable on the subscriber.
   *
   * @covers ::getSubscribedEvents
   */
  public function testSubscribedCallbacksExist(): void {
    $subscriber = new ConfigEventSubscriber();
    $this->assertTrue(is_callable([$subscriber, 'onConfigSave']));
    $this->assertTrue(is_callable([$subscriber, 'onConfigDelete']));
  }

}
