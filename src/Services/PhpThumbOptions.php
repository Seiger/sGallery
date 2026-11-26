<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use RuntimeException;

/** Normalize the options accepted by Evolution CMS's thumbnail helper. @internal */
final class PhpThumbOptions
{
    public const FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'wbmp'];

    private const DIMENSIONS = ['w', 'h', 'wp', 'hp', 'wl', 'hl', 'ws', 'hs', 'maxb'];
    private const FRACTIONS = ['sx', 'sy', 'sw', 'sh'];
    private const POSITIONS = [
        'TL' => 'topLeft', 'T' => 'top', 'TR' => 'topRight',
        'L' => 'left', 'C' => 'center', 'R' => 'right',
        'BL' => 'bottomLeft', 'B' => 'bottom', 'BR' => 'bottomRight',
    ];

    private function __construct(private readonly array $values, private readonly array $filters)
    {
    }

    /** Accept both option separators and the legacy key_value spelling. */
    public static function parse(string $options): self
    {
        $values = [];
        $filters = [];
        foreach (explode('&', str_replace(',', '&', $options)) as $option) {
            $separator = strpos($option, '=');
            $legacy = strpos($option, '_');
            if ($legacy !== false && ($separator === false || $legacy < $separator)) {
                $separator = $legacy;
            }
            if ($separator === false) {
                continue;
            }

            $key = trim(urldecode(substr($option, 0, $separator)));
            $value = trim(urldecode(substr($option, $separator + 1)));
            if (preg_match('/\Afltr(?:\[\d*\]|\{\d*\})\z/', $key)) {
                if ($value !== '') {
                    $filters[] = $value;
                }
            } elseif (in_array($key, self::DIMENSIONS, true)) {
                $values[$key] = GalleryFiles::id($value);
            } elseif (in_array($key, self::FRACTIONS, true)) {
                $values[$key] = is_numeric($value) && (float) $value > 0 && (float) $value < PHP_INT_MAX
                    ? (float) $value : null;
            } else {
                $values[$key] = match ($key) {
                    'f' => in_array(strtolower($value), self::FORMATS, true) ? strtolower($value) : null,
                    'q' => is_numeric($value) && is_finite((float) $value) ? (int) max(1, min(100, (float) $value)) : null,
                    'far', 'zc' => self::position($value),
                    'ra' => is_numeric($value) && is_finite((float) $value) ? fmod((float) $value, 360) : null,
                    'ar' => in_array($value, ['x', 'l', 'L', 'p', 'P'], true) ? $value : null,
                    'aoe', 'iar', 'xto' => $value === '1' ? 1 : null,
                    'bg', 'bc' => self::color($value),
                    'ica' => preg_match('/\A(?:[0-4]|5\|(?:0?(?:\.\d+)?|1(?:\.0+)?)\|[a-f0-9]{6})\z/iD', $value)
                        ? strtolower($value) : null,
                    // bgt and dpi have no effect on supported raster inputs.
                    default => null,
                };
            }
        }
        $values = array_filter($values, static fn ($value): bool => $value !== null && $value !== 0.0);
        ksort($values);
        return new self($values, $filters);
    }

    /** Store a canonical query so nested filter order survives sTask JSON persistence. */
    public function query(): string
    {
        return http_build_query($this->values + ['fltr' => $this->filters], '', '&', PHP_QUERY_RFC3986);
    }

    public function format(string $extension): string
    {
        return $this->values['f'] ?? self::defaultFormat($extension);
    }

    public function quality(string $extension): int
    {
        return $this->values['q'] ?? (self::defaultFormat($extension) === 'jpg' ? 85 : 75);
    }

    private static function defaultFormat(string $extension): string
    {
        return in_array($extension, ['png', 'gif', 'jpeg'], true) ? $extension : 'jpg';
    }

    public function embeddedOnly(): bool
    {
        return isset($this->values['xto']);
    }

    public function byteTarget(): ?int
    {
        return $this->values['maxb'] ?? null;
    }

    /** Include each local overlay revision in the cache key and queued job. */
    public function assetRevision(): string
    {
        $revisions = [];
        foreach ($this->filters as $filter) {
            if (str_starts_with($filter, 'wmi|')) {
                $parts = explode('|', $filter);
                $path = GalleryFiles::local(self::overlayPath($parts[1] ?? ''));
                $revision = $path === null ? null : ImageSource::version($path);
                if ($revision === null || ImageAnimation::isAnimated($path, @getimagesize($path)[2] ?? 0)) {
                    throw new RuntimeException('Invalid local thumbnail watermark.');
                }
                $revisions[] = $revision;
            }
        }
        return hash('sha256', json_encode($revisions, JSON_THROW_ON_ERROR));
    }

    /** Estimate the requested output before decoding; individual operations check their own buffers. */
    public function estimatedPixels(int $sourceWidth, int $sourceHeight): float
    {
        $pixels = (float) $sourceWidth * $sourceHeight;
        foreach ([[$sourceWidth, $sourceHeight], [$sourceHeight, $sourceWidth]] as [$w, $h]) {
            [$width, $height] = $this->bounds($w, $h);
            $width ??= $height === null ? $w : ceil($height * ($w / $h));
            $height ??= ceil($width * ($h / $w));
            $pixels = max($pixels, (float) $width * $height);
        }
        return $pixels;
    }

    /** Only ar=x requests the source's EXIF orientation. Explicit rotation takes precedence. */
    public function orientation(int $exifOrientation): int
    {
        return !isset($this->values['ra']) && ($this->values['ar'] ?? null) === 'x' ? $exifOrientation : 1;
    }

    /** Apply the compatibility geometry and ordered filters to a decoded frame. */
    public function transform(NativeImage $image, string $format, string $baseFormat): NativeImage
    {
        if ($this->embeddedOnly()) {
            return $image;
        }
        [$sourceWidth, $sourceHeight] = $image->dimensions();
        [$width, $height] = $this->bounds($sourceWidth, $sourceHeight);
        $background = $this->values['bg'] ?? null;
        $rotation = $this->values['ra'] ?? match ($this->values['ar'] ?? null) {
            'l' => $sourceWidth < $sourceHeight ? -90 : 0,
            'L' => $sourceWidth < $sourceHeight ? 90 : 0,
            'p' => $sourceWidth > $sourceHeight ? 90 : 0,
            'P' => $sourceWidth > $sourceHeight ? -90 : 0,
            default => 0,
        };
        if ($rotation != 0) {
            $image = $image->rotate($rotation, $background);
        }
        if (isset($this->values['ica'])) {
            $image = $this->trim($image);
        }
        [$sourceWidth, $sourceHeight] = $image->dimensions();
        if (array_intersect(self::FRACTIONS, array_keys($this->values))) {
            $x = self::pixels($this->values['sx'] ?? 0, $sourceWidth);
            $y = self::pixels($this->values['sy'] ?? 0, $sourceHeight);
            $cropWidth = self::pixels($this->values['sw'] ?? $sourceWidth, $sourceWidth);
            $cropHeight = self::pixels($this->values['sh'] ?? $sourceHeight, $sourceHeight);
            $image = $image->manualCrop($x, $y, max(1, $cropWidth), max(1, $cropHeight));
        }

        $alphaOutput = in_array($baseFormat, ['png', 'webp', 'gif', 'avif', 'ico'], true);
        $image = $this->fit($image, $width, $height, $background ?? ($alphaOutput ? null : 'ffffff'));
        foreach ($this->filters as $filter) {
            $image = $this->filter($image, $filter, $format);
        }
        if (!$alphaOutput || $background !== null) {
            [$width, $height] = $image->dimensions();
            $image = $image->onCanvas($width, $height, 0, 0, $background ?? 'ffffff');
        }
        return $image;
    }

    private function bounds(int $width, int $height): array
    {
        $order = match ($width <=> $height) {
            -1 => ['p', '', 's', 'l'],
            1 => ['l', '', 's', 'p'],
            default => ['s', '', 'l', 'p'],
        };
        $bounds = [];
        foreach (['w', 'h'] as $dimension) {
            $bound = null;
            foreach ($order as $suffix) {
                $bound = $this->values[$dimension . $suffix] ?? null;
                if ($bound !== null) {
                    break;
                }
            }
            $bounds[] = $bound;
        }
        return $bounds;
    }

    private function fit(NativeImage $image, ?int $width, ?int $height, ?string $background): NativeImage
    {
        [$sourceWidth, $sourceHeight] = $image->dimensions();
        if ($width === null && $height === null) {
            return $image;
        }
        if ($width !== null && $height !== null) {
            if (isset($this->values['zc'])) {
                $aspect = $width / $height;
                $cropWidth = min($sourceWidth, max(1, (int) round($sourceHeight * $aspect)));
                $cropHeight = min($sourceHeight, max(1, (int) round($sourceWidth / $aspect)));
                [$x, $y] = self::offset($this->values['zc'], $sourceWidth - $cropWidth, $sourceHeight - $cropHeight);
                return $image->manualCrop($x, $y, $cropWidth, $cropHeight)->fit('stretch', $width, $height);
            }
            if (isset($this->values['iar'])) {
                return $image->fit('stretch', $width, $height);
            }
        }
        $aspect = $sourceWidth / $sourceHeight;
        $maxWidth = $width ?? INF;
        $maxHeight = $height ?? INF;
        if (!isset($this->values['aoe'])) {
            $maxWidth = min($maxWidth, $sourceWidth);
            $maxHeight = min($maxHeight, $sourceHeight);
        }
        $scaledWidth = max(1, (int) floor(min($maxWidth, $maxHeight * $aspect)));
        $scaledHeight = max(1, (int) floor(min($maxHeight, $maxWidth / $aspect)));
        $image = $image->fit('stretch', $scaledWidth, $scaledHeight);
        if ($width !== null && $height !== null && isset($this->values['far'])) {
            [$x, $y] = self::offset($this->values['far'], $width - $scaledWidth, $height - $scaledHeight);
            $image = $image->onCanvas($width, $height, $x, $y, $this->values['bc'] ?? $background);
        }
        return $image;
    }

    /** Page orientation uses a fixed alignment for both crop and padding. */
    private static function offset(string $position, int $width, int $height): array
    {
        [$x, $y] = NativeImage::CROP_POSITIONS[self::POSITIONS[$position]];
        return [(int) round($x * $width), (int) round($y * $height)];
    }

    private static function position(string $value): ?string
    {
        $value = strtoupper($value);
        return $value === '1' ? 'C' : (isset(self::POSITIONS[$value]) ? $value : null);
    }

    private static function color(string $value): ?string
    {
        $value = ltrim($value, '#');
        return preg_match('/\A[a-f0-9]{6}\z/iD', $value) ? strtolower($value) : null;
    }

    private static function pixels(float|int $value, int $length): int
    {
        return (int) round($value > 0 && $value < 1 ? $value * $length : $value);
    }

    private function trim(NativeImage $image): NativeImage
    {
        [$mode, $threshold, $hex] = array_pad(explode('|', $this->values['ica']), 3, null);
        if ($mode === '5' && (float) $threshold === 0.0) {
            return $image;
        }
        $frame = $image->frame();
        if ($frame instanceof GdImage) {
            $color = $hex === null ? -1 : (int) hexdec($hex);
            if ($hex !== null && !imageistruecolor($frame)) {
                $color = imagecolorclosest($frame, $color >> 16, ($color >> 8) & 255, $color & 255);
            }
            $trimmed = imagecropauto($frame, (int) $mode, (float) $threshold, $color);
            return $trimmed === false ? $image : $image->withFrame($trimmed);
        }

        $frame = clone $frame;
        $background = match ($mode) {
            '1' => 'transparent', '2' => 'black', '3' => 'white', '5' => '#' . $hex,
            default => null,
        };
        if ($background !== null && !$frame->borderImage($background, 1, 1)) {
            throw new RuntimeException('Cannot set the thumbnail trim color.');
        }
        if (!$frame->trimImage((float) $threshold * Imagick::getQuantumRange()['quantumRangeLong']) ||
            !$frame->setImagePage(0, 0, 0, 0)) {
            throw new RuntimeException('Cannot trim the thumbnail.');
        }
        return $image->withFrame($frame);
    }

    private function filter(NativeImage $image, string $filter, string $format): NativeImage
    {
        [$operation, $a, $b, $c, $d, $e, $f] = array_pad(explode('|', $filter), 7, '');
        return match ($operation) {
            'rot' => $image->rotate(self::number($a, -360, 360), self::color($b)),
            'flip' => $image->orientFromExif(match ($a) {
                'x' => 2, 'y' => 4, default => throw new RuntimeException('Invalid thumbnail flip axis.'),
            }),
            'gray', 'neg', 'brit', 'cont', 'gam' => $this->colorFilter($image, $operation, $a),
            'crop' => $this->cropMargins($image, $a, $b, $c, $d),
            'size' => $this->resizeFilter($image, $a, $b, (bool) $c),
            'wmi' => $this->watermark($image, $format, $a, $b, $c, $d, $e, $f),
            default => throw new RuntimeException('Unsupported thumbnail filter.'),
        };
    }

    private function cropMargins(NativeImage $image, string $left, string $right, string $top, string $bottom): NativeImage
    {
        [$width, $height] = $image->dimensions();
        $x = min($width - 1, self::pixels(self::number($left, 0, $width), $width));
        $y = min($height - 1, self::pixels(self::number($top, 0, $height), $height));
        return $image->manualCrop($x, $y, max(1, $width - $x - self::pixels(self::number($right, 0, $width), $width)),
            max(1, $height - $y - self::pixels(self::number($bottom, 0, $height), $height)));
    }

    private function resizeFilter(NativeImage $image, string $width, string $height, bool $stretch): NativeImage
    {
        [$w, $h] = $image->dimensions();
        $targetWidth = self::pixels(self::number($width, 0, PHP_INT_MAX / 2), $w);
        $targetHeight = self::pixels(self::number($height, 0, PHP_INT_MAX / 2), $h);
        if (!$targetWidth && !$targetHeight) {
            return $image;
        }
        return $image->fit($stretch ? 'stretch' : 'contain', $targetWidth ?: $w, $targetHeight ?: $h);
    }

    private function colorFilter(NativeImage $image, string $operation, string $arg): NativeImage
    {
        $frame = $image->frame();
        $amount = self::number($arg, -255, 255);
        $percent = $amount * 100 / 255;
        if ($frame instanceof GdImage) {
            $success = match ($operation) {
                'gray' => imagefilter($frame, IMG_FILTER_GRAYSCALE),
                'neg' => imagefilter($frame, IMG_FILTER_NEGATE),
                'brit' => imagefilter($frame, IMG_FILTER_BRIGHTNESS, (int) $amount),
                'cont' => imagefilter($frame, IMG_FILTER_CONTRAST, -(int) round($percent)),
                'gam' => imagegammacorrect($frame, 1.0, self::number($arg, 0.01, 10, 1)),
            };
        } else {
            $success = match ($operation) {
                'gray' => $frame->modulateImage(100, 0, 100),
                'neg' => $frame->negateImage(false),
                'brit' => $frame->brightnessContrastImage($percent, 0),
                'cont' => $frame->brightnessContrastImage(0, $percent),
                'gam' => $frame->gammaImage(self::number($arg, 0.01, 10, 1)),
            };
        }
        if (!$success) {
            throw new RuntimeException('Cannot apply the thumbnail filter.');
        }
        return $image;
    }

    /** Decode a local still watermark and position it with phpThumb's independent margins. */
    private function watermark(NativeImage $image, string $format, string $file, string $align, string $opacity, string $x, string $y, string $angle): NativeImage
    {
        $source = self::overlayPath($file);
        $frame = $image->frame();
        $stamp = NativeImage::open($source, $format, $frame instanceof Imagick ? 'imagick' : 'gd')->withPixelAlpha();
        if (($rotation = self::number($angle, -360, 360)) !== 0.0) {
            $stamp = $stamp->rotate($rotation);
        }
        [$width, $height] = $image->dimensions();
        [$stampWidth, $stampHeight] = $stamp->dimensions();
        if (preg_match('/\A(-?\d+)x(-?\d+)\z/D', $align, $coordinates)) {
            if ($x !== '' || $y !== '') {
                $stamp = $this->resizeFilter($stamp, $x, $y, false);
                [$stampWidth, $stampHeight] = $stamp->dimensions();
            }
            $left = (int) self::number($coordinates[1], -$stampWidth, $width) - ($x !== '' || $y !== '' ? intdiv($stampWidth, 2) : 0);
            $top = (int) self::number($coordinates[2], -$stampHeight, $height) - ($x !== '' || $y !== '' ? intdiv($stampHeight, 2) : 0);
        } else {
            $position = self::position($align === '' ? 'BR' : $align);
            if ($position === null) {
                throw new RuntimeException('Unsupported thumbnail watermark position.');
            }
            [$anchorX, $anchorY] = NativeImage::CROP_POSITIONS[self::POSITIONS[$position]];
            $paddingX = self::pixels(self::number($x, 0, $width), $width);
            $paddingY = self::pixels(self::number($y, 0, $height), $height);
            $left = (int) round(($width - $stampWidth) * $anchorX + ($anchorX === 1 ? -$paddingX : $paddingX));
            $top = (int) round(($height - $stampHeight) * $anchorY + ($anchorY === 1 ? -$paddingY : $paddingY));
        }
        $stampFrame = $stamp->frame();
        Watermark::setNativeOpacity($stampFrame, (int) self::number($opacity, 0, 100, 100));
        return Watermark::composite($image, $stampFrame, $left, $top);
    }

    private static function overlayPath(string $file): string
    {
        return str_starts_with($file, EVO_BASE_PATH) ? substr($file, strlen(EVO_BASE_PATH)) : ltrim($file, '/');
    }

    private static function number(string $value, float $min, float $max, float $default = 0): float
    {
        return is_numeric($value) && is_finite((float) $value) ? max($min, min($max, (float) $value)) : $default;
    }
}

