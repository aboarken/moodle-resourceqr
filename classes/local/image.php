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
use context_module;
use moodle_exception;

/**
 * Produces the QR code image for a course module and logs it.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image {
    /** @var string Image data. */
    public readonly string $content;

    /** @var string MIME type. */
    public readonly string $mimetype;

    /** @var string Download file name. */
    public readonly string $filename;

    /**
     * Constructor.
     *
     * @param string $content
     * @param string $mimetype
     * @param string $filename
     */
    private function __construct(string $content, string $mimetype, string $filename) {
        $this->content = $content;
        $this->mimetype = $mimetype;
        $this->filename = $filename;
    }

    /**
     * Generate the image after checking access, and trigger the qrcode_generated event.
     *
     * The caller must already have called require_login() for the module.
     *
     * @param cm_info $cm Course module for the current user.
     * @param string $format 'png' or 'svg'.
     * @param bool $download True for a download, false for the preview.
     * @return self
     * @throws moodle_exception
     */
    public static function create(cm_info $cm, string $format, bool $download): self {
        access::require_generate($cm);

        if (!in_array($format, [access::FORMAT_PNG, access::FORMAT_SVG], true)) {
            throw new moodle_exception('error:invalidformat', 'local_resourceqr');
        }
        // Downloads are limited to the formats the administrator allows. The SVG
        // preview is always available because it needs no PHP extension.
        if ($download && !in_array($format, access::get_download_formats(), true)) {
            throw new moodle_exception('error:formatnotallowed', 'local_resourceqr');
        }
        if ($format === access::FORMAT_PNG && !qr_generator::png_supported()) {
            throw new moodle_exception('error:nopng', 'local_resourceqr');
        }

        $generator = qr_generator::from_config(access::get_target_url($cm)->out(false));
        if ($format === access::FORMAT_PNG) {
            $image = new self($generator->png(), 'image/png', filename::for_cm($cm, $format));
        } else {
            $image = new self($generator->svg(), 'image/svg+xml', filename::for_cm($cm, $format));
        }

        \local_resourceqr\event\qrcode_generated::create([
            'context' => context_module::instance($cm->id),
            'objectid' => $cm->id,
            'other' => ['format' => $format, 'download' => $download],
        ])->trigger();

        return $image;
    }

    /**
     * Send the image with headers that prevent caching and content sniffing.
     *
     * @param bool $download Send as an attachment.
     */
    public function send(bool $download): void {
        // Anything already printed (for example a PHP notice shown because debugging is on)
        // would corrupt the image. Discard it, but keep a record for developers.
        $stray = '';
        while (ob_get_level() > 0) {
            $stray .= (string) ob_get_clean();
        }
        if (trim($stray, " \t\n\r\0\x0B") !== '') {
            // The image endpoint defines NO_DEBUG_DISPLAY, so this goes to the server log.
            debugging('local_resourceqr: discarded unexpected output before sending a QR code image: ' .
                \core_text::substr(strip_tags($stray), 0, 1000), DEBUG_DEVELOPER);
        }
        \core\session\manager::write_close();
        $asciifilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $this->filename);
        header('Content-Type: ' . $this->mimetype);
        header('Content-Length: ' . strlen($this->content));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $asciifilename . '"; ' .
            "filename*=UTF-8''" . rawurlencode($this->filename));
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        if ($this->mimetype === 'image/svg+xml') {
            // An SVG is a document: make sure it can never run script or load anything external.
            header("Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox");
        }
        echo $this->content;
    }
}
