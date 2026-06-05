<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_menu\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that auditing a menu link translation deletion never breaks the site.
 *
 * Regression coverage for issue #3343561: deleting the translation of a menu
 * link used to fail with "The entity object refers to a removed translation
 * (…) and cannot be manipulated." The menu CUD hooks read translated fields
 * (title, link URI), so they must always receive a translation that is safe to
 * read after a translation has been removed.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
class MenuLinkContentTranslationDeleteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'link',
    'menu_link_content',
    'language',
    'content_translation',
    'admin_audit_trail',
    'admin_audit_trail_menu',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');
    $this->installSchema('admin_audit_trail', ['admin_audit_trail']);

    // Add a second language and make custom menu links translatable.
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('menu_link_content', 'menu_link_content', TRUE);
  }

  /**
   * Creates a custom menu link with an English and a Spanish translation.
   */
  private function createTranslatedLink(): MenuLinkContent {
    $link = MenuLinkContent::create([
      'title' => 'Audit Translated EN',
      'link' => ['uri' => 'internal:/node'],
      'menu_name' => 'main',
      'langcode' => 'en',
    ]);
    $link->save();
    $link->addTranslation('es', ['title' => 'Audit Translated ES'])->save();
    return $link;
  }

  /**
   * Removing a translation and saving must not throw from the update hook.
   *
   * Mirrors what content_translation's delete form does: it resolves the entity
   * to its default translation, removes the requested translation and saves.
   * The save fires admin_audit_trail_menu_menu_link_content_update(), which
   * reads the title and link URI while building the audit log entry.
   */
  public function testDeletingTranslationDoesNotBreakOnSave(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $id = $this->createTranslatedLink()->id();

    // Reload the default (English) translation, drop Spanish and save.
    $link = $storage->loadUnchanged($id);
    $this->assertTrue($link->hasTranslation('es'));
    $link->removeTranslation('es');
    $link->save();

    $reloaded = $storage->loadUnchanged($id);
    $this->assertFalse($reloaded->hasTranslation('es'), 'The Spanish translation was removed.');
    $this->assertTrue($reloaded->hasTranslation('en'), 'The default translation remains.');
    $this->assertSame('Audit Translated EN', $reloaded->getTitle());
  }

  /**
   * Deleting a translated menu link must not throw from the delete hook.
   *
   * The delete hook, admin_audit_trail_menu_menu_link_content_delete(), reads
   * translated fields too, so a fully translated link has to delete cleanly.
   */
  public function testDeletingTranslatedLinkDoesNotBreakOnDelete(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $id = $this->createTranslatedLink()->id();

    $storage->loadUnchanged($id)->delete();

    $this->assertNull($storage->loadUnchanged($id), 'The translated menu link was deleted.');
  }

  /**
   * The safe-link helper falls back to the untranslated default translation.
   *
   * When an entity object refers to a removed active translation, reading its
   * fields throws; _admin_audit_trail_menu_safe_link() must return a valid
   * translation so the hooks never fatal.
   */
  public function testSafeLinkFallsBackForRemovedTranslation(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $id = $this->createTranslatedLink()->id();

    // A valid object is returned unchanged.
    $valid = $storage->loadUnchanged($id);
    $this->assertSame($valid, _admin_audit_trail_menu_safe_link($valid));

    // Build an object that refers to the just-removed Spanish translation.
    $spanish = $storage->loadUnchanged($id)->getTranslation('es');
    $spanish->removeTranslation('es');

    $safe = _admin_audit_trail_menu_safe_link($spanish);
    // The fallback is the valid default (English) translation.
    $this->assertSame('en', $safe->language()->getId());
    $this->assertSame('Audit Translated EN', $safe->getTitle());
  }

  /**
   * Removed translations are detected for the distinct translation-delete log.
   */
  public function testRemovedTranslationsAreDetected(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $id = $this->createTranslatedLink()->id();

    // Previous version (original) still has both translations.
    $original = $storage->loadUnchanged($id);

    // Current version drops Spanish.
    $current = $storage->loadUnchanged($id);
    $current->removeTranslation('es');
    $this->setOriginal($current, $original);

    $this->assertSame(['es'], _admin_audit_trail_menu_removed_translations($current));

    // A normal edit (no translation removed) reports nothing.
    $unchanged = $storage->loadUnchanged($id);
    $this->setOriginal($unchanged, $storage->loadUnchanged($id));
    $this->assertSame([], _admin_audit_trail_menu_removed_translations($unchanged));
  }

  /**
   * Sets the previous version of an entity (Drupal 11.2+ setOriginal()).
   */
  private function setOriginal(EntityInterface $entity, EntityInterface $original): void {
    if (!method_exists($entity, 'setOriginal')) {
      $this->markTestSkipped('EntityInterface::setOriginal() requires Drupal 11.2+.');
    }
    $entity->setOriginal($original);
  }

}
