<?php namespace Seiger\sGallery\Services;

use RuntimeException;

/**
 * Store individually encoded WebP frames with their exact animation timing.
 *
 * Each frame covers the canvas and replaces the previous frame. Encoding the
 * frames separately prevents the animation encoder from merging equal frames.
 *
 * @internal
 * @since 1.9.0
 */
final class AnimatedWebpWriter
{
    private bool $started = false;
    private int $flags = 0x02;

    /** Keep the staged stream, playback count and optional color profile. @since 1.9.0 */
    public function __construct(private $stream, private readonly int $loops, private readonly ?string $profile)
    {
    }

    /** Wrap the frame payload in ANMF without changing the delay in milliseconds. @since 1.9.0 */
    public function addFrame(string $blob, int $width, int $height, int $delay): void
    {
        [$payload, $alpha] = self::framePayload($blob);
        if (!$this->started) {
            $this->started = true;
            $this->write('RIFF' . pack('V', 0) . 'WEBP');
            $this->write(self::chunk('VP8X', "\x02\0\0\0" . self::uint24($width - 1) . self::uint24($height - 1)));
            if ($this->profile !== null) {
                $this->flags |= 0x20;
                $this->write(self::chunk('ICCP', $this->profile));
            }
            $this->write(self::chunk('ANIM', "\0\0\0\0" . pack('v', $this->loops)));
        }
        if ($alpha) {
            $this->flags |= 0x10;
        }

        $rectangle = str_repeat("\0", 6) . self::uint24($width - 1) . self::uint24($height - 1);
        $this->write('ANMF' . pack('V', 16 + strlen($payload)) . $rectangle . self::uint24($delay) . "\x02");
        $this->write($payload);
    }

    /** Fill the RIFF size and final VP8X flags in the staged file. @since 1.9.0 */
    public function finish(): void
    {
        $length = ftell($this->stream);
        if (!$this->started || $length === false || $length - 8 > 0xffffffff || fseek($this->stream, 4) !== 0) {
            throw new RuntimeException('Cannot finalize the gallery WebP container.');
        }
        $this->write(pack('V', $length - 8));
        if (fseek($this->stream, 20) !== 0) {
            throw new RuntimeException('Cannot finalize the gallery WebP feature flags.');
        }
        $this->write(chr($this->flags));
    }

    /** Keep the compressed still-image chunks and their RIFF padding. @return array{string, bool} @since 1.9.0 */
    private static function framePayload(string $blob): array
    {
        $length = strlen($blob);
        if ($length < 12 || substr($blob, 0, 4) !== 'RIFF' || substr($blob, 8, 4) !== 'WEBP' ||
            unpack('V', substr($blob, 4, 4))[1] !== $length - 8) {
            throw new RuntimeException('The encoder did not produce a valid gallery WebP frame.');
        }

        $parts = [];
        $alpha = false;
        $image = false;
        $offset = 12;
        while ($offset + 8 <= $length) {
            $type = substr($blob, $offset, 4);
            $size = unpack('V', substr($blob, $offset + 4, 4))[1];
            $next = $offset + 8 + $size + ($size % 2);
            if ($next > $length) {
                break;
            }
            if (in_array($type, ['ALPH', 'VP8 ', 'VP8L'], true)) {
                $parts[] = substr($blob, $offset, $next - $offset);
                $image = $image || $type !== 'ALPH';
                // The lossless bitstream's alpha hint is bit 28 after its signature byte.
                $alpha = $alpha || $type === 'ALPH' || ($type === 'VP8L' && $size >= 5 && (ord($blob[$offset + 12]) & 0x10) !== 0);
            }
            $offset = $next;
        }
        if (!$image || $offset !== $length) {
            throw new RuntimeException('No complete still image payload in the encoded gallery WebP frame.');
        }

        return [implode('', $parts), $alpha];
    }

    /** Encode a bounded unsigned 24-bit WebP field. @since 1.9.0 */
    private static function uint24(int $value): string
    {
        if ($value < 0 || $value > 0xffffff) {
            throw new RuntimeException('A gallery WebP frame value exceeds the container range.');
        }
        return substr(pack('V', $value), 0, 3);
    }

    /** Encode a RIFF chunk with its required padding. @since 1.9.0 */
    private static function chunk(string $type, string $data): string
    {
        return $type . pack('V', strlen($data)) . $data . (strlen($data) % 2 === 0 ? '' : "\0");
    }

    /** Reject an incomplete write so its staged file cannot be published. @since 1.9.0 */
    private function write(string $bytes): void
    {
        if (fwrite($this->stream, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Cannot write the gallery WebP animation.');
        }
    }
}
