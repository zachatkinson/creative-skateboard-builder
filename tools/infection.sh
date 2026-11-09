#!/usr/bin/env bash
# Mutation testing (composer infection): 90 % outside the domain (infection.json5), 100 % inside it
# (infection-domain.json5). The domain's run waits for its first file (milestone 2).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p tmp
php -d xdebug.mode=off vendor/bin/infection --threads=max --configuration=infection.json5 "$@"
if [ -n "$(find src/Domain -name '*.php' 2>/dev/null | head -n 1)" ]; then
	php -d xdebug.mode=off vendor/bin/infection --threads=max --configuration=infection-domain.json5 "$@"
else
	echo "src/Domain has no file yet; its 100 % run is skipped."
fi
