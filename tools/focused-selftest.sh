#!/usr/bin/env bash
# The cases of tools/focused.sh (D-324), in a throwaway repository laid out as the plugin is: each change, the commands
# the dry run must print, and the ones it must not. A map that missed a test would let an early failure wait for the
# full /verify; one that chose everything would make the focused run pointless. Part of composer gates.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

ns='CreativeBranding\SkateboardBuilder'
file() { mkdir -p "$(dirname "$work/$1")"; printf '%s\n' "$2" > "$work/$1"; }
php_class() { # path, namespace below the plugin's, body
	file "$1" "<?php
namespace ${ns}\\$2;
$3"
}
test_class() { # name, the classes it uses
	local uses="" class
	for class in "Tests\\Support\\IntegrationTestCase" "${@:2}"; do uses="${uses}use ${ns}\\${class};
"; done
	file "tests/Integration/$1.php" "<?php
namespace ${ns}\\Tests\\Integration;
${uses}final class $1 extends IntegrationTestCase {}"
}

mkdir -p "$work/tools"
cp "$here/focused.sh" "$here/ports.sh" "$work/tools/"
php_class src/Front/BuilderPage.php Front "final class BuilderPage { public function render(): void { new Helper(); require CBUILD_PATH . 'templates/builder/builder.php'; } }"
php_class src/Front/Helper.php Front 'final class Helper {}'
php_class src/Admin/CheckScreen.php Admin "final class CheckScreen { public function render(): void { include CBUILD_PATH . 'templates/admin/check.php'; } }"
php_class src/Domain/Lonely.php Domain 'final class Lonely {}'
php_class src/Port/Things.php Port 'interface Things {}'
php_class src/Adapter/WpThings.php Adapter "use ${ns}\\Port\\Things;
final class WpThings implements Things {}"
php_class src/Service/UsesThings.php Service "use ${ns}\\Port\\Things;
final class UsesThings { public function __construct( private Things \$things ) {} }"
php_class src/Rest/StateRoute.php Rest "final class StateRoute { private const SCHEMA = 'state.json'; }"
file src/Container.php "<?php
namespace ${ns};
final class Container {
	private const BOUND = array(
		Port\\Things::class => Adapter\\WpThings::class,
	);
}"
php_class tests/Support/IntegrationTestCase.php 'Tests\Support' 'abstract class IntegrationTestCase {}'
file templates/builder/builder.php "<?php require __DIR__ . '/step.php'; ?>"
file templates/builder/step.php '<form class="cbuild__step"></form>'
file templates/admin/check.php '<div class="wrap"></div>'
test_class BuilderPageTest 'Front\BuilderPage'
test_class CheckScreenTest 'Admin\CheckScreen'
test_class ThingsTest 'Service\UsesThings'
test_class StateRouteTest 'Rest\StateRoute'
for name in OneTest TwoTest ThreeTest FourTest; do test_class "$name"; done
file tests/e2e/support/test.ts "export { test } from '@playwright/test';"
file tests/e2e/support/login.ts 'export const login = 1;'
file tests/e2e/support/global-setup.ts 'export default () => {};'
file tests/e2e/builder.spec.ts "import { test } from './support/test';

const helper = '.cbuild__step';

test( 'opens the builder', async () => {
	await go( helper );
} );

// About the deck.
test( 'picks a deck (the 8.25)', async () => {
	await go( 1 );
} );"
file tests/e2e/cart.spec.ts "import { test } from './support/test';
test( 'adds the build', async () => { await go( '.cbuild__bar' ); } );"
file tests/e2e/check.spec.ts "import { test } from './support/test';
import { login } from './support/login';
test( 'checks', async () => { await login(); } );"
file tests/e2e/build-tab.spec.ts "import { test } from './support/test';
test( 'saves', async () => {} );"
file tests/e2e/visual.spec.ts "import { test } from './support/test';
test( 'looks', async () => { await go( '.cbuild__stage' ); } );"
file assets/src/types/state.d.ts 'export type State = object;'
file assets/src/builder/view.ts "import type { State } from '../types/state';"
file assets/src/admin/settle.ts 'export const settle = 1;'
file assets/src/admin/build-tab.ts "import { settle } from './settle';"
file schema/state.json '{}'
file composer.json '{}'
file playwright.config.ts 'export default {};'
file docs/NOTES.md 'notes'
file tools/gates.sh 'exit 0'
file tools/integration.sh 'exit 0'

cd "$work"
git init -q
git config user.email ci@example.com
git config user.name ci
git add -A && git commit -qm base

