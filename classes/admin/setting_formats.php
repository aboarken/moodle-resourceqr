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

namespace local_resourceqr\admin;

use local_resourceqr\local\access;
use local_resourceqr\local\qr_generator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting for the allowed download formats. At least one must be chosen.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_formats extends \admin_setting_configmulticheckbox {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        $png = get_string('formatpng', 'local_resourceqr');
        if (!qr_generator::png_supported()) {
            $png .= ' ' . get_string('formatpngunavailable', 'local_resourceqr');
        }
        $choices = [
            access::FORMAT_PNG => $png,
            access::FORMAT_SVG => get_string('formatsvg', 'local_resourceqr'),
        ];
        $default = [access::FORMAT_PNG => 1, access::FORMAT_SVG => 1];
        parent::__construct($name, $visiblename, $description, $default, $choices);
    }

    /**
     * Save the setting, requiring at least one format.
     *
     * @param array $data
     * @return string Empty string on success, error otherwise.
     */
    public function write_setting($data) {
        $selected = is_array($data) ? array_filter($data) : [];
        $selected = array_intersect(array_keys($selected), [access::FORMAT_PNG, access::FORMAT_SVG]);
        if (empty($selected)) {
            return get_string('error:noformats', 'local_resourceqr');
        }
        return parent::write_setting($data);
    }
}
