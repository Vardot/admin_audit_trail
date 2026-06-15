<?php

namespace Drupal\admin_audit_trail_taxonomy\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_taxonomy.
 */
class AdminAuditTrailTaxonomyHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // Taxonomy event log handler.
    $handlers = [];
    $handlers['taxonomy'] = [
      'title' => $this->t('Taxonomy'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_taxonomy_vocabulary_insert().
   */
  #[Hook('taxonomy_vocabulary_insert')]
  public function taxonomyVocabularyInsert($vocabulary) {
    $log = [
      'type' => 'taxonomy',
      'operation' => 'vocabulary insert',
      'description' => $this->t('%title (%name)', [
        '%title' => $vocabulary->get('name'),
        '%name' => $vocabulary->get('vid'),
      ]),
      'ref_char' => $vocabulary->get('vid'),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_taxonomy_vocabulary_update().
   */
  #[Hook('taxonomy_vocabulary_update')]
  public function taxonomyVocabularyUpdate($vocabulary) {
    $log = [
      'type' => 'taxonomy',
      'operation' => 'vocabulary update',
      'description' => $this->t('%title (%name)', [
        '%title' => $vocabulary->label(),
        '%name' => $vocabulary->getOriginalId(),
      ]),
      'ref_char' => $vocabulary->getOriginalId(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_taxonomy_vocabulary_delete().
   */
  #[Hook('taxonomy_vocabulary_delete')]
  public function taxonomyVocabularyDelete($vocabulary) {
    $log = [
      'type' => 'taxonomy',
      'operation' => 'vocabulary delete',
      'description' => $this->t('%title (%name)', [
        '%title' => $vocabulary->label(),
        '%name' => $vocabulary->getOriginalId(),
      ]),
      'ref_char' => $vocabulary->getOriginalId(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_taxonomy_term_insert().
   */
  #[Hook('taxonomy_term_insert')]
  public function taxonomyTermInsert($term) {
    $log = [
      'type' => 'taxonomy',
      'operation' => 'term insert',
      'description' => $this->t('%name (%tid)', [
        '%name' => $term->getName(),
        '%tid' => $term->id(),
      ]),
      'ref_numeric' => $term->id(),
      'ref_char' => $term->get('vid')->target_id,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_taxonomy_term_update().
   */
  #[Hook('taxonomy_term_update')]
  public function taxonomyTermUpdate($term) {
    $log = [
      'type' => 'taxonomy',
      'operation' => 'term update',
      'description' => $this->t('%name (%tid)', [
        '%name' => $term->getName(),
        '%tid' => $term->id(),
      ]),
      'ref_numeric' => $term->id(),
      'ref_char' => $term->get('vid')->target_id,
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_taxonomy_term_delete().
   */
  #[Hook('taxonomy_term_delete')]
  public function taxonomyTermDelete($term) {
    $log = [
      'type' => 'taxonomy',
      'operation' => 'term delete',
      'description' => $this->t('%name (%tid)', [
        '%name' => $term->getName(),
        '%tid' => $term->id(),
      ]),
      'ref_numeric' => $term->id(),
      'ref_char' => $term->get('vid')->target_id,
    ];
    admin_audit_trail_insert($log);
  }

}
