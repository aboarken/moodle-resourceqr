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

namespace local_resourceqr\event;

/**
 * Event triggered when a QR code image is generated for a course module.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - string format: 'png' or 'svg'.
 *     - bool download: true if the image was downloaded, false for a preview.
 *     - int expires: expiry time of the QR code, or 0 for a lifetime QR code (optional).
 * }
 */
class qrcode_generated extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'course_modules';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventqrcodegenerated', 'local_resourceqr');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description() {
        $action = !empty($this->other['download']) ? 'downloaded' : 'previewed';
        $validity = !empty($this->other['expires'])
            ? ' valid until ' . gmdate('Y-m-d H:i', (int) $this->other['expires']) . ' UTC'
            : '';
        return "The user with id '$this->userid' {$action} a QR code{$validity} in " . strtoupper($this->other['format']) .
            " format for the course module with id '$this->contextinstanceid'.";
    }

    /**
     * Related URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/resourceqr/print.php', ['cmid' => $this->contextinstanceid]);
    }

    /**
     * Validate the event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
        if (!isset($this->other['format']) || !in_array($this->other['format'], ['png', 'svg'], true)) {
            throw new \coding_exception('The \'format\' value must be set in other to png or svg.');
        }
        if (!isset($this->other['download'])) {
            throw new \coding_exception('The \'download\' value must be set in other.');
        }
    }

    /**
     * Mapping for backup and restore of logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'course_modules', 'restore' => 'course_module'];
    }

    /**
     * Mapping of the other fields for restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
