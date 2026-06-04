@smoke @admin_audit_trail
Feature: Smoke - the Admin Audit Trail report loads
  As a site administrator
  I want the Views-based audit report to render
  So that the Admin Audit Trail module can be exercised

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The audit report page loads
    When I am on "/admin/reports/audit-trail"
    Then the "audit report" element should be visible
    And the "audit exposed form" element should be visible
    And there should be no JavaScript errors

  Scenario: The audit report appears in the Reports menu
    When I am on "/admin/reports"
    Then the "menu audit report link" element should be visible

  Scenario: The settings page loads
    When I am on "/admin/config/development/audit-trail/settings"
    Then the "settings form" element should be visible
    And there should be no JavaScript errors
