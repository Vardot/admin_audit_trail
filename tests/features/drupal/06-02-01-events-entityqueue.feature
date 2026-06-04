@admin_audit_trail @events @entityqueue
Feature: Admin Audit Trail - entity queue events
  As a site administrator
  I want entity queue creation recorded
  So that I can audit curated content queues

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating an entity queue logs an entity_queue insert event
    When I create an entity queue named "Audit Queue"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row entity_queue insert" element should be visible within 15 seconds
