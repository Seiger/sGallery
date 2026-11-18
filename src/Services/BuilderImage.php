<?php namespace Seiger\sGallery\Services;

use GdImage;
use Imagick;
use RuntimeException;
use Spatie\Image\Drivers\Gd\GdDriver;
use Spatie\Image\Drivers\Imagick\ImagickDriver;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\OptimizerChainFactory;

/**
 * Use native codecs with the Builder's existing Spatie transformations.
 *
 * The drivers keep their orientation and alpha setup. Spatie can replace the
 * underlying frame during a transformation, so encoding uses the current frame.
 *
 * @internal
 * @since 1.9.0
 */
final class BuilderImage extends Image
{
    private string $source;
    private ?OptimizerChain $optimizer = null;

    /** Decode with the existing Builder driver and attach its frame to the transformation API. @since 1.9.0 */
    public static function fromSource(string $source, string $path, string $format, string $driver): self
    {
        $frame = NativeImage::open($source, $format, $driver)->frame();
        $image = new self();
        $image->source = $source;
        if ($frame instanceof GdImage) {
            $adapter = new class extends GdDriver {
                /** Restore the GD driver setup normally performed while loading a file. @since 1.9.0 */
                public function useFrame(GdImage $frame, string $path): self
                {
                    $this->originalPath = $path;
                    $this->setExif($path);
                    imagealphablending($frame, false);
                    imagesavealpha($frame, true);
                    $this->setImage($frame);
                    $this->autoRotate();
                    return $this;
                }
            };
            $image->imageDriver = $adapter->useFrame($frame, $path);
            return $image;
        }

        $adapter = new class extends ImagickDriver {
            /** Restore the Imagick driver's orientation and EXIF state for a decoded frame. @since 1.9.0 */
            public function useFrame(Imagick $frame, string $path): self
            {
                $this->originalPath = $path;
                $this->exif = $frame->getImageProperties('exif:*');
                $this->setImage($frame);
                $this->autoRotate();
                return $this;
            }
        };
        $image->imageDriver = $adapter->useFrame($frame, $path);
        return $image;
    }

    /** Encode a candidate on the cache filesystem, forwarding the optional optimizer. @since 1.9.0 */
    public function saveNative(string $path, string $format, int $quality, bool $optimize, ?GdImage $frame = null): void
    {
        $root = realpath(EVO_BASE_PATH);
        if ($root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Invalid gallery derivative path.');
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
        $optimizer = $optimize ? ($this->optimizer ??= OptimizerChainFactory::create()) : null;
        NativeImage::fromFrame($frame ?? $this->image(), $this->source, $format)
            ->save($relative, $quality, $optimizer === null ? null : $optimizer->optimize(...));
    }
}
