#!/usr/bin/env bash
# Runs, before the full /verify, only the integration tests and browser tests that cover what changed, so a long
# /verify isn't spent finding an early failure. It never replaces /verify (D-324).
#
#   bash tools/focused.sh [--dry-run] [--browser-only] [<base-ref>]
#   bash tools/lane.sh 2 bash tools/focused.sh            (a second checkout's sites, tools/lane.sh)
#   composer focused -- --dry-run 757b8e4~1
#
# What changed is the difference between <base-ref> and the working tree, plus untracked files. <base-ref> is HEAD by
# default: the staged and unstaged changes. Each changed file is mapped to tests from what the repository says, and
# the reason for each pick is printed:
#
#   src/<A>/<B>.php        the integration tests that name the class in full (a use statement or \A\B::class), and
#                          the tests that name a class using it (one step out: a class in src/ or tests/Support/ that
#                          names it, or names it beside it in its namespace). An adapter counts its port's users too.
#                          With none, the whole suite. A class that requires a template under templates/builder/, or
#                          uses one that does, adds the builder's specs.
#   tests/Support/<X>.php  the same, for the tests' own helpers.
#   src/Container.php      the tests that name a class named on its changed lines.
#   templates/<d>/<t>.php  the classes that require it (or the template that requires it, followed up), mapped as
#                          above; the builder's specs for templates/builder/; an e2e spec named <t>.spec.ts.
#   schema/<s>.json        the classes and tests that name the file, and the scripts that import its types.
#   assets/src/builder/*   the builder's specs. assets/src/<d>/<x>.ts elsewhere: the spec named after <x>, after
#                          a file that imports it, or after <d>. npm run build runs first.
#   tests/Integration/*    the changed test classes.
#   tests/e2e/*.spec.ts    only the tests whose lines changed (-g with their titles; a comment right above a test is
#                          its), or the whole spec when a change is outside any test. tests/e2e/support/<x>.ts: the
#                          specs that import it.
#
# The builder's specs are the e2e specs that use the builder's classes (cbuild__), but visual.spec.ts, which runs only
# in tools/visual.sh's container. The browser runs are chromium-desktop and chromium-phone, as /verify's. When three
# quarters of the integration suite or more is chosen, the whole suite runs.
# Where a change can't be mapped with confidence, the whole suite or the whole browser run is chosen (the integration
# bootstrap, its base test case and shims, phpunit-integration.xml.dist, src/Install/; playwright.config.ts, the
# global setup, the mu-plugins and fixtures), or the script says to run the full /verify and runs nothing (Composer
# and npm files, wp-env's configs, webpack, the plugin's main file, src/Plugin.php, and the tools the suites run).
# Unit tests, Vitest, docs and the other tools are left to /verify's other steps.
#
# --browser-only is tools/verify.sh's browser step before a commit (D-567): only the browser tests, with no build (the
# run has built) and no integration tests (the run has had the whole suite). Where a change can't be mapped, it runs
# nothing and exits 3, so the caller runs the whole browser step.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/ports.sh
source tools/ports.sh

dry=0
browser_only=0
base=HEAD
while [ $# -gt 0 ]; do
	case "$1" in
		--dry-run) dry=1 ;;
		--browser-only) browser_only=1 ;;
		-h | --help)
			sed -n '2,/^set -euo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'
			exit 0
			;;
		-*) echo "Unknown option $1. Usage: bash tools/focused.sh [--dry-run] [--browser-only] [<base-ref>]" >&2; exit 2 ;;
		*) base="$1" ;;
	esac
	shift
done
if ! git rev-parse --verify --quiet "${base}^{commit}" > /dev/null; then
	echo "focused: ${base} is not a commit." >&2
	exit 2
fi

ns='CreativeBranding\SkateboardBuilder'
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
picks="$work/picks"   # kind<TAB>target<TAB>reason<TAB>a test's title, for e2e-title
: > "$picks"
pick() { printf '%s\t%s\t%s\t%s\n' "$1" "$2" "$3" "${4:-}" >> "$picks"; }

