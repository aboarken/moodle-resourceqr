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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use local_resourceqr\local\expiry;

/**
 * Behat steps for Resource QR Code: open the links that QR codes with an expiry date hold.
 *
 * A browser cannot read a QR code, and the signed link depends on the site's signing key,
 * so these steps build the link on the server, exactly as the plugin does, and visit it.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_resourceqr extends behat_base {
    /**
     * Open the link of a QR code with an expiry date, as someone scanning it would.
     *
     * @When /^I open the QR code link of the activity "(?P<idnumber>[^"]*)" with expiry "(?P<state>valid|expired|forged)"$/
     * @param string $idnumber Activity idnumber.
     * @param string $state valid: expires in 30 days; expired: expired yesterday; forged: wrong signature.
     */
    public function i_open_the_qr_code_link_of_the_activity(string $idnumber, string $state): void {
        $cm = $this->get_course_module_for_identifier($idnumber);
        if (!$cm) {
            throw new \Behat\Mink\Exception\ExpectationException("No activity with idnumber '{$idnumber}'", $this->getSession());
        }
        $expires = $state === 'expired' ? time() - DAYSECS : time() + 30 * DAYSECS;
        $signature = expiry::sign((int) $cm->id, $expires);
        if ($state === 'forged') {
            // Same link for a later date, keeping the original signature.
            $expires += DAYSECS;
        }
        $url = new moodle_url('/local/resourceqr/go.php', ['id' => $cm->id, 'exp' => $expires, 'sig' => $signature]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }
}
