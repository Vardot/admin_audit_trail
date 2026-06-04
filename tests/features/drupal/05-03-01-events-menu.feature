@admin_audit_trail @events @menu
Feature: Admin Audit Trail - menu events
  As a site administrator
  I want menu and menu-link changes recorded
  So that I can audit navigation changes

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a menu logs a menu insert event
    When I create a menu named "Audit Menu"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row menu insert" element should be visible within 15 seconds

  Scenario: Adding a menu link logs a menu link insert event
    When I create a menu named "Audit Link Menu"
    And I add a menu link titled "Audit Link" to the menu I created
    And I am on "/admin/reports/audit-trail"
    Then the "audit row menu link insert" element should be visible within 15 seconds
