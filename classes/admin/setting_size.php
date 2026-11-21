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
 * Admin setting for the QR code size in pixels, limited to a range.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_size extends \admin_setting_configtext {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, (string) qr_generator::DEFAULT_SIZE, PARAM_INT, 6);
    }

    /**
     * Validate the value: a whole number within the allowed range.
     *
     * @param string $data
     * @return true|string True if valid, otherwise an error message.
     */
    public function validate($data) {
        $data = trim((string) $data, " \t\n\r\0\x0B");
        if (!preg_match('/^\d+$/', $data)) {
            return get_string('error:size', 'local_resourceqr', ['min' => qr_generator::MIN_SIZE, 'max' => qr_generator::MAX_SIZE]);
        }
        $value = (int) $data;
        if ($value < qr_generator::MIN_SIZE || $value > qr_generator::MAX_SIZE) {
            return get_string('error:size', 'local_resourceqr', ['min' => qr_generator::MIN_SIZE, 'max' => qr_generator::MAX_SIZE]);
        }
        return true;
    }
}
