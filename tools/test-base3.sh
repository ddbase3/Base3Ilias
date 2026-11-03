
#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ilias_root="$(cd "$script_dir/../../../.." && pwd -P)"

cd "$ilias_root"
exec vendor/composer/vendor/bin/phpunit \
	-c scripts/PHPUnit/phpunit.xml \
	"$@" \
	components/Base3

