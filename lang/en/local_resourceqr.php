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
 * English strings for Resource QR Code.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allmodules'] = 'All activity types';
$string['allmodules_desc'] = 'Allow QR codes for every activity and resource type that has a page, including types installed later. Untick to choose the types below.';
$string['colour'] = 'QR code colour';
$string['colour_desc'] = 'Colour of the QR code on a white background. It must be dark enough to scan reliably: a contrast ratio of at least 4.5:1 with white.';
$string['copylink'] = 'Copy link';
$string['destinationurl'] = 'Link';
$string['downloadpng'] = 'Download PNG';
$string['downloadsuccess'] = 'QR code downloaded as {$a}.';
$string['downloadsvg'] = 'Download SVG';
$string['enabled'] = 'Enable QR codes';
$string['enabled_desc'] = 'Allow authorised staff to generate QR codes for activities and resources.';
$string['enabledmodules'] = 'Enabled activity types';
$string['enabledmodules_desc'] = 'Activity and resource types for which QR codes can be generated. Types without their own page, such as Text and media area and subsections, are not listed.';
$string['error:cannotgenerate'] = 'You cannot generate a QR code for this activity.';
$string['error:colourcontrast'] = 'This colour is too light to scan reliably (contrast {$a->ratio}:1 with white; at least {$a->min}:1 is needed). Choose a darker colour.';
$string['error:colourformat'] = 'Enter a colour in the form #rrggbb, for example #0f47ad.';
$string['error:disabled'] = 'QR code generation is disabled on this site.';
$string['error:download'] = 'The QR code could not be downloaded. Please try again.';
$string['error:formatnotallowed'] = 'This download format is not allowed.';
$string['error:generation'] = 'The QR code could not be generated.';
$string['error:invalidformat'] = 'Unknown image format.';
$string['error:moduletypedisabled'] = 'QR codes are not enabled for this type of activity.';
$string['error:noformats'] = 'Select at least one download format.';
$string['error:nopng'] = 'PNG images cannot be created on this server because neither the GD nor the Imagick PHP extension is available.';
$string['error:preview'] = 'The QR code preview could not be loaded.';
$string['error:size'] = 'Enter a whole number of pixels from {$a->min} to {$a->max}.';
$string['errorcorrection'] = 'Error correction level';
$string['errorcorrection_desc'] = 'Higher levels make the code easier to read when it is damaged or partly covered, but make it denser. When the site logo is shown, level H is always used.';
$string['eventqrcodegenerated'] = 'QR code generated';
$string['explanation'] = 'Scan this QR code to open this Moodle resource or activity. You may need to log in to Moodle first.';
$string['formatpng'] = 'PNG';
$string['formatpngunavailable'] = '(not available: requires the GD or Imagick PHP extension)';
$string['formats'] = 'Allowed download formats';
$string['formats_desc'] = 'File formats that staff can download. SVG scales to any size without losing quality.';
$string['formatsvg'] = 'SVG';
$string['generateqrcode'] = 'Generate QR code';
$string['levelh'] = 'H - High (about 30% recovery)';
$string['levell'] = 'L - Low (about 7% recovery)';
$string['levelm'] = 'M - Medium (about 15% recovery)';
$string['levelq'] = 'Q - Quartile (about 25% recovery)';
$string['linkcopied'] = 'Link copied to clipboard.';
$string['nodownloadformats'] = 'No download format is available. Ask your administrator to check the Resource QR Code settings.';
$string['nopngnotice'] = 'Neither the GD nor the Imagick PHP extension is available, so QR codes cannot be downloaded as PNG. SVG downloads still work. Install or enable one of these extensions to offer PNG.';
$string['pluginname'] = 'Resource QR Code';
$string['print'] = 'Print';
$string['privacy:metadata'] = 'The Resource QR Code plugin does not store any personal data. Generating a QR code is recorded in the site logs.';
$string['qrcodealt'] = 'QR code linking to {$a}';
$string['qrcodefor'] = 'QR code for {$a}';
$string['qrcodetitle'] = 'QR code: {$a}';
$string['resourceqr:generate'] = 'Generate QR codes for activities and resources';
$string['showlogo'] = 'Show site logo in centre';
$string['showlogo_desc'] = 'Place the site\'s compact logo (or the full logo if there is no compact logo) from <a href="{$a}">Logos</a> in the centre of the QR code. Error correction level H is then used so the code still scans. If no logo is set, none is shown.';
$string['size'] = 'QR code size';
$string['size_desc'] = 'Width and height of the PNG image in pixels, from {$a->min} to {$a->max}. 600 or more is recommended for printing. SVG images scale to any size.';
$string['style'] = 'QR code design';
$string['style_desc'] = 'How the QR code looks. All designs scan with standard QR code readers. Very small codes (a few pixels per square) always use the square design so they stay readable.';
$string['styledots'] = 'Dots';
$string['stylerounded'] = 'Rounded';
$string['stylesquare'] = 'Square (classic)';
$string['warning:hidden'] = 'This activity is hidden from students. Some users may not be able to open it.';
$string['warning:restricted'] = 'This activity has access restrictions. Some users may not be able to open it.';
$string['warning:sectionhidden'] = 'This activity is in a hidden section. Some users may not be able to open it.';
$string['warning:stealth'] = 'This activity is available but not shown on the course page. Some users may not be able to open it.';
