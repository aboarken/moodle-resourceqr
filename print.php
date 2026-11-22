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
 * Print view of a course module's QR code: only the name, the code and the URL.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_resourceqr\local\access;

$cmid = required_param('cmid', PARAM_INT);
$autoprint = optional_param('autoprint', 0, PARAM_BOOL);
$expires = optional_param('expires', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
access::require_generate($cm);
if (!\local_resourceqr\local\expiry::is_valid_expiry($expires)) {
    throw new moodle_exception('error:expirydate', 'local_resourceqr');
}

$PAGE->set_url(new moodle_url('/local/resourceqr/print.php', array_filter(['cmid' => $cm->id, 'expires' => $expires])));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('qrcodetitle', 'local_resourceqr', $cm->get_formatted_name()));
$PAGE->add_body_class('local-resourceqr-printpage');
$PAGE->activityheader->disable();
$PAGE->requires->js_call_amd('local_resourceqr/print', 'init', [(bool) $autoprint]);

$data = (new \local_resourceqr\output\qrcode_panel($cm, $expires))->export_for_template($OUTPUT);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_resourceqr/print', $data);
echo $OUTPUT->footer();
