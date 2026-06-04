@setup @admin_audit_trail
Feature: Provision the Admin Audit Trail test site
  As the site owner
  I want one user per role created through the admin UI
  So that the audit, filtering and access-control scenarios have what they expect

  Scenario: The Webmaster signs in and provisions the testing users
    Given I am a logged in user with the "Webmaster" user
    And I add testing users
    When I am on "/admin/reports/audit-trail"
    Then the "audit report" element should be visible
    And the "drupal page heading" element should contain text "Admin Audit Trail"
