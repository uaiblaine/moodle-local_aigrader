@local @local_aigrader
Feature: Review an AI grading proposal
  In order to keep humans in the loop for every grade
  As a teacher
  I need to review the AI's proposal and either publish it to the gradebook
  or save my edits as a draft without publishing

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | student1 | Student   | One      | student1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 1 | C1     | assign1  |
    And AI Grader Pro is enabled on the "Essay 1" assignment
    And the following local_aigrader submissions exist:
      | student  | assignment | status      | proposed_grade |
      | student1 | Essay 1    | ai_proposed | 8.0            |
    And I log in as "teacher1"

  @javascript
  Scenario: Teacher approves the AI proposal and publishes the grade
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Student One"
    And I should see "AI proposed"
    When I follow "Review"
    Then I should see "Review AI proposal"
    And the field "finalgrade" matches value "8.00"
    When I press "Approve and publish"
    Then I should see "Grade approved and published to the gradebook"
    And I should see "Published"

  @javascript
  Scenario: Teacher edits the grade and saves a draft without publishing
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "Review"
    And I set the field "finalgrade" to "6.5"
    And I press "Save without publishing"
    Then I should see "Saved without publishing"
    And I should see "Teacher reviewed"

  @javascript
  Scenario: A draft can be re-opened, edited again and finally published
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "Review"
    And I set the field "finalgrade" to "7.0"
    And I press "Save without publishing"
    Then I should see "Teacher reviewed"
    When I follow "Review"
    Then the field "finalgrade" matches value "7.00"
    When I set the field "finalgrade" to "7.5"
    And I press "Approve and publish"
    Then I should see "Grade approved and published to the gradebook"
    And I should see "Published"

  @javascript
  Scenario: Grade outside the 0-10 range is rejected
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "Review"
    And I set the field "finalgrade" to "15"
    And I press "Approve and publish"
    Then I should see "must be between 0 and 10"
