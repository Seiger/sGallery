# Runtime regression checks

Run each `*-test.php` with `PACKAGE_AUTOLOAD` pointing at an installed Evolution CMS 3.6.x `core/vendor/autoload.php`. The test bootstrap loads this package from the checkout ahead of any consumer classmap. Fixtures use isolated SQLite databases and temporary files; no site database is used. The CI matrix executes PHP 8.4 and 8.5.

Install the optional `spatie/image:^3` package for transformation tests. `optional-image-test.php` excludes every `Spatie\Image\` class from autoloading and verifies original-file fallbacks, watermark settings validation and background-job behavior without the package.

With the EXIF extension disabled, also run `PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/optional-exif.php`. This exercises thumbnail generation and verifies that a camera-orientation JPEG is resized without automatic EXIF rotation. CI runs this check after disabling EXIF.

`fit-test.php` uses the same fixtures in installations with and without Spatie. Run it with each Composer runtime to check fit geometry, padding, alpha, output formats and cache reuse. The script reports the active image extensions and whether Spatie is available.

`crop-test.php` checks the nine crop positions, manual and focal coordinates, edge clipping and pixel transparency. It also checks that equal-sized native manual crops from different coordinates have separate cache entries.

`orientation-test.php` checks all eight JPEG orientations, crop coordinates after rotation, and the cache transition from stored to corrected orientation. Run it with and without Spatie, and with EXIF disabled to check the GD fallback or Imagick metadata reading.

`transparency-test.php` checks RGBA, indexed and RGB color-key PNGs. The native checks cover alpha after resampling, automatic format selection, white JPEG compositing and cache reuse. The Spatie run reports decoded pixel values for comparison with the previous release.

`color-profile-test.php` compares Display P3 JPEG and PNG sources before and after native sRGB conversion. With Imagick and LittleCMS it checks output pixels, profiles, alpha and padding. On GD-only installations it verifies that profiled sources keep their original URL. Run it with each Composer runtime to compare the Spatie path.

`still-formats-test.php` checks static GIF and AVIF output, GIF palette transparency, cache reuse and native WebP fallback on a build that cannot encode AVIF. It also verifies that native still-format requests return animated PNG sources intact.

`animation-test.php` checks GIF and WebP both with and without Spatie. With Imagick it covers offset frames, transparency, duplicate frames, exact WebP delays, finite and infinite loops, and invalid cached timing. A GD-only run verifies that animated sources keep their original URL.

`quality-test.php` compares explicit quality with directly encoded references, exercises the first and last adaptive WebP candidates, and checks fixed/adaptive cache separation without Spatie. With Imagick it also checks animation timing and the final animated WebP candidate. Quality comparisons use small local fixtures with the frontend deadline disabled to isolate encoding from scheduling. Run it with and without Spatie.

`optimize-test.php` checks native PNG size and pixel preservation, progressive JPEG, output types, cache migration and reuse, and toggling optimization on a reused Builder. With Imagick it verifies ICC preservation and metadata removal from static and animated derivatives. Run it with and without Spatie.

`worker-test.php` requires sTask with its heartbeat API and PDO SQLite. Run it with and without Spatie to exercise the real runner against an isolated queue. It checks duplicate jobs, retries, stale heartbeat, replaced sources and watermarks, native geometry, animation, cache identity and continuation after a publication error. PHP 8.6 dependency deprecations are reported separately while package warnings still fail the test.

`modern-formats-test.php` checks native HEIC/HEIF and JPEG XL input conversion, content detection, alpha, JPEG backgrounds, ICC conversion, EXIF metadata, cache reuse and queued geometry. Its fixed JPEG XL fixtures include a raw codestream, a container and an animation that must retain its original URL. Run with Imagick codecs installed, with the codecs absent, and with each Composer runtime to compare the Spatie entry point. JPEG XL uploads must also pass the CMS extension setting and server MIME checks.

## Image compatibility baseline

`image-compatibility-test.php` runs the Evolution `HelperProcessor::phpThumb()` and the sGallery Builder against the same generated inputs in a private temporary directory. It requires the phpThumb and Spatie packages from the supplied Composer runtime and GD with JPEG, PNG and WebP support. It checks AVIF when GD can encode it.

```sh
PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/runtime/image-compatibility-test.php
```

The cases cover `far=C`, centered crop, one-sided resize, original and explicit formats, partial transparency, EXIF orientation, watermarks, missing files, SVG and cache reuse. The helper profile uses GD and explicit formats so an installed ImageMagick CLI or automatic WebP conversion cannot change the baseline. The Builder uses its current driver selection, and the test prints the driver and EXIF state.

Each render prints byte size, elapsed time and the increase in PHP-managed peak memory. These readings are diagnostic and include cold start costs. PHP-managed peak memory does not include all memory allocated by native codecs. The assertions check observable output properties and cache reuse.
