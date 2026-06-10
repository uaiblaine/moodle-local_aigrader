@local @local_aigrader
Feature: Honour the assignment's group mode on the manage page
  In order to respect separate groups and only review students I am responsible for
  As a teacher confined to my groups
  I need the AI Grader Pro manage page to list only the submissions of students
  I share a group with, and to keep me out of it entirely if I have no group

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Teacher   | One      | teacher1@test.com |
      | teacher2 | Teacher   | Two      | teacher2@test.com |
      | manager1 | Site      | Manager  | manager1@test.com |
      | s1       | Alice     | A        | s1@test.com       |
      | s2       | Bob       | B        | s2@test.com       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | s1       | C1     | student        |
      | s2       | C1     | student        |
    And the following "system role assigns" exist:
      | user     | role    | contextlevel |
      | manager1 | manager | System       |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "group members" exist:
      | group | user     |
      | G1    | teacher1 |
      | G1    | s1       |
      | G2    | s2       |
    # Editing teachers hold moodle/site:accessallgroups by default, which would
    # bypass separate groups entirely. Remove it in this course so the group
    # boundary actually applies to teacher1 and teacher2.
    And the following "permission overrides" exist:
      | capability                  | permission | role           | contextlevel | reference |
      | moodle/site:accessallgroups | Prevent    | editingteacher | Course       | C1        |
    And the following "activities" exist:
      | activity | name    | course | idnumber | groupmode |
      | assign   | Essay 1 | C1     | assign1  | 1         |
    And AI Grader Pro is enabled on the "Essay 1" assignment
    And the following local_aigrader submissions exist:
      | student | assignment | status      | proposed_grade |
      | s1      | Essay 1    | ai_proposed | 8.0            |
      | s2      | Essay 1    | ai_proposed | 7.0            |

  @javascript
  Scenario: A separate-groups teacher only sees their own group's submissions
    Given I log in as "teacher1"
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Alice A"
    And I should not see "Bob B"
    And I should see "Separate groups"

  @javascript
  Scenario: A separate-groups teacher with no group is locked out
    Given I log in as "teacher2"
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "You do not belong to any group in this activity"
    And I should not see "Alice A"
    And I should not see "Bob B"

  @javascript
  Scenario: A manager with access to all groups sees every group's submissions
    Given I log in as "manager1"
    When I open the AI Grader Pro manage page for "Essay 1"
    Then I should see "Alice A"
    And I should see "Bob B"
