<?php

namespace Drupal\admin_audit_trail\Hook;

use Drupal\views\ViewExecutable;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for admin_audit_trail.
 */
class AdminAuditTrailHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.admin_audit_trail':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t("You can track logs of specific events that you'd like to log. The events  by the user (using the forms) are saved in the database and can be viewed on the page admin/reports/events-track. You could use this to track number of times the CUD operation performed by which users. This module required by: Admin Audit Trail User Authentication, Admin Audit Trail Menu, Admin Audit Trail Node, Admin Audit Trail Taxonomy, Admin Audit Trail User.") . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Admin Audit Trail Menu') . '</dt>';
        $output .= '<dd>' . $this->t('Using this submodule you can logs menu CUD events performed by the user. This module requires: Admin Audit Trail.') . '</dd>';
        $output .= '</dl>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Admin Audit Trail Node') . '</dt>';
        $output .= '<dd>' . $this->t('Using this submodule you can logs node CUD events performed by the user. This module requires: Admin Audit Trail.') . '</dd>';
        $output .= '</dl>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Admin Audit Trail Taxonomy') . '</dt>';
        $output .= '<dd>' . $this->t('Using this submodule you can logs taxonomy vocabulary and term CUD events performed by the user. This module requires: Admin Audit Trail.') . '</dd>';
        $output .= '</dl>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Admin Audit Trail User') . '</dt>';
        $output .= '<dd>' . $this->t('Using this submodule you can logs user CUD events performed by the user. This module requires: Admin Audit Trail.') . '</dd>';
        $output .= '</dl>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Admin Audit Trail User Authentication') . '</dt>';
        $output .= '<dd>' . $this->t('Using this submodule you can logs user authentication (login logout and request password). This module requires: Admin Audit Trail.') . '</dd>';
        $output .= '<dt>' . $this->t('Admin Audit Trail OpenID Connect') . '</dt>';
        $output .= '<dd>' . $this->t('Using this submodule you can log Active Directory (Windows AAD) logins made through OpenID Connect. This module requires: Admin Audit Trail User Authentication and OpenID Connect Windows AAD.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public static function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    // Check if this form is relevant for audit logging before adding the
    // handler.
    $handlers = admin_audit_trail_get_event_handlers();
    $is_relevant = FALSE;
    foreach ($handlers as $handler) {
      if (!empty($handler['form_ids']) && in_array($form_id, $handler['form_ids'])) {
        $is_relevant = TRUE;
        break;
      }
      elseif (!empty($handler['form_ids_regexp'])) {
        foreach ($handler['form_ids_regexp'] as $regexp) {
          if (preg_match($regexp, $form_id)) {
            $is_relevant = TRUE;
            break 2;
          }
        }
      }
    }
    if ($is_relevant) {
      // Add submit callback only to relevant forms.
      admin_audit_trail_add_submit_handler($form, 'admin_audit_trail_form_submit');
    }
  }

  /**
   * Implements hook_cron().
   *
   * Controls the size of the audit trail log table, paring it to
   * 'admin_audit_trail_row_limit' messages.
   */
  #[Hook('cron')]
  public static function cron() {
    // Cleanup the watchdog table.
    $row_limit = \Drupal::config('admin_audit_trail.settings')->get('admin_audit_trail_row_limit');
    // For row limit n, get the wid of the nth row in descending wid order.
    // Counting the most recent n rows avoids issues with wid number sequences,
    // e.g. auto_increment value > 1 or rows deleted directly from the table.
    if ($row_limit > 0) {
      $connection = \Drupal::database();
      $min_row = $connection->select('admin_audit_trail', 'lid')->fields('lid', [
        'lid',
      ])->orderBy('lid', 'DESC')->range($row_limit - 1, 1)->execute()->fetchField();
      // Delete all table entries older than the nth row, if nth row was found.
      if ($min_row) {
        $connection->delete('admin_audit_trail')->condition('lid', $min_row, '<')->execute();
      }
    }
  }

  /**
   * Implements hook_views_pre_render().
   *
   * Attaches the report CSS library so long path/description values wrap
   * instead of stretching the table (issue #3583295).
   */
  #[Hook('views_pre_render')]
  public static function viewsPreRender(ViewExecutable $view) {
    if ($view->id() === 'admin_audit_trail') {
      $view->element['#attached']['library'][] = 'admin_audit_trail/report';
    }
  }

}
