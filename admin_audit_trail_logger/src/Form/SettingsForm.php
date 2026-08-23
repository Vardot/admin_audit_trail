<?php

namespace Drupal\admin_audit_trail_logger\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Admin Audit Trail Logger.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_audit_trail_logger_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['admin_audit_trail_logger.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('admin_audit_trail_logger.settings');

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Logging mode'),
      '#options' => [
        'hybrid' => $this->t('Hybrid (database + PSR-3)'),
        'psr3_only' => $this->t('PSR-3 only (skip database)'),
      ],
      '#default_value' => $config->get('mode') ?: 'hybrid',
      '#description' => $this->t('In <em>PSR-3 only</em> mode, audit events are sent exclusively to the logger backend. The admin report at <code>/admin/reports/audit-trail</code> will no longer receive new entries.'),
    ];

    $form['channel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Logger channel'),
      '#default_value' => $config->get('channel') ?: 'audit_trail',
      '#description' => $this->t('The PSR-3 logger channel name. Only lowercase letters, digits, underscores and dots are allowed.'),
      '#required' => TRUE,
      '#maxlength' => 64,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $channel = $form_state->getValue('channel');
    if (!preg_match('/^[a-z][a-z0-9_.]*$/', $channel)) {
      $form_state->setErrorByName('channel', $this->t('The channel name must start with a lowercase letter and contain only lowercase letters, digits, underscores and dots.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('admin_audit_trail_logger.settings')
      ->set('mode', $form_state->getValue('mode'))
      ->set('channel', $form_state->getValue('channel'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
