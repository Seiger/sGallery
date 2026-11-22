#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
php -v
php "$root/tests/ci/check-runtime.php"
for test in "$root"/tests/runtime/*-test.php; do
    if [ "$SGALLERY_TEST_BACKEND" = native ] && [ "$(basename "$test")" = image-compatibility-test.php ]; then continue; fi
    echo "Running $(basename "$test")"
    php "$test"
done
for name in config-bootstrap optional-stask settings template-assignments watermark; do
    echo "Running $name.php"
    php "$root/tests/$name.php"
done
if [ "$SGALLERY_TEST_BACKEND" = spatie ]; then
    echo "Running image-optimization.php"
    php "$root/tests/image-optimization.php"
fi
