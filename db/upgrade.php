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
 * Upgrade steps for Resource QR Code.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The version installed before this upgrade.
 * @return bool
 */
function xmldb_local_resourceqr_upgrade($oldversion) {
    if ($oldversion < 2026112500) {
        // Sites upgraded from 0.2.0 or 0.2.1 that never signed a link have no signing key yet.
        // Create it now, so checking a link never has to. An existing key is kept, so QR codes
        // already given out keep working.
        \local_resourceqr\local\expiry::ensure_secret();
        upgrade_plugin_savepoint(true, 2026112500, 'local', 'resourceqr');
    }
    return true;
}
