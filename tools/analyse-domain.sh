#!/usr/bin/env bash
# PHPStan at level 10 on src/Domain (phpstan-domain.neon). PHPStan fails on an empty path list, so the run waits for the
# domain's first file (milestone 2).
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -z "$(find src/Domain -name '*.php' 2>/dev/null | head -n 1)" ]; then
	echo "src/Domain has no file yet; the level 10 run is skipped."
	exit 0
fi
exec vendor/bin/phpstan analyse --memory-limit=2G --configuration=phpstan-domain.neon
