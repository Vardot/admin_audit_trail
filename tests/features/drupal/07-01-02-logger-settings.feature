@admin_audit_trail @logger
Feature: Admin Audit Trail Logger - the settings form
  As a site administrator
  I want to control the forwarding mode and channel
  So that audit events reach the right logging backend

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I am on "/admin/config/development/audit-trail/logger"

  Scenario: The logger settings form exposes its controls
    Then the "logger settings form" element should be visible
    And I should see "Hybrid (database + PSR-3)"
    And I should see "PSR-3 only (skip database)"
    And I should see a "Logger channel" field
    And "#edit-channel" should have value "audit_trail"
    And I should see the button "Save configuration"

  Scenario: Saving the logger channel persists it
    When I fill in "audit_trail_e2e" for "Logger channel"
    And I press "Save configuration"
    Then I should see "The configuration options have been saved."
    When I am on "/admin/config/development/audit-trail/logger"
    Then "#edit-channel" should have value "audit_trail_e2e"
    When I fill in "audit_trail" for "Logger channel"
    And I press "Save configuration"
    Then "#edit-channel" should have value "audit_trail"
    And there should be no JavaScript errors

  Scenario: An invalid channel name is rejected
    When I fill in "Invalid-Channel!" for "Logger channel"
    And I press "Save configuration"
    Then I should see "The channel name must start with a lowercase letter and contain only lowercase letters, digits, underscores and dots."
    And I should not see "The configuration options have been saved."

  Scenario: PSR-3-only mode skips the audit report but reaches the database log
    Given I select radio button "PSR-3 only (skip database)"
    And I press "Save configuration"
    When I create an article titled "AAT PSR3 Article"
    And I am on "/admin/reports/dblog?type%5B%5D=audit_trail"
    Then the "dblog message cells" element should contain text "AAT PSR3 Article"
    When I filter the audit report by the "Node" type
    Then the "audit col description" element should not contain text "AAT PSR3 Article"
    # Restore hybrid so later local re-runs against the same database keep
    # storing events (CI rebuilds per job; local databases persist).
    When I am on "/admin/config/development/audit-trail/logger"
    And I select radio button "Hybrid (database + PSR-3)"
    And I press "Save configuration"
    Then I should see "The configuration options have been saved."
