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
 * Link held by a QR code with an expiry date.
 *
 * Before the expiry time it redirects to the activity's normal link, where Moodle's usual
 * login, enrolment and access checks apply. Afterwards it says the QR code has expired.
 * No login is needed to reach this page, so it reveals nothing about the activity.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No login here: anyone who scans the code must reach this page. Access is checked by the
// activity the valid link redirects to.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');

use local_resourceqr\local\expiry;

$cmid = required_param('id', PARAM_INT);
$expires = required_param('exp', PARAM_INT);
$signature = optional_param('sig', '', PARAM_ALPHANUM);

[$status, $target] = expiry::resolve($cmid, $expires, $signature);
if ($status === expiry::VALID) {
    redirect($target);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/resourceqr/go.php', ['id' => $cmid, 'exp' => $expires, 'sig' => $signature]));
$PAGE->set_pagelayout('login');
$heading = get_string($status === expiry::EXPIRED ? 'expiredtitle' : 'invalidtitle', 'local_resourceqr');
$PAGE->set_title($heading);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
if ($status === expiry::EXPIRED) {
    $message = get_string('expiredmessage', 'local_resourceqr', expiry::format($expires));
} else {
    $message = get_string('invalidmessage', 'local_resourceqr');
}
echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING, false);
echo $OUTPUT->continue_button(new moodle_url('/'));
echo $OUTPUT->footer();
