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

use local_resourceqr\admin\setting_colour;
use local_resourceqr\admin\setting_formats;
use local_resourceqr\admin\setting_modules;
use local_resourceqr\admin\setting_size;
use local_resourceqr\local\filename;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for settings validation, defaults and file names.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\admin\setting_size
 * @covers     \local_resourceqr\admin\setting_colour
 * @covers     \local_resourceqr\admin\setting_formats
 * @covers     \local_resourceqr\admin\setting_modules
 * @covers     \local_resourceqr\local\filename
 */
#[\PHPUnit\Framework\Attributes\CoversClass(setting_size::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(setting_colour::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(setting_formats::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(setting_modules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(filename::class)]
final class settings_test extends \advanced_testcase {
    /**
     * Installed defaults.
     */
    public function test_defaults(): void {
        $this->assertEquals(1, get_config('local_resourceqr', 'enabled'));
        $this->assertEquals(1, get_config('local_resourceqr', 'allmodules'));
        $this->assertEquals(600, get_config('local_resourceqr', 'size'));
        $this->assertSame('M', get_config('local_resourceqr', 'errorcorrection'));
        $this->assertEquals(0, get_config('local_resourceqr', 'showlogo'));
        $this->assertSame('png,svg', get_config('local_resourceqr', 'formats'));
        $this->assertSame('rounded', get_config('local_resourceqr', 'style'));
        $this->assertSame('#0f47ad', get_config('local_resourceqr', 'colour'));
        $enabled = explode(',', get_config('local_resourceqr', 'enabledmodules'));
        $this->assertContains('quiz', $enabled);
        $this->assertNotContains('label', $enabled);
    }

    /**
     * Size values.
     *
     * @return array
     */
    public static function size_provider(): array {
        return [
            ['600', true], ['200', true], ['2000', true], ['199', false], ['2001', false],
            ['abc', false], ['-5', false], ['', false], ['600.5', false],
        ];
    }

    /**
     * Size validation.
     *
     * @dataProvider size_provider
     * @param string $value
     * @param bool $valid
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('size_provider')]
    public function test_size_validation(string $value, bool $valid): void {
        $setting = new setting_size('local_resourceqr/size', 'Size', '');
        $this->assertSame($valid, $setting->validate($value) === true);
    }

    /**
     * Colour validation rejects malformed and light colours.
     */
    public function test_colour_validation(): void {
        $this->resetAfterTest();
        $setting = new setting_colour('local_resourceqr/colour', 'Colour', '');
        $this->assertTrue($setting->check('#0f47ad'));
        $this->assertTrue($setting->check('#000000'));
        $this->assertIsString($setting->check('#ffeb3b'));
        $this->assertIsString($setting->check('blue'));
        $this->assertIsString($setting->check(''));
        $this->assertNotSame('', $setting->write_setting('#ffeb3b'));
        $this->assertSame('', $setting->write_setting('#1D2125'));
        $this->assertSame('#1d2125', get_config('local_resourceqr', 'colour'));
    }

    /**
     * At least one format is required.
     */
    public function test_formats_validation(): void {
        $this->resetAfterTest();
        $setting = new setting_formats('local_resourceqr/formats', 'Formats', '');
        $this->assertNotSame('', $setting->write_setting(['png' => 0, 'svg' => 0]));
        $this->assertSame('', $setting->write_setting(['svg' => 1]));
        $this->assertSame('svg', get_config('local_resourceqr', 'formats'));
    }

    /**
     * Module list excludes modules without a view URL and ignores unknown values.
     */
    public function test_modules_setting(): void {
        $this->resetAfterTest();
        $setting = new setting_modules('local_resourceqr/enabledmodules', 'Modules', '');
        $setting->load_choices();
        $this->assertArrayHasKey('page', $setting->choices);
        $this->assertArrayNotHasKey('label', $setting->choices);
        $this->assertSame('', $setting->write_setting(['page', 'label', 'doesnotexist']));
        $this->assertSame('page', get_config('local_resourceqr', 'enabledmodules'));
    }

    /**
     * File names.
     */
    public function test_filenames(): void {
        $this->assertSame('qrcode-week_1_quiz.png', filename::from_name('Week 1: Quiz', 'png'));
        $this->assertSame('qrcode-ab.svg', filename::from_name('../a/b', 'svg'));
        $this->assertSame('qrcode.svg', filename::from_name('???', 'svg'));
        $this->assertSame('qrcode-اختبار_الوحدة.png', filename::from_name('اختبار الوحدة', 'png'));
    }
}