status=0
count=0
pw='+ npx playwright test --project=chromium-desktop --project=chromium-phone'
builder="$pw tests/e2e/builder.spec.ts tests/e2e/cart.spec.ts"
# Each case: a change made by the shell words in $1, then lines the dry run must print (a line) and must not (!line).
check() {
	local change="$1" out line
	shift
	eval "$change"
	out="$(bash tools/focused.sh --dry-run "${base_ref:-HEAD}" 2>&1)" || { echo "focused self-test: '${change}' failed:"; echo "$out"; status=1; }
	count=$((count + 1))
	for line in "$@"; do
		case "$line" in
			!*)
				if grep -qF -- "${line#!}" <<< "$out"; then
					echo "focused self-test: '${change}' printed '${line#!}':"; echo "$out"; status=1
				fi ;;
			*)
				if ! grep -qxF -- "$line" <<< "$out"; then
					echo "focused self-test: '${change}' did not print '${line}':"; echo "$out"; status=1
				fi ;;
		esac
	done
	git reset -q --hard
	git clean -qfd
}
edit() { printf '%s\n' "$2" >> "$1"; }

check 'edit src/Front/BuilderPage.php "// changed"' \
	'+ bash tools/integration.sh --filter BuilderPageTest' "$builder" '!visual.spec.ts' '!npm run build'
check 'edit src/Front/Helper.php "// changed"' \
	'+ bash tools/integration.sh --filter BuilderPageTest' "$builder"
check 'edit templates/builder/step.php "<p></p>"' \
	'+ bash tools/integration.sh --filter BuilderPageTest' "$builder" '!npm run build'
check 'edit templates/admin/check.php "<p></p>"' \
	'+ bash tools/integration.sh --filter CheckScreenTest' "$pw tests/e2e/check.spec.ts"
check 'edit assets/src/builder/view.ts "// changed"' \
	'+ npm run build' "$builder" '!tools/integration.sh'
check 'edit assets/src/admin/settle.ts "// changed"' \
	'+ npm run build' "$pw tests/e2e/build-tab.spec.ts"
check 'sed -i.bak "s/go( 1 )/go( 2 )/" tests/e2e/builder.spec.ts && rm tests/e2e/builder.spec.ts.bak' \
	"$pw tests/e2e/builder.spec.ts -g 'picks a deck \\(the 8\\.25\\)'" '!opens the builder'
check 'sed -i.bak "s/About the deck/About a deck/" tests/e2e/builder.spec.ts && rm tests/e2e/builder.spec.ts.bak' \
	"$pw tests/e2e/builder.spec.ts -g 'picks a deck \\(the 8\\.25\\)'"
check 'printf "\ntest( %s, async () => {} );\n" "\"a new test\"" >> tests/e2e/builder.spec.ts' \
	"$pw tests/e2e/builder.spec.ts -g 'a new test'"
check 'sed -i.bak "s/const helper/const finder/" tests/e2e/builder.spec.ts && rm tests/e2e/builder.spec.ts.bak' \
	"$pw tests/e2e/builder.spec.ts" '! -g '
check 'edit tests/e2e/support/login.ts "// changed"' \
	"$pw tests/e2e/check.spec.ts"
check 'edit tests/Support/IntegrationTestCase.php "// changed"' \
	'+ bash tools/integration.sh' '!--filter'
check 'edit src/Domain/Lonely.php "// changed"' \
	'+ bash tools/integration.sh' '!--filter'
check 'edit src/Adapter/WpThings.php "// changed"' \
	'+ bash tools/integration.sh --filter ThingsTest'
check 'sed -i.bak "s/BOUND = array(/BOUND = array( Port\\\\Things::class => Adapter\\\\WpThings::class,/" src/Container.php && rm src/Container.php.bak' \
	'+ bash tools/integration.sh --filter ThingsTest'
check 'edit schema/state.json " "' \
	'+ bash tools/integration.sh --filter StateRouteTest' "$builder" '!npm run build'
check 'edit composer.json " "' \
	"Not run: a change above can't be mapped to some of the tests. Run the full /verify." '!+ '
check 'edit tools/integration.sh "# changed"' \
	"Not run: a change above can't be mapped to some of the tests. Run the full /verify."
check 'edit playwright.config.ts "// changed"' \
	"$pw" '!tools/integration.sh'
check 'edit docs/NOTES.md "more"' \
	'No integration or browser test covers these changes. Run the full /verify.' '!+ '
check 'edit tools/gates.sh "# changed"' \
	'No integration or browser test covers these changes. Run the full /verify.'
check 'test_class NewTest' \
	'+ bash tools/integration.sh --filter NewTest'
# shellcheck disable=SC2016 # expanded by check's eval
check 'for t in OneTest TwoTest ThreeTest FourTest BuilderPageTest CheckScreenTest; do edit "tests/Integration/$t.php" "// changed"; done' \
	'+ bash tools/integration.sh' '!--filter'
check 'true' 'Nothing changed since HEAD.'
# A base ref: what was committed since, with a clean working tree.
edit src/Front/Helper.php '// committed'
git commit -qam 'a commit'
base_ref=HEAD~1
check 'true' '+ bash tools/integration.sh --filter BuilderPageTest' "$builder"
base_ref=''

if [ "$status" -eq 0 ]; then echo "Focused tests self-test: ${count} cases."; fi
exit "$status"
