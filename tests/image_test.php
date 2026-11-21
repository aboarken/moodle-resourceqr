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

use local_resourceqr\local\image;
use local_resourceqr\local\qr_generator;

#[\PHPUnit\Framework\Attributes\CoversClass(image::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\qrcode_generated::class)]
/**
 * Tests for image generation and the qrcode_generated event.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\local\image
 * @covers     \local_resourceqr\event\qrcode_generated
 */
final class image_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $page;

    /**
     * Set up a course with a page and log in as teacher.
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
        return get_fast_modinfo($this->course)->get_cm($this->page->cmid);
    }

    /**
     * SVG preview triggers the event.
     */
    public function test_svg_event(): void {
        $sink = $this->redirectEvents();
        $image = image::create($this->cm(), 'svg', false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('image/svg+xml', $image->mimetype);
        $this->assertSame('qrcode-lab_safety.svg', $image->filename);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(event\qrcode_generated::class, $event);
        $this->assertSame('r', $event->crud);
        $this->assertEquals(\context_module::instance($this->page->cmid), $event->get_context());
        $this->assertEquals($this->page->cmid, $event->objectid);
        $this->assertSame('svg', $event->other['format']);
        $this->assertFalse($event->other['download']);
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_description());
        $this->assertStringContainsString('print.php', $event->get_url()->out(false));
    }

    /**
     * PNG download triggers the event.
     */
    public function test_png_download_event(): void {
        if (!qr_generator::png_supported()) {
            $this->markTestSkipped('No PNG support.');
        }
        $sink = $this->redirectEvents();
        $image = image::create($this->cm(), 'png', true);
        $events = $sink->get_events();
        $sink->close();
        $this->assertSame('image/png', $image->mimetype);
        $this->assertNotFalse(getimagesizefromstring($image->content));
        $this->assertTrue(reset($events)->other['download']);
    }

    /**
     * Downloads in formats that are not allowed are refused, but the SVG preview still works.
     */
    public function test_format_not_allowed(): void {
        set_config('formats', 'png', 'local_resourceqr');
        $this->assertSame('image/svg+xml', image::create($this->cm(), 'svg', false)->mimetype);
        $this->expectExceptionMessage(get_string('error:formatnotallowed', 'local_resourceqr'));
        image::create($this->cm(), 'svg', true);
    }

    /**
     * Unknown formats are refused.
     */
    public function test_invalid_format(): void {
        $this->expectExceptionMessage(get_string('error:invalidformat', 'local_resourceqr'));
        image::create($this->cm(), 'gif', false);
    }

    /**
     * Students cannot generate images, and no event is logged.
     */
    public function test_student_denied(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $sink = $this->redirectEvents();
        try {
            image::create($this->cm(), 'svg', false);
            $this->fail('Exception expected');
        } catch (\required_capability_exception $e) {
            $this->assertCount(0, $sink->get_events());
        }
        $sink->close();
    }

    /**
     * The event validates its data.
     */
    public function test_event_validation(): void {
        $this->expectException(\coding_exception::class);
        event\qrcode_generated::create([
            'context' => \context_module::instance($this->page->cmid),
            'objectid' => $this->page->cmid,
            'other' => ['format' => 'gif', 'download' => false],
        ]);
    }
}
