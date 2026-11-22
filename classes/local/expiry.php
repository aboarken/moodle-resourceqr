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
use moodle_exception;
use moodle_url;

/**
 * QR codes that stop working after a date.
 *
 * A lifetime QR code holds the activity's normal link. A QR code with an expiry date
 * holds a link to go.php instead, carrying the course module id, the expiry time and
 * a signature (HMAC-SHA256 with a site secret) so the date cannot be changed. Before
 * the expiry time go.php redirects to the activity's normal link, where Moodle's usual
 * login and access checks apply; afterwards it shows that the code has expired.
 *
 * Nothing is stored per QR code. Expiry is checked against the server clock.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expiry {
    /** @var int Longest allowed validity, in years from today. */
    public const MAX_YEARS = 10;

    /** @var int Default validity offered when "Valid until" is chosen, in days. */
    public const DEFAULT_DAYS = 30;

    /** @var int Length of the signature in hexadecimal characters (80 bits). */
    private const SIGNATURE_LENGTH = 20;

    /** @var string resolve() result: the link is valid. */
    public const VALID = 'valid';

    /** @var string resolve() result: the link has expired. */
    public const EXPIRED = 'expired';

    /** @var string resolve() result: the link is not genuine or the activity no longer exists. */
    public const INVALID = 'invalid';

    /**
     * The site secret used to sign links. Created on first use.
     *
     * @return string
     */
    private static function secret(): string {
        $secret = (string) get_config('local_resourceqr', 'secret');
        if ($secret === '') {
            $secret = random_string(64);
            set_config('secret', $secret, 'local_resourceqr');
        }
        return $secret;
    }

    /**
     * Cancel every QR code with an expiry date by replacing the site secret.
     *
     * Links signed with the old secret show "QR code not valid" from now on. Lifetime QR
     * codes hold the activity's own link and are not affected. The change is recorded in
     * the configuration changes report, without the secret itself.
     */
    public static function reset_secret(): void {
        set_config('secret', random_string(64), 'local_resourceqr');
        set_config('secretreset', time(), 'local_resourceqr');
        add_to_config_log('secret', '', get_string('resetlinks_logvalue', 'local_resourceqr'), 'local_resourceqr');
    }

    /**
     * When the expiring QR codes were last cancelled.
     *
     * @return int Unix timestamp, or 0 if never.
     */
    public static function last_reset(): int {
        return (int) get_config('local_resourceqr', 'secretreset');
    }

    /**
     * Signature for a course module and expiry time.
     *
     * @param int $cmid
     * @param int $expires Unix timestamp.
     * @return string
     */
    public static function sign(int $cmid, int $expires): string {
        $hash = hash_hmac('sha256', 'local_resourceqr:' . $cmid . ':' . $expires, self::secret());
        return substr($hash, 0, self::SIGNATURE_LENGTH);
    }

    /**
     * Is this a signature we issued for this course module and expiry time?
     *
     * @param int $cmid
     * @param int $expires
     * @param string $signature
     * @return bool
     */
    public static function verify(int $cmid, int $expires, string $signature): bool {
        return $signature !== '' && hash_equals(self::sign($cmid, $expires), $signature);
    }

    /**
     * The expiring link for a course module.
     *
     * @param cm_info $cm
     * @param int $expires Unix timestamp.
     * @return moodle_url
     */
    public static function url(cm_info $cm, int $expires): moodle_url {
        return new moodle_url('/local/resourceqr/go.php', [
            'id' => $cm->id,
            'exp' => $expires,
            'sig' => self::sign((int) $cm->id, $expires),
        ]);
    }

    /**
     * Is this an acceptable expiry time? 0 means lifetime.
     *
     * @param int $expires
     * @return bool
     */
    public static function is_valid_expiry(int $expires): bool {
        return $expires === 0 || ($expires > time() && $expires <= self::max_expiry());
    }

    /**
     * Latest expiry time accepted.
     *
     * @return int
     */
    private static function max_expiry(): int {
        return time() + self::MAX_YEARS * YEARSECS + 2 * DAYSECS;
    }

    /**
     * Convert a date (YYYY-MM-DD) to the end of that day, 23:59:59, in the user's time zone.
     *
     * @param string $date Empty for lifetime.
     * @return int Unix timestamp, or 0 for lifetime.
     * @throws moodle_exception If the date is malformed, in the past or too far ahead.
     */
    public static function date_to_timestamp(string $date): int {
        $date = trim($date, " \t\n\r\0\x0B");
        if ($date === '') {
            return 0;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new moodle_exception('error:expirydate', 'local_resourceqr');
        }
        $expires = make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 23, 59, 59);
        if (!self::is_valid_expiry($expires)) {
            throw new moodle_exception('error:expirydate', 'local_resourceqr');
        }
        return $expires;
    }

    /**
     * A timestamp as YYYY-MM-DD in the user's time zone (for date inputs).
     *
     * @param int $timestamp
     * @return string
     */
    public static function timestamp_to_date(int $timestamp): string {
        return userdate($timestamp, '%Y-%m-%d', 99, false, false);
    }

    /**
     * Human-readable expiry date in the user's language and time zone.
     *
     * @param int $expires
     * @return string
     */
    public static function format(int $expires): string {
        return userdate($expires, get_string('strftimedate', 'langconfig'));
    }

    /**
     * Check an expiring link and find where it leads.
     *
     * @param int $cmid
     * @param int $expires
     * @param string $signature
     * @return array{0: string, 1: ?moodle_url} One of VALID, EXPIRED or INVALID, and the target for VALID.
     */
    public static function resolve(int $cmid, int $expires, string $signature): array {
        global $DB;
        if (!self::verify($cmid, $expires, $signature)) {
            return [self::INVALID, null];
        }
        if ($expires < time()) {
            return [self::EXPIRED, null];
        }
        $courseid = $DB->get_field('course_modules', 'course', ['id' => $cmid]);
        if (!$courseid) {
            return [self::INVALID, null];
        }
        try {
            $cm = get_fast_modinfo($courseid)->get_cm($cmid);
        } catch (moodle_exception $e) {
            return [self::INVALID, null];
        }
        $target = access::get_target_url($cm);
        return $target ? [self::VALID, $target] : [self::INVALID, null];
    }
}
