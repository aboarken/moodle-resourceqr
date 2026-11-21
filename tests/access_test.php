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

use context_course;
use context_coursecat;
use context_module;
use context_system;
use local_resourceqr\local\access;

#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
/**
 * Tests for the access rules.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\local\access
 */
final class access_test extends \advanced_testcase {
    /** @var \core_course_category Course category. */
    private \core_course_category $category;

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass[] Users by scenario. */
    private array $users = [];

    /**
     * Create a course, an activity and one user per role scenario.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->category = $gen->create_category();
        $this->course = $gen->create_course(['category' => $this->category->id]);

        foreach (['editingteacher', 'teacher', 'student'] as $role) {
            $this->users[$role] = $gen->create_and_enrol($this->course, $role);
        }

        $this->users['manager'] = $gen->create_user();
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerrole, $this->users['manager']->id, context_system::instance()->id);

        // Coordinator: custom role assigned at category level, not enrolled in the course.
        $coordinatorrole = $gen->create_role(['shortname' => 'qrcoordinator', 'archetype' => '']);
        $catcontext = context_coursecat::instance($this->category->id);
        assign_capability('local/resourceqr:generate', CAP_ALLOW, $coordinatorrole, $catcontext->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $coordinatorrole, $catcontext->id);
        assign_capability('moodle/course:viewhiddenactivities', CAP_ALLOW, $coordinatorrole, $catcontext->id);
        $this->users['coordinator'] = $gen->create_user();
        role_assign($coordinatorrole, $this->users['coordinator']->id, $catcontext->id);

        // Non-teaching staff: custom role assigned at course level, generate only.
        $staffrole = $gen->create_role(['shortname' => 'qrstaff', 'archetype' => '']);
        $coursecontext = context_course::instance($this->course->id);
        assign_capability('local/resourceqr:generate', CAP_ALLOW, $staffrole, $coursecontext->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $staffrole, $coursecontext->id);
        $this->users['staff'] = $gen->create_user();
        role_assign($staffrole, $this->users['staff']->id, $coursecontext->id);

        $this->users['notenrolled'] = $gen->create_user();
    }

    /**
     * Get a cm_info for the current user.
     *
     * @param int $cmid
     * @return \cm_info
     */
    private function cm(int $cmid): \cm_info {
        return get_fast_modinfo($this->course->id)->get_cm($cmid);
    }

    /**
     * Role scenarios and the expected result for a visible activity.
     *
     * @return array
     */
    public static function role_provider(): array {
        return [
            'Editing teacher' => ['editingteacher', true],
            'Non-editing teacher' => ['teacher', false],
            'Coordinator role at category level' => ['coordinator', true],
            'Non-teaching staff role at course level' => ['staff', true],
            'Manager' => ['manager', true],
            'Student' => ['student', false],
            'Not enrolled' => ['notenrolled', false],
        ];
    }

    /**
     * Each role scenario on a visible activity.
     *
     * @dataProvider role_provider
     * @param string $scenario
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('role_provider')]
    public function test_role_scenarios(string $scenario, bool $expected): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->setUser($this->users[$scenario]);
        $this->assertSame($expected, access::can_generate($this->cm($page->cmid)));
    }

    /**
     * The capability can be granted to non-editing teachers.
     */
    public function test_non_editing_teacher_can_be_granted(): void {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/resourceqr:generate', CAP_ALLOW, $teacherrole, context_course::instance($this->course->id)->id);
        $this->setUser($this->users['teacher']);
        $this->assertTrue(access::can_generate($this->cm($page->cmid)));
    }

    /**
     * Capability can be prevented for a single activity.
     */
    public function test_capability_prohibited_at_module_level(): void {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('local/resourceqr:generate', CAP_PREVENT, $role, context_module::instance($page->cmid)->id);
        $this->setUser($this->users['editingteacher']);
        $this->assertFalse(access::can_generate($this->cm($page->cmid)));
    }

