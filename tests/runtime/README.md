# Runtime regression checks

Run each `*-test.php` with `PACKAGE_AUTOLOAD` pointing at an installed Evolution CMS 3.6.x `core/vendor/autoload.php`. The test bootstrap loads this package from the checkout ahead of any consumer classmap. Fixtures use isolated SQLite databases and temporary files; no site database is used. The CI matrix executes PHP 8.4 and 8.5.

Install the optional `spatie/image:^3` package for transformation tests. `optional-image-test.php` excludes every `Spatie\Image\` class from autoloading and verifies original-file fallbacks, watermark settings validation and background-job behavior without the package.

With the EXIF extension disabled, also run `PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/optional-exif.php`. This exercises thumbnail generation and verifies that a camera-orientation JPEG is resized without automatic EXIF rotation. CI runs this check after disabling EXIF.
