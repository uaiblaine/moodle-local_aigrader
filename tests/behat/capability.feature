@local @local_aigrader
Feature: Capability checks on the AI Grader Pro pages
  In order to keep students out of the teacher review surface
  As an installer / administrator
  I need the manage and review URLs to require local/aigrader:use,
  which by default is granted to editing teachers and managers only

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | student1 | Student   | One      | student1@test.com |
      | admin2   | Site      | Manager  | admin2@test.com   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "system role assigns" exist:
      | user   | role    | contextlevel |
      | admin2 | manager | System       |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 1 | C1     | assign1  |
    And AI Grader Pro is enabled on the "Essay 1" assignment
    And the following local_aigrader submissions exist:
      | student  | assignment | status      | proposed_grade |
      | student1 | Essay 1    | ai_proposed | 8.0            |

  @javascript
  Scenario: A student cannot reach the manage page
    Given I log in as "student1"
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Sorry, but you do not currently have permissions to do that"

  @javascript
  Scenario: An editing teacher can reach the manage page
    Given I log in as "teacher1"
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Student One"
    And I should see "AI proposed"

  @javascript
  Scenario: A site manager can reach the manage page even without enrolment
    Given I log in as "admin2"
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Student One"
    And I should see "AI proposed"
