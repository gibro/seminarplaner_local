@local @local_seminarplaner
Feature: Global method set management page smoke check
  In order to ensure beta baseline behavior
  As an admin
  I need to open the global management page.

  Scenario: Admin can open global method sets page
    Given I log in as "admin"
    When I visit "/local/seminarplaner/manage.php"
    Then I should see "Global method sets"
