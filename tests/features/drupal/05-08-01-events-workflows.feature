@admin_audit_trail @events @workflows
Feature: Admin Audit Trail - content moderation (workflows) events
  As a site administrator
  I want editorial state changes recorded
  So that I can audit the moderation history

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a moderated article logs a workflows event
    When I create an article titled "Audit Workflow Article"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row workflows" element should be visible within 15 seconds
