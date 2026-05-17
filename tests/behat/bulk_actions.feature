@local @local_aigrader
Feature: Apply bulk actions to several AI grading proposals at once
  In order to publish many AI proposals in one go without losing safety
  As a teacher
  I need a "With selected..." dropdown that classifies rows, shows a confirmation
  page when the action is destructive, and reports which rows were skipped and why

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | student1 | Alice     | Anderson | student1@test.com |
      | student2 | Bob       | Brown    | student2@test.com |
      | student3 | Carla     | Cooper   | student3@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 1 | C1     | assign1  |
    And AI Grader Pro is enabled on the "Essay 1" assignment
    And the following local_aigrader submissions exist:
      | student  | assignment | status             | proposed_grade |
      | student1 | Essay 1    | ai_proposed        | 8.0            |
      | student2 | Essay 1    | published          | 7.0            |
      | student3 | Essay 1    | unsupported_format |                |
    And I log in as "teacher1"

  @javascript
  Scenario: Confirmation page shows the skip summary before publishing
    When I open the AI Grader Pro manage page for "Essay 1"
    And I set the field "Select submission by Alice Anderson" to "1"
    And I set the field "Select submission by Bob Brown" to "1"
    And I set the field "Select submission by Carla Cooper" to "1"
    And I set the field "With selected:" to "Publish proposed grade"
    And I press "Apply"
    Then I should see "Publish proposed grade"
    And I should see "submissions will be processed"
    And I should see "Will be skipped"
    And I should see "Already published"
    And I should see "Unsupported file format"
    When I press "Yes, publish"
    Then I should see "submissions processed"
    And I should see "Published"

  @javascript
  Scenario: Cancelling the confirmation page leaves nothing changed
    When I open the AI Grader Pro manage page for "Essay 1"
    And I set the field "Select submission by Alice Anderson" to "1"
    And I set the field "With selected:" to "Publish proposed grade"
    And I press "Apply"
    Then I should see "Publish proposed grade"
    When I follow "Cancel"
    Then I should see "AI proposed"
    And I should not see "Grade approved and published"

  @javascript
  Scenario: Applying with no rows selected shows a warning and does not navigate
    When I open the AI Grader Pro manage page for "Essay 1"
    And I set the field "With selected:" to "Publish proposed grade"
    And I press "Apply"
    Then I should see "No submissions selected"
