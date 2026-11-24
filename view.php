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
 * Stand-alone QR code page for a course module.
 *
 * This is where the "Generate QR code" navigation item points. With JavaScript
 * the item opens a dialogue instead; this page is the no-JavaScript fallback
 * and a direct link that can be bookmarked.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_resourceqr\local\access;

$cmid = required_param('cmid', PARAM_INT);
// Validity chosen with the form on this page when JavaScript is not available.
$validity = optional_param('validity', 'lifetime', PARAM_ALPHA);
$expirydate = optional_param('expirydate', '', PARAM_ALPHANUMEXT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
access::require_generate($cm);
$expires = $validity === 'until' ? \local_resourceqr\local\expiry::date_to_timestamp($expirydate) : 0;

$url = new moodle_url('/local/resourceqr/view.php', ['cmid' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$title = get_string('qrcodetitle', 'local_resourceqr', $cm->get_formatted_name());
$PAGE->set_title(get_string('generateqrcode', 'local_resourceqr') . moodle_page::TITLE_SEPARATOR . $cm->get_formatted_name());
$PAGE->set_heading(format_string($course->fullname, true, ['context' => context_course::instance($course->id)]));
$PAGE->activityheader->disable();
$PAGE->navbar->add(get_string('generateqrcode', 'local_resourceqr'), $url);
$PAGE->requires->js_call_amd('local_resourceqr/qrcode_panel', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template(
    'local_resourceqr/qrcode_panel',
    (new \local_resourceqr\output\qrcode_panel($cm, $expires))->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
