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
 * Cancel every QR code with an expiry date (site administrators only).
 *
 * Shows a confirmation; on confirmation (POST with sesskey) the signing key is replaced.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_resourceqr\local\expiry;

$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Requires login and moodle/site:config.
admin_externalpage_setup('local_resourceqr_resetlinks');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_resourceqr']);

if ($confirm && confirm_sesskey()) {
    expiry::reset_secret();
    redirect($settingsurl, get_string('resetlinks_done', 'local_resourceqr'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$title = get_string('resetlinks', 'local_resourceqr');
$PAGE->set_title($title);
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->confirm(
    get_string('resetlinks_confirm', 'local_resourceqr'),
    new single_button(
        new moodle_url('/local/resourceqr/resetlinks.php', ['confirm' => 1, 'sesskey' => sesskey()]),
        $title,
        'post',
        single_button::BUTTON_DANGER
    ),
    new single_button($settingsurl, get_string('cancel'), 'get')
);
echo $OUTPUT->footer();
