@admin_audit_trail @events @taxonomy
Feature: Admin Audit Trail - taxonomy events
  As a site administrator
  I want vocabulary and term changes recorded
  So that I can audit the classification structure

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a vocabulary logs a taxonomy vocabulary insert event
    When I create a taxonomy vocabulary named "Audit Topics"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row taxonomy vocabulary insert" element should be visible within 15 seconds

  Scenario: Creating a term logs a taxonomy term insert event
    When I create a taxonomy term named "Audit Term One"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row taxonomy term insert" element should be visible within 15 seconds
