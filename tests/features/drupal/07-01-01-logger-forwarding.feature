@admin_audit_trail @events @logger
Feature: Admin Audit Trail Logger - PSR-3 forwarding
  As a site administrator
  I want audit events forwarded to the Drupal logger
  So that syslog / Monolog / dblog backends receive them

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: An audit event is forwarded to the database log in hybrid mode
    Given I create an article titled "Audit Trail Logger Article"
    When I am on "/admin/reports/dblog?type%5B%5D=audit_trail"
    Then the "dblog rows" element should have at least a count of 1
    And the "dblog message cells" element should contain text "Audit Trail Logger Article"

  Scenario: Hybrid mode still stores the event in the audit report
    Given I am on "/admin/config/development/audit-trail/logger"
    And I select radio button "Hybrid (database + PSR-3)"
    And I press "Save configuration"
    When I create an article titled "Audit Trail Logger Hybrid Article"
    And I filter the audit report by the "Node" type
    Then the "audit col description" element should contain text "Audit Trail Logger Hybrid Article"
