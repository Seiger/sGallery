#!/bin/sh
set -eu
profile=${1:?Use both, gd, imagick or none}
shift
case "$profile" in both|gd|imagick|none) ;; *) exit 2 ;; esac
scan=$(mktemp -d)
trap 'rm -rf "$scan"' EXIT
php -r 'echo str_replace(",\n", "\n", php_ini_scanned_files() ?: "");' | while IFS= read -r ini; do
    test -f "$ini" || continue
    name=$(basename "$ini")
    case "$name" in
        *imagick*) case "$profile" in both|imagick) ;; *) continue ;; esac ;;
        *gd*) case "$profile" in both|gd) ;; *) continue ;; esac ;;
    esac
    ln -s "$ini" "$scan/$name"
done
export PHP_INI_SCAN_DIR=$scan
export SGALLERY_EXPECT_DRIVER=$profile
"$@"
