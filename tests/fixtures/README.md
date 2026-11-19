# HEIF orientation and alpha fixtures

These small synthetic images were generated for this repository. They contain
no camera, location or personal metadata. Runtime tests only read them and do
not require a HEIF encoder or an external command.

The two source PNGs contain the same 32 × 16 RGBA pixels, divided into quadrants:

| Stored position | RGBA, 8-bit |
| --- | --- |
| Top left | 230, 30, 20, 255 |
| Top right | 20, 210, 40, 170 |
| Bottom left | 30, 40, 220, 85 |
| Bottom right | 220, 200, 20, 0 |

`heif-rgba-exif-source.png` also has a PNG `eXIf` chunk containing a minimal
little-endian TIFF IFD with `Orientation=6`. The other PNG has no EXIF.

The HEIF files were encoded losslessly using `heif-enc` / libheif 1.23.6 and
the x265 4.1 plugin. Their `irot` container property requests a 90-degree clockwise
rotation. The displayed image is 16 × 32: blue/red above transparent/green.
The auxiliary HEVC alpha image carries opacity 1/3, 1, 0 and 2/3 in that order.

From the repository root, the generation commands are:

```sh
heif-enc -L -p x265:pools=1 --rotate-cw 90 tests/fixtures/heif-rgba-source.png -o tests/fixtures/heic-irot-alpha.heic
heif-enc -L -p x265:pools=1 --rotate-cw 90 tests/fixtures/heif-rgba-exif-source.png -o tests/fixtures/heif-irot-exif-alpha.heif
```

After encoding, the second file's major brand was set to `mif1` (the compatible
HEVC brands remain). Its informational EXIF orientation was restored to 6 because
the encoder normalizes it to 1. This intentionally tests a redundant EXIF tag
after the decoder has applied the container transform:

```php
$path = 'tests/fixtures/heif-irot-exif-alpha.heif';
$bytes = file_get_contents($path);
$bytes = substr_replace($bytes, 'mif1', 8, 4);
$tiff = "II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x01\0\0\0\0\0\0\0";
$offset = strpos($bytes, $tiff);
if ($offset === false) throw new RuntimeException('Expected the minimal EXIF IFD.');
$bytes[$offset + 18] = "\x06";
file_put_contents($path, $bytes);
```

The test checks the decoder's displayed geometry and explicit pixel/alpha
expectations before checking Builder output. HEIF container transforms and EXIF
handling can be inspected in the tested
[ImageMagick HEIC reader](https://github.com/ImageMagick/ImageMagick/blob/7.1.1-43/coders/heic.c).

The Spatie path at baseline `94e777c` retained the redundant EXIF orientation in
the resulting PNG, causing another rotation in a viewer that honors PNG EXIF.
`heif-properties-test.php` now requires orientation 0 or 1 with and without
Spatie and checks portrait geometry after a viewer applies orientation. It also
seeds an old PNG cache entry, checks regeneration under the new cache identity,
and verifies that orientation cleanup preserves the ICC profile and alpha.
