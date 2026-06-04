@admin_audit_trail @events @file
Feature: Admin Audit Trail - file events
  As a site administrator
  I want uploaded files recorded
  So that I can audit assets entering the site

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Uploading an image creates a managed file insert event
    When I create an image media item named "Audit File Image"
    And I am on "/admin/reports/audit-trail"
    Then the "audit row file insert" element should be visible within 15 seconds
