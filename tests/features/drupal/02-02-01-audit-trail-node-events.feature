@admin_audit_trail @events @node
Feature: Admin Audit Trail - node CUD events are logged
  As a site administrator
  I want create / update / delete actions on content recorded
  So that I have an accountable history of node changes

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating an article logs a node insert event
    When I create an article titled "Audit Trail Insert Article"
    And I filter the audit report by the "Node" type
    Then the "audit row node insert" element should have at least a count of 1
    And the "audit col description" element should contain text "Audit Trail Insert Article"

  Scenario: Editing an article logs a node update event that appears immediately
    Given I create an article titled "Audit Trail Update Article"
    When I update the article I created with the title "Audit Trail Update Article (edited)"
    And I filter the audit report by the "Node" type
    Then the "audit row node update" element should have at least a count of 1
    And the "audit col description" element should contain text "Audit Trail Update Article (edited)"

  Scenario: Deleting an article logs a node delete event
    Given I create an article titled "Audit Trail Delete Article"
    When I delete the article I created
    And I filter the audit report by the "Node" type
    Then the "audit row node delete" element should have at least a count of 1
