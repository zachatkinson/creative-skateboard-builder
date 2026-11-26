#!/usr/bin/env bash
# The no-conflict landing's tools, held to what they promise (D-462 to D-464), in a throwaway repository:
# - two branches that each add decisions and index lines merge with no conflict, in number order, and two that add the
#   same number, or change an old line differently, still conflict;
# - two branches that each add translations merge with no conflict, and one entry changed differently on both fails;
# - the changelog's fragments are checked, and assembled into [Unreleased] or a release with its compare link.
set -euo pipefail
cd "$(dirname "$0")/.."
root="$PWD"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
fail=0
check() {
	if eval "$2"; then echo "ok: $1"; else echo "FAILED: $1"; fail=1; fi
}

repo="$work/repo"
git init -q -b main "$repo"
cd "$repo"
git config user.email selftest@example.com
git config user.name selftest
git config commit.gpgsign false
git config merge.decisions.driver "php $root/tools/merge-decisions.php %O %A %B"
git config merge.po.driver "php $root/tools/merge-po.php %O %A %B"
printf 'docs/DECISIONS.md merge=decisions\nlanguages/*.po merge=po\n' > .gitattributes
mkdir -p docs languages
cat > docs/DECISIONS.md <<'EOF'
# Decisions

## Index

- [D-1 · One](#d-1)

## Entries

### D-1 · One

The first.
EOF
cat > languages/fr.po <<'EOF'
msgid ""
msgstr ""
"POT-Creation-Date: 2026-11-26\n"

#: a.php:1
msgid "Deck"
msgstr "Planche"
EOF
git add -A && git commit -qm base

git switch -qc one
sed -i.bak 's/^- \[D-1 · One\](#d-1)$/- [D-1 · One](#d-1)\n- [D-470 · Lane one](#d-470)/' docs/DECISIONS.md && rm docs/DECISIONS.md.bak
printf '\n### D-470 · Lane one\n\nFrom lane one.\n' >> docs/DECISIONS.md
sed -i.bak 's/2026-11-26/2026-11-27/' languages/fr.po && rm languages/fr.po.bak
printf '\n#: b.php:1\nmsgid "Trucks"\nmsgstr "Trucks"\n' >> languages/fr.po
git commit -qam one

git switch -q main
git switch -qc two
sed -i.bak 's/^- \[D-1 · One\](#d-1)$/- [D-1 · One](#d-1)\n- [D-490 · Lane two](#d-490)/' docs/DECISIONS.md && rm docs/DECISIONS.md.bak
printf '\n### D-490 · Lane two\n\nFrom lane two.\n' >> docs/DECISIONS.md
sed -i.bak 's/2026-11-26/2026-11-28/' languages/fr.po && rm languages/fr.po.bak
printf '\n#: c.php:1\nmsgid "Wheels"\nmsgstr "Roues"\n' >> languages/fr.po
git commit -qam two

git switch -q one
check "two lanes' decisions and translations merge with no conflict" "git merge -q --no-edit two >/dev/null 2>&1"
check "the index lines are in number order" "grep -A2 '^- \[D-1 ' docs/DECISIONS.md | tr '\n' ' ' | grep -q 'D-1 .*D-470 .*D-490'"
check "the entries are in number order" "[ \"\$(grep -o '^### D-[0-9]*' docs/DECISIONS.md | tr '\n' ' ')\" = '### D-1 ### D-470 ### D-490 ' ]"
check "every entry has a blank line before it" "[ \"\$(grep -c -B1 '^### D-' docs/DECISIONS.md | head -1)\" ] && ! grep -B1 '^### D-' docs/DECISIONS.md | grep -v '^### D-' | grep -v '^--$' | grep -q ."
check "no conflict marker is left" "! grep -q '^<<<<<<<\|^>>>>>>>' docs/DECISIONS.md languages/fr.po"
check "both lanes' translations are kept" "grep -q 'msgstr \"Trucks\"' languages/fr.po && grep -q 'msgstr \"Roues\"' languages/fr.po"
check "the base's translation is kept once" "[ \"\$(grep -c 'msgid \"Deck\"' languages/fr.po)\" = 1 ]"

git switch -q main
git switch -qc three
printf '\n### D-470 · Another lane one\n\nThe same number.\n' >> docs/DECISIONS.md
sed -i.bak 's/msgstr "Planche"/msgstr "Plateau"/' languages/fr.po && rm languages/fr.po.bak
git commit -qam three
git switch -q main
git switch -qc four
sed -i.bak 's/msgstr "Planche"/msgstr "Board"/' languages/fr.po && rm languages/fr.po.bak
git commit -qam four
git switch -q three
check "one entry translated differently on both sides still conflicts" "! git merge -q --no-edit four >/dev/null 2>&1"
git merge --abort
git switch -q one
check "two lanes adding the same number still conflict" "! git merge -q --no-edit three >/dev/null 2>&1"
git merge --abort

cd "$root"
frag="$work/frag"
mkdir -p "$frag/changelog.d" "$frag/tools"
cp tools/changelog.php "$frag/tools/"
cat > "$frag/CHANGELOG.md" <<'EOF'
# Changelog

## [1.3.0] - 2026-11-26

### Added

- Old.

[1.3.0]: https://github.com/example/repo/compare/v1.2.0...v1.3.0
EOF
printf '### Fixed\n\n- A fix (`cbuild_x`).\n\n### Added\n\n- A thing.\n' > "$frag/changelog.d/l1-a.md"
printf '### Added\n\n- Another thing.\n' > "$frag/changelog.d/l2-b.md"
check "good fragments pass the check" "php '$frag/tools/changelog.php' --check >/dev/null"
printf 'Loose words.\n### Added\n\n- x\n' > "$frag/changelog.d/l3-bad.md"
check "words before a heading fail the check" "! php '$frag/tools/changelog.php' --check >/dev/null 2>&1"
printf '### Improved\n\n- x\n' > "$frag/changelog.d/l3-bad.md"
check "a heading Keep a Changelog doesn't name fails the check" "! php '$frag/tools/changelog.php' --check >/dev/null 2>&1"
rm "$frag/changelog.d/l3-bad.md"
php "$frag/tools/changelog.php" --assemble 1.4.0 2026-12-01 >/dev/null
check "assembly names the release with its date" "grep -q '^## \[1.4.0\] - 2026-12-01$' '$frag/CHANGELOG.md'"
check "Added comes before Fixed, with both lanes' bullets" "awk '/^## \[1.4.0\]/,/^## \[1.3.0\]/' '$frag/CHANGELOG.md' | tr '\n' ' ' | grep -q '### Added  - A thing. - Another thing.  ### Fixed  - A fix'"
check "the release's compare link is added" "grep -q '^\[1.4.0\]: https://github.com/example/repo/compare/v1.3.0...v1.4.0$' '$frag/CHANGELOG.md'"
check "the fragments are removed" "[ -z \"\$(ls '$frag/changelog.d')\" ]"
check "the old entry is kept" "grep -q '^- Old.$' '$frag/CHANGELOG.md'"

if [ "$fail" -ne 0 ]; then
	echo "Landing selftest: FAILED"
	exit 1
fi
echo "Landing selftest: every case holds."
