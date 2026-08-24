<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_audit_trail_openid_connect\Kernel;

use Drupal\admin_audit_trail_openid_connect\Hook\AdminAuditTrailOpenidConnectHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Active Directory (Windows AAD) login detection and the log payload.
 *
 * Coverage for issue #3549423: only logins performed through a Windows Azure
 * AD OpenID Connect client should be recorded, and they should be recorded as
 * an "authentication / ad_login" event. The detection lives in
 * AdminAuditTrailOpenidConnectHooks::adLoginLog(), which this test drives
 * directly so no live identity provider is required.
 *
 * @group admin_audit_trail
 */
#[RunTestsInSeparateProcesses]
#[Group('admin_audit_trail')]
class AdLoginLogTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'key',
    'externalauth',
    'openid_connect',
    'openid_connect_windows_aad',
    'admin_audit_trail',
    'admin_audit_trail_auth',
    'admin_audit_trail_openid_connect',
  ];

  /**
   * A test account that "logs in".
   */
  protected UserInterface $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // openid_connect formats the display name via user.module's users_data.
    $this->installSchema('user', ['users_data']);
    $this->installSchema('admin_audit_trail', ['admin_audit_trail']);

    // A Windows AAD client and a plain generic client.
    OpenIDConnectClientEntity::create([
      'id' => 'aad_client',
      'label' => 'Azure AD',
      'plugin' => 'windows_aad',
      'settings' => [],
    ])->save();
    OpenIDConnectClientEntity::create([
      'id' => 'generic_client',
      'label' => 'Generic',
      'plugin' => 'generic',
      'settings' => [],
    ])->save();

    $this->account = User::create([
      'name' => 'ad-tester',
      'mail' => 'ad-tester@example.com',
    ]);
    $this->account->save();
  }

  /**
   * Returns the hook service under test.
   */
  protected function hooks(): AdminAuditTrailOpenidConnectHooks {
    return $this->container->get(AdminAuditTrailOpenidConnectHooks::class);
  }

  /**
   * A login through a Windows AAD client produces an "ad_login" log entry.
   */
  public function testWindowsAadLoginIsLogged(): void {
    $log = $this->hooks()->adLoginLog($this->account, ['plugin_id' => 'aad_client']);

    $this->assertIsArray($log);
    $this->assertSame('authentication', $log['type']);
    $this->assertSame('ad_login', $log['operation']);
    $this->assertSame($this->account->id(), $log['ref_numeric']);
    $this->assertSame($this->account->getDisplayName(), $log['ref_char']);
    $this->assertStringContainsString('Active Directory', (string) $log['description']);
  }

  /**
   * A login through a non-AAD client is not logged by this submodule.
   */
  public function testGenericClientLoginIsNotLogged(): void {
    $log = $this->hooks()->adLoginLog($this->account, ['plugin_id' => 'generic_client']);
    $this->assertNull($log);
  }

  /**
   * A missing or unknown client id is not logged.
   */
  public function testMissingOrUnknownClientIsNotLogged(): void {
    $this->assertNull($this->hooks()->adLoginLog($this->account, []));
    $this->assertNull($this->hooks()->adLoginLog($this->account, ['plugin_id' => 'does_not_exist']));
  }

}
