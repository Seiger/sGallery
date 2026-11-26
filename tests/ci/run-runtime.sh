#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
php -v
php "$root/tests/ci/check-runtime.php"
for test in "$root"/tests/runtime/*-test.php; do
    echo "Running $(basename "$test")"
    php "$test"
done
for name in config-bootstrap image-optimization optional-stask settings template-assignments watermark; do
    echo "Running $name.php"
    php "$root/tests/$name.php"
done
