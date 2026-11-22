<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_resourceqr;

use local_resourceqr\output\course_list;

#[\PHPUnit\Framework\Attributes\CoversClass(course_list::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_resourceqr_extend_navigation_course')]
/**
 * Tests for the course "QR codes" page and its navigation item.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\output\course_list
 * @covers     ::local_resourceqr_extend_navigation_course
 */
final class course_list_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /**
     * Create a course with a few activities in two sections.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['numsections' => 2]);
        $this->setAdminUser();
        $gen->create_module('page', ['course' => $this->course->id, 'name' => 'Welcome page', 'section' => 0]);
        $gen->create_module('label', ['course' => $this->course->id, 'section' => 0]);
        $gen->create_module('resource', ['course' => $this->course->id, 'name' => 'Handbook PDF', 'section' => 1]);
        $gen->create_module('quiz', ['course' => $this->course->id, 'name' => 'Hidden quiz', 'section' => 2, 'visible' => 0]);
        $this->setUser(null);
    }

    /**
     * Export the list for the current user.
     *
     * @return array
     */
    private function export(): array {
        global $PAGE;
        return (new course_list($this->course))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Names of all listed items.
     *
     * @param array $data Exported data.
     * @return string[]
     */
    private static function names(array $data): array {
        $names = [];
        foreach ($data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $names[] = $item['plainname'];
            }
        }
        return $names;
    }

    /**
     * Teachers see every activity and resource with a page, grouped by section.
     */
    public function test_teacher_list(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $data = $this->export();
        $this->assertTrue($data['hasitems']);
        $this->assertSame(['Welcome page', 'Handbook PDF', 'Hidden quiz'], self::names($data));
        $this->assertCount(3, $data['sections']);
        // The hidden quiz carries its warning; the label (no page) is not listed.
        $quiz = $data['sections'][2]['items'][0];
        $this->assertTrue($quiz['haswarnings']);
        $this->assertStringContainsString('/local/resourceqr/view.php?cmid=', $quiz['url']);
    }

    /**
     * Users without the capability get nothing.
     */
    public function test_student_list_empty(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $data = $this->export();
        $this->assertFalse($data['hasitems']);
        $this->assertSame([], $data['sections']);
    }

    /**
     * Disabled activity types are not listed.
     */
    public function test_disabled_module_type(): void {
        set_config('allmodules', 0, 'local_resourceqr');
        set_config('enabledmodules', 'resource', 'local_resourceqr');
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertSame(['Handbook PDF'], self::names($this->export()));
    }

    /**
     * Build the course navigation node as the current user.
     *
     * @return \navigation_node
     */
    private function course_node(): \navigation_node {
        global $CFG;
        require_once($CFG->dirroot . '/local/resourceqr/lib.php');
        $node = \navigation_node::create('Course', null, \navigation_node::TYPE_COURSE, null, 'courseadmin');
        local_resourceqr_extend_navigation_course($node, $this->course, \context_course::instance($this->course->id));
        return $node;
    }

    /**
     * The navigation item is added only for users with the capability, and only when enabled.
     */
    public function test_navigation_item(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $item = $this->course_node()->get('local_resourceqr_course');
        $this->assertNotFalse($item);
        $this->assertStringContainsString('/local/resourceqr/course.php?id=' . $this->course->id, $item->action->out(false));

        set_config('enabled', 0, 'local_resourceqr');
        $this->assertFalse($this->course_node()->get('local_resourceqr_course'));
        set_config('enabled', 1, 'local_resourceqr');

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->assertFalse($this->course_node()->get('local_resourceqr_course'));
    }
}
