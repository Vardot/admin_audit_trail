@admin_audit_trail @events @paragraphs
Feature: Admin Audit Trail - paragraph events
  As a site administrator
  I want paragraph entities recorded
  So that I can audit structured in-node content

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Saving an article with a paragraph logs a paragraph insert event
    When I create an article with a paragraph titled "Audit Paragraph Article"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row paragraph insert" element should be visible within 15 seconds
