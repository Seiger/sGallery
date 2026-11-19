# Native installation and upgrade acceptance

This suite boots real Evolution CMS installations. It is separate from the
isolated runtime scripts and currently reports an **unaccepted** task 12.
See [the Ukrainian report](../../docs/testing/native-image-acceptance-2026-11.md).

Use disposable Linux containers and a named volume mounted at `/lab`. Mount this
checkout at `/pkg` and the tested sTask checkout at `/stask`, both read-only.
Never mount an existing site's database or configuration into `/lab`.

## Images and profiles

The Dockerfile builds GD and Imagick. `with-driver.sh` selects `both`, `gd`,
`imagick` or `none` through a private INI scan directory. Each selection is
verified by `capabilities.php`; PHP's other extensions remain enabled.

```sh
docker build -f tests/acceptance/Dockerfile --build-arg BASE=php:8.4.27-cli -t sgallery-acceptance-php84:local .
docker build -f tests/acceptance/Dockerfile --build-arg BASE=php:8.5.10-cli -t sgallery-acceptance-php85:local .
docker build -f tests/acceptance/Dockerfile --build-arg BASE=php:8.6-rc-cli --build-arg IMAGICK_REF=55ed2e9fda264395636a2e27e51f31673f5566ab -t sgallery-acceptance-php86:local .
```

The recorded 8.4 run used the existing local ImageMagick build with JPEG XL.
The Dockerfile's distribution library may have fewer codecs; unavailable codecs
must retain originals. PHP 8.6 RC7 was tested with the pinned upstream Imagick
commit because PECL 3.8.1 failed to compile against its API. Record image digests
and `capabilities.php` output when reproducing the matrix.

## Installation and upgrade fixtures

Export Evolution CMS tag `3.5.9` and sGallery tag `v1.8.1` into archives. Inside
the disposable volume, extract Evolution into `/lab/clean` and `/lab/upgrade`,
and old sGallery into `/lab/legacy-package`. There must be no pre-existing vendor
tree in either site. The test run used Composer 2.10.2 and sTask `6355cf2`.

For the clean site, inside a container with network access for Composer only:

```sh
php /pkg/tests/acceptance/prepare-core.php --site=/lab/clean
cd /lab/clean/core
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-dev --no-scripts --no-interaction --prefer-dist
```

For the old site, run the same preparation and Composer command with:

```sh
php /pkg/tests/acceptance/prepare-core.php --site=/lab/upgrade --package=/lab/legacy-package --version=1.8.1 --spatie
```

Install each site's SQLite database from its `install/` working directory:

```sh
php cli-install.php --typeInstall=1 --databaseType=sqlite --database=acceptance --tablePrefix=evo_ --cmsAdmin=acceptance --cmsAdminEmail=admin@images.test --cmsPassword=Acceptance12-LocalOnly! --language=uk --removeInstall=n --skipComposer=y
cd ../core
php artisan package:discover --no-interaction
php artisan migrate --force --no-interaction
```

These credentials belong only to the temporary fixture. No HTTP server is needed.
While the old site still contains Spatie and sGallery 1.8.1, generate its caches
and persist five real old descriptors in SQLite:

```sh
SGALLERY_ACCEPTANCE_SITE=/lab/upgrade php /pkg/tests/acceptance/seed-legacy.php
```

Upgrade its Composer installation without replacing its database or cache:

```sh
php /pkg/tests/acceptance/prepare-core.php --site=/lab/upgrade
cd /lab/upgrade/core
COMPOSER_ALLOW_SUPERUSER=1 composer update --no-dev --no-scripts --no-interaction --prefer-dist
php artisan package:discover --no-interaction
php artisan migrate --force --no-interaction
```

`spatie/image`, `spatie/image-optimizer` and their temporary-directory dependency
are removed. `spatie/once` remains a framework dependency; it is not an image
processor. Seed fixed input files on both upgraded/current sites using the
8.4 image with both drivers, so decoder-only profiles can test the same AVIF,
BMP and TIFF files without needing their encoders:

```sh
SGALLERY_ACCEPTANCE_SITE=/lab/clean php /pkg/tests/acceptance/seed-inputs.php
SGALLERY_ACCEPTANCE_SITE=/lab/upgrade php /pkg/tests/acceptance/seed-inputs.php
```

## Matrix and results

Run each image with `--network none` and the same isolated `/lab` volume:

```sh
sh /pkg/tests/acceptance/run-matrix.sh php84-acceptance
sh /pkg/tests/acceptance/run-matrix.sh php85-acceptance
sh /pkg/tests/acceptance/run-matrix.sh php86-acceptance
php /pkg/tests/acceptance/summarize.php
```

Each command runs in its respective PHP image. Use unique labels on retries.
`summarize.php` selects the three `phpXX-acceptance` labels for the final site
results. `SGALLERY_SITE_ONLY=1` reruns the integration gate without repeating
the runtime scripts. Failed scripts can be rerun in the same profile and their
new status appended to `status.tsv`; the summary uses the last result per key.

Every cell gets a separate site snapshot and SQLite file. The Composer vendor
tree is shared read-only in use; `rebase-site.php` rewrites absolute database
paths and `verify-site.php` refuses a snapshot pointing at another database.
The five old queue rows and old cache bytes remain in the base upgrade fixture.

The runner deliberately continues after failed assertions and returns a nonzero
exit code when acceptance fails. Inspect `/lab/results/*-site.json`, logs and
`summary.json`; a green runtime suite does not make the integration gate pass.
Without either image driver, image tasks must fail without a published result
and following tasks must still be processed. Original images remain unchanged.

After changing package code, refresh the copied package in both current sites
with `composer reinstall seiger/sgallery --no-scripts --no-interaction` before
taking new snapshots. The path repositories deliberately use copies, not links
to the working checkout. Update the recorded revision metadata in `summarize.php`
when producing a report for another commit.
