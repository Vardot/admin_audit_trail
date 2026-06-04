@admin_audit_trail @events @media
Feature: Admin Audit Trail - media events
  As a site administrator
  I want media items recorded
  So that I can audit the media library

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Creating an image media item logs a media insert event
    When I create an image media item named "Audit Media Image"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row media insert" element should be visible within 15 seconds
