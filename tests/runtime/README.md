# Runtime regression checks

Run each `*-test.php` with `PACKAGE_AUTOLOAD` pointing at an installed Evolution CMS 3.6.x `core/vendor/autoload.php`. The test bootstrap loads this package from the checkout ahead of any consumer classmap. Fixtures use isolated SQLite databases and temporary files; no site database is used. The CI matrix executes PHP 8.4 and 8.5.

Install the optional `spatie/image:^3` package for transformation tests. `optional-image-test.php` excludes every `Spatie\Image\` class from autoloading and verifies original-file fallbacks, watermark settings validation and background-job behavior without the package.

With the EXIF extension disabled, also run `PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/optional-exif.php`. This exercises thumbnail generation and verifies that a camera-orientation JPEG is resized without automatic EXIF rotation. CI runs this check after disabling EXIF.

`fit-test.php` uses the same fixtures in installations with and without Spatie. Run it with each Composer runtime to check fit geometry, padding, alpha, output formats and cache reuse. The script reports the active image extensions and whether Spatie is available.

`crop-test.php` checks the nine crop positions, manual and focal coordinates, edge clipping and pixel transparency. It also checks that equal-sized native manual crops from different coordinates have separate cache entries.

## Image compatibility baseline

`image-compatibility-test.php` runs the Evolution `HelperProcessor::phpThumb()` and the sGallery Builder against the same generated inputs in a private temporary directory. It requires the phpThumb and Spatie packages from the supplied Composer runtime and GD with JPEG, PNG and WebP support. It checks AVIF when GD can encode it.

```sh
PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/runtime/image-compatibility-test.php
```

The cases cover `far=C`, centered crop, one-sided resize, original and explicit formats, partial transparency, EXIF orientation, watermarks, missing files, SVG and cache reuse. The helper profile uses GD and explicit formats so an installed ImageMagick CLI or automatic WebP conversion cannot change the baseline. The Builder uses its current driver selection, and the test prints the driver and EXIF state.

Each render prints byte size, elapsed time and the increase in PHP-managed peak memory. These readings are diagnostic and include cold start costs. PHP-managed peak memory does not include all memory allocated by native codecs. The assertions check observable output properties and cache reuse.
