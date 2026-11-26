<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use ImagickKernel;
use RuntimeException;

/** Color, tonal and convolution filters exposed by phpThumb's fltr array. @internal */
final class PhpThumbEffects
{
    public const FILTERS = ['ds', 'sat', 'clr', 'sep', 'th', 'rcd', 'lvl', 'wb', 'stc',
        'edge', 'emb', 'blur', 'gblr', 'sblr', 'mean', 'smth', 'usm'];

    private const GAUSSIAN = [[1, 2, 1], [2, 4, 2], [1, 2, 1]];

    public static function apply(NativeImage $image, string $filter, array $args, callable $check): NativeImage
    {
        [$a, $b, $c] = array_pad($args, 3, '');
        return match ($filter) {
            'ds', 'sat', 'clr', 'sep' => self::tint($image, $filter, $a, $b, $check),
            'th' => self::threshold($image, $a, $check),
            'rcd' => $image->limitColors((int) PhpThumbRaster::number($a ?: '256', 2, 256), $b === '' || (bool) $b),
            'lvl' => self::levels($image, $a, $b, $c, $check),
            'wb' => self::whiteBalance($image, $a, $check),
            'stc' => self::transparentColor($image, $a, $b, $c, $check),
            'usm' => self::unsharp($image, $a, $b, $c, $check),
            default => self::convolution($image, $filter, $a, $check),
        };
    }

