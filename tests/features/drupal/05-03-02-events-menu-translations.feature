@admin_audit_trail @events @menu @i18n
Feature: Admin Audit Trail - menu link translation events
  As a site administrator
  I want menu link translation changes recorded without breaking the site
  So that I can audit multilingual navigation changes safely

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Deleting a menu link translation is logged and does not break the site
    When I create a menu named "Audit Trans Menu"
    And I add a menu link titled "Audit Trans Link" to the menu I created
    And I add the "es" translation titled "Audit Trans Link ES" to the menu link I created
    And I delete the "es" translation of the menu link I created
    And I am on "/admin/reports/audit-trail"
    # The event is only logged - and the report only reachable - if the delete
    # completed without the "refers to a removed translation" fatal.
    Then the audit report should list the operation "link translation delete" within 20 seconds
    And the "audit col description" element should contain text "removed es translation"

  Scenario: Adding a menu link translation keeps the link insert logged
    When I create a menu named "Audit Trans Add Menu"
    And I add a menu link titled "Audit Trans Add Link" to the menu I created
    And I add the "es" translation titled "Audit Trans Add Link ES" to the menu link I created
    And I am on "/admin/reports/audit-trail"
    Then the "audit row menu link insert" element should be visible within 15 seconds
