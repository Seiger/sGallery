#!/bin/sh
set -eu
label=${1:?Pass a unique PHP/run label}
case "$label" in *[!a-zA-Z0-9_-]*) exit 2 ;; esac
mkdir -p /lab/results
failed=0
for profile in both gd imagick none; do
    for scenario in clean upgrade; do
        base=/lab/$scenario
        target=/lab/cases/$label-$profile-$scenario
        test ! -e "$target" || { echo "Refusing to overwrite $target"; exit 2; }
        mkdir -p "$target"
        (cd "$base" && tar --exclude='./core/vendor' -cf - .) | (cd "$target" && tar xf -)
        ln -s "$base/core/vendor" "$target/core/vendor"
        php /pkg/tests/acceptance/rebase-site.php "$base" "$target"
        export SGALLERY_ACCEPTANCE_SITE=$target
        export PACKAGE_AUTOLOAD=$base/core/vendor/autoload.php
        prefix=/lab/results/$label-$profile-$scenario
        sh /pkg/tests/acceptance/with-driver.sh "$profile" php /pkg/tests/acceptance/capabilities.php > "$prefix-capabilities.json"
        code=0
        sh /pkg/tests/acceptance/with-driver.sh "$profile" php /pkg/tests/acceptance/verify-site.php > "$prefix-site.log" 2>&1 || code=$?
        if test -f "$target/acceptance-result.json"; then cp "$target/acceptance-result.json" "$prefix-site.json"; fi
        printf '%s\t%s\t%s\tsite\t%s\n' "$label" "$profile" "$scenario" "$code" >> /lab/results/status.tsv
        test "$code" = 0 || failed=1
        echo "$label $profile $scenario site=$code"
        if test "${SGALLERY_SITE_ONLY:-0}" = 1; then continue; fi
        if test "$scenario" = upgrade; then continue; fi
        tests='native-image fit optional-image modern-formats heif-properties client-format'
        if test "$profile" != none; then
            tests="$tests crop orientation transparency color-profile still-formats animation quality optimize sharpen worker thumbnail"
        fi
        for test in $tests; do
            code=0
            sh /pkg/tests/acceptance/with-driver.sh "$profile" php "/pkg/tests/runtime/$test-test.php" > "$prefix-$test.log" 2>&1 || code=$?
            printf '%s\t%s\t%s\t%s\t%s\n' "$label" "$profile" "$scenario" "$test" "$code" >> /lab/results/status.tsv
            test "$code" = 0 || failed=1
            echo "$label $profile $test=$code"
        done
    done
done
exit "$failed"
