#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ilias_root="$(cd "$script_dir/../../../.." && pwd -P)"

cd "$ilias_root"
mapfile -d '' -t test_dirs < <(
    find components/Base3 -mindepth 2 -maxdepth 2 -type d \
        \( -name test -o -name tests \) -print0 | LC_ALL=C sort -z
)

exec vendor/composer/vendor/bin/phpunit \
    -c scripts/PHPUnit/phpunit.xml \
    "$@" \
    "${test_dirs[@]}"
