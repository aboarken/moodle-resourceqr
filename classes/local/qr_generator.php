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

use coding_exception;
use moodle_exception;
use stored_file;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');

/**
 * Renders QR codes as PNG or SVG using the QR encoder shipped with Moodle core.
 *
 * The encoding is done by TCPDF's 2D barcode class (lib/tcpdf), which is what
 * core_qrcode wraps. core_qrcode always uses error-correction level L and adds
 * no quiet zone, so this class asks the same encoder for the module matrix at
 * the configured level and draws it itself, in one of several designs. Nothing
 * is stored and no external service is used.
 *
 * Drawing works in two steps: {@see self::shapes()} turns the matrix into a list
 * of shapes (rounded rectangles and circles, in module units), and the SVG, GD
 * and Imagick back ends paint that list. PNG and SVG therefore always match.
 *
 * @package    local_resourceqr
 * @copyright  2026 Resource QR Code contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qr_generator {
    /** @var int Smallest allowed image size in pixels. */
    public const MIN_SIZE = 200;

    /** @var int Largest allowed image size in pixels. */
    public const MAX_SIZE = 2000;

    /** @var int Default image size in pixels. */
    public const DEFAULT_SIZE = 600;

    /** @var string[] Valid error-correction levels. */
    public const LEVELS = ['L', 'M', 'Q', 'H'];

    /** @var string Rounded modules joined into smooth shapes, rounded finder patterns. */
    public const STYLE_ROUNDED = 'rounded';

    /** @var string Round dots, rounded finder patterns. */
    public const STYLE_DOTS = 'dots';

    /** @var string Classic square modules. */
    public const STYLE_SQUARE = 'square';

    /** @var string[] Valid designs. */
    public const STYLES = [self::STYLE_ROUNDED, self::STYLE_DOTS, self::STYLE_SQUARE];

    /** @var string Default foreground colour (dark blue, contrast about 8.6:1 on white). */
    public const DEFAULT_COLOUR = '#0f47ad';

    /** @var float Minimum contrast ratio between the foreground colour and white. */
    public const MIN_CONTRAST = 4.5;

    /** @var int Quiet zone around the symbol, in modules (ISO/IEC 18004 requires 4). */
    private const QUIET_ZONE = 4;

    /** @var float Largest share of the symbol width the centre logo (with its padding) may cover. */
    private const LOGO_MAX_RATIO = 0.2;

    /** @var int Below this many pixels per module, the square design is used so small codes stay readable. */
    private const MIN_CURVED_MODULE = 5;

    /** @var int Largest working canvas for anti-aliased GD drawing, in pixels per side. */
    private const GD_MAX_CANVAS = 3000;

    /** @var string[] Logo MIME types that can be embedded. */
    private const LOGO_MIMETYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];

    /** @var string Encoded text. */
    private string $data;

    /** @var string Error-correction level actually used. */
    private string $level;

    /** @var int Image size in pixels. */
    private int $size;

    /** @var stored_file|null Logo to place in the centre. */
    private ?stored_file $logo;

    /** @var string Design, one of STYLES. */
    private string $style;

    /** @var string Foreground colour as #rrggbb. */
    private string $colour;

    /** @var bool[][]|null Cached module matrix, true = dark. */
    private ?array $matrix = null;

    /**
     * Constructor.
     *
     * @param string $data Text to encode.
     * @param string $level Error-correction level L, M, Q or H. Forced to H when a logo is used.
     * @param int $size Image size in pixels, clamped to MIN_SIZE..MAX_SIZE.
     * @param stored_file|null $logo Optional centre logo.
     * @param string $style Design, one of STYLES.
     * @param string $colour Foreground colour #rrggbb; replaced by the default if invalid or too light.
     */
    public function __construct(
        string $data,
        string $level = 'M',
        int $size = self::DEFAULT_SIZE,
        ?stored_file $logo = null,
        string $style = self::STYLE_ROUNDED,
        string $colour = self::DEFAULT_COLOUR
    ) {
        if ($data === '') {
            throw new coding_exception('QR code data must not be empty.');
        }
        $level = strtoupper($level);
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'M';
        }
        $this->data = $data;
        $this->logo = $logo;
        $this->level = $logo ? 'H' : $level;
        $this->size = max(self::MIN_SIZE, min(self::MAX_SIZE, $size));
        $this->style = in_array($style, self::STYLES, true) ? $style : self::STYLE_ROUNDED;
        $this->colour = self::is_valid_colour($colour) ? strtolower($colour) : self::DEFAULT_COLOUR;
    }

    /**
     * Create a generator using the site configuration.
     *
     * @param string $data Text to encode.
     * @return self
     */
    public static function from_config(string $data): self {
        $config = get_config('local_resourceqr');
        $logo = !empty($config->showlogo) ? self::get_site_logo() : null;
        return new self(
            $data,
            !empty($config->errorcorrection) ? (string) $config->errorcorrection : 'M',
            !empty($config->size) ? (int) $config->size : self::DEFAULT_SIZE,
            $logo,
            !empty($config->style) ? (string) $config->style : self::STYLE_ROUNDED,
            !empty($config->colour) ? (string) $config->colour : self::DEFAULT_COLOUR
        );
    }

    /**
     * Is this a #rrggbb colour dark enough to scan reliably on white?
     *
     * @param string $colour
     * @return bool
     */
    public static function is_valid_colour(string $colour): bool {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
            return false;
        }
        return self::contrast_with_white($colour) >= self::MIN_CONTRAST;
    }

    /**
     * WCAG contrast ratio between a #rrggbb colour and white.
     *
     * @param string $colour
     * @return float
     */
    public static function contrast_with_white(string $colour): float {
        [$r, $g, $b] = self::rgb($colour);
        $channel = function (int $value): float {
            $c = $value / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
        return 1.05 / ($luminance + 0.05);
    }

    /**
     * Split #rrggbb into integers.
     *
     * @param string $colour
     * @return int[] [r, g, b]
     */
    private static function rgb(string $colour): array {
        return [hexdec(substr($colour, 1, 2)), hexdec(substr($colour, 3, 2)), hexdec(substr($colour, 5, 2))];
    }

    /**
     * The site logo from Site administration > Appearance > Logos, compact logo preferred.
     *
     * @return stored_file|null
     */
    public static function get_site_logo(): ?stored_file {
        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach (['logocompact', 'logo'] as $area) {
            $filename = get_config('core_admin', $area);
            if (empty($filename)) {
                continue;
            }
            $files = $fs->get_area_files($context->id, 'core_admin', $area, 0, 'sortorder, id', false);
            foreach ($files as $file) {
                if (in_array($file->get_mimetype(), self::LOGO_MIMETYPES, true)) {
                    return $file;
                }
            }
        }
        return null;
    }

    /**
     * Which image library is used for PNG output.
     *
     * @return string|null 'gd', 'imagick', or null if neither is usable.
     */
    public static function png_backend(): ?string {
        if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
            return 'gd';
        }
        if (class_exists(\Imagick::class) && \Imagick::queryFormats('PNG')) {
            return 'imagick';
        }
        return null;
    }

    /**
     * Can this server produce PNG files?
     *
     * @return bool
     */
    public static function png_supported(): bool {
        return self::png_backend() !== null;
    }

    /**
     * Error-correction level used (H when a logo is used).
     *
     * @return string
     */
    public function get_level(): string {
        return $this->level;
    }

    /**
     * Image size in pixels.
     *
     * @return int
     */
    public function get_size(): int {
        return $this->size;
    }

    /**
     * Design used.
     *
     * @return string
     */
    public function get_style(): string {
        return $this->style;
    }

    /**
     * Foreground colour used.
     *
     * @return string
     */
    public function get_colour(): string {
        return $this->colour;
    }

    /**
     * The QR module matrix, without the quiet zone.
     *
     * @return bool[][] Rows of modules, true = dark.
     */
    public function get_matrix(): array {
        if ($this->matrix === null) {
            $barcode = new \TCPDF2DBarcode($this->data, 'QRCODE,' . $this->level);
            $array = $barcode->getBarcodeArray();
            if (empty($array['bcode']) || empty($array['num_cols'])) {
                throw new moodle_exception('error:generation', 'local_resourceqr');
            }
            $this->matrix = [];
            foreach ($array['bcode'] as $row) {
                $this->matrix[] = array_map(fn($v) => (bool) $v, array_values($row));
            }
        }
        return $this->matrix;
    }

    /**
     * Work out the drawing geometry.
     *
     * @return array{n:int, module:int, origin:int, size:int, logo:?array}
     *     n: modules per side; module: pixels per module; origin: pixel position of
     *     unit 0 (the outer edge of the quiet zone); size: image size; logo: [first
     *     module, modules] of the square logo box, or null.
     */
    private function layout(): array {
        $n = count($this->get_matrix());
        $total = $n + 2 * self::QUIET_ZONE;
        $module = max(1, intdiv($this->size, $total));
        $size = max($this->size, $module * $total);
        $origin = intdiv($size - $module * $total, 2);
        $logobox = null;
        if ($this->logo) {
            // Logo box in whole modules, centred on the module grid. n is always odd.
            $k = (int) floor($n * self::LOGO_MAX_RATIO);
            if ($k % 2 === 0) {
                $k--;
            }
            if ($k >= 3) {
                $logobox = [intdiv($n - $k, 2), $k];
            }
        }
        return ['n' => $n, 'module' => $module, 'origin' => $origin, 'size' => $size, 'logo' => $logobox];
    }

    /**
     * Is the module inside the logo box?
     *
     * @param array $layout
     * @param int $x
     * @param int $y
     * @return bool
     */
    private function in_logo_box(array $layout, int $x, int $y): bool {
        if (!$layout['logo']) {
            return false;
        }
        [$first, $k] = $layout['logo'];
        return $x >= $first && $x < $first + $k && $y >= $first && $y < $first + $k;
    }

    /**
     * Is the module part of one of the three 7x7 finder patterns?
     *
     * @param int $n Modules per side.
     * @param int $x
     * @param int $y
     * @return bool
     */
    private static function in_finder(int $n, int $x, int $y): bool {
        return ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
    }

    /**
     * The shapes to paint, in order, in module units with (0, 0) at the outer edge of the quiet zone.
     *
     * Each shape is one of:
     * - ['rect', x, y, w, h, [tl, tr, br, bl] corner radii, fill, outside]
     * - ['circle', cx, cy, r, fill]
     * where fill and outside are 'fg' or 'bg'. 'outside' is the colour around
     * a rounded rectangle's corners, which raster back ends need to paint them.
     *
     * @param array $layout From layout().
     * @return array[]
     */
    private function shapes(array $layout): array {
        $matrix = $this->get_matrix();
        $n = $layout['n'];
        $q = self::QUIET_ZONE;
        // Curves blur when modules are only a few pixels wide, so very small codes are drawn square.
        $modulestyle = $layout['module'] < self::MIN_CURVED_MODULE ? self::STYLE_SQUARE : $this->style;
        $fancy = $modulestyle !== self::STYLE_SQUARE;
        $dark = function (int $x, int $y) use ($matrix, $n, $layout, $fancy): bool {
            if ($x < 0 || $y < 0 || $x >= $n || $y >= $n || !$matrix[$y][$x]) {
                return false;
            }
            if ($fancy && self::in_finder($n, $x, $y)) {
                return false;
            }
            return !$this->in_logo_box($layout, $x, $y);
        };

        $shapes = [];
        if ($fancy) {
            // Rounded finder patterns ("eyes"): ring, then hole, then centre.
            foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
                $x = $q + $fx;
                $y = $q + $fy;
                $shapes[] = ['rect', $x, $y, 7, 7, array_fill(0, 4, 2.2), 'fg', 'bg'];
                $shapes[] = ['rect', $x + 1, $y + 1, 5, 5, array_fill(0, 4, 1.3), 'bg', 'fg'];
                $shapes[] = ['rect', $x + 2, $y + 2, 3, 3, array_fill(0, 4, 1.0), 'fg', 'bg'];
            }
        }

        for ($y = 0; $y < $n; $y++) {
            if ($modulestyle === self::STYLE_SQUARE) {
                // Merge horizontal runs into single rectangles to keep the output small.
                $start = null;
                for ($x = 0; $x <= $n; $x++) {
                    $isdark = $x < $n && $dark($x, $y);
                    if ($isdark && $start === null) {
                        $start = $x;
                    } else if (!$isdark && $start !== null) {
                        $shapes[] = ['rect', $q + $start, $q + $y, $x - $start, 1, [0, 0, 0, 0], 'fg', 'bg'];
                        $start = null;
                    }
                }
                continue;
            }
            for ($x = 0; $x < $n; $x++) {
                if (!$dark($x, $y)) {
                    continue;
                }
                if ($modulestyle === self::STYLE_DOTS) {
                    $shapes[] = ['circle', $q + $x + 0.5, $q + $y + 0.5, 0.45, 'fg'];
                    continue;
                }
                // Rounded: round each outer corner that has no dark neighbour on either side.
                $up = $dark($x, $y - 1);
                $down = $dark($x, $y + 1);
                $left = $dark($x - 1, $y);
                $right = $dark($x + 1, $y);
                $radii = [
                    (!$up && !$left) ? 0.5 : 0,
                    (!$up && !$right) ? 0.5 : 0,
                    (!$down && !$right) ? 0.5 : 0,
                    (!$down && !$left) ? 0.5 : 0,
                ];
                $shapes[] = ['rect', $q + $x, $q + $y, 1, 1, $radii, 'fg', 'bg'];
            }
        }

        if ($layout['logo']) {
            [$first, $k] = $layout['logo'];
            $radius = $fancy ? 1.0 : 0;
            $shapes[] = ['rect', $q + $first, $q + $first, $k, $k, array_fill(0, 4, $radius), 'bg', 'bg'];
        }
        return $shapes;
    }

    /**
     * Format a number for SVG output.
     *
     * @param float $value
     * @return string
     */
    private static function num(float $value): string {
        $text = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
        return $text === '-0' ? '0' : $text;
    }

    /**
     * SVG path data for a rectangle with per-corner radii, in pixels.
     *
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @param float[] $r [tl, tr, br, bl]
     * @return string
     */
    private static function rect_path(float $x, float $y, float $w, float $h, array $r): string {
        [$tl, $tr, $br, $bl] = $r;
        $f = [self::class, 'num'];
        $d = 'M' . $f($x + $tl) . ' ' . $f($y) . 'H' . $f($x + $w - $tr);
        if ($tr > 0) {
            $d .= 'A' . $f($tr) . ' ' . $f($tr) . ' 0 0 1 ' . $f($x + $w) . ' ' . $f($y + $tr);
        }
        $d .= 'V' . $f($y + $h - $br);
        if ($br > 0) {
            $d .= 'A' . $f($br) . ' ' . $f($br) . ' 0 0 1 ' . $f($x + $w - $br) . ' ' . $f($y + $h);
        }
        $d .= 'H' . $f($x + $bl);
        if ($bl > 0) {
            $d .= 'A' . $f($bl) . ' ' . $f($bl) . ' 0 0 1 ' . $f($x) . ' ' . $f($y + $h - $bl);
        }
        $d .= 'V' . $f($y + $tl);
        if ($tl > 0) {
            $d .= 'A' . $f($tl) . ' ' . $f($tl) . ' 0 0 1 ' . $f($x + $tl) . ' ' . $f($y);
        }
        return $d . 'Z';
    }

    /**
     * SVG path data for a circle, in pixels.
     *
     * @param float $cx
     * @param float $cy
     * @param float $r
     * @return string
     */
    private static function circle_path(float $cx, float $cy, float $r): string {
        $f = [self::class, 'num'];
        return 'M' . $f($cx - $r) . ' ' . $f($cy) . 'A' . $f($r) . ' ' . $f($r) . ' 0 1 0 ' . $f($cx + $r) . ' ' . $f($cy) .
            'A' . $f($r) . ' ' . $f($r) . ' 0 1 0 ' . $f($cx - $r) . ' ' . $f($cy) . 'Z';
    }

    /**
     * Render the QR code as an SVG document.
     *
     * @return string
     */
    public function svg(): string {
        $layout = $this->layout();
        $m = $layout['module'];
        $o = $layout['origin'];
        $size = $layout['size'];
        $colours = ['fg' => $this->colour, 'bg' => '#ffffff'];

        // Consecutive shapes with the same fill share one path element.
        $elements = [];
        $current = null;
        $d = '';
        foreach ($this->shapes($layout) as $shape) {
            if ($shape[0] === 'circle') {
                [, $cx, $cy, $r, $fill] = $shape;
                $part = self::circle_path($o + $cx * $m, $o + $cy * $m, $r * $m);
            } else {
                [, $x, $y, $w, $h, $radii, $fill] = $shape;
                $part = self::rect_path($o + $x * $m, $o + $y * $m, $w * $m, $h * $m, array_map(fn($r) => $r * $m, $radii));
            }
            if ($fill !== $current && $d !== '') {
                $elements[] = '<path fill="' . $colours[$current] . '" d="' . $d . '"/>';
                $d = '';
            }
            $current = $fill;
            $d .= $part;
        }
        if ($d !== '') {
            $elements[] = '<path fill="' . $colours[$current] . '" d="' . $d . '"/>';
        }

        $rendering = $this->style === self::STYLE_SQUARE ? 'crispEdges' : 'geometricPrecision';
        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="' . $size . '" height="' . $size .
            '" viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="' . $rendering . '">';
        $title = get_string('qrcodefor', 'local_resourceqr', $this->data);
        $svg .= '<title>' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title>';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
        $svg .= implode('', $elements);
        if ($layout['logo'] && $this->logo) {
            [$first, $k] = $layout['logo'];
            $inner = ($k - 2) * $m;
            $pos = $o + (self::QUIET_ZONE + $first + 1) * $m;
            $data = 'data:' . $this->logo->get_mimetype() . ';base64,' . base64_encode($this->logo->get_content());
            $svg .= '<image x="' . $pos . '" y="' . $pos . '" width="' . $inner . '" height="' . $inner .
                '" preserveAspectRatio="xMidYMid meet" href="' . $data . '"/>';
        }
        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Render the QR code as a PNG image.
     *
     * @return string PNG binary data.
     * @throws moodle_exception If neither GD nor Imagick is available.
     */
    public function png(): string {
        \core_php_time_limit::raise(30);
        raise_memory_limit(MEMORY_EXTRA);
        $backend = self::png_backend();
        if ($backend === 'gd') {
            return $this->png_gd();
        }
        if ($backend === 'imagick') {
            return $this->png_imagick();
        }
        throw new moodle_exception('error:nopng', 'local_resourceqr');
    }

    /**
     * PNG using GD. Curved designs are drawn on a larger canvas and scaled down for anti-aliasing.
     *
     * @return string
     */
    private function png_gd(): string {
        $layout = $this->layout();
        $size = $layout['size'];
        $factor = 1;
        if ($this->style !== self::STYLE_SQUARE) {
            $factor = max(1, min(4, intdiv(self::GD_MAX_CANVAS, $size)));
        }
        $m = $layout['module'] * $factor;
        $o = $layout['origin'] * $factor;

        $canvas = imagecreatetruecolor($size * $factor, $size * $factor);
        [$r, $g, $b] = self::rgb($this->colour);
        $colours = [
            'fg' => imagecolorallocate($canvas, $r, $g, $b),
            'bg' => imagecolorallocate($canvas, 255, 255, 255),
        ];
        imagefill($canvas, 0, 0, $colours['bg']);

        foreach ($this->shapes($layout) as $shape) {
            if ($shape[0] === 'circle') {
                [, $cx, $cy, $radius, $fill] = $shape;
                $diameter = (int) round(2 * $radius * $m);
                imagefilledellipse(
                    $canvas,
                    (int) round($o + $cx * $m),
                    (int) round($o + $cy * $m),
                    $diameter,
                    $diameter,
                    $colours[$fill]
                );
                continue;
            }
            [, $x, $y, $w, $h, $radii, $fill, $outside] = $shape;
            $x1 = (int) round($o + $x * $m);
            $y1 = (int) round($o + $y * $m);
            $x2 = (int) round($o + ($x + $w) * $m) - 1;
            $y2 = (int) round($o + ($y + $h) * $m) - 1;
            imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $colours[$fill]);
            // Corners: [corner x, corner y, x direction into the shape, y direction into the shape].
            $corners = [[$x1, $y1, 1, 1], [$x2, $y1, -1, 1], [$x2, $y2, -1, -1], [$x1, $y2, 1, -1]];
            foreach ($corners as $i => [$cx, $cy, $dx, $dy]) {
                $rad = (int) round($radii[$i] * $m);
                if ($rad <= 0) {
                    continue;
                }
                imagefilledrectangle(
                    $canvas,
                    min($cx, $cx + $dx * ($rad - 1)),
                    min($cy, $cy + $dy * ($rad - 1)),
                    max($cx, $cx + $dx * ($rad - 1)),
                    max($cy, $cy + $dy * ($rad - 1)),
                    $colours[$outside]
                );
                imagefilledellipse($canvas, $cx + $dx * $rad, $cy + $dy * $rad, 2 * $rad, 2 * $rad, $colours[$fill]);
            }
        }

        if ($factor > 1) {
            $image = imagecreatetruecolor($size, $size);
            imagecopyresampled($image, $canvas, 0, 0, 0, 0, $size, $size, $size * $factor, $size * $factor);
            imagedestroy($canvas);
        } else {
            $image = $canvas;
        }

        if ($layout['logo'] && $this->logo && $this->logo->get_mimetype() !== 'image/svg+xml') {
            $logo = @imagecreatefromstring($this->logo->get_content());
            if ($logo !== false) {
                [$x, $y, $w, $h] = $this->fit_logo($layout, imagesx($logo), imagesy($logo));
                imagealphablending($image, true);
                imagecopyresampled($image, $logo, $x, $y, 0, 0, $w, $h, imagesx($logo), imagesy($logo));
                imagedestroy($logo);
            }
        }
        ob_start();
        imagepng($image, null, 9);
        $png = ob_get_clean();
        imagedestroy($image);
        return $png;
    }

    /**
     * PNG using Imagick (used only when GD is not available).
     *
     * @return string
     */
    private function png_imagick(): string {
        $layout = $this->layout();
        $m = $layout['module'];
        $o = $layout['origin'];
        $image = new \Imagick();
        $image->newImage($layout['size'], $layout['size'], new \ImagickPixel('#ffffff'));
        $colours = ['fg' => new \ImagickPixel($this->colour), 'bg' => new \ImagickPixel('#ffffff')];
        foreach ($this->shapes($layout) as $shape) {
            $draw = new \ImagickDraw();
            $draw->setStrokeOpacity(0);
            if ($shape[0] === 'circle') {
                [, $cx, $cy, $radius, $fill] = $shape;
                $draw->setFillColor($colours[$fill]);
                $draw->circle($o + $cx * $m, $o + $cy * $m, $o + $cx * $m + $radius * $m, $o + $cy * $m);
            } else {
                [, $x, $y, $w, $h, $radii, $fill] = $shape;
                $draw->setFillColor($colours[$fill]);
                [$tl, $tr, $br, $bl] = array_map(fn($r) => $r * $m, $radii);
                $x1 = $o + $x * $m;
                $y1 = $o + $y * $m;
                $x2 = $x1 + $w * $m;
                $y2 = $y1 + $h * $m;
                $draw->pathStart();
                $draw->pathMoveToAbsolute($x1 + $tl, $y1);
                $draw->pathLineToAbsolute($x2 - $tr, $y1);
                if ($tr > 0) {
                    $draw->pathEllipticArcAbsolute($tr, $tr, 0, false, true, $x2, $y1 + $tr);
                }
                $draw->pathLineToAbsolute($x2, $y2 - $br);
                if ($br > 0) {
                    $draw->pathEllipticArcAbsolute($br, $br, 0, false, true, $x2 - $br, $y2);
                }
                $draw->pathLineToAbsolute($x1 + $bl, $y2);
                if ($bl > 0) {
                    $draw->pathEllipticArcAbsolute($bl, $bl, 0, false, true, $x1, $y2 - $bl);
                }
                $draw->pathLineToAbsolute($x1, $y1 + $tl);
                if ($tl > 0) {
                    $draw->pathEllipticArcAbsolute($tl, $tl, 0, false, true, $x1 + $tl, $y1);
                }
                $draw->pathClose();
                $draw->pathFinish();
            }
            $image->drawImage($draw);
        }
        if ($layout['logo'] && $this->logo) {
            try {
                $logo = new \Imagick();
                $logo->setBackgroundColor(new \ImagickPixel('transparent'));
                $logo->readImageBlob($this->logo->get_content());
                [$x, $y, $w, $h] = $this->fit_logo($layout, $logo->getImageWidth(), $logo->getImageHeight());
                $logo->resizeImage($w, $h, \Imagick::FILTER_LANCZOS, 1);
                $image->compositeImage($logo, \Imagick::COMPOSITE_OVER, $x, $y);
            } catch (\ImagickException $e) {
                // The logo cannot be read (for example SVG without a delegate): leave the area blank.
                debugging('local_resourceqr: could not read site logo: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $image->setImageFormat('png');
        return $image->getImageBlob();
    }

    /**
     * Position and size of a raster logo inside the logo box, keeping its aspect ratio.
     *
     * @param array $layout
     * @param int $width Logo width.
     * @param int $height Logo height.
     * @return int[] [x, y, width, height] in pixels.
     */
    private function fit_logo(array $layout, int $width, int $height): array {
        [$first, $k] = $layout['logo'];
        $m = $layout['module'];
        $inner = ($k - 2) * $m;
        $scale = min($inner / max(1, $width), $inner / max(1, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));
        $start = $layout['origin'] + (self::QUIET_ZONE + $first + 1) * $m;
        $x = $start + intdiv($inner - $w, 2);
        $y = $start + intdiv($inner - $h, 2);
        return [$x, $y, $w, $h];
    }
}
