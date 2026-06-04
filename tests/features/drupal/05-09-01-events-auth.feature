@admin_audit_trail @events @auth
Feature: Admin Audit Trail - authentication events
  As a site administrator
  I want sign-in, sign-out and failed logins recorded
  So that I can audit account access

  Scenario: Logging out logs an authentication logout event
    Given I am a logged in user with the "Webmaster" user
    When I log out
    And I am a logged in user with the "Webmaster" user
    And I am on "/admin/reports/audit-trail"
    Then the "audit row auth logout" element should be visible within 15 seconds

  Scenario: A failed login logs an authentication fail event
    Given I attempt to log in with an incorrect password
    And I am a logged in user with the "Webmaster" user
    And I am on "/admin/reports/audit-trail"
    Then the "audit row auth fail" element should be visible within 15 seconds

  Scenario: Requesting a new password logs a request password event
    Given I request a new password for "webmaster"
    And I am a logged in user with the "Webmaster" user
    And I am on "/admin/reports/audit-trail"
    Then the "audit row auth request password" element should be visible within 15 seconds
