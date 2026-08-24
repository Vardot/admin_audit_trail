<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_workflows\Kernel;

use Drupal\admin_audit_trail\AdminAuditTrailLogger;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that workflow transitions log the latest revision's old state.
 *
 * Coverage for issue #3271261: after content is published, a forward (non-
 * default) draft revision must log its own state as the "from" state of the
 * next transition - not the published default revision's state, and not the
 * new state (which is what a post-save lookup would resolve, silencing the
 * log entirely).
 *
 * The audit logger skips inserts under PHP_SAPI 'cli', so the tests replace
 * the logger service with a spy that captures the log records the hooks
 * produce instead of writing rows.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class WorkflowsOldStateTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
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
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Editorial workflow (draft/published/archived) on articles only, extended
    // with a "Ready for review" state reachable from draft.
    $workflow = $this->createEditorialWorkflow();
    /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $type_plugin */
    $type_plugin = $workflow->getTypePlugin();
    $type_plugin->addState('ready_for_review', 'Ready for review');
    $type_plugin->addTransition('submit_for_review', 'Submit for review', ['draft'], 'ready_for_review');
    $type_plugin->addEntityTypeAndBundle('node', 'article');
    $workflow->save();

    // Replace the logger with a spy: insert() captures the record instead of
    // writing to the database (which the real logger refuses under CLI).
    $this->loggerSpy = new AuditTrailLoggerSpy();
    $this->container->set(AdminAuditTrailLogger::class, $this->loggerSpy);
  }

  /**
   * Returns the captured workflows log records.
   *
   * @return array[]
   *   The captured records with type 'workflows'.
   */
  protected function workflowsLogs(): array {
    return array_values(array_filter(
      $this->loggerSpy->captured,
      static fn(array $log): bool => $log['type'] === 'workflows',
    ));
  }

  /**
   * Reloads the latest revision, the way an edit form would.
   */
  protected function reloadLatestRevision(NodeInterface $node): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $revision_id = $storage->getLatestRevisionId($node->id());
    $revision = $storage->loadRevision($revision_id);
    $this->assertInstanceOf(NodeInterface::class, $revision);
    return $revision;
  }

  /**
   * Creating a moderated node logs an insert with the initial state.
   */
  public function testInsertLogsInitialState(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Audit article',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $logs = $this->workflowsLogs();
    $this->assertCount(1, $logs);
    $this->assertSame('insert', $logs[0]['operation']);
    $this->assertStringContainsString('New node created with workflow state draft', strip_tags((string) $logs[0]['description']));
  }

  /**
   * A forward draft's next transition logs the draft as the old state.
   *
   * Publish, then create a forward draft (the default revision stays
   * published), then submit the draft for review: the "from" state must be
   * the latest revision's draft, not the default revision's published (the
   * original #3271261 bug) and not the new state (which a post-save
   * getOriginalState() lookup returns, suppressing the log entirely).
   */
  public function testForwardDraftTransitionLogsLatestOldState(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Audit article',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $this->reloadLatestRevision($node);
    $node->set('moderation_state', 'published');
    $node->save();

    $node = $this->reloadLatestRevision($node);
    $node->set('moderation_state', 'draft');
    $node->save();

    $node = $this->reloadLatestRevision($node);
    $node->set('moderation_state', 'ready_for_review');
    $node->save();

    $logs = $this->workflowsLogs();
    $descriptions = array_map(static fn(array $log): string => strip_tags((string) $log['description']), $logs);
    $this->assertCount(4, $logs, implode(' | ', $descriptions));
    $this->assertStringContainsString('from draft to published', strip_tags((string) $logs[1]['description']));
    $this->assertStringContainsString('from published to draft', strip_tags((string) $logs[2]['description']));
    $this->assertStringContainsString('from draft to ready_for_review', strip_tags((string) $logs[3]['description']));
  }

  /**
   * Resaving without a state change logs no update.
   */
  public function testNoLogWhenStateUnchanged(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Audit article',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $this->reloadLatestRevision($node);
    $node->set('title', 'Audit article (renamed)');
    $node->save();

    $logs = $this->workflowsLogs();
    $this->assertCount(1, $logs);
    $this->assertSame('insert', $logs[0]['operation']);
  }

  /**
   * Saving an unmoderated bundle logs nothing and does not fail.
   */
  public function testUnmoderatedBundleIgnored(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Plain page',
    ]);
    $node->save();

    $node->set('title', 'Plain page (renamed)');
    $node->save();

    $this->assertSame([], $this->workflowsLogs());
  }

}
