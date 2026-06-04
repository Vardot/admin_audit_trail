@admin_audit_trail @events @config
Feature: Admin Audit Trail - configuration events
  As a site administrator
  I want configuration create / update / delete recorded
  So that I can audit changes to site configuration

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating a config entity logs a config insert event
    When I create a taxonomy vocabulary named "Audit Config Vocab"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row config insert" element should be visible within 15 seconds

  Scenario: Updating simple configuration logs a config update event
    When I go to "/admin/config/system/site-information"
    And I fill in "Site name" with "Audit Trail Config Test Site"
    And I press "Save configuration"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row config update" element should be visible within 15 seconds

  Scenario: The config event records the configuration object name
    When I go to "/admin/config/system/site-information"
    And I fill in "Site name" with "Audit Trail Config Name Check"
    And I press "Save configuration"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row config system site" element should be visible within 15 seconds
    And the "audit col description" element should contain text "Config: system.site"

  Scenario: Deleting a config entity logs a config delete event
    Given I create a taxonomy vocabulary named "Audit Config Delete Vocab"
    When I delete the taxonomy vocabulary I created
    And I am on "/admin/reports/audit-trail"
    Then the "audit row config delete" element should be visible within 15 seconds

  Scenario: Filtering the report by the Configuration type shows config rows
    When I go to "/admin/config/system/site-information"
    And I fill in "Site name" with "Audit Trail Config Filter"
    And I press "Save configuration"
    And I filter the audit report by the "Configuration" type
    Then the "audit report rows" element should have at least a count of 1
    And the "audit col type" element should contain text "config"
