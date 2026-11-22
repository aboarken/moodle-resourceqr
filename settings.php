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

/**
 * Admin settings for Resource QR Code.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_resourceqr', new lang_string('pluginname', 'local_resourceqr'));
    $ADMIN->add('localplugins', $settings);
    // Confirmation page for cancelling every QR code with an expiry date (linked from the settings below).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_resourceqr_resetlinks',
        new lang_string('resetlinks', 'local_resourceqr'),
        new moodle_url('/local/resourceqr/resetlinks.php'),
        'moodle/site:config',
        true
    ));

    if ($ADMIN->fulltree) {
        if (!\local_resourceqr\local\qr_generator::png_supported()) {
            $settings->add(new admin_setting_heading(
                'local_resourceqr/nopngnotice',
                '',
                $OUTPUT->notification(
                    get_string('nopngnotice', 'local_resourceqr'),
                    \core\output\notification::NOTIFY_WARNING,
                    false
                )
            ));
        }

        $settings->add(new admin_setting_configcheckbox(
            'local_resourceqr/enabled',
            new lang_string('enabled', 'local_resourceqr'),
            new lang_string('enabled_desc', 'local_resourceqr'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_resourceqr/allmodules',
            new lang_string('allmodules', 'local_resourceqr'),
            new lang_string('allmodules_desc', 'local_resourceqr'),
            1
        ));

        $settings->add(new \local_resourceqr\admin\setting_modules(
            'local_resourceqr/enabledmodules',
            new lang_string('enabledmodules', 'local_resourceqr'),
            new lang_string('enabledmodules_desc', 'local_resourceqr')
        ));
        $settings->hide_if('local_resourceqr/enabledmodules', 'local_resourceqr/allmodules', 'checked');

        $settings->add(new \local_resourceqr\admin\setting_size(
            'local_resourceqr/size',
            new lang_string('size', 'local_resourceqr'),
            new lang_string('size_desc', 'local_resourceqr', [
                'min' => \local_resourceqr\local\qr_generator::MIN_SIZE,
                'max' => \local_resourceqr\local\qr_generator::MAX_SIZE,
            ])
        ));

        $styles = [];
        foreach (\local_resourceqr\local\qr_generator::STYLES as $style) {
            $styles[$style] = new lang_string('style' . $style, 'local_resourceqr');
        }
        $settings->add(new admin_setting_configselect(
            'local_resourceqr/style',
            new lang_string('style', 'local_resourceqr'),
            new lang_string('style_desc', 'local_resourceqr'),
            \local_resourceqr\local\qr_generator::STYLE_ROUNDED,
            $styles
        ));

        $settings->add(new \local_resourceqr\admin\setting_colour(
            'local_resourceqr/colour',
            new lang_string('colour', 'local_resourceqr'),
            new lang_string('colour_desc', 'local_resourceqr')
        ));

        $levels = [];
        foreach (\local_resourceqr\local\qr_generator::LEVELS as $level) {
            $levels[$level] = new lang_string('level' . strtolower($level), 'local_resourceqr');
        }
        $settings->add(new admin_setting_configselect(
            'local_resourceqr/errorcorrection',
            new lang_string('errorcorrection', 'local_resourceqr'),
            new lang_string('errorcorrection_desc', 'local_resourceqr'),
            'M',
            $levels
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_resourceqr/showlogo',
            new lang_string('showlogo', 'local_resourceqr'),
            new lang_string(
                'showlogo_desc',
                'local_resourceqr',
                (new moodle_url('/admin/settings.php', ['section' => 'logos']))->out()
            ),
            0
        ));

        $settings->add(new \local_resourceqr\admin\setting_formats(
            'local_resourceqr/formats',
            new lang_string('formats', 'local_resourceqr'),
            new lang_string('formats_desc', 'local_resourceqr')
        ));

        $lastreset = \local_resourceqr\local\expiry::last_reset();
        $settings->add(new admin_setting_heading(
            'local_resourceqr/resetlinksheading',
            new lang_string('resetlinks', 'local_resourceqr'),
            html_writer::tag('p', get_string('resetlinks_desc', 'local_resourceqr')) .
            html_writer::tag('p', $lastreset
                ? get_string('resetlinks_last', 'local_resourceqr', userdate($lastreset))
                : get_string('resetlinks_never', 'local_resourceqr')) .
            // A link, not a button: this text is shown inside the settings form, and forms cannot be nested.
            html_writer::link(
                new moodle_url('/local/resourceqr/resetlinks.php'),
                get_string('resetlinks', 'local_resourceqr'),
                ['class' => 'btn btn-secondary']
            )
        ));
    }
}
