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

namespace local_resourceqr;

use local_resourceqr\external\get_qrcode_data;
use local_resourceqr\local\access;
use local_resourceqr\local\expiry;
use local_resourceqr\local\filename;
use local_resourceqr\local\image;
use local_resourceqr\local\qr_generator;

#[\PHPUnit\Framework\Attributes\CoversClass(expiry::class)]
/**
 * Tests for QR codes with an expiry date.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\local\expiry
 */
final class expiry_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $page;

    /**
     * Course with a page, logged in as an editing teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => 'Lab safety']);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
    }

    /**
     * cm_info for the page.
     *
     * @return \cm_info
     */
    private function cm(): \cm_info {
        return get_fast_modinfo($this->course->id)->get_cm($this->page->cmid);
    }

    /**
     * Signatures verify, and any change to the module, time or signature is rejected.
     */
    public function test_sign_and_verify(): void {
        $expires = time() + WEEKSECS;
        $sig = expiry::sign($this->page->cmid, $expires);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{20}$/', $sig);
        $this->assertTrue(expiry::verify($this->page->cmid, $expires, $sig));
        $this->assertFalse(expiry::verify($this->page->cmid, $expires + 1, $sig));
        $this->assertFalse(expiry::verify($this->page->cmid + 1, $expires, $sig));
        $this->assertFalse(expiry::verify($this->page->cmid, $expires, strrev($sig)));
        $this->assertFalse(expiry::verify($this->page->cmid, $expires, ''));
        // The secret is created once and kept.
        $secret = get_config('local_resourceqr', 'secret');
        $this->assertSame(64, strlen($secret));
        $this->assertSame($sig, expiry::sign($this->page->cmid, $expires));
    }

    /**
     * Dates become the end of that day in the user's time zone; bad dates are refused.
     */
    public function test_date_to_timestamp(): void {
        $this->setTimezone('Asia/Riyadh');
        $this->assertSame(0, expiry::date_to_timestamp(''));

        $date = date('Y-m-d', time() + 20 * DAYSECS);
        $expires = expiry::date_to_timestamp($date);
        $this->assertSame($date . ' 23:59:59', userdate($expires, '%Y-%m-%d %H:%M:%S', 'Asia/Riyadh', false, false));
        $this->assertSame($date, expiry::timestamp_to_date($expires));
        $this->assertTrue(expiry::is_valid_expiry($expires));

        foreach (['2020-01-01', '2026-02-30', 'tomorrow', '31/12/2030', date('Y-m-d', time() + 11 * YEARSECS)] as $bad) {
            try {
                expiry::date_to_timestamp($bad);
                $this->fail("Accepted '$bad'");
            } catch (\moodle_exception $e) {
                $this->assertSame('error:expirydate', $e->errorcode);
            }
        }
    }

    /**
     * Lifetime codes keep the activity's link; dated codes use the signed go.php link.
     */
    public function test_qr_url(): void {
        global $CFG;
        $cm = $this->cm();
        $this->assertSame($CFG->wwwroot . '/mod/page/view.php?id=' . $cm->id, access::get_qr_url($cm)->out(false));

        $expires = time() + WEEKSECS;
        $url = access::get_qr_url($cm, $expires);
        $this->assertSame($CFG->wwwroot . '/local/resourceqr/go.php', $url->out_omit_querystring());
        $this->assertEquals(['id' => $cm->id, 'exp' => $expires, 'sig' => expiry::sign($cm->id, $expires)], $url->params());
    }

    /**
     * Resolving: valid links lead to the activity; expired, forged and orphaned links do not.
     */
    public function test_resolve(): void {
        global $CFG;
        $cmid = (int) $this->page->cmid;
        $this->setUser(null);

        $expires = time() + DAYSECS;
        [$status, $target] = expiry::resolve($cmid, $expires, expiry::sign($cmid, $expires));
        $this->assertSame(expiry::VALID, $status);
        $this->assertSame($CFG->wwwroot . '/mod/page/view.php?id=' . $cmid, $target->out(false));

        $past = time() - 1;
        $this->assertSame([expiry::EXPIRED, null], expiry::resolve($cmid, $past, expiry::sign($cmid, $past)));
        $this->assertSame([expiry::INVALID, null], expiry::resolve($cmid, $expires + 99, expiry::sign($cmid, $expires)));

        // Remove the activity (course_delete_module() is deprecated from Moodle 5.2).
        global $DB;
        $DB->delete_records('course_modules', ['id' => $cmid]);
        rebuild_course_cache($this->course->id, true);
        $this->assertSame([expiry::INVALID, null], expiry::resolve($cmid, $expires, expiry::sign($cmid, $expires)));
    }

    /**
     * A dated QR code image encodes the signed link and is logged with its expiry.
     */
    public function test_image_with_expiry(): void {
        $expires = time() + 10 * DAYSECS;
        $sink = $this->redirectEvents();
        $image = image::create($this->cm(), 'svg', true, $expires);
        $events = $sink->get_events();
        $sink->close();

        $this->assertStringContainsString('go.php?id=' . $this->page->cmid . '&amp;exp=' . $expires, $image->content);
        $this->assertStringEndsWith('-until-' . expiry::timestamp_to_date($expires) . '.svg', $image->filename);
        $this->assertSame($expires, reset($events)->other['expires']);
        $this->assertStringContainsString('valid until', reset($events)->get_description());

        if (qr_generator::png_supported() && is_executable('/usr/bin/zbarimg')) {
            $file = make_request_directory() . '/qr.png';
            file_put_contents($file, image::create($this->cm(), 'png', false, $expires)->content);
            exec('/usr/bin/zbarimg --quiet --raw ' . escapeshellarg($file) . ' 2>/dev/null', $output);
            $this->assertSame(access::get_qr_url($this->cm(), $expires)->out(false), trim(implode('', $output)));
        }
    }

    /**
     * Expiry times in the past or too far ahead are refused by the image endpoint.
     */
    public function test_image_rejects_bad_expiry(): void {
        $this->expectExceptionMessage(get_string('error:expirydate', 'local_resourceqr'));
        image::create($this->cm(), 'svg', false, time() - 10);
    }

    /**
     * File names carry the expiry date.
     */
    public function test_filename(): void {
        $expires = time() + 10 * DAYSECS;
        $this->assertSame('qrcode-lab_safety.png', filename::for_cm($this->cm(), 'png'));
        $this->assertSame(
            'qrcode-lab_safety-until-' . expiry::timestamp_to_date($expires) . '.png',
            filename::for_cm($this->cm(), 'png', $expires)
        );
    }

    /**
     * The dialogue data switches between lifetime and dated codes.
     */
    public function test_external(): void {
        $lifetime = get_qrcode_data::execute($this->page->cmid);
        $this->assertTrue($lifetime['lifetime']);
        $this->assertSame('', $lifetime['expirytext']);
        $this->assertStringContainsString('/mod/page/view.php', $lifetime['url']);

        $date = date('Y-m-d', time() + 40 * DAYSECS);
        $dated = get_qrcode_data::execute($this->page->cmid, $date);
        $dated = \core_external\external_api::clean_returnvalue(get_qrcode_data::execute_returns(), $dated);
        $this->assertFalse($dated['lifetime']);
        $this->assertSame($date, $dated['expirydate']);
        $this->assertStringContainsString('/local/resourceqr/go.php?id=', $dated['url']);
        $this->assertStringContainsString('expires=' . $dated['expires'], $dated['previewurl']);
        $this->assertStringContainsString('expires=' . $dated['expires'], $dated['printurl']);
        $this->assertSame(get_string('validuntil', 'local_resourceqr', expiry::format($dated['expires'])), $dated['expirytext']);

        $this->expectException(\moodle_exception::class);
        get_qrcode_data::execute($this->page->cmid, '2020-01-01');
    }
}
