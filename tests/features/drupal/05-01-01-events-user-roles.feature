@admin_audit_trail @events @user_roles
Feature: Admin Audit Trail - user role events
  As a site administrator
  I want role creation recorded
  So that I can audit permission-structure changes

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a role logs a user_roles role_created event
    When I create a user role named "Audit Reviewer"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row user_roles created" element should be visible within 15 seconds
