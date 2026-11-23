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
 * Version information for Resource QR Code.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_resourceqr';
$plugin->version   = 2026112500;
$plugin->release   = '0.2.2';
$plugin->maturity  = MATURITY_STABLE;
// Moodle 4.5 LTS (4.5.0 = 2024100700) is the lowest supported version.
$plugin->requires  = 2024100700;
// Supported branches: 4.5 LTS to 5.3 LTS.
$plugin->supported = [405, 503];
