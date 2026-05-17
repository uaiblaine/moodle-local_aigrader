@local @local_aigrader
Feature: Filter the manage page by status and paginate large cohorts
  In order to find the submissions I still have to act on
  As a teacher with a large cohort
  I need clickable status chips that filter the table and a per-page selector
  that paginates without losing the chip totals

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | s1       | Alice     | A        | s1@test.com       |
      | s2       | Bob       | B        | s2@test.com       |
      | s3       | Carla     | C        | s3@test.com       |
      | s4       | Diego     | D        | s4@test.com       |
      | s5       | Elena     | E        | s5@test.com       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | s1       | C1     | student        |
      | s2       | C1     | student        |
      | s3       | C1     | student        |
      | s4       | C1     | student        |
      | s5       | C1     | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | assign   | Essay 1 | C1     | assign1  |
    And AI Grader Pro is enabled on the "Essay 1" assignment
    And the following local_aigrader submissions exist:
      | student | assignment | status             | proposed_grade |
      | s1      | Essay 1    | ai_proposed        | 8.0            |
      | s2      | Essay 1    | ai_proposed        | 7.0            |
      | s3      | Essay 1    | published          | 9.0            |
      | s4      | Essay 1    | teacher_reviewed   | 6.5            |
      | s5      | Essay 1    | unsupported_format |                |
    And I log in as "teacher1"

  @javascript
  Scenario: Counter chips display the cohort breakdown
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "5 submissions"
    And I should see "2 with AI proposal"
    And I should see "1 published"
    And I should see "1 reviewed"
    And I should see "1 with problems"

  @javascript
  Scenario: Clicking the "with AI proposal" chip filters the table
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "2 with AI proposal"
    Then I should see "Alice A"
    And I should see "Bob B"
    And I should not see "Carla C" in the ".aigrader-manage-table" "css_element"
    And I should not see "Diego D" in the ".aigrader-manage-table" "css_element"
    And I should see "Show all"

  @javascript
  Scenario: Show all clears an active filter
    When I open the AI Grader Pro manage page for "Essay 1"
    And I follow "2 with AI proposal"
    Then I should see "Show all"
    When I follow "Show all"
    Then I should see "Alice A"
    And I should see "Carla C"
    And I should see "Diego D"

  @javascript
  Scenario: Per-page selector keeps chip totals stable
    When I open the AI Grader Pro manage page for "Essay 1"
    And I set the field "Show per page:" to "10"
    Then I should see "5 submissions"
    And I should see "Alice A"
    And I should see "Elena E"
