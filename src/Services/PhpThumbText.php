<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use ImagickDraw;
use RuntimeException;

/** Render the legacy text watermark using a checked local font or a built-in face. @internal */
final class PhpThumbText
{
    public static function apply(NativeImage $image, array $args, callable $check): NativeImage
    {
        $font = $args[4] ?? '';
        if ($font === '') {
            return self::render($image, $args, null, $check);
        }
        $source = PhpThumbDecorations::asset($font, true);
        if (!ImageMemoryBudget::allows(0, (int) @filesize($source), PHP_SAPI === 'cli')) {
            throw new RuntimeException('Insufficient memory budget for the thumbnail font.');
        }

        // FreeType can hold a mapping of the font after rendering. Give it a private snapshot.
        $path = @tempnam(sys_get_temp_dir(), 'sg-font-');
        if ($path === false) {
            throw new RuntimeException('Cannot reserve a thumbnail font snapshot.');
        }
        try {
            if (!@copy($source, $path)) {
                throw new RuntimeException('Cannot copy the local thumbnail font.');
            }
            return self::render($image, $args, $path, $check);
        } finally {
            unlink($path);
        }
    }

    private static function render(NativeImage $image, array $args, ?string $path, callable $check): NativeImage
    {
        [$text, $size, $align, $color, , $opacity, $margin, $angle, $bg, $bgOpacity, $extend, $leading] =
            array_pad($args, 12, '');
        if ($text === '') {
            return $image;
        }
        [$w, $h] = $image->dimensions();
        $text = strtr($text, $image->sourceMetrics() + ['^x' => $w, '^y' => $h, '^^' => '^']);
        if (strlen($text) > 4096 || str_contains($text, "\0")) {
            throw new RuntimeException('Invalid thumbnail watermark text.');
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $color = PhpThumbRaster::color($color);
        $opacity = (int) PhpThumbRaster::number($opacity, 0, 100, 50);
        $margin = (int) PhpThumbRaster::number($margin, 0, max($w, $h), 5);
        $leading = PhpThumbRaster::number($leading, 0.01, 100, 1);
        $align = strtoupper($align ?: 'BR');
        $lineAlignment = $align;
        if (preg_match('/\A(-?\d+)[xX](-?\d+)(?:@([LCR]))?\z/', $align, $position)) {
            $align = $position[1] . 'x' . $position[2];
            $lineAlignment = $position[3] ?? 'L';
        }

        $imagick = $image->frame() instanceof Imagick;
        $gd = function_exists('imagecreatetruecolor') && ($path === null || function_exists('imagettfbbox'));
        if (!$gd && !$imagick) {
            throw new RuntimeException('The thumbnail text requires a font-capable driver.');
        }
        $measure = $gd ? self::gdMetrics($path, $size) : self::imagickMetrics($image->frame(), $path, $size, $color);
        $metrics = array_map($measure, $lines);
        $width = max(1, max(array_column($metrics, 0)));
        $lineHeight = max(1, max(array_column($metrics, 1)));
        $step = max(1, (int) round($lineHeight * $leading));
        $height = (count($lines) - 1) * $step + $lineHeight;
        PhpThumbRaster::budget((float) $width * $height * 2);
        if ($gd) {
            $frame = imagecreatetruecolor($width, $height);
            if (!$frame) {
                throw new RuntimeException('Cannot create the thumbnail text.');
            }
            imagealphablending($frame, false);
            imagesavealpha($frame, true);
            imagefill($frame, 0, 0, 0x7f000000);
            $draw = PhpThumbRaster::gdColor($color, 1);
        } else {
            $frame = PhpThumbRaster::canvas($image, $width, $height)->frame();
        }

        foreach ($lines as $i => $line) {
            $check();
            [$length, , $xOffset, $baseline, $face] = $metrics[$i];
            $x = match (true) {
                str_contains($lineAlignment, 'R') => $width - $length,
                str_contains($lineAlignment, 'L') => 0,
                default => (int) round(($width - $length) / 2),
            };
            if ($line === '') {
                continue;
            }
            $y = $i * $step;
            if ($frame instanceof Imagick) {
                $success = $frame->annotateImage($face, $x, $y + $baseline, 0, $line);
            } elseif ($path !== null) {
                $success = imagettftext($frame, $face, 0, $x - $xOffset, $y + $baseline, $draw, $path, $line) !== false;
            } else {
                $success = imagestring($frame, $face, $x, $y, $line, $draw);
            }
            if (!$success) {
                throw new RuntimeException('Cannot draw the thumbnail text.');
            }
        }
        if ($imagick && $frame instanceof GdImage) {
            $frame = self::transfer($frame);
        }
        $stamp = $image->withFrame($frame)->rotate(PhpThumbRaster::number($angle, -360, 360));
        $bg = PhpThumbRaster::color($bg, '');
        $bgOpacity = PhpThumbRaster::number($bgOpacity, 0, 100) / 100;
        if ($bg !== '' && $bgOpacity > 0) {
            [$sw, $sh] = $stamp->dimensions();
            $bw = str_contains(strtolower($extend), 'x') ? max($w, $sw) : $sw;
            $bh = str_contains(strtolower($extend), 'y') ? max($h, $sh) : $sh;
            $back = PhpThumbRaster::canvas($image, $bw, $bh);
            $paint = PhpThumbDecorations::painter($back);
            $paint(0, 0, $bw - 1, $bh - 1, $bg, $bgOpacity);
            $stamp = Watermark::composite($back, $stamp->frame(), intdiv($bw - $sw, 2), intdiv($bh - $sh, 2));
        }
        return PhpThumbDecorations::place($image, $stamp, $align, $opacity, $margin, $margin, $check);
    }

    private static function gdMetrics(?string $path, string $size): callable
    {
        if ($path === null) {
            $face = (int) PhpThumbRaster::number($size, 1, 5, 3);
            return static fn (string $line): array => [imagefontwidth($face) * strlen($line), imagefontheight($face), 0, 0, $face];
        }
        $face = PhpThumbRaster::number($size, 1, 1024, 3);
        return static function (string $line) use ($path, $face): array {
            $box = imagettfbbox($face, 0, $path, $line === '' ? ' ' : $line);
            if ($box === false) {
                throw new RuntimeException('Cannot measure the thumbnail font.');
            }
            $left = min($box[0], $box[2], $box[4], $box[6]);
            $right = max($box[0], $box[2], $box[4], $box[6]);
            $top = min($box[1], $box[3], $box[5], $box[7]);
            $bottom = max($box[1], $box[3], $box[5], $box[7]);
            return [$right - $left + 1, $bottom - $top + 1, $left, -$top, $face];
        };
    }

    private static function imagickMetrics(Imagick $image, ?string $path, string $size, string $color): callable
    {
        $face = new ImagickDraw();
        $font = $path ?? (Imagick::queryFonts()[0] ?? null);
        if ($font === null || !$face->setFont($font)) {
            throw new RuntimeException('No readable font for the thumbnail watermark.');
        }
        $face->setFontSize(PhpThumbRaster::number($size, 1, 1024, $path === null ? 12 : 3));
        $face->setFillColor('#' . $color);
        return static function (string $line) use ($face, $image): array {
            $metrics = $image->queryFontMetrics($face, $line === '' ? ' ' : $line);
            return [(int) ceil($metrics['textWidth']), (int) ceil($metrics['textHeight']), 0, (int) ceil($metrics['ascender']), $face];
        };
    }

    private static function transfer(GdImage $frame): Imagick
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Cannot stage the thumbnail text.');
        }
        try {
            $image = new Imagick();
            if (!imagepng($frame, $stream) || !rewind($stream) || !$image->readImageFile($stream)) {
                throw new RuntimeException('Cannot transfer the thumbnail text to Imagick.');
            }
            return $image;
        } finally {
            fclose($stream);
        }
    }
}

