@admin_audit_trail @logger
Feature: Admin Audit Trail Logger - PSR-3 forwarding
  As a site administrator
  I want audit events forwarded to the Drupal logger
  So that syslog / Monolog / dblog backends receive them

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The logger settings form exposes its controls
    When I am on "/admin/config/development/audit-trail/logger"
    Then the "logger settings form" element should be visible
    And the "logger mode hybrid" element should be visible
    And the "logger mode psr3 only" element should be visible
    And the "logger channel" element should be visible
    And I should see the button "Save configuration"

  Scenario: Saving the logger settings reports success
    Given I am on "/admin/config/development/audit-trail/logger"
    When I fill in "audit_trail" for "Logger channel"
    And I press "Save configuration"
    Then the "drupal status messages" element should be visible
    And there should be no JavaScript errors

  Scenario: An invalid channel name is rejected
    Given I am on "/admin/config/development/audit-trail/logger"
    When I fill in "Invalid-Channel!" for "Logger channel"
    And I press "Save configuration"
    Then the "drupal error messages" element should be visible

  Scenario: An audit event is forwarded to the database log in hybrid mode
    Given I create an article titled "Audit Trail Logger Article"
    When I am on "/admin/reports/dblog?type%5B%5D=audit_trail"
    Then the "dblog rows" element should have at least a count of 1

  Scenario: Hybrid mode still stores the event in the audit report
    Given I create an article titled "Audit Trail Logger Hybrid Article"
    When I filter the audit report by the "Node" type
    Then the "audit col description" element should contain text "Audit Trail Logger Hybrid Article"
