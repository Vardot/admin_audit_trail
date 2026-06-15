<?php

namespace Drupal\admin_audit_trail_user_roles\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail_user_roles.
 */
class AdminAuditTrailUserRolesHooks {
  use StringTranslationTrait;

  /**
   * Constructs an AdminAuditTrailUserRolesHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_admin_audit_trail_handlers().
   */
  #[Hook('admin_audit_trail_handlers')]
  public function adminAuditTrailHandlers() {
    // User roles event log handler.
    $handlers = [];
    $handlers['user_roles'] = [
      'title' => $this->t('User Roles'),
    ];
    return $handlers;
  }

  /**
   * Implements hook_user_update().
   */
  #[Hook('user_update')]
  public function userUpdate($account) {
    $original_roles = $account->original->getRoles();
    $current_roles = $account->getRoles();
    $role_operations = [
      'role_added' => array_diff($current_roles, $original_roles),
      'role_removed' => array_diff($original_roles, $current_roles),
    ];
    foreach ($role_operations as $operation => $roles) {
      foreach ($roles as $role_id) {
        // Skip authenticated role as it's automatically assigned.
        if ($role_id === 'authenticated') {
          continue;
        }
        $role = $this->entityTypeManager->getStorage('user_role')->load($role_id);
        $role_name = $role ? $role->label() : $role_id;
        if ($operation === 'role_added') {
          $description = $this->t('Role %role_name added to user %user_name (uid %uid)', [
            '%role_name' => $role_name,
            '%user_name' => $account->getDisplayName(),
            '%uid' => $account->id(),
          ]);
        }
        else {
          $description = $this->t('Role %role_name removed from user %user_name (uid %uid)', [
            '%role_name' => $role_name,
            '%user_name' => $account->getDisplayName(),
            '%uid' => $account->id(),
          ]);
        }
        $log = [
          'type' => 'user_roles',
          'operation' => $operation,
          'description' => $description,
          'ref_numeric' => $account->id(),
          'ref_char' => $account->getDisplayName() . ' - ' . $role_name,
        ];
        admin_audit_trail_insert($log);
      }
    }
  }

  /**
   * Implements hook_user_role_insert().
   */
  #[Hook('user_role_insert')]
  public function userRoleInsert($role) {
    $log = [
      'type' => 'user_roles',
      'operation' => 'role_created',
      'description' => $this->t('Role %role_name (%role_id) created', [
        '%role_name' => $role->label(),
        '%role_id' => $role->id(),
      ]),
      'ref_numeric' => 0,
      'ref_char' => $role->label(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_user_role_update().
   */
  #[Hook('user_role_update')]
  public function userRoleUpdate($role) {
    $log = [
      'type' => 'user_roles',
      'operation' => 'role_updated',
      'description' => $this->t('Role %role_name (%role_id) updated', [
        '%role_name' => $role->label(),
        '%role_id' => $role->id(),
      ]),
      'ref_numeric' => 0,
      'ref_char' => $role->label(),
    ];
    admin_audit_trail_insert($log);
  }

  /**
   * Implements hook_user_role_delete().
   */
  #[Hook('user_role_delete')]
  public function userRoleDelete($role) {
    $log = [
      'type' => 'user_roles',
      'operation' => 'role_deleted',
      'description' => $this->t('Role %role_name (%role_id) deleted', [
        '%role_name' => $role->label(),
        '%role_id' => $role->id(),
      ]),
      'ref_numeric' => 0,
      'ref_char' => $role->label(),
    ];
    admin_audit_trail_insert($log);
  }

}