# The fully qualified name a PHP file under src/ or tests/ declares (composer.json's PSR-4 roots).
class_of() {
	local path="${1%.php}"
	case "$path" in
		src/*) path="${ns}\\${path#src/}" ;;
		tests/*) path="${ns}\\Tests\\${path#tests/}" ;;
	esac
	printf '%s' "$path" | tr '/' '\134'
}
# An extended regular expression for a class's full name, written with one backslash or two (a PHP string), whole.
class_pattern() {
	printf '%s' "$1" | sed 's/\\/\\\\{1,2}/g'
	printf '%s' '([^A-Za-z0-9_]|$)'
}
# The integration test classes whose files name the class in full.
tests_naming() {
	grep -rlE "$(class_pattern "$1")" tests/Integration --include='*Test.php' 2> /dev/null | sed 's|.*/||; s|\.php$||' | sort -u || true
}
# The e2e specs that use the builder's classes. visual.spec.ts runs only in tools/visual.sh's container.
builder_specs() {
	grep -lF 'cbuild__' tests/e2e/*.spec.ts 2> /dev/null | grep -v '/visual\.spec\.ts$' || true
}
pick_builder_specs() {
	local spec
	for spec in $(builder_specs); do pick e2e "$spec" "$1"; done
}

# The files in src/ and tests/Support/ that use a class: they name it in full, or by its short name beside it in its
# namespace. The container and the plugin's entry wire every class, and every test extends a base test case, so those
# are left out.
referrers_of() {
	local file="$1" fq short
	fq="$(class_of "$file")"
	short="${fq##*\\}"
	{
		grep -rlE "$(class_pattern "$fq")" src tests/Support --include='*.php' 2> /dev/null || true
		grep -lw "$short" "$(dirname "$file")"/*.php 2> /dev/null || true
	} | grep -vxF -e "$file" -e src/Container.php -e src/Plugin.php | grep -v 'TestCase\.php$' | sort -u || true
}
# The ports (src/Port/) a class implements, by the names its use statements give them.
ports_of() {
	local file="$1" name
	[ -f "$file" ] || return 0
	for name in $(sed -n 's/^.*class [A-Za-z0-9_]* .*implements \([A-Za-z0-9_, ]*\).*$/\1/p' "$file" | tr ',' ' '); do
		if grep -qF "use ${ns}\\Port\\${name};" "$file" || [ "$(dirname "$file")" = src/Port ]; then
			[ -f "src/Port/${name}.php" ] && echo "src/Port/${name}.php"
		fi
	done
	return 0
}
# A class: the tests that name it, and the tests that name a class using it, one step out. An adapter is reached
# through its port, so the port and the port's users count as its users. With none, the whole suite.
map_class() {
	local file="$1" fq short refs ref found=0 test port
	fq="$(class_of "$file")"
	short="${fq##*\\}"
	if [ -f "$file" ] && grep -q 'templates/builder/' "$file"; then
		pick_builder_specs "$file renders the builder's templates"
	fi
	for test in $(tests_naming "$fq"); do
		pick int "$test" "names ${short} (${file})"
		found=1
	done
	refs="$(referrers_of "$file")"
	for port in $(ports_of "$file"); do
		refs="$(printf '%s\n%s\n%s' "$refs" "$port" "$(referrers_of "$port")" | grep -vxF "$file" | sort -u || true)"
	done
	for ref in $refs; do
		case "$ref" in src/* | tests/Support/*) ;; *) continue ;; esac
		for test in $(tests_naming "$(class_of "$ref")"); do
			pick int "$test" "names $(basename "$ref" .php), which uses ${short} (${file})"
			found=1
		done
		if grep -q 'templates/builder/' "$ref"; then
			pick_builder_specs "$ref, which uses ${short}, renders the builder's templates"
		fi
	done
	if [ "$found" -eq 0 ]; then
		pick int-all '' "no integration test names ${short} or a class using it (${file})"
	fi
}

# A template: the classes that require it, or the templates that require it, followed up to a class.
map_template() {
	local file="$1" name parent classes class seen="$2"
	name="$(basename "$file" .php)"
	case "$file" in templates/builder/*) pick_builder_specs "$file is the builder's" ;; esac
	if [ -f "tests/e2e/${name}.spec.ts" ]; then
		pick e2e "tests/e2e/${name}.spec.ts" "named after $file"
	fi
	classes="$(grep -rlF "$file" src --include='*.php' 2> /dev/null || true)"
	for class in $classes; do
		map_class "$class"
	done
	for parent in $(grep -lE "__DIR__ \. '/${name}\.php'" "$(dirname "$file")"/*.php 2> /dev/null || true); do
		case " $seen " in *" $parent "*) continue ;; esac
		classes="${classes}${parent}"
		map_template "$parent" "$seen $file"
	done
	if [ -z "$classes" ]; then
		pick int-all '' "nothing in src/ requires $file"
	fi
}

# A script: the builder's specs, or the specs named after it, a file that imports it, or its folder.
map_script() {
	local file="$1" dir name entries entry found=0
	dir="$(dirname "$file")"
	name="$(basename "$file")"
	name="${name%%.*}"
	case "$file" in
		assets/src/builder/*) pick_builder_specs "$file is the builder's script or style"; return 0 ;;
	esac
	entries="$name $(grep -rlE "from '\.{1,2}/([a-z]+/)?${name}'" assets/src 2> /dev/null | sed 's|.*/||; s|\..*$||' | tr '\n' ' ')"
	for entry in $entries; do
		if [ -f "tests/e2e/${entry}.spec.ts" ]; then
			pick e2e "tests/e2e/${entry}.spec.ts" "named after ${entry}, which is or imports $file"
			found=1
		fi
	done
	if [ "$found" -eq 0 ] && [ -f "tests/e2e/$(basename "$dir").spec.ts" ]; then
		pick e2e "tests/e2e/$(basename "$dir").spec.ts" "named after $dir"
		found=1
	fi
	if [ "$found" -eq 0 ]; then
		for entry in $entries; do
			case "$(grep -rlE "from '\.{1,2}/([a-z]+/)?${entry}'" assets/src/builder 2> /dev/null || true)" in
				'') ;;
				*) pick_builder_specs "the builder's script imports ${entry}"; found=1 ;;
			esac
		done
	fi
	if [ "$found" -eq 0 ]; then
		pick e2e-all '' "no spec is named after $file or a file that imports it"
	fi
}

