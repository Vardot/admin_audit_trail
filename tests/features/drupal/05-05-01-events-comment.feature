@admin_audit_trail @events @comment
Feature: Admin Audit Trail - comment events
  As a site administrator
  I want comment postings recorded
  So that I can audit user-contributed discussion

  Background:
    Given I am a logged in user with the "Webmaster" user
    And I create an article titled "Audit Comment Host"

  Scenario: Posting a comment logs a comment insert event
    When I post the comment "An audited comment body" on the article I created
    And I am on "/admin/reports/audit-trail"
    Then the "audit row comment insert" element should be visible within 15 seconds
