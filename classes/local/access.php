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
use moodle_url;

/**
 * Access rules and configuration helpers for Resource QR Code.
 *
 * Every entry point (navigation, the image endpoint, the print page and the
 * external function) goes through {@see self::can_generate()} or
 * {@see self::require_generate()}, so the rules live in one place.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /** @var string The capability that allows generating QR codes. */
    public const CAPABILITY = 'local/resourceqr:generate';

    /** @var string PNG format identifier. */
    public const FORMAT_PNG = 'png';

    /** @var string SVG format identifier. */
    public const FORMAT_SVG = 'svg';

    /** @var string[] URL parameters that are never encoded in a QR code. */
    private const DISALLOWED_PARAMS = ['sesskey', 'token', 'wstoken', 'userid', 'user', 'username', 'key', 'session'];

    /**
     * Is the plugin enabled site-wide?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_resourceqr', 'enabled');
    }

    /**
     * Does a module type have a page that a QR code can point to?
     *
     * Excludes modules that declare FEATURE_NO_VIEW_LINK (Text and media area),
     * modules that cannot be displayed on the course page (for example
     * mod_qbank), and modules that delegate a course section (mod_subsection).
     *
     * @param string $modname Module name without the mod_ prefix, e.g. 'quiz'.
     * @return bool
     */
    public static function module_has_view_url(string $modname): bool {
        if (plugin_supports('mod', $modname, FEATURE_NO_VIEW_LINK, false)) {
            return false;
        }
        // FEATURE_CAN_DISPLAY exists from Moodle 5.0.
        if (defined('FEATURE_CAN_DISPLAY') && !plugin_supports('mod', $modname, FEATURE_CAN_DISPLAY, true)) {
            return false;
        }
        if (
            class_exists(\core_courseformat\sectiondelegate::class)
                && \core_courseformat\sectiondelegate::has_delegate_class('mod_' . $modname)
        ) {
            return false;
        }
        return true;
    }

    /**
     * Installed module types that can have a QR code, keyed by module name.
     *
     * @return array<string, string> Module name => localised module name, sorted by localised name.
     */
    public static function get_supported_module_types(): array {
        global $DB;

        $installed = $DB->get_fieldset_select('modules', 'name', '1 = 1');
        $present = \core_component::get_plugin_list('mod');
        $result = [];
        foreach ($installed as $modname) {
            if (!isset($present[$modname]) || !self::module_has_view_url($modname)) {
                continue;
            }
            $result[$modname] = get_string('modulename', 'mod_' . $modname);
        }
        \core_collator::asort($result);
        return $result;
    }

    /**
     * Is QR code generation enabled for this module type by the administrator?
     *
     * @param string $modname Module name, e.g. 'quiz'.
     * @return bool
     */
    public static function is_module_type_enabled(string $modname): bool {
        if (!self::module_has_view_url($modname)) {
            return false;
        }
        if (get_config('local_resourceqr', 'allmodules')) {
            return true;
        }
        $enabled = (string) get_config('local_resourceqr', 'enabledmodules');
        if ($enabled === '') {
            return false;
        }
        return in_array($modname, explode(',', $enabled), true);
    }

    /**
     * Can the current user generate a QR code for this course module?
     *
     * This does not call require_login(); callers must have done so (or used
     * external_api::validate_context()) for the module's course.
     *
     * @param cm_info $cm Course module, obtained from get_fast_modinfo() for the current user.
     * @return bool
     */
    public static function can_generate(cm_info $cm): bool {
        if (!self::is_enabled() || !self::is_module_type_enabled($cm->modname)) {
            return false;
        }
        if ($cm->deletioninprogress) {
            return false;
        }
        $context = context_module::instance($cm->id);
        if (!has_capability(self::CAPABILITY, $context)) {
            return false;
        }
        if (!$cm->uservisible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
            return false;
        }
        return self::get_target_url($cm) !== null;
    }

    /**
     * Throw an exception unless the current user can generate a QR code for this module.
     *
     * @param cm_info $cm
     * @throws moodle_exception
     */
    public static function require_generate(cm_info $cm): void {
        if (!self::is_enabled()) {
            throw new moodle_exception('error:disabled', 'local_resourceqr');
        }
        if (!self::is_module_type_enabled($cm->modname)) {
            throw new moodle_exception('error:moduletypedisabled', 'local_resourceqr');
        }
        require_capability(self::CAPABILITY, context_module::instance($cm->id));
        if (!self::can_generate($cm)) {
            throw new moodle_exception('error:cannotgenerate', 'local_resourceqr');
        }
    }

    /**
     * The URL the QR code encodes: the module's normal view URL, absolute, from $CFG->wwwroot.
     *
     * Contains only the course module id. No user, session or token data is ever added.
     *
     * @param cm_info $cm
     * @return moodle_url|null Null if the module has no view URL.
     */
    public static function get_target_url(cm_info $cm): ?moodle_url {
        $url = $cm->get_url();
        if (empty($url)) {
            return null;
        }
        // Rebuild the URL without any parameter that could carry session or
        // user-specific data, in case a module adds one to its view URL.
        $clean = new moodle_url($url->out_omit_querystring());
        foreach ($url->params() as $name => $value) {
            if (!in_array(strtolower($name), self::DISALLOWED_PARAMS, true)) {
                $clean->param($name, $value);
            }
        }
        return $clean;
    }

    /**
     * Warnings to show when not every user will be able to open the activity.
     *
     * @param cm_info $cm
     * @return string[] Localised warning messages.
     */
    public static function get_warnings(cm_info $cm): array {
        $warnings = [];
        $section = $cm->get_section_info();
        if (!$cm->visible) {
            $warnings[] = get_string('warning:hidden', 'local_resourceqr');
        } else if ($section && !$section->visible) {
            // Checked before is_stealth(), which is also true for visible activities in hidden sections.
            $warnings[] = get_string('warning:sectionhidden', 'local_resourceqr');
        } else if ($cm->is_stealth()) {
            $warnings[] = get_string('warning:stealth', 'local_resourceqr');
        }
        $hasrestrictions = !empty($cm->availability) || ($section && !empty($section->availability));
        if ($hasrestrictions && !empty($GLOBALS['CFG']->enableavailability)) {
            $warnings[] = get_string('warning:restricted', 'local_resourceqr');
        }
        return $warnings;
    }

    /**
     * Download formats allowed by the administrator and supported by the server.
     *
     * @return string[] Subset of ['png', 'svg'].
     */
    public static function get_download_formats(): array {
        $allowed = (string) get_config('local_resourceqr', 'formats');
        $allowed = $allowed === '' ? [] : explode(',', $allowed);
        $formats = [];
        if (in_array(self::FORMAT_PNG, $allowed, true) && qr_generator::png_supported()) {
            $formats[] = self::FORMAT_PNG;
        }
        if (in_array(self::FORMAT_SVG, $allowed, true)) {
            $formats[] = self::FORMAT_SVG;
        }
        return $formats;
    }
}