# The tests a spec's changed lines fall in, by title; the whole spec when a change is outside every test.
map_spec() {
	local file="$1" lines tests
	if ! git cat-file -e "${base}:${file}" 2> /dev/null; then
		pick e2e "$file" "new"
		return 0
	fi
	# The new side's changed lines; a deletion touches the lines on either side of it.
	lines="$(git diff -U0 --no-renames "$base" -- "$file" | awk '
		/^@@ / {
			split($3, n, ",")
			start = substr(n[1], 2) + 0
			count = (n[2] == "") ? 1 : n[2] + 0
			if (count == 0) { print start; print start + 1 }
			for (i = 0; i < count; i++) print start + i
		}')"
	# Each test(: its first line (with the comment right above it), its last line and its title, or ? when the title
	# isn't one plain string.
	tests="$(awk '
		function title(s,   q, rest, i) {
			q = substr(s, 1, 1)
			if (q != "\047" && q != "\"" && q != "`") return "?"
			rest = substr(s, 2)
			i = index(rest, q)
			if (i == 0) return "?"
			s = substr(rest, 1, i - 1)
			if (s ~ /\\/ || (q == "`" && s ~ /\$\{/)) return "?"
			return s
		}
		/^[ \t]*\/\// { if (lead == 0) lead = NR; next }
		{
			if (open && $0 ~ ("^" indent "\\}[ \t]*\\)[ \t]*;?[ \t]*$")) {
				print first "\t" NR "\t" name
				open = 0
			}
		}
		!open && /^[ \t]*test\(/ {
			indent = $0; sub(/test\(.*/, "", indent)
			first = lead ? lead : NR
			rest = $0; sub(/^[ \t]*test\([ \t]*/, "", rest)
			if (rest == "") { getline; rest = $0; sub(/^[ \t]*/, "", rest) }
			name = title(rest)
			open = 1
			# A test on one line ends where it starts.
			if ($0 ~ /\}[ \t]*\)[ \t]*;?[ \t]*$/) { print first "\t" NR "\t" name; open = 0 }
		}
		{ lead = 0 }
	' "$file")"
	printf '%s\n' "$tests" > "$work/tests"
	# A blank line belongs to no test, and changes none.
	awk 'NF == 0 { print NR }' "$file" > "$work/blank"
	local line hit whole=0 titles=""
	for line in $lines; do
		grep -qx "$line" "$work/blank" && continue
		hit="$(awk -F '\t' -v l="$line" '$1 <= l && l <= $2 { print $3; exit }' "$work/tests")"
		if [ -z "$hit" ] || [ "$hit" = '?' ]; then
			whole=1
			break
		fi
		case "$titles" in *"|${hit}|"*) ;; *) titles="${titles}|${hit}|" ;; esac
	done
	if [ "$whole" -eq 1 ] || [ -z "$titles" ]; then
		pick e2e "$file" "changed outside a test with a plain title"
		return 0
	fi
	printf '%s' "$titles" | tr '|' '\n' | sed '/^$/d' | while IFS= read -r line; do
		pick e2e-title "$file" "this test changed" "$line"
	done
}

