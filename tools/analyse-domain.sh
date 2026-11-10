#!/usr/bin/env bash
# PHPStan at level 10 on src/Domain (phpstan-domain.neon). It fails when the domain has no file.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/domain-present.sh
source tools/domain-present.sh
exec vendor/bin/phpstan analyse --memory-limit=2G --configuration=phpstan-domain.neon
