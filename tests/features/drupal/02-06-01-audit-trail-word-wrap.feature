@admin_audit_trail @report @wrap
Feature: Admin Audit Trail - report column word wrapping
  As a site administrator
  I want long path and description values to wrap in the report
  So that the table stays readable and does not stretch awkwardly wide

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The path and description columns wrap long values
    When I create an article titled "Word Wrap Article"
    And I filter the audit report by the "Node" type
    Then the "audit col path" element should be visible
    And the "audit col path" element should have the computed style "overflow-wrap" of "anywhere"
    And the "audit col description" element should have the computed style "overflow-wrap" of "anywhere"
