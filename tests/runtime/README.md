# Runtime regression checks

Task 12 also has a [real-installation acceptance suite](../acceptance/README.md)
for clean CMS installations, upgrades from 1.8.1 and the PHP/driver matrix.
Its integration gate currently fails; see the [recorded results](../../docs/testing/native-image-acceptance-2026-11.md).

Run each `*-test.php` with `PACKAGE_AUTOLOAD` pointing at an installed Evolution CMS 3.6.x `core/vendor/autoload.php`. The test bootstrap loads this package from the checkout ahead of any consumer classmap. Fixtures use isolated SQLite databases and temporary files; no site database is used. The CI matrix executes PHP 8.4 and 8.5.

Install the optional `spatie/image:^3` package for transformation tests. `optional-image-test.php` excludes every `Spatie\Image\` class from autoloading and verifies original-file fallbacks, watermark settings validation and background-job behavior without the package.

With the EXIF extension disabled, also run `PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/optional-exif.php`. This exercises thumbnail generation and verifies that a camera-orientation JPEG is resized without automatic EXIF rotation. CI runs this check after disabling EXIF.

`fit-test.php` uses the same fixtures in installations with and without Spatie. Run it with each Composer runtime to check fit geometry, padding, alpha, output formats and cache reuse. The script reports the active image extensions and whether Spatie is available.

`client-format-test.php` checks HTTP Accept, session/cookie preferences, invalid cookies, and opaque/transparent defaults. It keeps the existing Spatie behavior and the native transparent-image format policy separate. This script also runs without either image extension.

`crop-test.php` checks the nine crop positions, manual and focal coordinates, edge clipping and pixel transparency. It also checks that equal-sized native manual crops from different coordinates have separate cache entries.

`orientation-test.php` checks all eight JPEG orientations, crop coordinates after rotation, and the cache transition from stored to corrected orientation. Run it with and without Spatie, and with EXIF disabled to check the GD fallback or Imagick metadata reading.

`transparency-test.php` checks RGBA, indexed and RGB color-key PNGs. The native checks cover alpha after resampling, automatic format selection, white JPEG compositing and cache reuse. The Spatie run reports decoded pixel values for comparison with the previous release.

`color-profile-test.php` compares Display P3 JPEG and PNG sources before and after native sRGB conversion. With Imagick and LittleCMS it checks output pixels, profiles, alpha and padding. On GD-only installations it verifies that profiled sources keep their original URL. Run it with each Composer runtime to compare the Spatie path.

The Spatie/Imagick checks also cover opaque and partially transparent neutral
RGB PNGs with Display P3 profiles. They require RGB/RGBA samples with the original
profile, warning-free decoding, optimization on/off, cold worker replay and
the legacy Spatie writer. An earlier grayscale PNG with RGB ICC is seeded under
the old `upright` key; the frontend regenerates it under `upright-icc` and reuses
the corrected derivative while preserving the old file and source bytes.

`still-formats-test.php` checks static GIF and AVIF output, GIF palette transparency, cache reuse and native WebP fallback on a build that cannot encode AVIF. It also verifies that native still-format requests return animated PNG sources intact.

`static-inputs-test.php` checks fixed GIF, WebP, AVIF, BMP and TIFF sources with
and without Spatie, including a GD-readable 24-bit BMP. It covers resize, fit,
three crop methods, PNG/JPEG output, cache reuse, cold worker replay, alpha,
white JPEG compositing and source preservation. Native checks also cover TIFF
endianness/orientation, multi-page fallback, ICC conversion or original-file
fallback, content-based input selection and unsupported GD metadata. Run it
with both drivers, GD only, Imagick only and neither driver. Input fixtures
remain available even when the selected profile cannot encode their format.

`animation-test.php` checks GIF and WebP both with and without Spatie. With Imagick it covers offset frames, transparency, duplicate frames, exact WebP delays, finite and infinite loops, and invalid cached timing. A GD-only run verifies that animated sources keep their original URL.

`quality-test.php` compares explicit quality with directly encoded references, exercises the first and last adaptive WebP candidates, and checks fixed/adaptive cache separation without Spatie. With Imagick it also checks animation timing and the final animated WebP candidate. Quality comparisons use small local fixtures with the frontend deadline disabled to isolate encoding from scheduling. Run it with and without Spatie.

`optimize-test.php` checks native PNG size and pixel preservation, progressive JPEG, output types, cache migration and reuse, and toggling optimization on a reused Builder. With Imagick it verifies ICC preservation and metadata removal from static and animated derivatives. Run it with and without Spatie.

`worker-test.php` requires sTask with its heartbeat API and PDO SQLite. Run it with and without Spatie to exercise the real runner against an isolated queue. It checks duplicate jobs, retries, stale heartbeat, replaced sources and watermarks, native geometry, animation, cache identity and continuation after a publication error. PHP 8.6 dependency deprecations are reported separately while package warnings still fail the test.

`modern-formats-test.php` checks native HEIC/HEIF and JPEG XL input conversion, content detection, alpha, JPEG backgrounds, ICC conversion, EXIF metadata, cache reuse and queued geometry. Its fixed JPEG XL fixtures include a raw codestream, a container and an animation that must retain its original URL. Run with Imagick codecs installed, with the codecs absent, and with each Composer runtime to compare the Spatie entry point. JPEG XL uploads must also pass the CMS extension setting and server MIME checks.

`heif-properties-test.php` uses committed HEIC/HEIF fixtures with a container rotation, an auxiliary alpha image and a redundant EXIF tag. With and without Spatie it checks portrait geometry, PNG colors and opacity, crop coordinates, background replay and cache reuse. It rejects stale PNG rotation in both configurations, checks migration from an old Spatie PNG cache entry, and exercises the legacy Spatie writer while verifying ICC and alpha preservation. Native checks also cover white JPEG compositing and optimized PNG output. See [fixture provenance and reproduction](../fixtures/README.md). A run without an HEIF decoder checks the existing original/placeholder fallback contract.

## Image compatibility baseline

`image-compatibility-test.php` runs the Evolution `HelperProcessor::phpThumb()` and the sGallery Builder against the same generated inputs in a private temporary directory. It requires the phpThumb and Spatie packages from the supplied Composer runtime and GD with JPEG, PNG and WebP support. It checks AVIF when GD can encode it.

```sh
PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/runtime/image-compatibility-test.php
```

The cases cover `far=C`, centered crop, one-sided resize, original and explicit formats, partial transparency, EXIF orientation, watermarks, missing files, SVG and cache reuse. The helper profile uses GD and explicit formats so an installed ImageMagick CLI or automatic WebP conversion cannot change the baseline. The Builder uses its current driver selection, and the test prints the driver and EXIF state.

Each render prints byte size, elapsed time and the increase in PHP-managed peak memory. These readings are diagnostic and include cold start costs. PHP-managed peak memory does not include all memory allocated by native codecs. The assertions check observable output properties and cache reuse.
