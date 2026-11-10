#!/usr/bin/env bash
# Mutation testing (composer infection): 95 % outside the domain (infection.json5), 100 % inside it
# (infection-domain.json5). The domain's run fails when the domain has no file, and when static analysis alone caught a
# mutant that tools/infection-static-allowed.txt does not list.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/domain-present.sh
source tools/domain-present.sh
mkdir -p tmp
php -d xdebug.mode=off vendor/bin/infection --threads=max --configuration=infection.json5 "$@"
php -d xdebug.mode=off vendor/bin/infection --threads=max --configuration=infection-domain.json5 "$@"
# A mutant only static analysis caught must be one the list allows (D-063).
php tools/infection-static.php tmp/infection-domain.json tools/infection-static-allowed.txt