# The container: the tests that name a class named on its changed lines (Port\X => Adapter\Y); for a class no test
# names, its users' tests (map_class). A change that names no class in src/ can't be mapped.
map_wiring() {
	local file="$1" names name class tests test found=0
	names="$(git diff -U0 --no-renames "$base" -- "$file" | grep -E '^[-+][^-+]' | grep -oE '[A-Z][A-Za-z0-9]*(\\[A-Z][A-Za-z0-9]*)+' | sed 's/^CreativeBranding\\SkateboardBuilder\\//' | sort -u || true)"
	for name in $names; do
		class="src/$(printf '%s' "$name" | tr '\134' '/').php"
		[ -f "$class" ] || continue
		found=1
		tests="$(tests_naming "$(class_of "$class")")"
		[ -n "$tests" ] || { map_class "$class"; continue; }
		for test in $tests; do
			pick int "$test" "names ${name}, which $file wires"
		done
	done
	[ "$found" -eq 1 ] || pick full "$file" "its change names no class in src/"
}

# A JSON Schema: the classes and integration tests that name its file, and the scripts that import the types made from
# it (npm run types). With none, the whole suite.
map_schema() {
	local file="$1" name class test found=0
	name="$(basename "$file")"
	for class in $(grep -rlF "$name" src --include='*.php' 2> /dev/null || true); do
		map_class "$class"
		found=1
	done
	for test in $(grep -rlF "$name" tests/Integration --include='*Test.php' 2> /dev/null || true); do
		pick int "$(basename "$test" .php)" "names $file"
		found=1
	done
	if [ -f "assets/src/types/${name%.json}.d.ts" ]; then
		map_script "assets/src/types/${name%.json}.d.ts"
	fi
	[ "$found" -eq 1 ] || pick int-all '' "nothing in src/ or tests/Integration/ names $file"
}

# The specs that import an e2e support file, by its name.
map_support() {
	local file="$1" name specs spec
	name="$(basename "$file" .ts)"
	specs="$(grep -lE "from '\./support/${name}'" tests/e2e/*.spec.ts 2> /dev/null || true)"
	for spec in $specs; do
		pick e2e "$spec" "imports $file"
	done
	[ -n "$specs" ] || pick e2e-all '' "$file is used by no one spec"
}

# The changed files: base to working tree, and untracked files.
{
	git diff --name-only --no-renames "$base" --
	git ls-files --others --exclude-standard
} | sort -u > "$work/changed"

