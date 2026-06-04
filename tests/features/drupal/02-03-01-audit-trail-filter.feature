@admin_audit_trail @filter
Feature: Admin Audit Trail - filtering the report
  As a site administrator
  I want to narrow the report by event type
  So that I can focus on a single kind of change

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I create an article titled "Audit Trail Filter Article"

  Scenario: Filtering by the Node type only shows node rows
    When I filter the audit report by the "Node" type
    Then the "audit row node" element should have at least a count of 1
    And the "audit row user" element should have a count of 0

  Scenario: Filtering by the User type hides node rows
    When I filter the audit report by the "User" type
    Then the "audit row node" element should have a count of 0
