@local @local_resourceqr
Feature: Generate QR codes for activities and resources
  In order to share activities on printed material
  As a teacher or authorised staff member
  I need to generate, download and print a QR code for an activity

  Background:
    Given the following "categories" exist:
      | name    | category | idnumber |
      | Science | 0        | SCI      |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | SCI      |
    And the following "users" exist:
      | username    | firstname   | lastname | email                   |
      | teacher1    | Teacher     | One      | teacher1@example.com    |
      | noneditor1  | Noneditor   | One      | noneditor1@example.com  |
      | student1    | Student     | One      | student1@example.com    |
      | coordinator | Coordinator | One      | coordinator@example.com |
      | outsider    | Outsider    | One      | outsider@example.com    |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | teacher1   | C1     | editingteacher |
      | noneditor1 | C1     | teacher        |
      | student1   | C1     | student        |
    And the following "roles" exist:
      | shortname     | name              | archetype |
      | qrcoordinator | QR code generator |           |
    And the following "permission overrides" exist:
      | capability                         | permission | role          | contextlevel | reference |
      | local/resourceqr:generate          | Allow      | qrcoordinator | System       |           |
      | moodle/course:view                 | Allow      | qrcoordinator | System       |           |
    And the following "role assigns" exist:
      | user        | role          | contextlevel | reference |
      | coordinator | qrcoordinator | Category     | SCI       |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
      | page     | Page 1 | C1     | page1    |

  @javascript
  Scenario: A teacher opens the QR code dialogue
    Given I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    When I navigate to "Generate QR code" in current page administration
    Then I should see "Quiz 1" in the "QR code: Quiz 1" "dialogue"
    And I should see "Scan this QR code to open this Moodle resource or activity. You may need to log in to Moodle first." in the "QR code: Quiz 1" "dialogue"
    And "img.local-resourceqr-image[alt='QR code linking to Quiz 1']" "css_element" should exist in the "QR code: Quiz 1" "dialogue"
    And "input.local-resourceqr-urlinput[value*='/mod/quiz/view.php?id=']" "css_element" should exist in the "QR code: Quiz 1" "dialogue"
    And "Copy link" "button" should exist in the "QR code: Quiz 1" "dialogue"
    And "Download PNG" "link" should exist in the "QR code: Quiz 1" "dialogue"
    And "Download SVG" "link" should exist in the "QR code: Quiz 1" "dialogue"
    And "Print" "link" should exist in the "QR code: Quiz 1" "dialogue"
    And I click on "Copy link" "button" in the "QR code: Quiz 1" "dialogue"
    And I should see "Link copied to clipboard."

  @javascript
  Scenario: Hidden activities show a warning
    Given the following "activities" exist:
      | activity | name          | course | idnumber | visible |
      | page     | Hidden page   | C1     | hidden1  | 0       |
    And I am on the "Hidden page" "page activity" page logged in as teacher1
    When I navigate to "Generate QR code" in current page administration
    Then I should see "This activity is hidden from students. Some users may not be able to open it." in the "QR code: Hidden page" "dialogue"

  @javascript
  Scenario: Only allowed download formats are offered
    Given the following config values are set as admin:
      | formats | svg | local_resourceqr |
    And I am on the "Page 1" "page activity" page logged in as teacher1
    When I navigate to "Generate QR code" in current page administration
    Then "Download SVG" "link" should exist in the "QR code: Page 1" "dialogue"
    And "Download PNG" "link" should not exist in the "QR code: Page 1" "dialogue"

  @javascript
  Scenario: A coordinator with a category-level role can generate QR codes
    Given I am on the "Page 1" "page activity" page logged in as coordinator
    When I navigate to "Generate QR code" in current page administration
    Then I should see "Page 1" in the "QR code: Page 1" "dialogue"

  Scenario Outline: Users without the capability do not see the action
    Given I am on the "Page 1" "page activity" page logged in as <user>
    Then "Generate QR code" "link" should not exist

    Examples:
      | user       |
      | student1   |
      | noneditor1 |

  Scenario: The action is not shown when the plugin is disabled
    Given the following config values are set as admin:
      | enabled | 0 | local_resourceqr |
    When I am on the "Page 1" "page activity" page logged in as teacher1
    Then "Generate QR code" "link" should not exist

  Scenario: The stand-alone page works without JavaScript
    Given I am on the "Page 1" "page activity" page logged in as teacher1
    When I navigate to "Generate QR code" in current page administration
    Then I should see "QR code: Page 1"
    And I should see "Scan this QR code to open this Moodle resource or activity. You may need to log in to Moodle first."
    And "Download PNG" "link" should exist
    And "Download SVG" "link" should exist

  Scenario: The image endpoint returns an SVG image without errors
    Given I am on the "Page 1" "page activity" page logged in as teacher1
    And I navigate to "Generate QR code" in current page administration
    When I follow "Download SVG"
    Then I should see "QR code for"

  @javascript @theme_classic
  Scenario: The action works in the Classic theme
    # Classic is no longer bundled with Moodle 5.3; skip where it is not installed.
    Given the "theme_classic" plugin is installed
    And the following config values are set as admin:
      | theme | classic |
    And I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    When I click on "Generate QR code" "link" in the "Administration" "block"
    Then I should see "Quiz 1" in the "QR code: Quiz 1" "dialogue"
    And "Download PNG" "link" should exist in the "QR code: Quiz 1" "dialogue"

  @javascript
  Scenario: The dialogue works on a mobile-size screen
    Given I change viewport size to "mobile"
    And I am on the "Quiz 1" "quiz activity" page logged in as teacher1
    When I navigate to "Generate QR code" in current page administration
    Then I should see "Quiz 1" in the "QR code: Quiz 1" "dialogue"
    And "Copy link" "button" should exist in the "QR code: Quiz 1" "dialogue"
    And "Print" "link" should exist in the "QR code: Quiz 1" "dialogue"