    /**
     * Hidden activities: teachers can generate, staff without viewhiddenactivities cannot.
     */
    public function test_hidden_activity(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'visible' => 0]);

        $this->setUser($this->users['editingteacher']);
        $cm = $this->cm($page->cmid);
        $this->assertTrue(access::can_generate($cm));
        $this->assertContains(get_string('warning:hidden', 'local_resourceqr'), access::get_warnings($cm));

        $this->setUser($this->users['coordinator']);
        $this->assertTrue(access::can_generate($this->cm($page->cmid)));

        $this->setUser($this->users['staff']);
        $this->assertFalse(access::can_generate($this->cm($page->cmid)));
    }

    /**
     * Stealth activities show a warning.
     */
    public function test_stealth_activity(): void {
        set_config('allowstealth', 1);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        set_coursemodule_visible($page->cmid, 1, 0);

        $this->setUser($this->users['editingteacher']);
        $cm = $this->cm($page->cmid);
        $this->assertTrue($cm->is_stealth());
        $this->assertTrue(access::can_generate($cm));
        $this->assertContains(get_string('warning:stealth', 'local_resourceqr'), access::get_warnings($cm));
    }

    /**
     * Activities with access restrictions show a warning, and users who do not meet them are denied.
     */
    public function test_restricted_activity(): void {
        set_config('enableavailability', 1);
        $availability = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => time() + YEARSECS]],
            'showc' => [true],
        ]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'availability' => $availability,
        ]);

        $this->setUser($this->users['editingteacher']);
        $cm = $this->cm($page->cmid);
        $this->assertTrue(access::can_generate($cm));
        $this->assertContains(get_string('warning:restricted', 'local_resourceqr'), access::get_warnings($cm));

        // Staff role without viewhiddenactivities does not meet the restriction.
        $this->setUser($this->users['staff']);
        $this->assertFalse(access::can_generate($this->cm($page->cmid)));
    }

    /**
     * A visible, unrestricted activity has no warnings.
     */
    public function test_no_warnings(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->setUser($this->users['editingteacher']);
        $this->assertSame([], access::get_warnings($this->cm($page->cmid)));
    }

    /**
     * Activities in a hidden section show a warning.
     */
    public function test_hidden_section(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'section' => 1]);
        global $DB;
        // Hide the section directly: set_section_visible() is deprecated from 5.2 and also hides the activity.
        $DB->set_field('course_sections', 'visible', 0, ['course' => $this->course->id, 'section' => 1]);
        rebuild_course_cache($this->course->id, true);
        $this->setUser($this->users['editingteacher']);
        $cm = $this->cm($page->cmid);
        $warnings = access::get_warnings($cm);
        $this->assertContains(get_string('warning:sectionhidden', 'local_resourceqr'), $warnings);
    }

    /**
     * Disabling the plugin denies everyone.
     */
    public function test_plugin_disabled(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->setUser($this->users['editingteacher']);
        set_config('enabled', 0, 'local_resourceqr');
        $this->assertFalse(access::is_enabled());
        $this->assertFalse(access::can_generate($this->cm($page->cmid)));
        $this->expectException(\moodle_exception::class);
        access::require_generate($this->cm($page->cmid));
    }

    /**
     * Disabling a module type denies that type only.
     */
    public function test_module_type_disabled(): void {
        $gen = $this->getDataGenerator();
        $page = $gen->create_module('page', ['course' => $this->course->id]);
        $quiz = $gen->create_module('quiz', ['course' => $this->course->id]);
        $this->setUser($this->users['editingteacher']);

        set_config('allmodules', 0, 'local_resourceqr');
        set_config('enabledmodules', 'page', 'local_resourceqr');
        $this->assertTrue(access::can_generate($this->cm($page->cmid)));
        $this->assertFalse(access::can_generate($this->cm($quiz->cmid)));

        set_config('enabledmodules', '', 'local_resourceqr');
        $this->assertFalse(access::can_generate($this->cm($page->cmid)));

        set_config('allmodules', 1, 'local_resourceqr');
        $this->assertTrue(access::can_generate($this->cm($quiz->cmid)));
    }

    /**
     * Modules for URL resolution.
     *
     * @return array
     */
    public static function module_provider(): array {
        return [
            ['page'], ['resource'], ['folder'], ['url'], ['book'], ['assign'], ['quiz'], ['forum'],
            ['lesson'], ['glossary'], ['data'], ['choice'], ['feedback'], ['wiki'], ['workshop'], ['lti'],
        ];
    }

    /**
     * The encoded URL is the module's normal view URL.
     *
     * @dataProvider module_provider
     * @param string $modname
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('module_provider')]
    public function test_target_url(string $modname): void {
        global $CFG;
        $params = ['course' => $this->course->id];
        if ($modname === 'url') {
            $params['externalurl'] = 'https://example.com/elsewhere';
        }
        // Some module generators need a logged-in user.
        $this->setAdminUser();
        $module = $this->getDataGenerator()->create_module($modname, $params);
        $this->setUser($this->users['editingteacher']);
        $cm = $this->cm($module->cmid);

        $this->assertTrue(access::can_generate($cm));
        $expected = $CFG->wwwroot . '/mod/' . $modname . '/view.php?id=' . $module->cmid;
        $this->assertSame($expected, access::get_target_url($cm)->out(false));
    }

    /**
     * The encoded URL never contains a sesskey or other user data.
     */
    public function test_target_url_has_no_user_data(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->setUser($this->users['editingteacher']);
        $url = access::get_target_url($this->cm($page->cmid))->out(false);
        $this->assertStringNotContainsString(sesskey(), $url);
        $this->assertStringNotContainsString('sesskey', $url);
        $this->assertStringNotContainsString((string) $this->users['editingteacher']->id . '&', $url);
        $this->assertSame(['id' => (string) $page->cmid], (new \moodle_url($url))->params());
    }

    /**
     * Text and media areas (and subsections) are not supported.
     */
    public function test_modules_without_view_url(): void {
        $label = $this->getDataGenerator()->create_module('label', ['course' => $this->course->id]);
        $this->setUser($this->users['editingteacher']);
        $this->assertFalse(access::module_has_view_url('label'));
        $this->assertFalse(access::can_generate($this->cm($label->cmid)));
        $this->assertArrayNotHasKey('label', access::get_supported_module_types());
        $this->assertArrayHasKey('page', access::get_supported_module_types());

        if (\core_component::get_component_directory('mod_subsection')) {
            $this->assertFalse(access::module_has_view_url('subsection'));
            $this->assertArrayNotHasKey('subsection', access::get_supported_module_types());
        }
        if (\core_component::get_component_directory('mod_qbank')) {
            $this->assertFalse(access::module_has_view_url('qbank'));
        }
    }

    /**
     * Download formats follow the settings.
     */
    public function test_download_formats(): void {
        $this->assertSame(['png', 'svg'], access::get_download_formats());
        set_config('formats', 'svg', 'local_resourceqr');
        $this->assertSame(['svg'], access::get_download_formats());
        set_config('formats', '', 'local_resourceqr');
        $this->assertSame([], access::get_download_formats());
    }
}
