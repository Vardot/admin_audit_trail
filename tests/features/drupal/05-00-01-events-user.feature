@admin_audit_trail @events @user
Feature: Admin Audit Trail - user account events
  As a site administrator
  I want account creations recorded
  So that I can audit who provisioned which user

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a user logs a user insert event
    When I create a user named "audit_user_one"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row user insert" element should be visible within 15 seconds
