@qtype @qformat_stack
Feature: Test importing STACK questions from the old STACK 2 format.
  In order reuse old questions
  As an teacher
  I need to be able to import them.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname |
      | teacher  | Teacher   |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And I log in as "teacher"
    And I follow "Course 1"

  @javascript
  Scenario: import a STACK question from a Moodle XML file
    When I navigate to "Import" node in "Course administration > Question bank"
    And I set the field "id_format_stack" to "1"
    And I upload "question/format/stack/samples/syntax_practice.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "Importing 9 questions from file"
    And I should see "Some longer mathematical expressions are difficult to type in by hand."
    And I press "Continue"
    And I should see "SP.1"
