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

namespace local_resourceqr\output;

use local_resourceqr\local\access;
use moodle_url;
use renderer_base;

/**
 * The course "QR codes" page: every activity and resource the user can make a QR code for.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_list implements \renderable, \templatable {
    /** @var \stdClass The course. */
    protected \stdClass $course;

    /**
     * Constructor.
     *
     * @param \stdClass $course
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $modinfo = get_fast_modinfo($this->course);
        $sections = [];
        $count = 0;
        foreach ($modinfo->get_section_info_all() as $section) {
            $items = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!access::can_generate($cm)) {
                    continue;
                }
                $warnings = array_map(fn(string $message) => ['message' => $message], access::get_warnings($cm));
                $name = $cm->get_formatted_name();
                $items[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $name,
                    'plainname' => html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'modname' => (string) $cm->get_module_type_name(),
                    'iconurl' => $cm->get_icon_url()->out(false),
                    'url' => (new moodle_url('/local/resourceqr/view.php', ['cmid' => $cm->id]))->out(false),
                    'warnings' => $warnings,
                    'haswarnings' => !empty($warnings),
                ];
            }
            if ($items) {
                $count += count($items);
                $sections[] = [
                    'name' => get_section_name($this->course, $section),
                    'items' => $items,
                ];
            }
        }
        return [
            'sections' => $sections,
            'hasitems' => $count > 0,
        ];
    }
}
