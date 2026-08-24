@admin_audit_trail @settings
Feature: Admin Audit Trail - the settings form
  As a site administrator
  I want to control filter expansion and the log row limit
  So that I can tune how the audit trail behaves

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I am on "/admin/config/development/audit-trail/settings"

  Scenario: The settings form exposes its controls
    Then the "settings form" element should be visible
    And the "settings filter expanded" element should be visible
    And the "settings row limit" element should be visible
    And the "settings log cli" element should be visible
    And I should see "Log CLI events"
    And I should see the button "Save configuration"

  Scenario: Enabling CLI logging persists
    When I check "Log CLI events"
    And I press "Save configuration"
    Then I should see "The configuration options have been saved."
    When I am on "/admin/config/development/audit-trail/settings"
    Then the "settings log cli" element should be visible
    When I uncheck "Log CLI events"
    And I press "Save configuration"
    Then I should see "The configuration options have been saved."

  Scenario: Saving the settings reports success
    When I check "Filters Expanded"
    And I press "Save configuration"
    Then the "drupal status messages" element should be visible
    And there should be no JavaScript errors
