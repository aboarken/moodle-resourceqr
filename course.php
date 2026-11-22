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

/**
 * Course "QR codes" page: lists the course's activities and resources with a QR code button for each.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_resourceqr\local\access;

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
if (!access::is_enabled()) {
    throw new moodle_exception('error:disabled', 'local_resourceqr');
}
require_capability(access::CAPABILITY, $context);

$url = new moodle_url('/local/resourceqr/course.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$title = get_string('courseqrcodes', 'local_resourceqr');
$PAGE->set_title($title . moodle_page::TITLE_SEPARATOR . format_string($course->shortname, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
// The list links to the stand-alone QR page; this opens the dialogue instead when JavaScript is available.
$PAGE->requires->js_call_amd('local_resourceqr/qrcode_dialogue', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template(
    'local_resourceqr/course_list',
    (new \local_resourceqr\output\course_list($course))->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
