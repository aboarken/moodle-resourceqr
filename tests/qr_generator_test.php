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

use local_resourceqr\local\qr_generator;

/**
 * Tests for the QR code renderer.
 *
 * PNG and SVG output are decoded with zbarimg (package zbar-tools) when it is
 * installed; otherwise the decoding tests are skipped.
 *
 * @package    local_resourceqr
 * @category   test
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_resourceqr\local\qr_generator
 */
#[\PHPUnit\Framework\Attributes\CoversClass(qr_generator::class)]
final class qr_generator_test extends \advanced_testcase {
    /** @var string URL used in the tests. */
    private const URL = 'https://moodle.example.org/mod/quiz/view.php?id=12345';

    /**
     * Path to zbarimg, or null.
     *
     * @return string|null
     */
    private static function zbarimg(): ?string {
        foreach (['/usr/bin/zbarimg', '/usr/local/bin/zbarimg', '/opt/homebrew/bin/zbarimg'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Decode a PNG with zbarimg.
     *
     * @param string $png
     * @return string Decoded text.
     */
    private function decode_png(string $png): string {
        $zbarimg = self::zbarimg();
        if (!$zbarimg) {
            $this->markTestSkipped('zbarimg is not installed; cannot decode QR codes.');
        }
        $file = make_request_directory() . '/qr.png';
        file_put_contents($file, $png);
        $output = [];
        $command = escapeshellarg($zbarimg) . ' --quiet --raw -Sdisable -Sqrcode.enable ' . escapeshellarg($file) . ' 2>/dev/null';
        exec($command, $output, $status);
        $this->assertSame(0, $status, 'zbarimg could not decode the image: ' . implode("\n", $output));
        return trim(implode("\n", $output));
    }

    /**
     * Rasterise SVG output of the square design (axis-aligned rectangles only) with GD.
     *
     * @param string $svg
     * @return string PNG data.
     */
    private function rasterise_svg(string $svg): string {
        $xml = new \SimpleXMLElement($svg);
        $size = (int) $xml['width'];
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        foreach ($xml->path as $path) {
            $white = (string) $path['fill'] === '#ffffff';
            $fill = $white ? imagecolorallocate($image, 255, 255, 255) : imagecolorallocate($image, 0, 0, 0);
            preg_match_all('/M([\d.]+) ([\d.]+)H([\d.]+)V([\d.]+)H[\d.]+V[\d.]+Z/', (string) $path['d'], $matches, PREG_SET_ORDER);
            foreach ($matches as [, $x1, $y1, $x2, $y2]) {
                imagefilledrectangle($image, (int) round($x1), (int) round($y1), (int) round($x2) - 1, (int) round($y2) - 1, $fill);
            }
        }
        ob_start();
        imagepng($image);
        return ob_get_clean();
    }

    /**
     * Create a site logo in the Logos settings area.
     *
     * @param string $area 'logo' or 'logocompact'.
     */
    private function create_site_logo(string $area = 'logocompact'): void {
        $logo = imagecreatetruecolor(200, 100);
        imagefill($logo, 0, 0, imagecolorallocate($logo, 200, 30, 30));
        imagefilledrectangle($logo, 20, 20, 180, 80, imagecolorallocate($logo, 30, 30, 200));
        ob_start();
        imagepng($logo);
        $content = ob_get_clean();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'core_admin',
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'logo.png',
        ], $content);
        set_config($area, '/logo.png', 'core_admin');
    }

    /**
     * PNG output is a valid image of the configured size.
     */
    public function test_png_is_valid(): void {
        if (!qr_generator::png_supported()) {
            $this->markTestSkipped('Neither GD nor Imagick is available.');
        }
        $png = (new qr_generator(self::URL, 'M', 600))->png();
        $info = getimagesizefromstring($png);
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame(600, $info[0]);
        $this->assertSame(600, $info[1]);
    }

    /**
     * SVG output is valid XML with the expected size and no script.
     */
    public function test_svg_is_valid(): void {
        $svg = (new qr_generator(self::URL, 'M', 800))->svg();
        $xml = new \SimpleXMLElement($svg);
        $this->assertSame('svg', $xml->getName());
        $this->assertSame('800', (string) $xml['width']);
        $this->assertSame('0 0 800 800', (string) $xml['viewBox']);
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertNotEmpty((string) $xml->path['d']);
    }

    /**
     * Error correction levels.
     *
     * @return array
     */
    public static function level_provider(): array {
        return [['L'], ['M'], ['Q'], ['H']];
    }

    /**
     * PNG decodes to the URL at every level.
     *
     * @dataProvider level_provider
     * @param string $level
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('level_provider')]
    public function test_png_decodes(string $level): void {
        if (!qr_generator::png_supported()) {
            $this->markTestSkipped('Neither GD nor Imagick is available.');
        }
        $generator = new qr_generator(self::URL, $level, 600);
        $this->assertSame($level, $generator->get_level());
        $this->assertSame(self::URL, $this->decode_png($generator->png()));
    }

    /**
     * SVG decodes to the URL.
     */
    public function test_svg_decodes(): void {
        $svg = (new qr_generator(self::URL, 'M', 600, null, qr_generator::STYLE_SQUARE))->svg();
        $this->assertSame(self::URL, $this->decode_png($this->rasterise_svg($svg)));
    }

    /**
     * Designs and sizes.
     *
     * @return array
     */
    public static function style_provider(): array {
        $cases = [];
        foreach (qr_generator::STYLES as $style) {
            foreach ([200, 600, 1200] as $size) {
                foreach ([false, true] as $logo) {
                    $cases["$style $size" . ($logo ? ' with logo' : '')] = [$style, $size, $logo];
                }
            }
        }
        return $cases;
    }

    /**
     * Every design decodes, with and without the logo, at small and large sizes.
     *
     * @dataProvider style_provider
     * @param string $style
     * @param int $size
     * @param bool $logo
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('style_provider')]
    public function test_styles_decode(string $style, int $size, bool $logo): void {
        if (!qr_generator::png_supported()) {
            $this->markTestSkipped('Neither GD nor Imagick is available.');
        }
        $this->resetAfterTest();
        $file = null;
        if ($logo) {
            $this->create_site_logo();
            $file = qr_generator::get_site_logo();
        }
        $generator = new qr_generator(self::URL, 'M', $size, $file, $style, '#1d2125');
        $this->assertSame($style, $generator->get_style());
        $png = $generator->png();
        $info = getimagesizefromstring($png);
        $this->assertSame($size, $info[0]);
        $this->assertSame(self::URL, $this->decode_png($png));
        $svg = $generator->svg();
        $this->assertStringContainsString('fill="#1d2125"', $svg);
        new \SimpleXMLElement($svg);
    }

    /**
     * Colour validation.
     */
    public function test_colours(): void {
        $this->assertTrue(qr_generator::is_valid_colour('#000000'));
        $this->assertTrue(qr_generator::is_valid_colour(qr_generator::DEFAULT_COLOUR));
        $this->assertFalse(qr_generator::is_valid_colour('#ffff00'));
        $this->assertFalse(qr_generator::is_valid_colour('#cccccc'));
        $this->assertFalse(qr_generator::is_valid_colour('red'));
        $this->assertFalse(qr_generator::is_valid_colour('#12345'));
        $this->assertEqualsWithDelta(21.0, qr_generator::contrast_with_white('#000000'), 0.01);
        // Invalid colours and designs fall back to the defaults.
        $generator = new qr_generator(self::URL, 'M', 600, null, 'sparkly', '#ffffff');
        $this->assertSame(qr_generator::DEFAULT_COLOUR, $generator->get_colour());
        $this->assertSame(qr_generator::STYLE_ROUNDED, $generator->get_style());
    }

    /**
     * Higher levels make a denser symbol.
     */
    public function test_levels_change_density(): void {
        $low = count((new qr_generator(self::URL, 'L'))->get_matrix());
        $high = count((new qr_generator(self::URL, 'H'))->get_matrix());
        $this->assertGreaterThan($low, $high);
    }

    /**
     * Invalid level falls back to M, and size is clamped.
     */
    public function test_invalid_values(): void {
        $generator = new qr_generator(self::URL, 'X', 50);
        $this->assertSame('M', $generator->get_level());
        $this->assertSame(qr_generator::MIN_SIZE, $generator->get_size());
        $this->assertSame(qr_generator::MAX_SIZE, (new qr_generator(self::URL, 'M', 99999))->get_size());
    }

    /**
     * Empty data is a coding error.
     */
    public function test_empty_data(): void {
        $this->expectException(\coding_exception::class);
        new qr_generator('');
    }

    /**
     * Configuration is applied.
     */
    public function test_from_config(): void {
        $this->resetAfterTest();
        set_config('size', 900, 'local_resourceqr');
        set_config('errorcorrection', 'Q', 'local_resourceqr');
        $generator = qr_generator::from_config(self::URL);
        $this->assertSame(900, $generator->get_size());
        $this->assertSame('Q', $generator->get_level());
        $this->assertSame(qr_generator::STYLE_ROUNDED, $generator->get_style());
        $this->assertSame(qr_generator::DEFAULT_COLOUR, $generator->get_colour());
        set_config('style', 'dots', 'local_resourceqr');
        set_config('colour', '#000000', 'local_resourceqr');
        $generator = qr_generator::from_config(self::URL);
        $this->assertSame('dots', $generator->get_style());
        $this->assertSame('#000000', $generator->get_colour());
    }

    /**
     * With the logo enabled, level H is forced and the code still scans.
     */
    public function test_logo_forces_h_and_scans(): void {
        $this->resetAfterTest();
        $this->create_site_logo();
        set_config('showlogo', 1, 'local_resourceqr');
        set_config('errorcorrection', 'L', 'local_resourceqr');

        $generator = qr_generator::from_config(self::URL);
        $this->assertSame('H', $generator->get_level());

        $this->assertStringContainsString('data:image/png;base64,', $generator->svg());
        // The square SVG geometry (with the logo area cleared) must still decode.
        set_config('style', qr_generator::STYLE_SQUARE, 'local_resourceqr');
        $svg = qr_generator::from_config(self::URL)->svg();
        $this->assertSame(self::URL, $this->decode_png($this->rasterise_svg($svg)));
        set_config('style', qr_generator::STYLE_ROUNDED, 'local_resourceqr');

        if (qr_generator::png_supported()) {
            $png = $generator->png();
            $this->assertSame(self::URL, $this->decode_png($png));
        }
    }

    /**
     * The logo stays within 20% of the symbol width.
     */
    public function test_logo_size_limit(): void {
        $this->resetAfterTest();
        $this->create_site_logo('logo');
        $logo = qr_generator::get_site_logo();
        $this->assertNotNull($logo);
        $generator = new qr_generator(self::URL, 'M', 600, $logo);
        $n = count($generator->get_matrix());
        $xml = new \SimpleXMLElement($generator->svg());
        $image = $xml->image;
        $this->assertCount(1, $image);
        // The image plus one module of padding on each side must stay within 20% of the symbol width.
        $module = intdiv(600, $n + 8);
        $this->assertLessThanOrEqual(0.2 * $n * $module, (int) $image['width'] + 2 * $module);
        $this->assertGreaterThan(0, (int) $image['width']);
    }

    /**
     * Without a configured logo nothing is embedded.
     */
    public function test_no_logo_configured(): void {
        $this->resetAfterTest();
        set_config('showlogo', 1, 'local_resourceqr');
        $this->assertNull(qr_generator::get_site_logo());
        $this->assertStringNotContainsString('<image', qr_generator::from_config(self::URL)->svg());
    }

    /**
     * The PNG backend is detected.
     */
    public function test_png_backend(): void {
        $backend = qr_generator::png_backend();
        $this->assertContains($backend, ['gd', 'imagick', null]);
        $this->assertSame($backend !== null, qr_generator::png_supported());
    }
}