    private static function tint(NativeImage $image, string $filter, string $amount, string $hex, callable $check): NativeImage
    {
        $strength = PhpThumbRaster::number($amount, $filter === 'sep' ? 0 : -100, 100, match ($filter) {
            'clr' => 25, 'sep' => 50, default => 0,
        }) / 100;
        if ($strength === 0.0) {
            return $image;
        }
        if ($filter === 'sat') {
            $strength = -$strength;
        }
        $hex = match ($filter) {
            'sep' => PhpThumbRaster::color($hex, 'a28065'),
            'clr' => PhpThumbRaster::color($hex, '808080'),
            default => '808080',
        };
        [$dr, $dg, $db] = array_map(static fn (int $channel): int => (int) round($channel * $strength), PhpThumbRaster::rgb($hex));
        $image = $image->withPixelAlpha();
        $frame = $image->frame();
        if ($frame instanceof GdImage) {
            imagealphablending($frame, false);
            if (($filter === 'sep' && !imagefilter($frame, IMG_FILTER_GRAYSCALE)) ||
                !imagefilter($frame, IMG_FILTER_COLORIZE, $dr, $dg, $db)) {
                throw new RuntimeException('Cannot tint the thumbnail.');
            }
            return $image;
        }
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a) use ($filter, $dr, $dg, $db): array {
            if ($filter === 'sep') {
                $r = $g = $b = PhpThumbRaster::gray($r, $g, $b);
            }
            return [self::byte($r + $dr), self::byte($g + $dg), self::byte($b + $db), $a];
        }, $check);
    }

    private static function threshold(NativeImage $image, string $value, callable $check): NativeImage
    {
        $cutoff = PhpThumbRaster::number($value ?: '128', 0, 255);
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a) use ($cutoff): array {
            $mono = PhpThumbRaster::gray($r, $g, $b) < $cutoff ? 0 : 255;
            return [$mono, $mono, $mono, $a];
        }, $check);
    }

    /** @return array<string, list<int>> */
    public static function histogram(NativeImage $image, callable $check): array
    {
        $histogram = array_fill_keys(['r', 'g', 'b', 'a', '*'], array_fill(0, 256, 0));
        PhpThumbRaster::read($image, static function (int $r, int $g, int $b, int $a) use (&$histogram): void {
            $histogram['r'][$r]++;
            $histogram['g'][$g]++;
            $histogram['b'][$b]++;
            $histogram['a'][(255 - $a) >> 1]++;
            $histogram['*'][PhpThumbRaster::gray($r, $g, $b)]++;
        }, $check);
        return $histogram;
    }

    private static function levels(NativeImage $image, string $band, string $method, string $threshold, callable $check): NativeImage
    {
        $channel = strtolower($band[0] ?? '*');
        $histogram = self::histogram($image, $check);
        if (!isset($histogram[$channel])) {
            throw new RuntimeException('Invalid thumbnail levels channel.');
        }
        $rgbMode = $method === '0';
        [$width] = $image->dimensions();
        // The legacy levels filter uses the square of the width for the clipping count.
        $clip = $width * $width * PhpThumbRaster::number($threshold, 0, 100, 0.1) / 100;
        [$low, $high] = [0, 255];
        foreach ([false, true] as $reverse) {
            $count = 0;
            for ($step = 0; $step < 256; $step++) {
                $i = $reverse ? 255 - $step : $step;
                $count = $rgbMode ? max($histogram['r'][$i], $histogram['g'][$i], $histogram['b'][$i])
                    : $count + $histogram[$channel][$i];
                if ($count >= $clip) {
                    if ($reverse) {
                        $high = min(255, $i + 1);
                    } else {
                        $low = max(0, $i - 1);
                    }
                    break;
                }
            }
        }
        if ($low >= $high || ($low === 0 && $high === 255)) {
            return $image;
        }
        $scale = 255 / ($high - $low);
        $stretch = static fn (int $value): int => self::byte(($value - $low) * $scale);
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a) use ($channel, $stretch): array {
            return [
                str_contains('*r', $channel) ? $stretch($r) : $r,
                str_contains('*g', $channel) ? $stretch($g) : $g,
                str_contains('*b', $channel) ? $stretch($b) : $b,
                str_contains('*a', $channel) ? 255 - 2 * min(127, $stretch((255 - $a) >> 1)) : $a,
            ];
        }, $check);
    }

    private static function whiteBalance(NativeImage $image, string $hex, callable $check): NativeImage
    {
        if (PhpThumbRaster::color($hex, '') !== '') {
            [$r, $g, $b] = PhpThumbRaster::rgb($hex);
        } else {
            $histogram = self::histogram($image, $check);
            [$r, $g, $b] = array_map(static fn (string $c): int => (int) array_key_last(array_filter($histogram[$c])), ['r', 'g', 'b']);
        }
        $gray = PhpThumbRaster::gray($r, $g, $b);
        [$sr, $sg, $sb] = [$r === 0 ? 1 : $gray / $r, $g === 0 ? 1 : $gray / $g, $b === 0 ? 1 : $gray / $b];
        return PhpThumbRaster::map($image, static fn (int $r, int $g, int $b, int $a): array =>
            [self::byte(round($r * $sr)), self::byte(round($g * $sg)), self::byte(round($b * $sb)), $a], $check);
    }

    private static function transparentColor(NativeImage $image, string $hex, string $min, string $max, callable $check): NativeImage
    {
        if (PhpThumbRaster::color($hex, '') === '') {
            throw new RuntimeException('Invalid thumbnail transparent color.');
        }
        [$kr, $kg, $kb] = PhpThumbRaster::rgb($hex);
        $low = PhpThumbRaster::number($min, 0, 100, 5);
        $high = PhpThumbRaster::number($max, $low, 100, max($low, 10));
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a) use ($kr, $kg, $kb, $low, $high): array {
            $distance = max(abs($r - $kr), abs($g - $kg), abs($b - $kb)) * 100 / 255;
            $opacity = $high === $low ? (int) ($distance > $low) : (int) min($high - $low, max(0, $distance - $low)) / ($high - $low);
            return [$r, $g, $b, (int) round($a * $opacity)];
        }, $check);
    }

    private static function convolution(NativeImage $image, string $filter, string $arg, callable $check): NativeImage
    {
        $image = $image->withPixelAlpha();
        $frame = $image->frame();
        if ($frame instanceof Imagick && in_array($filter, ['edge', 'emb'], true)) {
            return self::biasedConvolution($image, $frame, $filter === 'emb', $check);
        }
        $amount = (int) PhpThumbRaster::number($arg, 0, $filter === 'smth' ? 25 : 50, $filter === 'blur' ? 1 : 0);
        $passes = $filter === 'blur' ? (int) round(2 * PhpThumbRaster::number($arg ?: '1', 0, 50)) : 1;
        if ($filter === 'smth' && $amount === 0) {
            return $image;
        }
        for ($i = 0; $i < $passes; $i++) {
            $check();
            if ($frame instanceof GdImage) {
                imagealphablending($frame, false);
                $success = match ($filter) {
                    'edge' => imagefilter($frame, IMG_FILTER_EDGEDETECT),
                    'emb' => imagefilter($frame, IMG_FILTER_EMBOSS),
                    'blur', 'gblr' => imagefilter($frame, IMG_FILTER_GAUSSIAN_BLUR),
                    'sblr' => imagefilter($frame, IMG_FILTER_SELECTIVE_BLUR),
                    'mean' => imagefilter($frame, IMG_FILTER_MEAN_REMOVAL),
                    'smth' => imagefilter($frame, IMG_FILTER_SMOOTH, $amount),
                };
            } else {
                $channels = Imagick::CHANNEL_RED | Imagick::CHANNEL_GREEN | Imagick::CHANNEL_BLUE;
                $success = match ($filter) {
                    'blur', 'gblr' => $frame->convolveImage(self::kernel(self::GAUSSIAN, 16), $channels),
                    'sblr' => $frame->selectiveBlurImage(1, 0.5, 0.12 * Imagick::getQuantum()),
                    'mean' => $frame->convolveImage(self::kernel([[-1, -1, -1], [-1, 9, -1], [-1, -1, -1]], 1), $channels),
                    'smth' => $frame->convolveImage(self::kernel([[1, 1, 1], [1, $amount, 1], [1, 1, 1]], 8 + $amount), $channels),
                };
            }
            if (!$success) {
                throw new RuntimeException('Cannot apply the thumbnail convolution.');
            }
        }
        return $image;
    }

    /** Keep the signed response until the legacy 127 bias is applied. */
    private static function biasedConvolution(NativeImage $image, Imagick $frame, bool $emboss, callable $check): NativeImage
    {
        [$width, $height] = $image->dimensions();
        PhpThumbRaster::budget((float) $width * $height * 2 + $width * 32);
        $source = clone $frame;
        $top = $bottom = [];
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a, int $x, int $y)
            use ($source, $emboss, $width, $height, &$top, &$bottom): array {
            if ($x === 0) {
                $top = $source->exportImagePixels(0, max(0, $y - 1), $width, 1, 'RGB', Imagick::PIXEL_CHAR);
                $bottom = $source->exportImagePixels(0, min($height - 1, $y + 1), $width, 1, 'RGB', Imagick::PIXEL_CHAR);
                if ($top === false || $bottom === false) {
                    throw new RuntimeException('Cannot read the thumbnail convolution scanline.');
                }
            }
            $left = 3 * max(0, $x - 1);
            $right = 3 * min($width - 1, $x + 1);
            $response = static fn (int $i, int $original): int => self::byte(127 + ($emboss
                ? 1.5 * ($top[$left + $i] - $bottom[$right + $i])
                : 4 * $original - $top[$left + $i] - $top[$right + $i] - $bottom[$left + $i] - $bottom[$right + $i]));
            return [$response(0, $r), $response(1, $g), $response(2, $b), $a];
        }, $check);
    }

    private static function kernel(array $matrix, int $divisor): ImagickKernel
    {
        return ImagickKernel::fromMatrix(array_map(
            static fn (array $row): array => array_map(static fn (int $value): float => $value / $divisor, $row),
            $matrix
        ));
    }

    private static function unsharp(NativeImage $image, string $a, string $r, string $t, callable $check): NativeImage
    {
        $amount = PhpThumbRaster::number($a ?: '80', 0, 500) * 0.016;
        $radius = PhpThumbRaster::number($r ?: '0.5', 0, 50);
        $threshold = PhpThumbRaster::number($t, 0, 255, 3);
        if (round(2 * $radius) === 0.0) {
            return $image;
        }
        [$width, $height] = $image->dimensions();
        PhpThumbRaster::budget((float) $width * $height * 2);
        $image = $image->withPixelAlpha();
        $frame = $image->frame();
        if ($frame instanceof Imagick) {
            $rgb = Imagick::CHANNEL_RED | Imagick::CHANNEL_GREEN | Imagick::CHANNEL_BLUE;
            if (!$frame->unsharpMaskImage(1, 0.65, $amount, $threshold / 255, $rgb)) {
                throw new RuntimeException('Cannot sharpen the thumbnail.');
            }
            return $image;
        }
        $blur = imagecreatetruecolor($width, $height);
        if (!$blur || !imagecopy($blur, $frame, 0, 0, 0, 0, $width, $height) || !imageconvolution($blur, self::GAUSSIAN, 16, 0)) {
            throw new RuntimeException('Cannot construct the thumbnail unsharp mask.');
        }
        return PhpThumbRaster::map($image, static function (int $r, int $g, int $b, int $a, int $x, int $y) use ($blur, $amount, $threshold): array {
            $rgb = imagecolorat($blur, $x, $y);
            $sharpen = static fn (int $source, int $soft): int => abs($source - $soft) >= $threshold
                ? self::byte($source + $amount * ($source - $soft)) : $source;
            return [$sharpen($r, ($rgb >> 16) & 255), $sharpen($g, ($rgb >> 8) & 255), $sharpen($b, $rgb & 255), $a];
        }, $check);
    }

    private static function byte(float $value): int
    {
        return (int) min(255, max(0, $value));
    }
}

