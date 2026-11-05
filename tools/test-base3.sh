#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
components_dir="$script_dir"
while [[ "$components_dir" != "/" && "${components_dir##*/}" != "components" ]]; do
    components_dir="$(dirname "$components_dir")"
done
if [[ "$components_dir" == "/" ]]; then
    echo "Cannot locate the ILIAS components/ directory." >&2
    exit 1
fi

ilias_root="$(dirname "$components_dir")"
cd "$ilias_root"

mapfile -d '' -t manifests < <(
    find components -type f -name base3.json -print0 | LC_ALL=C sort -z
)

test_dirs=()
coverage_filters=()
for manifest in "${manifests[@]}"; do
    module_dir="${manifest%/base3.json}"

    if [[ -d "$module_dir/src" ]]; then
        coverage_filters+=(--coverage-filter "$module_dir/src")
    fi
    if [[ -d "$module_dir/test" ]]; then
        test_dirs+=("$module_dir/test")
    fi
done

if (( ${#test_dirs[@]} == 0 )); then
    echo "No BASE3 test/ directories found under components/." >&2
    exit 1
fi

php_args=()
for arg in "$@"; do
    if [[ "$arg" == "--coverage-html" ]]; then
        if (( ${#coverage_filters[@]} == 0 )); then
            echo "No BASE3 src/ directories found under components/." >&2
            exit 1
        fi

        if php -m | grep -qi '^pcov$'; then
            php_args+=(-d pcov.enabled=1)
            export XDEBUG_MODE=off
        elif php -m | grep -qi '^xdebug$'; then
            export XDEBUG_MODE=coverage
        else
            echo "Coverage requires PCOV or Xdebug in CLI PHP." >&2
            exit 1
        fi

        rm -rf -- public/docs/coverage
        mkdir -p public/docs/coverage
        break
    fi
done

cache_dir="${TMPDIR:-/tmp}/base3-phpunit-$(printf '%s' "$ilias_root" | sha256sum | cut -c1-16)"
export BASE3_ILIAS_TEST_ROOT="$ilias_root"
exec php "${php_args[@]}" vendor/composer/vendor/bin/phpunit \
    -c "$(dirname "$script_dir")/phpunit.xml" \
    --bootstrap "$script_dir/test-bootstrap.php" \
    --cache-directory "$cache_dir" \
    "${coverage_filters[@]}" \
    "$@" \
    "${test_dirs[@]}"

