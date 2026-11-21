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

namespace local_resourceqr\local;

use cm_info;

/**
 * Builds safe download file names from activity names.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filename {
    /**
     * File name for a course module's QR code, e.g. "qrcode-week_1_quiz.png".
     *
     * @param cm_info $cm
     * @param string $format 'png' or 'svg'.
     * @return string
     */
    public static function for_cm(cm_info $cm, string $format): string {
        $name = html_entity_decode(strip_tags($cm->get_formatted_name()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return self::from_name($name, $format);
    }

    /**
     * File name from a plain-text name.
     *
     * @param string $name
     * @param string $format 'png' or 'svg'.
     * @return string
     */
    public static function from_name(string $name, string $format): string {
        $name = clean_param($name, PARAM_FILE);
        // Keep letters (any script), digits, dot, dash and underscore; collapse the rest.
        $name = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name);
        $name = trim(preg_replace('/_+/', '_', $name), '._-');
        $name = \core_text::substr($name, 0, 80);
        if ($name === '') {
            return 'qrcode.' . $format;
        }
        return 'qrcode-' . \core_text::strtolower($name) . '.' . $format;
    }
}
