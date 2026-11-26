<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use ImagickDraw;
use RuntimeException;

/** Spatial filters that use alpha masks, drawing and compositing. @internal */
final class PhpThumbDecorations
{
    public const FILTERS = ['hist', 'ric', 'elip', 'mask', 'bvl', 'fram', 'drop', 'bord', 'over', 'wmt'];

    public static function apply(NativeImage $image, string $filter, array $args, string $format, callable $check): NativeImage
    {
        [$a, $b, $c, $d, $e] = array_pad($args, 5, '');
        return match ($filter) {
            'ric', 'elip' => self::shape($image, $filter, $a, $b, $check),
            'mask' => self::mask($image, self::openOverlay($image, $a, $format), (bool) $b, $check),
            'bvl', 'fram' => self::frame($image, $filter, $a, $b, $c, $d, $e, $check),
            'drop' => self::shadow($image, $a, $b, $c, $d, $e, $check),
            'bord' => self::border($image, $a, $b, $c, $d, $check),
            'over' => self::overlay($image, self::openOverlay($image, $a, $format), (bool) $b, $c, $d),
            'hist' => self::histogram($image, $args, $check),
            'wmt' => PhpThumbText::apply($image, $args, $check),
        };
    }

    /** Resolve and validate file-backed filters before cache lookup and again before publication. */
    public static function asset(string $file, bool $font = false): string
    {
        $base = rtrim(str_replace('\\', '/', EVO_BASE_PATH), '/') . '/';
        $relative = str_starts_with($file, $base) ? substr($file, strlen($base)) : (str_starts_with($file, '/') ? substr($file, 1) : $file);
        $path = GalleryFiles::local(rawurldecode($relative));
        if ($path === null) {
            throw new RuntimeException('Invalid local thumbnail filter asset.');
        }

        if ($font) {
            $header = @file_get_contents($path, false, null, 0, 4);
            if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['ttf', 'otf', 'ttc'], true) ||
                !in_array($header, ["\0\1\0\0", 'OTTO', 'true', 'ttcf'], true)) {
                throw new RuntimeException('Invalid local thumbnail font.');
            }
        } elseif (ImageAnimation::isAnimated($path, @getimagesize($path)[2] ?? 0)) {
            throw new RuntimeException('Thumbnail filters require still assets.');
        }
        return $path;
    }

    public static function openOverlay(NativeImage $image, string $file, string $format): NativeImage
    {
        $path = self::asset($file);
        $root = realpath(EVO_BASE_PATH) . DIRECTORY_SEPARATOR;
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        $driver = $image->frame() instanceof Imagick ? 'imagick' : 'gd';
        return NativeImage::open($relative, $format, $driver)->withPixelAlpha();
    }

    private static function mask(NativeImage $image, NativeImage $mask, bool $invert, callable $check): NativeImage
    {
        [$width, $height] = $image->dimensions();
        PhpThumbRaster::budget((float) $width * $height * 2);
        $frame = $mask->fit('stretch', $width, $height)->frame();
        $hasAlpha = $frame instanceof Imagick && $frame->getImageAlphaChannel();
        $row = [];
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a, int $x, int $y) use ($frame, $width, $invert, $hasAlpha, &$row): array {
            if ($frame instanceof GdImage) {
                $pixel = imagecolorat($frame, $x, $y);
                $gray = PhpThumbRaster::gray(($pixel >> 16) & 255, ($pixel >> 8) & 255, $pixel & 255);
                $opacity = 1 - (($pixel >> 24) / 127);
            } else {
                if ($x === 0) {
                    $row = $frame->exportImagePixels(0, $y, $width, 1, 'RGBA', Imagick::PIXEL_CHAR);
                    if ($row === false) {
                        throw new RuntimeException('Cannot read thumbnail mask pixels.');
                    }
                }
                $offset = $x * 4;
                $gray = PhpThumbRaster::gray($row[$offset], $row[$offset + 1], $row[$offset + 2]);
                $opacity = $hasAlpha ? $row[$offset + 3] / 255 : 1;
            }
            return [$r, $g, $b, (int) round($a * ($invert ? 1 - $gray / 255 : $gray / 255) * $opacity)];
        }, $check);
    }

    private static function shape(NativeImage $image, string $type, string $x, string $y, callable $check): NativeImage
    {
        [$width, $height] = $image->dimensions();
        $rx = $type === 'elip' ? max(0.5, ($width - 1) / 2) : PhpThumbRaster::number($x, 0, $width / 2);
        $ry = $type === 'elip' ? max(0.5, ($height - 1) / 2) : PhpThumbRaster::number($y, 0, $height / 2);
        if ($rx < 0.5 || $ry < 0.5) {
            return $image;
        }
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a, int $x, int $y) use ($width, $height, $rx, $ry): array {
            $left = min($x + 0.5, $width - $x - 0.5);
            $top = min($y + 0.5, $height - $y - 0.5);
            if ($left >= $rx || $top >= $ry) {
                return [$r, $g, $b, $a];
            }
            $covered = 0;
            foreach ([-0.25, 0.25] as $dx) {
                foreach ([-0.25, 0.25] as $dy) {
                    $covered += (int) (((($rx - $left + $dx) / $rx) ** 2) + ((($ry - $top + $dy) / $ry) ** 2) <= 1);
                }
            }
            return [$r, $g, $b, (int) round($a * $covered / 4)];
        }, $check);
    }

    private static function frame(NativeImage $image, string $type, string $a, string $b, string $c, string $d, string $e, callable $check): NativeImage
    {
        $image = $image->withPixelAlpha();
        [$width, $height] = $image->dimensions();
        $max = (int) ceil(min($width, $height) / 2);
        $size = (int) PhpThumbRaster::number($a ?: '5', 0, $max);
        $edge = $type === 'bvl' ? 0 : (int) PhpThumbRaster::number($b ?: '1', 0, $max);
        $light = PhpThumbRaster::color($type === 'bvl' ? $b : $d, 'ffffff');
        $dark = PhpThumbRaster::color($type === 'bvl' ? $c : $e);
        $neutral = PhpThumbRaster::color($c, 'cccccc');
        $paint = self::painter($image);
        for ($i = 0; $i < min($max, $size + 2 * $edge); $i++) {
            $check();
            $inner = $i >= $edge + $size;
            $face = $type === 'fram' && !$inner && $i >= $edge;
            $opacity = $type === 'bvl' ? 1 - $i / $size : 1;
            $paint($i, $i, $width - 1 - $i, $i, $face ? $neutral : ($inner ? $dark : $light), $opacity);
            $paint($i, $i + 1, $i, $height - 1 - $i, $face ? $neutral : ($inner ? $dark : $light), $opacity);
            $paint($width - 1 - $i, $i + 1, $width - 1 - $i, $height - 1 - $i, $face ? $neutral : ($inner ? $light : $dark), $opacity);
            $paint($i, $height - 1 - $i, $width - 1 - $i, $height - 1 - $i, $face ? $neutral : ($inner ? $light : $dark), $opacity);
        }
        return $image;
    }

    private static function border(NativeImage $image, string $width, string $x, string $y, string $hex, callable $check): NativeImage
    {
        [$w, $h] = $image->dimensions();
        $border = (int) PhpThumbRaster::number($width ?: '1', 0, min($w, $h) / 2);
        $rx = PhpThumbRaster::number($x, 0, $w / 2);
        $ry = PhpThumbRaster::number($y, 0, $h / 2);
        $inset = $image->fit('contain', max(1, $w - max(2 * $border, (int) $rx)), max(1, $h - max(2 * $border, (int) $ry)));
        [$iw, $ih] = $inset->dimensions();
        $canvas = PhpThumbRaster::canvas($image, $w, $h);
        $paint = self::painter($canvas);
        $hex = PhpThumbRaster::color($hex);
        $paint(0, 0, $w - 1, $h - 1, $hex, 1);
        $canvas = Watermark::composite($canvas, $inset->frame(), intdiv($w - $iw, 2), intdiv($h - $ih, 2));
        return self::shape($canvas, 'ric', (string) $rx, (string) $ry, $check);
    }

    private static function shadow(NativeImage $image, string $distance, string $width, string $hex, string $angle, string $fade, callable $check): NativeImage
    {
        $step = PhpThumbRaster::number($distance ?: '10', 0, PHP_INT_MAX / 8);
        if ($step === 0.0) {
            return $image;
        }
        $spread = (int) PhpThumbRaster::number($width ?: '10', 1, PHP_INT_MAX / 8);
        $radians = deg2rad(PhpThumbRaster::number($angle ?: '225', -360, 360));
        $opacity = PhpThumbRaster::number($fade === '' ? '100' : $fade, 0, 100) / 100;
        [$r, $g, $b] = PhpThumbRaster::rgb(PhpThumbRaster::color($hex));
        [$w, $h] = $image->dimensions();
        $dx = (int) (-cos($radians) * ($step + $spread - 1));
        $dy = (int) (-sin($radians) * ($step + $spread - 1));
        $cw = $w + abs($dx);
        $ch = $h + abs($dy);
        PhpThumbRaster::budget((float) $cw * $ch + (float) $w * $h);
        $copy = $image->manualCrop(0, 0, $w, $h);
        $shade = PhpThumbRaster::map($copy, static fn (int $_r, int $_g, int $_b, int $a): array =>
            [$r, $g, $b, (int) round($a * $opacity)], $check);
        $canvas = PhpThumbRaster::canvas($image, $cw, $ch);
        $canvas = Watermark::composite($canvas, $shade->frame(), $dx, $dy);
        return Watermark::composite($canvas, $image->frame(), 0, 0)->fit('stretch', $w, $h);
    }

    private static function overlay(NativeImage $image, NativeImage $overlay, bool $under, string $margin, string $opacity): NativeImage
    {
        [$w, $h] = $image->dimensions();
        $margin = PhpThumbRaster::number($margin, -max($w, $h), max($w, $h), $under ? 0.1 : 0);
        $x = abs($margin) < 1 ? max(-0.499, min(0.499, $margin)) * $w : $margin;
        $y = abs($margin) < 1 ? max(-0.499, min(0.499, $margin)) * $h : $margin;
        $iw = max(1, $w - (int) round(2 * $x));
        $ih = max(1, $h - (int) round(2 * $y));
        $base = $under ? $overlay->fit('stretch', $w, $h) : $image;
        $stamp = ($under ? $image : $overlay)->fit('stretch', $iw, $ih)->withPixelAlpha();
        Watermark::setNativeOpacity($stamp->frame(), (int) PhpThumbRaster::number($opacity, 0, 100, 100));
        return Watermark::composite($base, $stamp->frame(), (int) round(($w - $iw) / 2), (int) round(($h - $ih) / 2));
    }

    private static function histogram(NativeImage $image, array $args, callable $check): NativeImage
    {
        [$bands, $colors, $width, $height, $align, $opacity, $mx, $my] = array_pad($args, 8, '');
        $bins = PhpThumbEffects::histogram($image, $check);
        $palette = ['r' => 'ff0000', 'g' => '00ff00', 'b' => '0000ff', 'a' => '999999', '*' => 'ffffff'];
        $colors = explode(';', $colors);
        $chart = PhpThumbRaster::canvas($image, 256, 100);
        $paint = self::painter($chart);
        foreach (array_unique(str_split(strtolower($bands ?: '*'))) as $i => $band) {
            if (!isset($bins[$band])) {
                continue;
            }
            $color = PhpThumbRaster::color($colors[$i] ?? '', $palette[$band]);
            $peak = max($bins[$band]);
            foreach ($bins[$band] as $x => $n) {
                $check();
                $paint($x, max(0, 99 - (int) round($n / $peak * 100)), $x, 99, $color, 1);
            }
            $paint(0, 98, 255, 99, $color, 1);
        }
        [$w, $h] = $image->dimensions();
        $width = PhpThumbRaster::number($width ?: '0.25', 0, $w);
        $cw = max(1, (int) round($width > 1 ? $width : $w * $width));
        // phpThumb's GD chart is square and both sides are derived from width.
        $chart = $chart->fit('stretch', $cw, $cw);
        return self::place($image, $chart, $align ?: 'BR', (int) PhpThumbRaster::number($opacity ?: '50', 0, 100),
            (int) PhpThumbRaster::number($mx ?: '5', 0, $w), (int) PhpThumbRaster::number($my, 0, $h), $check);
    }

    public static function place(NativeImage $image, NativeImage $overlay, string $align, int $opacity, int $mx, int $my, callable $check): NativeImage
    {
        [$w, $h] = $image->dimensions();
        [$ow, $oh] = $overlay->dimensions();
        $overlay = $overlay->withPixelAlpha();
        Watermark::setNativeOpacity($overlay->frame(), $opacity);
        if (preg_match('/\A(-?\d+)x(-?\d+)\z/i', $align, $coordinates)) {
            return Watermark::composite($image, $overlay->frame(), (int) $coordinates[1], (int) $coordinates[2]);
        }
        if ($align === '*') {
            for ($y = $my; $y < $h; $y += max(1, $oh + $my)) {
                for ($x = $mx; $x < $w; $x += max(1, $ow + $mx)) {
                    $check();
                    $image = Watermark::composite($image, $overlay->frame(), $x, $y);
                }
            }
            return $image;
        }
        $align = strtoupper($align);
        $x = match (true) {
            str_contains($align, 'L') => $mx,
            str_contains($align, 'R') => $w - $ow - $mx,
            default => (int) round(($w - $ow) / 2),
        };
        $y = match (true) {
            str_contains($align, 'T') => $my,
            str_contains($align, 'B') => $h - $oh - $my,
            default => (int) round(($h - $oh) / 2),
        };
        return Watermark::composite($image, $overlay->frame(), $x, $y);
    }

    /** Draw a line or an axis-aligned filled rectangle with either backend. */
    public static function painter(NativeImage $image): callable
    {
        $frame = $image->frame();
        if ($frame instanceof GdImage) {
            imagealphablending($frame, true);
            return static function (int $x, int $y, int $right, int $bottom, string $hex, float $opacity) use ($frame): void {
                imagefilledrectangle($frame, $x, $y, $right, $bottom, PhpThumbRaster::gdColor($hex, $opacity));
            };
        }
        return static function (int $x, int $y, int $right, int $bottom, string $hex, float $opacity) use ($frame): void {
            $draw = new ImagickDraw();
            $color = PhpThumbRaster::paint($hex, $opacity);
            if ($x === $right || $y === $bottom) {
                $draw->setStrokeColor($color);
                $draw->setStrokeAntialias(false);
                $draw->line($x, $y, $right, $bottom);
            } else {
                $draw->setFillColor($color);
                $draw->rectangle($x, $y, $right, $bottom);
            }
            if (!$frame->drawImage($draw)) {
                throw new RuntimeException('Cannot draw the thumbnail decoration.');
            }
        };
    }
}

