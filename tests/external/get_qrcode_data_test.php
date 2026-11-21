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

namespace local_resourceqr\external;

use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

#[\PHPUnit\Framework\Attributes\CoversClass(get_qrcode_data::class)]
/**
 * Tests for the get_qrcode_data external function.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\external\get_qrcode_data
 */
final class get_qrcode_data_test extends \externallib_advanced_testcase {
    /**
     * Teachers get the dialogue data.
     */
    public function test_execute(): void {
        global $CFG;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Safety & <b>rules</b>']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = get_qrcode_data::execute($page->cmid);
        $result = external_api::clean_returnvalue(get_qrcode_data::execute_returns(), $result);

        $this->assertSame($CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid, $result['url']);
        $this->assertSame((int) $page->cmid, $result['cmid']);
        $this->assertSame('Safety & rules', $result['plainname']);
        $this->assertStringNotContainsString('<b>', $result['name']);
        $this->assertStringContainsString('format=svg', $result['previewurl']);
        $this->assertCount(2, $result['downloads']);
        $this->assertFalse($result['haswarnings']);
        $this->assertStringContainsString('print.php', $result['printurl']);
    }

    /**
     * Hidden activity returns a warning.
     */
    public function test_execute_hidden(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $result = get_qrcode_data::execute($page->cmid);
        $this->assertTrue($result['haswarnings']);
        $this->assertSame(get_string('warning:hidden', 'local_resourceqr'), $result['warnings'][0]['message']);
    }

    /**
     * Students are denied.
     */
    public function test_execute_student_denied(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->expectException(\required_capability_exception::class);
        get_qrcode_data::execute($page->cmid);
    }

    /**
     * Users not enrolled in the course are denied, even with the capability in another course.
     */
    public function test_execute_not_enrolled_denied(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($other, 'editingteacher'));
        $this->expectException(\require_login_exception::class);
        get_qrcode_data::execute($page->cmid);
    }

    /**
     * Hidden activities are not revealed to users who cannot see them.
     */
    public function test_execute_hidden_not_revealed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $role = $this->getDataGenerator()->create_role(['shortname' => 'qrstaff']);
        assign_capability('local/resourceqr:generate', CAP_ALLOW, $role, \context_course::instance($course->id)->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        role_assign($role, $user->id, \context_course::instance($course->id)->id);
        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        get_qrcode_data::execute($page->cmid);
    }

    /**
     * Disabled plugin is refused.
     */
    public function test_execute_disabled(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        set_config('enabled', 0, 'local_resourceqr');
        $this->expectExceptionMessage(get_string('error:disabled', 'local_resourceqr'));
        get_qrcode_data::execute($page->cmid);
    }
}
