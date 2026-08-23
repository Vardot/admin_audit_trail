<?php

namespace Drupal\admin_audit_trail_workflows\Hook;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for admin_audit_trail_workflows.
 */
class AdminAuditTrailWorkflowsHooks {
  use StringTranslationTrait;

  /**
   * Old moderation states captured at presave, keyed by uuid:langcode.
   *
   * The hook_node_update() hook runs after the save has updated the loaded
   * revision id, so the moderation information service can no longer resolve
   * the previous state there (issue #3271261: it would return the new state,
   * or the default revision's state instead of the latest revision's). The
   * state is therefore captured in hook_node_presave() and consumed here.
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
   * Implements hook_node_presave().
   */
  #[Hook('node_presave')]
  public function nodePresave($node) {
    /** @var \Drupal\node\NodeInterface $node */
    if ($node->isNew() || !$this->moderationInformation->isModeratedEntity($node)) {
      return;
    }
    $this->oldStates[$this->stateKey($node)] = $this->loadStoredState($node)
      ?? $this->moderationInformation->getOriginalState($node)->id();
  }

  /**
   * Loads the stored moderation state of the node's loaded revision.
   *
   * Reads the content_moderation_state storage directly instead of
   * ModerationInformation::getOriginalState(): that helper re-loads the
   * node's loaded revision, and the entity memory cache can hand back the
   * very object currently being saved - already carrying the NEW state -
   * which would make every transition from a pending revision look like a
   * no-op.
   *
   * @return string|null
   *   The stored state id, or NULL when none exists yet (first-time
   *   moderation).
   */
  protected function loadStoredState(NodeInterface $node): ?string {
    $storage = $this->entityTypeManager->getStorage('content_moderation_state');
    $ids = $storage->getQuery()
      ->condition('content_entity_type_id', 'node')
      ->condition('content_entity_id', $node->id())
      ->condition('content_entity_revision_id', $node->getLoadedRevisionId())
      ->allRevisions()
      ->accessCheck(FALSE)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $state */
    $state = $storage->loadRevision((int) array_key_first($ids));
    $langcode = $node->language()->getId();
    if ($state->hasTranslation($langcode)) {
      $state = $state->getTranslation($langcode);
    }
    $value = $state->get('moderation_state')->getString();
    return $value === '' ? NULL : $value;
  }

  /**
   * Implements hook_node_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert($node) {
    /** @var \Drupal\node\NodeInterface $node */
    if (!$this->moderationInformation->isModeratedEntity($node)) {
      return;
    }
    $new_state = $node->get("moderation_state")->getString();
    $log = [
      'type' => 'workflows',
      'operation' => 'insert',
      'description' => $this->t('%type: %title - New node created with workflow state %new_state', [
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
        '%new_state' => $new_state,
      ]),
      'ref_numeric' => $node->id(),
      'ref_char' => $node->getTitle(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_node_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate($node) {
    /** @var \Drupal\node\NodeInterface $node */
    if (!$this->moderationInformation->isModeratedEntity($node)) {
      return;
    }
    $new_state = $node->get("moderation_state")->getString();
    $key = $this->stateKey($node);
    if (isset($this->oldStates[$key])) {
      $old_state = $this->oldStates[$key];
      unset($this->oldStates[$key]);
    }
    else {
      // Fallback for saves that skipped presave capture: the default
      // revision's state. Less precise for forward (non-default) drafts.
      $original = method_exists($node, 'getOriginal') ? $node->getOriginal() : ($node->original ?? NULL);
      if (!$original instanceof NodeInterface) {
        return;
      }
      $old_state = $original->get("moderation_state")->getString();
    }
    if ($old_state != $new_state) {
      $log = [
        'type' => 'workflows',
        'operation' => 'update',
        'description' => $this->t('%type: %title - Workflow state changed from %old_state to %new_state', [
          '%type' => $node->getType(),
          '%title' => $node->getTitle(),
          '%old_state' => $old_state,
          '%new_state' => $new_state,
        ]),
        'ref_numeric' => $node->id(),
        'ref_char' => $node->getTitle(),
      ];
      admin_audit_trail_insert($log);
    }
  }

  /**
   * Builds the captured-state key for a node translation.
   */
  protected function stateKey(NodeInterface $node): string {
    return $node->uuid() . ':' . $node->language()->getId();
  }

}
