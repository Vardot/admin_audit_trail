@admin_audit_trail @a11y
Feature: Admin Audit Trail - accessibility of the report
  As an administrator using assistive technology
  I want the audit report to be a well-structured, labelled page
  So that I can review the log with a screen reader

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I am on "/admin/reports/audit-trail"

  Scenario: The report page has the expected landmarks and heading
    Then the page should have a main landmark
    And the page should have exactly one h1
    And the page should declare a language

  Scenario: Every exposed filter has an accessible label
    Then every form field should have an accessible label

  Scenario: The report passes an accessibility audit
    Then the page should have no critical accessibility violations
    And the page should have no serious accessibility violations
