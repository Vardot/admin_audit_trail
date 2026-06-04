@admin_audit_trail @events @redirect
Feature: Admin Audit Trail - URL redirect events
  As a site administrator
  I want redirect creation recorded
  So that I can audit URL routing changes

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a redirect logs a redirect insert event
    When I create a redirect from "/audit-old-path" to "/node"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row redirect insert" element should be visible within 15 seconds
