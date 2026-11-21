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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting to choose the activity types that can have QR codes.
 *
 * Lists installed modules that have a view page; modules without one (such as
 * Text and media area and subsections) are not offered.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_modules extends \admin_setting_configmultiselect {
    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        // Choices and default are loaded lazily, see load_choices() and get_defaultsetting().
        parent::__construct($name, $visiblename, $description, null, null);
    }

    /**
     * Load the module choices.
     *
     * @return bool
     */
    public function load_choices() {
        if (is_array($this->choices)) {
            return true;
        }
        $this->choices = access::get_supported_module_types();
        return true;
    }

    /**
     * Default: every supported module.
     *
     * @return array
     */
    public function get_defaultsetting() {
        $this->load_choices();
        return array_keys($this->choices);
    }

    /**
     * Only accept installed modules that have a view page.
     *
     * @param array $data
     * @return string Empty string on success, error otherwise.
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return '';
        }
        $this->load_choices();
        $data = array_values(array_filter($data, fn($name) => isset($this->choices[$name])));
        return parent::write_setting($data);
    }
}
