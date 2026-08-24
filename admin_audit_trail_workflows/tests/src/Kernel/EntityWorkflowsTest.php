<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_workflows\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that workflow transitions are logged for non-node entities.
 *
 * Coverage for issue #3271228: the workflows handler must track every
 * moderated content entity type (media, custom blocks, ...), not only nodes.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class EntityWorkflowsTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'media',
    'media_test_source',
    'block_content',
    'workflows',
    'content_moderation',
    'admin_audit_trail',
    'admin_audit_trail_workflows',
  ];

  /**
   * The spy replacing the audit trail logger; captures log records.
   */
  protected AuditTrailLoggerSpy $loggerSpy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filter', 'media']);

    $media_type = $this->createMediaType('test', ['id' => 'test_media']);
    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();

    $workflow = $this->createEditorialWorkflow();
    /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
    $type_plugin = $workflow->getTypePlugin();
    $type_plugin->addEntityTypeAndBundle('media', (string) $media_type->id());
    $type_plugin->addEntityTypeAndBundle('block_content', 'basic');
    $workflow->save();

    $this->loggerSpy = new AuditTrailLoggerSpy();
    $this->container->set(AdminAuditTrailLogger::class, $this->loggerSpy);
  }

  /**
   * Returns the captured workflows log descriptions, tags stripped.
   *
   * @return string[]
   *   The plain-text descriptions of the captured workflows records.
   */
  protected function workflowsDescriptions(): array {
    $logs = array_filter(
      $this->loggerSpy->captured,
      static fn(array $log): bool => $log['type'] === 'workflows',
    );
    return array_values(array_map(
      static fn(array $log): string => strip_tags((string) $log['description']),
      $logs,
    ));
  }

  /**
   * A moderated media item's transitions are logged.
   */
  public function testMediaTransitionsLogged(): void {
    $media = Media::create([
      'bundle' => 'test_media',
      'name' => 'Audit media',
      'moderation_state' => 'draft',
    ]);
    $media->save();

    $media->set('moderation_state', 'published');
    $media->save();

    $descriptions = $this->workflowsDescriptions();
    $this->assertCount(2, $descriptions, implode(' | ', $descriptions));
    $this->assertStringContainsString('New media created with workflow state draft', $descriptions[0]);
    $this->assertStringContainsString('test_media: Audit media - Workflow state changed from draft to published', $descriptions[1]);
  }

  /**
   * A moderated custom block's transitions are logged.
   */
  public function testBlockContentTransitionsLogged(): void {
    $block = BlockContent::create([
      'type' => 'basic',
      'info' => 'Audit block',
      'moderation_state' => 'draft',
    ]);
    $block->save();

    $block->set('moderation_state', 'published');
    $block->save();

    $descriptions = $this->workflowsDescriptions();
    $this->assertCount(2, $descriptions, implode(' | ', $descriptions));
    $this->assertStringContainsString('New block_content created with workflow state draft', $descriptions[0]);
    $this->assertStringContainsString('basic: Audit block - Workflow state changed from draft to published', $descriptions[1]);
  }

  /**
   * Unmoderated entities are ignored without errors.
   */
  public function testUnmoderatedEntityIgnored(): void {
    $user = User::create(['name' => 'audit_user']);
    $user->save();
    $user->set('name', 'audit_user_renamed');
    $user->save();

    $this->assertSame([], $this->workflowsDescriptions());
  }

}