build=0
while IFS= read -r file; do
	[ -n "$file" ] || continue
	case "$file" in
		tools/focused.sh | tools/focused-selftest.sh)
			pick none "$file" "tested by its self-test in composer gates" ;;
		*.md | docs/* | .github/* | languages/* | LICENSE | readme.txt | .claude/*)
			pick none "$file" "not run by the integration or browser suites" ;;
		tests/Unit/* | tests/js/* | tests/Benchmark/* | tests/Fixtures/* | .githooks/*)
			pick none "$file" "unit, Vitest, benchmark and gate cases run in /verify's other steps" ;;
		composer.json | composer.lock | package.json | package-lock.json | .wp-env.json | .wp-env.tests.json \
			| webpack.config.js | tsconfig.json | creative-skateboard-builder.php | uninstall.php | src/Plugin.php \
			| tools/integration.sh | tools/integration/* | tools/e2e-site.sh | tools/build-assets.sh | tools/wp-env*.sh)
			pick full "$file" "can't be mapped with confidence" ;;
		tools/*)
			# A tool the suites' own setup names runs under every test; any other runs in /verify's other steps or CI.
			if grep -qF "$file" tools/integration.sh tools/e2e-site.sh playwright.config.ts tests/bootstrap-integration.php \
				tests/Support/*.php tests/e2e/support/*.ts 2> /dev/null; then
				pick full "$file" "the suites' setup runs it"
			else
				pick none "$file" "the suites don't run it; /verify's other steps and CI do"
			fi ;;
		schema/*.json) map_schema "$file" ;;
		tests/bootstrap-integration.php | phpunit-integration.xml.dist | tests/Support/IntegrationTestCase.php \
			| tests/Support/wp-*.php | src/Install/*)
			pick int-all '' "$file sets up every integration test" ;;
		playwright.config.ts | tests/e2e/support/global-setup.ts | tests/e2e/mu-plugins/* | tests/e2e/fixtures/*)
			pick e2e-all '' "$file sets up every browser test" ;;
		tests/e2e/__snapshots__/* | tests/e2e/visual.spec.ts)
			pick none "$file" "visual runs only in its container: bash tools/visual.sh" ;;
		tests/Integration/*Test.php)
			if [ -f "$file" ]; then pick int "$(basename "$file" .php)" "changed"; else pick none "$file" "removed"; fi ;;
		tests/e2e/*.spec.ts)
			if [ -f "$file" ]; then map_spec "$file"; else pick none "$file" "removed"; fi ;;
		tests/e2e/support/*.ts) map_support "$file" ;;
		src/Container.php) map_wiring "$file" ;;
		src/*.php | tests/Support/*.php) map_class "$file" ;;
		templates/*.php) map_template "$file" "" ;;
		assets/src/*)
			build=1
			map_script "$file" ;;
		*) pick full "$file" "can't be mapped with confidence" ;;
	esac
done < "$work/changed"

# Plain words for a shell: a word as it is, or in single quotes.
quoted() {
	local arg out=""
	for arg in "$@"; do
		case "$arg" in
			'' | *[!A-Za-z0-9_./=:,+@%-]*) arg="'$(printf '%s' "$arg" | sed "s/'/'\\\\''/g")'" ;;
		esac
		out="${out:+$out }$arg"
	done
	printf '%s' "$out"
}
run() {
	echo "+ $(quoted "$@")"
	[ "$dry" -eq 1 ] || "$@"
}

echo "Focused tests for the changes since ${base} (sites on :${CBUILD_PORT} and :${CBUILD_TESTS_PORT}). Run the full /verify after."
if [ ! -s "$work/changed" ]; then
	echo "Nothing changed since ${base}."
	exit 0
fi

# Three quarters of the integration suite or more is run whole.
suite=0
for f in tests/Integration/*Test.php; do [ -f "$f" ] && suite=$((suite + 1)); done
int_all=0
grep -q '^int-all	' "$picks" && int_all=1
int_tests="$(awk -F '\t' '$1 == "int" { print $2 }' "$picks" | sort -u)"
chosen="$(printf '%s' "$int_tests" | grep -c . || true)"
if [ "$int_all" -eq 0 ] && [ "$suite" -gt 0 ] && [ $((chosen * 4)) -ge $((suite * 3)) ]; then
	pick int-all '' "${chosen} of its ${suite} tests were chosen"
	int_all=1
fi

# Each pick once, with its first reason and how many more there are. Under the whole suite, its tests aren't listed.
awk '!seen[$0]++' "$picks" | sort -s -t "$(printf '\t')" -k1,1 -k2,2 | awk -F '\t' -v all="$int_all" '
	$1 == "int" && all == 1 { next }
	{
		if ($1 == "int") what = "integration " $2
		else if ($1 == "int-all") what = "integration, whole suite"
		else if ($1 == "e2e") what = "browser " $2
		else if ($1 == "e2e-title") what = "browser " $2 " > \"" $4 "\""
		else if ($1 == "e2e-all") what = "browser, every spec"
		else if ($1 == "full") what = "full /verify: " $2
		else what = "nothing: " $2
		if (!(what in why)) { order[++n] = what; why[what] = $3 } else more[what]++
	}
	END {
		for (i = 1; i <= n; i++) {
			w = order[i]
			print "  " w "  (" why[w] (more[w] ? ", and " more[w] " more" : "") ")"
		}
	}'

if grep -q '^full	' "$picks"; then
	echo "Not run: a change above can't be mapped to some of the tests. Run the full /verify."
	[ "$browser_only" -eq 0 ] || exit 3
	exit 0
fi

e2e_all=0
grep -q '^e2e-all	' "$picks" && e2e_all=1
whole_specs="$(awk -F '\t' '$1 == "e2e" { print $2 }' "$picks" | sort -u)"
title_specs="$(awk -F '\t' '$1 == "e2e-title" { print $2 }' "$picks" | sort -u | grep -vxF "${whole_specs:-//}" || true)"
titles=""
if [ -n "$title_specs" ]; then
	# Only the titles of the specs not run whole, each a literal in Playwright's -g expression.
	titles="$(awk -F '\t' -v keep="$(printf '%s\n' "$title_specs" | tr '\n' '\034')" '
		BEGIN { n = split(keep, k, "\034"); for (i = 1; i <= n; i++) if (k[i] != "") w[k[i]] = 1 }
		$1 == "e2e-title" && ($2 in w) { print $4 }' "$picks" | sort -u)"
fi

if [ -z "$int_tests" ] && [ "$int_all" -eq 0 ] && [ -z "$whole_specs" ] && [ -z "$title_specs" ] && [ "$e2e_all" -eq 0 ]; then
	echo "No integration or browser test covers these changes. Run the full /verify."
	exit 0
fi

if [ "$dry" -eq 0 ]; then
	if [ "$browser_only" -eq 0 ] && { [ "$int_all" -eq 1 ] || [ -n "$int_tests" ]; }; then
		curl -sf "http://localhost:${CBUILD_TESTS_PORT}/" > /dev/null || { echo "The integration site isn't up on :${CBUILD_TESTS_PORT}. Start it (npm run env:start, in this lane)." >&2; exit 1; }
	fi
	if [ "$e2e_all" -eq 1 ] || [ -n "$whole_specs" ] || [ -n "$title_specs" ]; then
		curl -sf "http://localhost:${CBUILD_PORT}/" > /dev/null || { echo "The browser site isn't up on :${CBUILD_PORT}. Start it (npm run env:start, in this lane)." >&2; exit 1; }
	fi
fi

projects=(--project=chromium-desktop --project=chromium-phone)
if [ "$browser_only" -eq 1 ]; then
	build=0
	int_all=0
	int_tests=""
fi
[ "$build" -eq 0 ] || run npm run build
if [ "$int_all" -eq 1 ]; then
	run bash tools/integration.sh
elif [ -n "$int_tests" ]; then
	run bash tools/integration.sh --filter "$(printf '%s\n' "$int_tests" | paste -sd '|' -)"
fi
if [ "$e2e_all" -eq 1 ]; then
	run npx playwright test "${projects[@]}"
else
	if [ -n "$whole_specs" ]; then
		# shellcheck disable=SC2046 # one word a spec path, none with spaces
		run npx playwright test "${projects[@]}" $(printf '%s\n' "$whole_specs")
	fi
	if [ -n "$title_specs" ]; then
		grep_expr="$(printf '%s\n' "$titles" | sed 's/[][\\.*^$?+(){}|/]/\\&/g' | paste -sd '|' -)"
		# shellcheck disable=SC2046 # one word a spec path, none with spaces
		run npx playwright test "${projects[@]}" $(printf '%s\n' "$title_specs") -g "$grep_expr"
	fi
fi
