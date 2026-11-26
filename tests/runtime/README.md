# Runtime regression checks

`migrations-test.php` uses the real Illuminate Migrator with prefixed SQLite
tables to check fresh/partial installs, installer history, preserved data,
repeat migrations and rollback. `manager-message-test.php` checks the delete
controller with the shipped en/uk/ru lexicons and a real Translator.
See [the follow-up CMS validation](../../docs/testing/sgallery-e2e-fixes-2026-11-22.md).

Task 12 also has a [real-installation acceptance suite](../acceptance/README.md)
for clean CMS installations, upgrades from 1.8.1 and the PHP/driver matrix.
The 1.x matrix passed after fixes 12-A–12-D; see the [recorded results](../../docs/testing/native-image-acceptance-2026-11.md), including the earlier rejected snapshot.

Run each `*-test.php` with `PACKAGE_AUTOLOAD` pointing at an installed Evolution CMS 3.6+ `core/vendor/autoload.php`. The shared `tests/bootstrap.php` loads this package from the checkout ahead of any consumer classmap, including authoritative maps. `bootstrap-test.php` checks this precedence. Fixtures use isolated SQLite databases and temporary files; no site database is used.

CI checks PHP 8.4, 8.5 and 8.6 with native processing. The fixture uses
Evolution CMS 3.6.0 at `6d3a58974` and sTask `6355cf293422d43885d803cdd43637c0896f63cd`.
PHP 8.4 and 8.5 use GD and Imagick. PHP 8.6 CI uses GD because the pinned
Imagick PECL release does not build there.

`tests/ci/prepare-runtime.php` configures only the disposable `.test-core` fixture.
On PHP 8.4, Composer resolves the local sGallery and sTask packages against
the pinned CMS checkout. CI then checks platform requirements on the target PHP version.
`tests/ci/run-runtime.sh` verifies the Composer fixture and runs every runtime
test, including the phpThumb comparison and sTask job replay.

`phpthumb-test.php` checks normalization, output formats, geometry, EXIF,
the byte target, ordered filters, and source and watermark cache revisions.
`image-compatibility-test.php` compares the old and native helpers on the same
sources for padding, zoom crop, a single dimension, and watermark placement.
It also compares all added legacy filters on one patterned source, with pixel
tolerances for differences between GD and Imagick. On system libgd it verifies
bundled-GD reference pixels where phpThumb uses a different color formula
and checks the legacy no-op where phpThumb skips convolution. When DejaVu Sans and
FreeType are available, it checks TrueType placement and font replacement.

`optional-image-test.php` rejects any attempt to load legacy image packages
during Builder transformations. It also verifies original-file fallbacks,
watermark validation and background-job behavior.

With EXIF and Imagick disabled, run
`PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/optional-exif.php`.
It verifies that GD uses the stored orientation for a camera JPEG. CI runs
this check with both extensions disabled.

`fit-test.php` checks fit geometry, padding, alpha, output formats and cache reuse.
`client-format-test.php` checks HTTP Accept, session/cookie preferences,
invalid cookies and opaque/transparent defaults. It also runs without either
image extension.

`source-cache-test.php` checks same-size/same-mtime replacement on a reused
Builder, current pixels and URLs, refreshed alpha metadata, unchanged-source
cache reuse, full-path identity, long UTF-8 filenames and old two-field jobs.
Deterministic filesystem hooks replace the source during encoding and immediately
before/after final publication, with a newer derivative generated concurrently.
The stale worker must fail without leaving its result or harming either earlier
valid caches or the newer revision. Frontend races return the current original.
`worker-test.php` also runs a stale hashed job followed by a current job through
the real sTask runner and checks failure isolation and cache identity.

`queue-dedup-test.php` uses real sTask models and an isolated SQLite database to
check gallery descriptors after JSON persistence. It covers default/integral
sharpening, preserved fractional strengths, reordered keys, earlier active rows,
queued/preparing/running states, attempts/progress preservation, finished/failed
records and retry cooldowns. It also exercises `defer()` with earlier integral
float descriptors. Only known PHP 8.6 deprecations from Illuminate's `mb_split()`
and Carbon's `spl_object_hash()` are recorded separately; other warnings stay strict.

`crop-test.php` checks the nine crop positions, manual and focal coordinates, edge clipping and pixel transparency. It also checks that equal-sized native manual crops from different coordinates have separate cache entries.

