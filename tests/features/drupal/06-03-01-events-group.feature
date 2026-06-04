@admin_audit_trail @events @group
Feature: Admin Audit Trail - group events
  As a site administrator
  I want group creation recorded
  So that I can audit access-controlled group spaces

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a group logs a group insert event
    When I create a group named "Audit Team"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row group insert" element should be visible within 15 seconds
