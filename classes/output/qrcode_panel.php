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

namespace local_resourceqr\output;

use cm_info;
use local_resourceqr\local\access;
use local_resourceqr\local\expiry;
use moodle_url;
use renderer_base;

/**
 * The QR code panel shown in the dialogue and on the stand-alone page.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qrcode_panel implements \renderable, \templatable {
    /** @var cm_info The course module. */
    protected cm_info $cm;

    /** @var int Expiry time of the QR code, or 0 for lifetime. */
    protected int $expires;

    /**
     * Constructor. Callers must have checked access with {@see access::require_generate()}
     * and validated the expiry time with {@see expiry::is_valid_expiry()}.
     *
     * @param cm_info $cm
     * @param int $expires Expiry time (Unix timestamp), or 0 for a lifetime QR code.
     */
    public function __construct(cm_info $cm, int $expires = 0) {
        $this->cm = $cm;
        $this->expires = $expires;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $cm = $this->cm;
        $name = $cm->get_formatted_name();
        $plainname = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $expires = $this->expires;
        $target = access::get_qr_url($cm, $expires);

        $downloads = [];
        foreach (access::get_download_formats() as $format) {
            $downloads[] = [
                'format' => $format,
                'label' => get_string('download' . $format, 'local_resourceqr'),
                'url' => self::image_url($cm, $format, true, $expires)->out(false),
            ];
        }

        $warnings = array_map(fn(string $message) => ['message' => $message], access::get_warnings($cm));

        return [
            'cmid' => (int) $cm->id,
            'name' => $name,
            'plainname' => $plainname,
            'url' => $target->out(false),
            'previewurl' => self::image_url($cm, access::FORMAT_SVG, false, $expires)->out(false),
            'alt' => get_string('qrcodealt', 'local_resourceqr', $plainname),
            'warnings' => $warnings,
            'haswarnings' => !empty($warnings),
            'downloads' => $downloads,
            'hasdownloads' => !empty($downloads),
            'printurl' => (new moodle_url('/local/resourceqr/print.php', array_filter([
                'cmid' => $cm->id,
                'expires' => $expires,
                'autoprint' => 1,
            ])))->out(false),
            // Validity: lifetime, or valid until the end of a day.
            'lifetime' => $expires === 0,
            'expires' => $expires,
            'expirydate' => $expires ? expiry::timestamp_to_date($expires) : '',
            'expirytext' => $expires ? get_string('validuntil', 'local_resourceqr', expiry::format($expires)) : '',
            'defaultexpirydate' => expiry::timestamp_to_date(time() + expiry::DEFAULT_DAYS * DAYSECS),
            'minexpirydate' => expiry::timestamp_to_date(time()),
            'maxexpirydate' => expiry::timestamp_to_date(time() + expiry::MAX_YEARS * YEARSECS),
            'viewurl' => (new moodle_url('/local/resourceqr/view.php', ['cmid' => $cm->id]))->out(false),
        ];
    }

    /**
     * URL of the image endpoint.
     *
     * @param cm_info $cm
     * @param string $format 'png' or 'svg'.
     * @param bool $download
     * @param int $expires Expiry time, or 0 for lifetime.
     * @return moodle_url
     */
    public static function image_url(cm_info $cm, string $format, bool $download, int $expires = 0): moodle_url {
        $params = [
            'cmid' => $cm->id,
            'format' => $format,
            'download' => (int) $download,
        ];
        if ($expires) {
            $params['expires'] = $expires;
        }
        return new moodle_url('/local/resourceqr/qr.php', $params);
    }
}