`orientation-test.php` checks all eight JPEG orientations, crop coordinates after rotation, and the cache transition from stored to corrected orientation. Run it with EXIF disabled to check the GD fallback or Imagick metadata reading.

`transparency-test.php` checks RGBA, indexed and RGB color-key PNGs. The native checks cover alpha after resampling, automatic format selection, white JPEG compositing and cache reuse.

`color-profile-test.php` compares Display P3 JPEG and PNG sources before and after native sRGB conversion. With Imagick and LittleCMS it checks output pixels, profiles, alpha and padding. On GD-only installations it verifies that profiled sources keep their original URL.

`still-formats-test.php` checks static GIF and AVIF output, GIF palette transparency, cache reuse and native WebP fallback on a build that cannot encode AVIF. It also verifies that native still-format requests return animated PNG sources intact.

`static-inputs-test.php` checks fixed GIF, WebP, AVIF, BMP and TIFF sources, including a GD-readable 24-bit BMP. It covers resize, fit,
three crop methods, PNG/JPEG output, cache reuse, cold worker replay, alpha,
white JPEG compositing and source preservation. Native checks also cover TIFF
endianness/orientation, multi-page fallback, ICC conversion or original-file
fallback, content-based input selection and unsupported GD metadata. Run it
with both drivers, GD only, Imagick only and neither driver. Input fixtures
remain available even when the selected profile cannot encode their format.

`animation-test.php` checks GIF and WebP. With Imagick it covers offset frames, transparency, duplicate frames, exact WebP delays, finite and infinite loops, and invalid cached timing. A GD-only run verifies that animated sources keep their original URL.

`quality-test.php` compares explicit quality with directly encoded references, exercises the first and last adaptive WebP candidates, and checks fixed/adaptive cache separation. With Imagick it also checks animation timing and the final animated WebP candidate. Quality comparisons use small local fixtures with the frontend deadline disabled to isolate encoding from scheduling.

`optimize-test.php` checks native PNG size and pixel preservation, progressive JPEG, output types, cache migration and reuse, and toggling optimization on a reused Builder. With Imagick it verifies ICC preservation and metadata removal from static and animated derivatives.

`worker-test.php` requires sTask with its heartbeat API and PDO SQLite. It exercises the real runner against an isolated queue. It checks duplicate jobs, retries, stale heartbeat, replaced sources and watermarks, native geometry, animation, cache identity and continuation after a publication error. PHP 8.6 dependency deprecations are reported separately while package warnings still fail the test.

`modern-formats-test.php` checks native HEIC/HEIF and JPEG XL input conversion, content detection, alpha, JPEG backgrounds, ICC conversion, EXIF metadata, cache reuse and queued geometry. Its fixed JPEG XL fixtures include a raw codestream, a container and an animation that must retain its original URL. Run with Imagick codecs installed and with the codecs absent. JPEG XL uploads must also pass the CMS extension setting and server MIME checks.

`heif-properties-test.php` uses HEIC/HEIF fixtures with container rotation,
an auxiliary alpha image and a redundant EXIF tag. It checks portrait geometry,
PNG colors and opacity, crop coordinates, background replay, cache reuse,
white JPEG compositing and optimized PNG output. See the
[fixture provenance](../fixtures/README.md). A run without a HEIF decoder
verifies the original-file fallback.

## Image compatibility baseline

`image-compatibility-test.php` runs the Evolution `HelperProcessor::phpThumb()` and the sGallery Builder against the same generated inputs in a private temporary directory. It requires phpThumb from the Evolution CMS Composer runtime and GD with JPEG, PNG and WebP support. It checks AVIF when GD can encode it.

```sh
PACKAGE_AUTOLOAD=/path/to/core/vendor/autoload.php php tests/runtime/image-compatibility-test.php
```

The cases cover `far=C`, centered crop, one-sided resize, original and explicit formats, partial transparency, EXIF orientation, watermarks, missing files, SVG and cache reuse. The helper profile uses GD and explicit formats so an installed ImageMagick CLI or automatic WebP conversion cannot change the baseline. The Builder uses its current driver selection, and the test prints the driver and EXIF state.

Each render prints byte size, elapsed time and the increase in PHP-managed peak memory. These readings are diagnostic and include cold start costs. PHP-managed peak memory does not include all memory allocated by native codecs. The assertions check observable output properties and cache reuse.
