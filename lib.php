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
 * Callbacks for Resource QR Code.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Generate QR code" to the activity's settings navigation.
 *
 * In Boost-based themes the node appears in the activity's "More" menu (secondary
 * navigation). In Classic it appears in the Administration block. The course page
 * activity action menu offers no extension point for local plugins, so this is the
 * only place the action is added.
 *
 * @param settings_navigation $settingsnav The settings navigation.
 * @param context $context The current context.
 */
function local_resourceqr_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $PAGE;

    if ($context->contextlevel != CONTEXT_MODULE || during_initial_install()) {
        return;
    }
    $page = $settingsnav->get_page();
    // Do not use empty() here: moodle_page properties are magic and empty() always reports them as unset.
    if (!$page->cm || !\local_resourceqr\local\access::is_enabled()) {
        return;
    }
    try {
        $cm = get_fast_modinfo($page->course)->get_cm($page->cm->id);
    } catch (moodle_exception $e) {
        return;
    }
    if (!\local_resourceqr\local\access::can_generate($cm)) {
        return;
    }

    $modulenode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulenode) {
        // Core removes the module node when it has no children, which happens for
        // staff who can only generate QR codes. Recreate it so the item still shows.
        $modulenode = $settingsnav->add(
            get_string('pluginadministration', $cm->modname),
            null,
            navigation_node::TYPE_SETTING,
            null,
            'modulesettings'
        );
        $modulenode->nodetype = navigation_node::NODETYPE_BRANCH;
        $modulenode->force_open();
    }

    $url = new moodle_url('/local/resourceqr/view.php', ['cmid' => $cm->id]);
    $node = $modulenode->add(
        get_string('generateqrcode', 'local_resourceqr'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_resourceqr_generate',
        new pix_icon('qrcode', '', 'local_resourceqr')
    );
    $node->add_attribute('data-local-resourceqr-cmid', (string) $cm->id);

    // Enhance the link so it opens the dialogue instead of the stand-alone page.
    if ($page === $PAGE) {
        $PAGE->requires->js_call_amd('local_resourceqr/qrcode_dialogue', 'init');
    }
}

/**
 * Add "QR codes" to the course's navigation.
 *
 * In Boost-based themes it appears in the course's "More" menu; in Classic, in the
 * Course administration block. It opens a page listing the course's activities and
 * resources, each with a button to generate its QR code.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context $context The course context.
 */
function local_resourceqr_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if (
        !\local_resourceqr\local\access::is_enabled()
            || !has_capability(\local_resourceqr\local\access::CAPABILITY, $context)
    ) {
        return;
    }
    $navigation->add(
        get_string('courseqrcodes', 'local_resourceqr'),
        new moodle_url('/local/resourceqr/course.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_resourceqr_course',
        new pix_icon('qrcode', '', 'local_resourceqr')
    );
}

/**
 * Font Awesome icons used by this plugin.
 *
 * Mapping the menu icon to a font icon makes it use the theme's icon colours,
 * like Moodle's own menu items. Themes that do not use Font Awesome fall back
 * to pix/qrcode.svg.
 *
 * @return array<string, string> Moodle icon name => Font Awesome icon.
 */
function local_resourceqr_get_fontawesome_icon_map(): array {
    return [
        'local_resourceqr:qrcode' => 'fa-qrcode',
    ];
}
