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
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | Page             | Description                                   |
     * | Cancel QR codes  | Confirmation page for cancelling expiring QR codes |
     *
     * @param string $page Name of the page, with the component name removed, e.g. 'Cancel QR codes'.
     * @return moodle_url
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'cancel qr codes':
                return new moodle_url('/local/resourceqr/resetlinks.php');
            default:
                throw new Exception("Unrecognised local_resourceqr page '{$page}'.");
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | Page type  | Identifier        | Description                                  |
     * | QR code    | Activity idnumber | Stand-alone QR code page (view.php)          |
     * | Print      | Activity idnumber | Print page (print.php)                       |
     * | Image      | Activity idnumber | SVG image endpoint (qr.php)                  |
     * | Course     | Course shortname  | Course "QR codes" page (course.php)          |
     *
     * @param string $type Page type, with the component name removed, e.g. 'QR code'.
     * @param string $identifier Activity idnumber or course shortname.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $type = strtolower($type);
        if ($type === 'course') {
            return new moodle_url('/local/resourceqr/course.php', ['id' => $this->get_course_id($identifier)]);
        }
        $cm = $this->get_course_module_for_identifier($identifier);
        if (!$cm) {
            throw new Exception("No activity with idnumber '{$identifier}'.");
        }
        switch ($type) {
            case 'qr code':
                return new moodle_url('/local/resourceqr/view.php', ['cmid' => $cm->id]);
            case 'print':
                return new moodle_url('/local/resourceqr/print.php', ['cmid' => $cm->id]);
            case 'image':
                return new moodle_url('/local/resourceqr/qr.php', ['cmid' => $cm->id, 'format' => 'svg']);
            default:
                throw new Exception("Unrecognised local_resourceqr page type '{$type}'.");
        }
    }

    /**
     * Open a plugin page that must be refused, check the error shown, then go back to the site home.
     *
     * Moodle's Behat fails any step that ends on an error page, so a refusal cannot be checked
     * with the normal navigation steps. This step expects the error page, checks its message and
     * that no QR code was shown, and leaves the browser on a normal page.
     *
     * @Then /^opening the local_resourceqr "(?P<type>[^"]*)" page of "(?P<id>[^"]*)" should fail with "(?P<message>[^"]*)"$/
     * @param string $type Page type, see resolve_page_instance_url().
     * @param string $id Activity idnumber or course shortname.
     * @param string $message Text the error must contain.
     */
    public function opening_the_instance_page_should_fail(string $type, string $id, string $message): void {
        $this->visit_expecting_error($this->resolve_page_instance_url($type, $id), $message);
    }

    /**
     * Open a plugin page that must be refused, check the error shown, then go back to the site home.
     *
     * @Then /^opening the local_resourceqr "(?P<page>[^"]*)" page should fail with "(?P<message>[^"]*)"$/
     * @param string $page Page name, see resolve_page_url().
     * @param string $message Text the error must contain.
     */
    public function opening_the_page_should_fail(string $page, string $message): void {
        $this->visit_expecting_error($this->resolve_page_url($page), $message);
    }

    /**
     * Visit a URL that must show a Moodle error with the given message and no QR code.
     *
     * @param moodle_url $url
     * @param string $message Text the error must contain.
     */
    private function visit_expecting_error(moodle_url $url, string $message): void {
        $session = $this->getSession();
        $session->visit($this->locate_path($url->out_as_local_url(false)));
        $page = $session->getPage();
        $error = $page->find('xpath', "//div[@data-rel='fatalerror']");
        $errortext = $error ? $error->getText() : '';
        $leaked = $page->find('css', "[data-region='local-resourceqr-panel'], [data-region='local-resourceqr-print'], " .
            "[data-region='local-resourceqr-courselist'], svg");
        // Leave the error page before the step ends: Behat fails a step that ends on one.
        $session->visit($this->locate_path('/'));

        if (!$error) {
            throw new \Behat\Mink\Exception\ExpectationException("'{$url->out(false)}' did not show an error.", $session);
        }
        if (strpos($errortext, $message) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The error did not contain '{$message}'. It was: '{$errortext}'",
                $session
            );
        }
        if ($leaked) {
            throw new \Behat\Mink\Exception\ExpectationException('The refused page still showed plugin content.', $session);
        }
    }

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
