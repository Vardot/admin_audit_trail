@admin_audit_trail @report @operation_filter
Feature: Admin Audit Trail - operation exposed filter
  As a site administrator
  I want to filter the report by operation
  So that I can narrow events down to a specific action

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The Operation filter is present and populated with logged operations
    When I create an article titled "Operation Filter Article"
    And I am on "/admin/reports/audit-trail"
    Then the "audit filter operation select" element should be visible
    And the "audit filter operation select" element should contain text "Insert"
