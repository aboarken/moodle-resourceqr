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

#[\PHPUnit\Framework\Attributes\CoversFunction('local_resourceqr_extend_settings_navigation')]
/**
 * Tests for the settings navigation callback.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_resourceqr_extend_settings_navigation
 */
final class navigation_test extends \advanced_testcase {
    /**
     * Build the settings navigation for a module page as the current user.
     *
     * @param \stdClass $course
     * @param int $cmid
     * @return \settings_navigation
     */
    private function settingsnav(\stdClass $course, int $cmid): \settings_navigation {
        global $PAGE;
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        $PAGE->set_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_context(\context_module::instance($cmid));
        $nav = new \settings_navigation($PAGE);
        $nav->initialise();
        return $nav;
    }

    /**
     * Scenarios.
     *
     * @return array
     */
    public static function scenario_provider(): array {
        return [
            'Editing teacher sees the item' => ['editingteacher', true],
            'Student does not' => ['student', false],
            'Non-editing teacher does not by default' => ['teacher', false],
        ];
    }

    /**
     * The item appears only for authorised users.
     *
     * @dataProvider scenario_provider
     * @param string $role
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenario_provider')]
    public function test_item(string $role, bool $expected): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, $role));
        $node = $this->settingsnav($course, $quiz->cmid)->find('local_resourceqr_generate', \navigation_node::TYPE_SETTING);
        $this->assertSame($expected, (bool) $node);
        if ($expected) {
            $this->assertStringContainsString('/local/resourceqr/view.php?cmid=' . $quiz->cmid, $node->action->out(false));
        }
    }

    /**
     * Staff with only the generate capability still get the item (the module node is recreated).
     */
    public function test_item_for_generate_only_role(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $role = $this->getDataGenerator()->create_role(['shortname' => 'qrstaff']);
        $context = \context_course::instance($course->id);
        assign_capability('local/resourceqr:generate', CAP_ALLOW, $role, $context->id);
        assign_capability('moodle/course:view', CAP_ALLOW, $role, $context->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, $context->id);
        $this->setUser($user);
        $nav = $this->settingsnav($course, $page->cmid);
        $this->assertNotEmpty($nav->find('local_resourceqr_generate', \navigation_node::TYPE_SETTING));
        $this->assertNotEmpty($nav->find('modulesettings', \navigation_node::TYPE_SETTING));
    }

    /**
     * The menu icon is mapped to a Font Awesome icon, so it follows the theme's icon colour.
     */
    public function test_icon_is_font_icon(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/resourceqr/lib.php');
        $this->resetAfterTest();
        $map = local_resourceqr_get_fontawesome_icon_map();
        $this->assertSame('fa-qrcode', $map['local_resourceqr:qrcode']);

        $system = \core\output\icon_system::instance(\core\output\icon_system::FONTAWESOME);
        $this->assertStringContainsString('fa-qrcode', $system->remap_icon_name('qrcode', 'local_resourceqr'));
    }

    /**
     * No item for a disabled plugin.
     */
    public function test_disabled(): void {
        $this->resetAfterTest();
        set_config('enabled', 0, 'local_resourceqr');
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $nav = $this->settingsnav($course, $quiz->cmid);
        $this->assertFalse($nav->find('local_resourceqr_generate', \navigation_node::TYPE_SETTING));
    }
}
