<?php

namespace Drupal\admin_audit_trail_workflows\Hook;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_workflows.
 */
class AdminAuditTrailWorkflowsHooks {
  use StringTranslationTrait;

  /**
   * Old moderation states captured at presave, keyed per entity translation.
   *
   * The update hooks run after the save has updated the loaded revision id,
   * so the moderation information service can no longer resolve the previous
   * state there (issue #3271261: it would return the new state, or the
   * default revision's state instead of the latest revision's). The state is
   * therefore captured in hook_entity_presave() and consumed here.
   *
   * @var string[]
   */
  protected array $oldStates = [];

  public function __construct(
    protected ModerationInformationInterface $moderationInformation,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Workflows event log handler.
    $handlers = [];
    $handlers['workflows'] = [
      'title' => $this->t('Workflows'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity) {
    if (!$this->applies($entity) || $entity->isNew()) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $this->oldStates[$this->stateKey($entity)] = $this->loadStoredState($entity)
      ?? $this->moderationInformation->getOriginalState($entity)->id();
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity) {
    if (!$this->applies($entity)) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $new_state = $entity->get('moderation_state')->getString();
    $log = [
      'type' => 'workflows',
      'operation' => 'insert',
      'description' => $this->t('%type: %title - New @entity_type created with workflow state %new_state', [
        '%type' => $entity->bundle(),
        '%title' => $entity->label(),
        // The entity type id keeps the historic node wording byte-identical
        // ("New node created with workflow state ...").
        '@entity_type' => $entity->getEntityTypeId(),
        '%new_state' => $new_state,
      ]),
      'ref_numeric' => is_numeric($entity->id()) ? $entity->id() : NULL,
      'ref_char' => AdminAuditTrailLogger::safeTruncate((string) $entity->label()),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    if (!$this->applies($entity)) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $new_state = $entity->get('moderation_state')->getString();
    $key = $this->stateKey($entity);
    if (isset($this->oldStates[$key])) {
      $old_state = $this->oldStates[$key];
      unset($this->oldStates[$key]);
    }
    else {
      // Fallback for saves that skipped presave capture: the default
      // revision's state. Less precise for forward (non-default) drafts.
      $original = method_exists($entity, 'getOriginal') ? $entity->getOriginal() : ($entity->original ?? NULL);
      if (!$original instanceof ContentEntityInterface) {
        return;
      }
      $old_state = $original->get('moderation_state')->getString();
    }
    if ($old_state != $new_state) {
      $log = [
        'type' => 'workflows',
        'operation' => 'update',
        'description' => $this->t('%type: %title - Workflow state changed from %old_state to %new_state', [
          '%type' => $entity->bundle(),
          '%title' => $entity->label(),
          '%old_state' => $old_state,
          '%new_state' => $new_state,
        ]),
        'ref_numeric' => is_numeric($entity->id()) ? $entity->id() : NULL,
        'ref_char' => AdminAuditTrailLogger::safeTruncate((string) $entity->label()),
      ];
      admin_audit_trail_insert($log);
    }
  }

  /**
   * Whether the workflows handler tracks this entity.
   *
   * Any moderated content entity qualifies (issue #3271228: media, custom
   * blocks and other moderated entity types, not only nodes). The internal
   * content_moderation_state entity that content_moderation saves alongside
   * the moderated entity is excluded.
   */
  protected function applies(EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface
      && $entity->getEntityTypeId() !== 'content_moderation_state'
      && $this->moderationInformation->isModeratedEntity($entity);
  }

  /**
   * Loads the stored moderation state of the entity's loaded revision.
   *
   * Reads the content_moderation_state storage directly instead of
   * ModerationInformation::getOriginalState(): that helper re-loads the
   * entity's loaded revision, and the entity memory cache can hand back the
   * very object currently being saved - already carrying the NEW state -
   * which would make every transition from a pending revision look like a
   * no-op.
   *
   * @return string|null
   *   The stored state id, or NULL when none exists yet (first-time
   *   moderation).
   */
  protected function loadStoredState(ContentEntityInterface $entity): ?string {
    $storage = $this->entityTypeManager->getStorage('content_moderation_state');
    $ids = $storage->getQuery()
      ->condition('content_entity_type_id', $entity->getEntityTypeId())
      ->condition('content_entity_id', $entity->id())
      ->condition('content_entity_revision_id', $entity->getLoadedRevisionId())
      ->allRevisions()
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $state */
    $state = $storage->loadRevision((int) array_key_first($ids));
    $langcode = $entity->language()->getId();
    if ($state->hasTranslation($langcode)) {
      $state = $state->getTranslation($langcode);
    }
    $value = $state->get('moderation_state')->getString();
    return $value === '' ? NULL : $value;
  }

  /**
   * Builds the captured-state key for an entity translation.
   */
  protected function stateKey(ContentEntityInterface $entity): string {
    return $entity->getEntityTypeId() . ':' . $entity->uuid() . ':' . $entity->language()->getId();
  }

}
