@admin_audit_trail @redirect @i18n
Feature: Admin Audit Trail - redirect source with non-ASCII characters
  As a site administrator
  I want redirects with non-ASCII source paths recorded without a database error
  So that international URLs are audited and stay readable

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: A redirect with a long non-ASCII source is logged readably without an error
    When I create a redirect from "시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시시" to "/node"
    Then I should see "The redirect has been saved."
    And there should be no JavaScript errors
    When I filter the audit report by the "Redirect" type
    Then the "audit row redirect insert" element should be visible within 15 seconds
    And the "audit col ref name" element text should be at most 255 characters long
    And the "audit col ref name" element should contain text "시"
