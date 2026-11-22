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
 * Serves a QR code image (PNG or SVG) for a course module.
 *
 * Read-only: the image is generated on demand and never stored, so no sesskey is needed.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The response is an image; never print debugging output into it.
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');

// Buffer everything from here on, so that no stray output can end up inside the image.
ob_start();

$cmid = required_param('cmid', PARAM_INT);
$format = optional_param('format', 'svg', PARAM_ALPHA);
$download = optional_param('download', 0, PARAM_BOOL);
$expires = optional_param('expires', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, false, $cm);
$PAGE->set_url(new moodle_url('/local/resourceqr/qr.php', [
    'cmid' => $cm->id,
    'format' => $format,
    'download' => (int) $download,
    'expires' => $expires,
]));

$image = \local_resourceqr\local\image::create($cm, $format, (bool) $download, $expires);
$image->send((bool) $download);
