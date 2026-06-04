<?php

/**
 * @file
 * Idempotent test fixtures for the Admin Audit Trail webship-js suite.
 *
 * Creates the structures the per-submodule scenarios need so that a single
 * UI action can trigger each handler's event:
 *   - an "image" media type        (admin_audit_trail_media)
 *   - an Editorial content-moderation workflow on node.article
 *                                   (admin_audit_trail_workflows)
 *   - a "team" group type           (admin_audit_trail_group)
 *   - a "test_section" paragraph type + a field_sections paragraph field on
 *     node.article                  (admin_audit_trail_paragraphs)
 *
 * Run under Drush (CLI), which the audit logger ignores, so provisioning
 * produces no audit rows of its own.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$etm = \Drupal::entityTypeManager();

// 1. Media type: image.
if (!$etm->getStorage('media_type')->load('image')) {
  $etm->getStorage('media_type')->create([
    'id' => 'image',
    'label' => 'Image',
    'source' => 'image',
  ])->save();
  $type = $etm->getStorage('media_type')->load('image');
  // Wire the source field configuration.
  $source = $type->getSource();
  $field = $source->createSourceField($type);
  $field->getFieldStorageDefinition()->save();
  $field->save();
  $type->set('source_configuration', ['source_field' => $field->getName()])->save();
  print "media type image: created\n";
}
else {
  print "media type image: exists\n";
}

// 2. Editorial workflow on node.article (content_moderation).
if (!$etm->getStorage('workflow')->load('editorial')) {
  $etm->getStorage('workflow')->create([
    'id' => 'editorial',
    'label' => 'Editorial',
    'type' => 'content_moderation',
    'type_settings' => [
      'states' => [
        'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
        'published' => ['label' => 'Published', 'weight' => 1, 'published' => TRUE, 'default_revision' => TRUE],
      ],
      'transitions' => [
        'create_new_draft' => ['label' => 'Create New Draft', 'from' => ['draft', 'published'], 'to' => 'draft', 'weight' => 0],
        'publish' => ['label' => 'Publish', 'from' => ['draft', 'published'], 'to' => 'published', 'weight' => 1],
      ],
    ],
  ])->save();
  print "workflow editorial: created\n";
}
$workflow = $etm->getStorage('workflow')->load('editorial');
$config = $workflow->getTypePlugin();
if (!in_array('article', $config->getConfiguration()['entity_types']['node'] ?? [], TRUE)) {
  $config->addEntityTypeAndBundle('node', 'article');
  $workflow->save();
  print "workflow editorial: article enabled\n";
}
else {
  print "workflow editorial: article already enabled\n";
}

// 3. Group type: team.
if (!$etm->getStorage('group_type')->load('team')) {
  $etm->getStorage('group_type')->create([
    'id' => 'team',
    'label' => 'Team',
    'creator_membership' => TRUE,
    'creator_wizard' => FALSE,
    'new_revision' => FALSE,
  ])->save();
  print "group type team: created\n";
}
else {
  print "group type team: exists\n";
}

// 4. Paragraph type + paragraph reference field on node.article.
if (!$etm->getStorage('paragraphs_type')->load('test_section')) {
  $etm->getStorage('paragraphs_type')->create([
    'id' => 'test_section',
    'label' => 'Test section',
  ])->save();
  print "paragraph type test_section: created\n";
}
if (!FieldStorageConfig::loadByName('node', 'field_sections')) {
  FieldStorageConfig::create([
    'field_name' => 'field_sections',
    'entity_type' => 'node',
    'type' => 'entity_reference_revisions',
    'settings' => ['target_type' => 'paragraph'],
    'cardinality' => -1,
  ])->save();
  print "field storage field_sections: created\n";
}
if (!FieldConfig::loadByName('node', 'article', 'field_sections')) {
  FieldConfig::create([
    'field_name' => 'field_sections',
    'entity_type' => 'node',
    'bundle' => 'article',
    'label' => 'Sections',
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => ['target_bundles' => ['test_section' => 'test_section']],
    ],
  ])->save();
  // Form display: paragraphs widget.
  $form_display = \Drupal::service('entity_display.repository')
    ->getFormDisplay('node', 'article', 'default');
  $form_display->setComponent('field_sections', [
    'type' => 'paragraphs',
    'weight' => 50,
  ])->save();
  print "field_sections on article: created\n";
}
else {
  print "field_sections on article: exists\n";
}

print "provisioning complete\n";
