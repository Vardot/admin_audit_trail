@admin_audit_trail @path
Feature: Admin Audit Trail - request path logging
  As a site administrator
  I want every event to record the request path within the column limit
  So that I can see where a change was made without hitting a database error

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: A logged event records the request path
    When I create an article titled "Audit Trail Path Article"
    And I filter the audit report by the "Node" type
    Then the "audit col path" element should contain text "node/add/article"

  Scenario: An over-long path is trimmed to the column limit without a database error
    When I create an article titled "LONGPATH trim test"
    And I filter the audit report by the "Node" type
    Then the "audit col path" element should contain text "longpath"
    And the "audit col path" element text should be at most 255 characters long
