@admin_audit_trail @access
Feature: Admin Audit Trail - access control
  As a site owner
  I want only users with the audit permissions to reach the report and settings
  So that the change history cannot be read or reconfigured by regular users

  Scenario: The Webmaster can reach the audit report
    Given I am a logged in user with the "Webmaster" user
    When I am on "/admin/reports/audit-trail"
    Then the "audit report" element should be visible

  Scenario: A content editor is denied the audit report
    Given I am a logged in user with the "Content editor" user
    When I am on "/admin/reports/audit-trail"
    Then the "audit report" element should have a count of 0
    And the "drupal page heading" element should contain text "Access denied"

  Scenario: An authenticated user is denied the audit settings
    Given I am a logged in user with the "Authenticated user" user
    When I am on "/admin/config/development/audit-trail/settings"
    Then the "settings form" element should have a count of 0
    And the "drupal page heading" element should contain text "Access denied"
