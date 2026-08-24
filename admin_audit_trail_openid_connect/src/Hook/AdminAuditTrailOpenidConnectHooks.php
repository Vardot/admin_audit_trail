<?php

namespace Drupal\admin_audit_trail_openid_connect\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\openid_connect_windows_aad\Plugin\OpenIDConnectClient\WindowsAad;
use Drupal\user\UserInterface;

/**
 * Hook implementations for admin_audit_trail_openid_connect.
 */
class AdminAuditTrailOpenidConnectHooks {
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_openid_connect_post_authorize().
   *
   * OpenID Connect fires this hook once a user has actually been logged in
   * (after userLoginFinalize()), which is the correct point to record an
   * authentication event. The hook runs for every configured client, so log
   * only logins that went through a Windows Azure AD (Active Directory)
   * client.
   */
  #[Hook('openid_connect_post_authorize')]
  public function postAuthorize(UserInterface $account, array $context) {
    $log = $this->adLoginLog($account, $context);
    if ($log !== NULL) {
      admin_audit_trail_insert($log);
    }
  }

  /**
   * Builds the audit log entry for an Active Directory login, if applicable.
   *
   * Kept separate from the hook so the "is this a Windows AAD login?" check
   * and the log payload can be tested without a full OpenID Connect flow.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account that just logged in.
   * @param array $context
   *   The openid_connect authorization context. The "plugin_id" key holds
   *   the id of the OpenID Connect client config entity that performed the
   *   login.
   *
   * @return array|null
   *   An admin_audit_trail log array when the login used a Windows AAD
   *   client, or NULL when it did not (so nothing should be logged).
   */
  public function adLoginLog(UserInterface $account, array $context): ?array {
    $client_id = $context['plugin_id'] ?? NULL;
    if (empty($client_id)) {
      return NULL;
    }

    /** @var \Drupal\openid_connect\OpenIDConnectClientEntityInterface|null $client */
    $client = $this->entityTypeManager
      ->getStorage('openid_connect_client')
      ->load($client_id);
    if (!$client || !$client->getPlugin() instanceof WindowsAad) {
      return NULL;
    }

    return [
      'type' => 'authentication',
      // Differentiate Active Directory logins from local user authentication.
      'operation' => 'ad_login',
      'description' => $this->t('%user (uid %uid) logged in via Active Directory', [
        '%user' => $account->getDisplayName(),
        '%uid' => $account->id(),
      ]),
      'ref_numeric' => $account->id(),
      'ref_char' => $account->getDisplayName(),
    ];
  }

}
