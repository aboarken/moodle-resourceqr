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

use local_resourceqr\local\qr_generator;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Colour picker for the QR code colour, refusing colours too light to scan reliably.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_colour extends \admin_setting_configcolourpicker {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, qr_generator::DEFAULT_COLOUR);
    }

    /**
     * Check a value: a #rrggbb colour with enough contrast against white.
     *
     * @param string $data
     * @return true|string True if valid, otherwise an error message.
     */
    public function check($data) {
        $data = trim((string) $data, " \t\n\r\0\x0B");
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data)) {
            return get_string('error:colourformat', 'local_resourceqr');
        }
        if (!qr_generator::is_valid_colour($data)) {
            return get_string('error:colourcontrast', 'local_resourceqr', [
                'ratio' => format_float(qr_generator::contrast_with_white($data), 1),
                'min' => format_float(qr_generator::MIN_CONTRAST, 1),
            ]);
        }
        return true;
    }

    /**
     * Save the setting after checking it.
     *
     * @param string $data
     * @return string Empty string on success, error otherwise.
     */
    public function write_setting($data) {
        $result = $this->check($data);
        if ($result !== true) {
            return $result;
        }
        $colour = strtolower(trim((string) $data, " \t\n\r\0\x0B"));
        return $this->config_write($this->name, $colour) ? '' : get_string('errorsetting', 'admin');
    }
}
