<?php

namespace Drupal\admin_audit_trail_auth\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_auth.
 */
class AdminAuditTrailAuthHooks {
  use StringTranslationTrait;

  /**
   * Constructs an AdminAuditTrailAuthHooks object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // User Authentication event log handler.
    $handlers = [];
    $handlers['authentication'] = [
      'title' => $this->t('User authentication'),
      'form_ids' => [
        'user_login_form',
        'user_pass',
      ],
      'form_submit_callback' => 'admin_audit_trail_auth_form_submit',
    ];
    return $handlers;
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public function userLogout($account) {
    $log = [
      'type' => 'authentication',
      'operation' => 'logout',
      'description' => $this->t('%user (uid %uid)', [
        '%user' => $account->getDisplayName(),
        '%uid' => $account->id(),
      ]),
      'ref_numeric' => $account->id(),
      'ref_char' => $account->getDisplayName(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_user_login_form_alter')]
  public static function formUserLoginFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $form['#validate'][] = 'admin_audit_trail_auth_user_login_validate';
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin($account) {
    $log = [
      'type' => 'authentication',
      'operation' => 'login',
      'description' => $this->t('%user (uid %uid)', [
        '%user' => $account->getDisplayName(),
        '%uid' => $account->id(),
      ]),
      'ref_numeric' => $account->id(),
      'ref_char' => $account->getDisplayName(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Event log callback for the user authentication event log.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form ID.
   *
   * @return array|null
   *   An associative array of data to insert in the database, or NULL.
   */
  public function formSubmitCallback($form, FormStateInterface $form_state, $form_id) {
    $log = NULL;
    switch ($form_id) {
      case 'user_login_form':
        $log = [
          'operation' => 'login',
          'description' => $this->t('%user (uid %uid)', [
            '%user' => $this->currentUser->getDisplayName(),
            '%uid' => $this->currentUser->id(),
          ]),
          'ref_numeric' => $this->currentUser->id(),
          'ref_char' => $this->currentUser->getDisplayName(),
        ];
        break;

      case 'user_pass':
        $uid = 0;
        $account = $form_state->getValue('account');
        if (isset($account)) {
          $uid = $account->id();
        }
        $log = [
          'operation' => 'request password',
          'description' => $this->t('%user (uid %uid)', [
            '%user' => $form_state->getValue('name'),
            '%uid' => $uid,
          ]),
          'ref_numeric' => $uid,
          'ref_char' => $form_state->getValue('name'),
        ];
        break;
    }
    return $log;
  }

  /**
   * Validation callback for the user login form: logs failed login attempts.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function userLoginValidate($form, FormStateInterface $form_state) {
    // Check for errors and log them.
    $errors = $form_state->getErrors();
    if (!empty($errors)) {
      $name = admin_audit_trail_safe_truncate($form_state->getValue('name'), 255);
      $log = [
        'type' => 'authentication',
        'operation' => 'fail',
        'description' => $this->t('%user', ['%user' => $name]),
        'ref_char' => $name,
      ];
      admin_audit_trail_insert($log);
    }
  }

}
