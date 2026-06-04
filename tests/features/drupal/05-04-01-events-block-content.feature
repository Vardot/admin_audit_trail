@admin_audit_trail @events @block_content
Feature: Admin Audit Trail - custom block events
  As a site administrator
  I want custom block creation recorded
  So that I can audit reusable content blocks

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a custom block logs a block_content insert event
    When I create a custom block named "Audit Promo Block"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row block_content insert" element should be visible within 15 seconds
