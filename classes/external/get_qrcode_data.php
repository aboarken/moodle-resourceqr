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

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_resourceqr\local\access;
use local_resourceqr\output\qrcode_panel;

/**
 * Returns the data for the QR code dialogue of a course module.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_qrcode_data extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Get the dialogue data.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $PAGE;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cm->id);
        // Runs require_login() for the course and module.
        self::validate_context($context);
        access::require_generate($cm);

        $panel = new qrcode_panel($cm);
        return $panel->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_RAW, 'Activity name, formatted with format_string()'),
            'plainname' => new external_value(PARAM_TEXT, 'Activity name as plain text'),
            'url' => new external_value(PARAM_URL, 'URL encoded in the QR code'),
            'previewurl' => new external_value(PARAM_URL, 'URL of the SVG preview image'),
            'alt' => new external_value(PARAM_TEXT, 'Alternative text for the QR code image'),
            'warnings' => new external_multiple_structure(new external_single_structure([
                'message' => new external_value(PARAM_TEXT, 'Warning message'),
            ])),
            'haswarnings' => new external_value(PARAM_BOOL, 'Whether there are warnings'),
            'downloads' => new external_multiple_structure(new external_single_structure([
                'format' => new external_value(PARAM_ALPHA, 'png or svg'),
                'label' => new external_value(PARAM_TEXT, 'Button label'),
                'url' => new external_value(PARAM_URL, 'Download URL'),
            ])),
            'hasdownloads' => new external_value(PARAM_BOOL, 'Whether any download format is available'),
            'printurl' => new external_value(PARAM_URL, 'URL of the print page'),
        ]);
    }
}
