<?php

namespace Drupal\admin_audit_trail_menu\Hook;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Hook implementations for admin_audit_trail_menu.
 */
class AdminAuditTrailMenuHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    $handlers = [];
    $handlers['menu'] = [
      'title' => $this->t('Menu'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_menu_insert().
   */
  #[Hook('menu_insert')]
  public function menuInsert($menu) {
    $log = [
      'type' => 'menu',
      'operation' => 'insert',
      'description' => $this->t('%title (%name)', [
        '%title' => $menu->get('label'),
        '%name' => $menu->get('id'),
      ]),
      'ref_char' => $menu->get('id'),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_menu_update().
   */
  #[Hook('menu_update')]
  public function menuUpdate($menu) {
    $log = [
      'type' => 'menu',
      'operation' => 'update',
      'description' => $this->t('%title (%name)', [
        '%title' => $menu->get('label'),
        '%name' => $menu->get('id'),
      ]),
      'ref_char' => $menu->get('id'),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_menu_delete().
   */
  #[Hook('menu_delete')]
  public function menuDelete($menu) {
    $log = [
      'type' => 'menu',
      'operation' => 'delete',
      'description' => $this->t('%title (%name)', [
        '%title' => $menu->get('label'),
        '%name' => $menu->get('id'),
      ]),
      'ref_char' => $menu->get('id'),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for menu_link_content entities.
   */
  #[Hook('menu_link_content_insert')]
  public function menuLinkContentInsert(MenuLinkContentInterface $link) {
    $link = $this->safeLink($link);
    $log = [
      'type' => 'menu',
      'operation' => 'link insert',
      'description' => $this->t('%title (%id), %path', [
        '%title' => $link->getTitle(),
        '%id' => $link->id(),
        '%path' => $link->get('link')->uri,
      ]),
      'ref_numeric' => $link->id(),
      'ref_char' => $link->getMenuName(),
    ];

    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for menu_link_content entities.
   */
  #[Hook('menu_link_content_update')]
  public function menuLinkContentUpdate(MenuLinkContentInterface $link) {
    // Deleting a menu link translation removes the translation and saves the
    // entity, firing this update hook with one fewer translation. Record it as
    // a distinct "translation delete" event and read the fields from the
    // (valid) default translation, so the request never breaks with "The entity
    // object refers to a removed translation …" (issue #3343561).
    $removed = $this->removedTranslations($link);
    if ($removed) {
      $default = $link->getUntranslated();
      assert($default instanceof MenuLinkContentInterface);
      $log = [
        'type' => 'menu',
        'operation' => 'link translation delete',
        'description' => $this->t('%title (%id), %path - removed %languages translation', [
          '%title' => $default->getTitle(),
          '%id' => $default->id(),
          '%path' => $default->get('link')->uri,
          '%languages' => implode(', ', $removed),
        ]),
        'ref_numeric' => $default->id(),
        'ref_char' => $default->getMenuName(),
      ];
      admin_audit_trail_insert($log);
      return;
    }

    $link = $this->safeLink($link);
    $log = [
      'type' => 'menu',
      'operation' => 'link update',
      'description' => $this->t('%title (%id), %path', [
        '%title' => $link->getTitle(),
        '%id' => $link->id(),
        '%path' => $link->get('link')->uri,
      ]),
      'ref_numeric' => $link->id(),
      'ref_char' => $link->getMenuName(),
    ];

    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for menu_link_content entities.
   */
  #[Hook('menu_link_content_delete')]
  public function menuLinkContentDelete(MenuLinkContentInterface $link) {
    $link = $this->safeLink($link);
    $log = [
      'type' => 'menu',
      'operation' => 'link delete',
      'description' => $this->t('%title (%id), %path', [
        '%title' => $link->getTitle(),
        '%id' => $link->id(),
        '%path' => $link->get('link')->uri,
      ]),
      'ref_numeric' => $link->id(),
      'ref_char' => $link->getMenuName(),
    ];

    admin_audit_trail_insert($log);
  }

  /**
   * Returns a menu link content translation that is safe to read fields from.
   *
   * When a menu link translation is deleted, Drupal can hand the saved entity
   * to the CUD hooks while it still reports the just-removed language as the
   * active translation. Reading any field on such an object throws "The entity
   * object refers to a removed translation (…) and cannot be manipulated." and
   * breaks the request (issue #3343561). The untranslated (default) translation
   * always stays valid, so fall back to it whenever the active translation is
   * gone.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link content entity passed to a CUD hook.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   A translation whose fields are safe to read.
   */
  public function safeLink(MenuLinkContentInterface $link): MenuLinkContentInterface {
    if ($link->hasTranslation($link->language()->getId())) {
      return $link;
    }
    $untranslated = $link->getUntranslated();
    assert($untranslated instanceof MenuLinkContentInterface);
    return $untranslated;
  }

  /**
   * Returns the languages of translations removed in the current save.
   *
   * Compares the saved menu link against its previous version: any language
   * that existed before but not now was removed (a translation deletion).
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link content entity being saved.
   *
   * @return string[]
   *   The removed translation languages (empty for a normal update).
   */
  public function removedTranslations(MenuLinkContentInterface $link): array {
    $original = method_exists($link, 'getOriginal') ? $link->getOriginal() : ($link->original ?? NULL);
    if (!$original instanceof TranslatableInterface) {
      return [];
    }
    return array_values(array_diff(
      array_keys($original->getTranslationLanguages()),
      array_keys($link->getTranslationLanguages())
    ));
  }

}
