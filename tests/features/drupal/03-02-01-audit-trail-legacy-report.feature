@admin_audit_trail @legacy
Feature: Admin Audit Trail - the legacy form-based report
  As a site administrator
  I want the classic report to remain reachable
  So that existing bookmarks and workflows keep working

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I create an article titled "Audit Trail Legacy Article"

  Scenario: The legacy report loads and lists events
    When I am on "/admin/reports/audit-trail/legacy"
    Then the "drupal page heading" element should contain text "Legacy"
    And the "audit legacy table rows" element should have at least a count of 1
    And there should be no JavaScript errors
