<?php namespace Seiger\sGallery\Services;

use Imagick;
use RuntimeException;

/**
 * Transform an animated GIF or WebP with Imagick and verify it before publication.
 *
 * @internal
 * @since 1.9.0
 */
final class NativeAnimation
{
    /**
     * Decode only after checking the aggregate canvas size and frame count.
     *
     * @param array{loops: int, delays: list<int>} $timing
     * @param callable(Imagick): Imagick $transform
     * @param callable(): void $checkBudget
     * @since 1.9.0
     */
    public static function render(
        string $source,
        array $dimensions,
        array $timing,
        string $format,
        string $output,
        int $quality,
        callable $transform,
        callable $checkBudget,
    ): void {
        $count = count($timing['delays']);
        $loops = $timing['loops'];
        if (!NativeImage::canEncode($format, 'imagick') || ($format === 'webp' && $loops > 65535)) {
            throw new RuntimeException('The animation output format is unavailable.');
        }
        if ($format === 'gif') {
            foreach ($timing['delays'] as $delay) {
                if ($delay > 655350 || $delay % 10 !== 0) {
                    throw new RuntimeException('GIF cannot represent an animation delay exactly.');
                }
            }
        }

        $sourcePixels = (float) $dimensions[0] * $dimensions[1] * $count;
        $sourceBytes = (int) @filesize($source);
        if (!ImageMemoryBudget::allows(2 * $sourcePixels, $sourceBytes, PHP_SAPI === 'cli')) {
            throw new RuntimeException('Insufficient memory budget to decode the gallery animation.');
        }
        $checkBudget();
        $input = self::decode($source, $dimensions[2] === IMAGETYPE_GIF ? 'GIF' : 'WEBP', $count);
        $profile = $input->getImageProfiles('icc', false) === [] ? null : $input->getImageProfile('icc');
        if ($profile !== null && $format === 'gif') {
            throw new RuntimeException('The GIF animation output cannot carry the source ICC profile.');
        }
        $canvas = $input->coalesceImages();
        unset($input);
        $checkBudget();

        $stream = @fopen($output, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Cannot stage a native gallery animation.');
        }
        try {
            $writer = $format === 'webp' ? new AnimatedWebpWriter($stream, $loops, $profile) : new Imagick();
            $size = null;
            $outputPixels = 0;
            foreach ($canvas as $index => $image) {
                $checkBudget();
                $frame = $transform($image->getImage());
                $frameSize = [$frame->getImageWidth(), $frame->getImageHeight()];
                if ($size === null) {
                    $size = $frameSize;
                    $outputPixels = (float) $size[0] * $size[1] * $count;
                    if (!ImageMemoryBudget::allows($sourcePixels + $outputPixels, $sourceBytes, PHP_SAPI === 'cli')) {
                        throw new RuntimeException('Insufficient memory budget to encode the gallery animation.');
                    }
                }
                if ($frameSize !== $size || !$frame->setImagePage(0, 0, 0, 0) || !$frame->setImageFormat(strtoupper($format)) ||
                    !$frame->setImageCompressionQuality($quality)) {
                    throw new RuntimeException('Cannot encode a gallery animation frame.');
                }

                if ($writer instanceof AnimatedWebpWriter) {
                    if (!$frame->setFormat('WEBP')) {
                        throw new RuntimeException('Cannot select the gallery WebP frame encoder.');
                    }
                    $writer->addFrame($frame->getImageBlob(), $size[0], $size[1], $timing['delays'][$index]);
                } else {
                    self::addGifFrame($writer, $frame, intdiv($timing['delays'][$index], 10), $loops);
                }
            }
            unset($canvas, $frame, $image);
            $checkBudget();
            if ($writer instanceof AnimatedWebpWriter) {
                $writer->finish();
            } else {
                $writer->setFirstIterator();
                if (!$writer->writeImagesFile($stream)) {
                    throw new RuntimeException('Cannot write the gallery GIF animation.');
                }
            }
            unset($writer);
            if (!fflush($stream)) {
                throw new RuntimeException('Cannot flush the gallery animation.');
            }
        } finally {
            fclose($stream);
        }

        $checkBudget();
        $type = $format === 'gif' ? IMAGETYPE_GIF : IMAGETYPE_WEBP;
        $metadata = @getimagesize($output);
        if ($size === null || $metadata === false || array_slice($metadata, 0, 3) !== [...$size, $type] ||
            ImageAnimation::timing($output, $type) !== $timing ||
            !ImageMemoryBudget::allows($outputPixels, (int) @filesize($output), PHP_SAPI === 'cli')) {
            throw new RuntimeException('The encoder did not preserve the gallery animation.');
        }
        self::decode($output, strtoupper($format), $count);
        $checkBudget();
    }

    /** Decode a local sequence through a file handle and require every expected frame. @since 1.9.0 */
    private static function decode(string $path, string $format, int $frames): Imagick
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Cannot open the gallery animation.');
        }
        try {
            $image = new Imagick();
            if (!$image->setFormat($format) || !$image->readImageFile($stream) || $image->getNumberImages() !== $frames) {
                throw new RuntimeException('Cannot decode all gallery animation frames.');
            }
            $image->setFirstIterator();
            return $image;
        } finally {
            fclose($stream);
        }
    }

    /** Store full GIF frames so transparent pixels cannot reveal a previous frame. @since 1.9.0 */
    private static function addGifFrame(Imagick $output, Imagick $frame, int $delay, int $loops): void
    {
        if (!$frame->setImageTicksPerSecond(100) || !$frame->setImageDelay($delay) || !$frame->setImageIterations($loops) ||
            !$frame->setImageBackgroundColor('transparent') || !$frame->setImageDispose(Imagick::DISPOSE_BACKGROUND) ||
            !$output->addImage($frame)) {
            throw new RuntimeException('Cannot retain the timing or disposal of a gallery GIF frame.');
        }
    }
}
