@admin_audit_trail @report
Feature: Admin Audit Trail - the Views-based report
  As a site administrator
  I want a report with the audited columns and exposed filters
  So that I can inspect who changed what, when and from where

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I am on "/admin/reports/audit-trail"

  Scenario: The report exposes all of the audit filters
    Then the "audit filter type select" element should be visible
    And the "audit filter name" element should be visible
    And the "audit filter ip" element should be visible
    And the "audit filter ref id" element should be visible
    And the "audit filter ref name" element should be visible
    And the "audit filter path" element should be visible
    And the "audit filter description" element should be visible
    And the "audit filter apply" element should be visible

  Scenario: The report links to the legacy form-based report
    Then the "audit legacy link" element should be visible

  Scenario: The report renders without JavaScript errors
    Then there should be no JavaScript errors
