#!/usr/bin/env bash
# The grep gates (CLAUDE.md: composer gates). Each fails the run:
#   1. SQL outside src/Repository/ and src/Install/, and SQL built from strings anywhere.
#   2. Banned functions.
#   3. Any reading of the time outside Adapter\SystemClock (times go through Port\Clock, in UTC).
#   4. A file without declare( strict_types=1 ).
#   5. An outbound request anywhere but Updates\HttpManifestSource (and the package download in Updates\Updater).
#   6. WordPress or WooCommerce in src/Domain.
#   7. Anything from WooCommerce's Internal namespace.
#   8. An em dash, an exclamation mark or a word from tools/copy-words.txt in the POT file (docs/STANDARDS.md: Copy).
#   9. A job of release.yml that holds a secret and runs anything but gh and a few shell built-ins (D-043, D-048).
#  10. release.yml running the release dry run without installing Composer's and npm's packages first (D-048).
#  11. A string literal with words in it in src/Domain: the domain answers in codes (D-062).
#  12. An exception of PHP's own made in src/Domain: its refusals are typed, with a reason code (D-062).
# Gate 1 also covers string-built SQL (1b) and the database's clock in a statement (1c). Every gate has snippets it
# must reject in tests/Fixtures/gates/, run by tools/gates-selftest.sh.
# Run: composer gates
set -euo pipefail
tools="$(cd "$(dirname "$0")" && pwd)"
# CBUILD_GATES_ROOT points the gates at another tree: tools/gates-selftest.sh runs them on snippets they must reject.
cd "${CBUILD_GATES_ROOT:-$tools/..}"
status=0
main=creative-skateboard-builder.php
php_files=(src "$main" uninstall.php)

# 1. The database outside src/Repository/ and src/Install/: $wpdb by either name (Support\Tables reads its prefix and
# nothing else), and wpdb's reading methods on any variable, so an alias does not slip by.
hits="$(grep -rnE '\$wpdb|\$GLOBALS\[\s*.wpdb.\s*\]|->(get_results|get_row|get_var|get_col)\(' "${php_files[@]}" --include='*.php' | grep -vE '^src/(Repository|Install)/' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' | grep -vE '^src/Support/Tables\.php:[0-9]+:\s*(global \$wpdb;|return \$wpdb->prefix )' || true)"
if [ -n "$hits" ]; then
	echo "Gate 1: direct SQL outside src/Repository/ and src/Install/:"; echo "$hits"; status=1
fi
# 1b. Interpolated or concatenated SQL handed to $wpdb without prepare(). A statement assembled in a variable first
# passes here, so the repositories take only prepare()d strings and PHPCS and the review carry the rule.
hits="$(grep -rnE '\$wpdb->(query|get_results|get_row|get_var|get_col)\(\s*("[^"]*\$|'"'"'[^'"'"']*'"'"'\s*\.)' src --include='*.php' | grep -v 'prepare(' || true)"
if [ -n "$hits" ]; then
	echo "Gate 1b: string-built SQL (use \$wpdb->prepare()):"; echo "$hits"; status=1
fi

# 1c. The database's clock in a statement: times come from Port\Clock and are bound as values. Matched in capitals, as
# SQL is written here.
hits="$(grep -rnE '(^|[^A-Za-z_])(NOW|CURDATE|CURTIME|SYSDATE|UTC_TIMESTAMP|UTC_DATE|UTC_TIME|UNIX_TIMESTAMP|LOCALTIME|LOCALTIMESTAMP)\s*\(\s*\)|(^|[^A-Za-z_])CURRENT_(TIMESTAMP|DATE|TIME)([^A-Za-z_]|$)' "${php_files[@]}" --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
if [ -n "$hits" ]; then
	echo "Gate 1c: the database's clock in SQL; bind a time from Port\\Clock:"; echo "$hits"; status=1
fi

# 2. Banned functions.
hits="$(grep -rnE '(^|[^a-zA-Z_>$])(eval|extract|unserialize|maybe_unserialize|query_posts|create_function|base64_decode|exec|shell_exec|system|passthru|popen|proc_open|var_dump|print_r|var_export|error_log)\s*\(' "${php_files[@]}" --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
if [ -n "$hits" ]; then
	echo "Gate 2: banned function:"; echo "$hits"; status=1
fi

# 3. Every way of reading the time: the Clock port is the only clock. The functions, WordPress's own that fall back to
# now (wp_date(), date_i18n(), human_time_diff()), the request's start time, and a date object made from "now", which
# is what a bare constructor gives.
hits="$(grep -rnE '(^|[^a-zA-Z_>$])(date|time|mktime|gmdate|gmmktime|current_time|current_datetime|microtime|hrtime|strtotime|date_create|date_create_immutable|getdate|localtime|idate|gettimeofday|wp_date|date_i18n|human_time_diff)\s*\(|new\s+\\?DateTime(Immutable)?\s*\(|REQUEST_TIME' "${php_files[@]}" --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' | grep -vE '^src/Adapter/SystemClock\.php:' || true)"
if [ -n "$hits" ]; then
	echo "Gate 3: a clock call outside Adapter\\SystemClock; use Port\\Clock:"; echo "$hits"; status=1
fi

# 4. declare( strict_types=1 ) in every PHP file the plugin ships.
missing="$(grep -rL 'declare( strict_types=1 );' "${php_files[@]}" --include='*.php' | grep -v '/index.php$' || true)"
if [ -n "$missing" ]; then
	echo "Gate 4: missing declare( strict_types=1 ):"; echo "$missing"; status=1
fi

# 5. No remote requests but the update manifest (Updates\HttpManifestSource). The package itself is downloaded by
#    WordPress's upgrader (download_url() through Updates\Updater's injected downloader). PHP's file functions take a
#    URL as readily as a path, so they are refused too: a class that must read a local file is exempted here by name.
hits="$(grep -rnE 'wp_(safe_)?remote_(get|post|request|head|fopen)|WP_Http|WpOrg.Requests|Requests::|curl_(init|exec|multi_init)|fsockopen|pfsockopen|stream_socket_client|download_url\(|copy\(\s*["'"'"']https?:|(^|[^a-zA-Z_>$])(file_get_contents|fopen|readfile|file|getimagesize|exif_read_data|get_headers|simplexml_load_file|wp_oembed_get|wp_get_http_headers|fetch_feed|_wp_http_get_object)\s*\(' "${php_files[@]}" --include='*.php' \
	| grep -vE '^src/Updates/HttpManifestSource\.php:[0-9]+:.*wp_safe_remote_get\(|^src/Updates/Updater\.php:[0-9]+:.*return download_url\(' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
if [ -n "$hits" ]; then
	echo "Gate 5: outbound request:"; echo "$hits"; status=1
fi

# 6. No WordPress or WooCommerce in the domain: no global function of theirs, no class of theirs, no ABSPATH guard.
if [ -d src/Domain ]; then
	hits="$(grep -rnE '(^|[^a-zA-Z_\\>$])(wp_[a-z_]+|wc_[a-z_]+|get_option|update_option|add_option|delete_option|apply_filters|do_action|add_filter|add_action|__|_e|_x|_n|_nx|esc_[a-z_]+|sanitize_[a-z_]+|get_post[a-z_]*|get_user[a-z_]*|current_user_can|is_admin|absint|WC|wp_cache_[a-z_]+)\s*\(|\\?(WP_[A-Za-z_]+|WC_[A-Za-z_]+|wpdb|WooCommerce|Automattic\\\\WooCommerce|ActionScheduler)\b|ABSPATH' src/Domain --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
	if [ -n "$hits" ]; then
		echo "Gate 6: WordPress or WooCommerce in src/Domain:"; echo "$hits"; status=1
	fi
fi

# 11. No words in the domain: it answers in codes and values, never in a sentence (tools/gate-domain-strings.php).
if [ -d src/Domain ]; then
	hits="$(php "$tools/gate-domain-strings.php" src/Domain)"
	if [ -n "$hits" ]; then
		echo "Gate 11: a string with words in src/Domain:"; echo "$hits"; status=1
	fi
fi

# 12. No untyped throw in the domain: every refusal is an exception of the domain's own with a reason code, so the edge
#     never has to match a message (docs/AUDIT.md: Resilience; D-062).
if [ -d src/Domain ]; then
	hits="$(grep -rnE 'new[[:space:]]+\\?([A-Za-z]*Exception|Error|[A-Z][A-Za-z]*Error)[[:space:]]*\(' src/Domain --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
	if [ -n "$hits" ]; then
		echo "Gate 12: an untyped throw in src/Domain:"; echo "$hits"; status=1
	fi
fi

# 7. Nothing private of WooCommerce's (docs/STANDARDS.md: nothing private, internal or undocumented).
hits="$(grep -rnE 'Automattic\\WooCommerce\\Internal\\' "${php_files[@]}" --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
if [ -n "$hits" ]; then
	echo "Gate 7: WooCommerce's Internal namespace:"; echo "$hits"; status=1
fi

# 8. The copy gate on the POT: no em dash, no banned word ($tools/copy-words.txt), no exclamation mark in a string.
pot=languages/creative-skateboard-builder.pot
if [ -f "$pot" ]; then
	# Each string whole: a long one is wrapped over lines that each start with a quote.
	strings="$(awk '/^(msgid|msgid_plural|msgstr(\[[0-9]+\])?) "/ { if ( text != "" ) print text; sub( /^[a-z_]+(\[[0-9]+\])? "/, "" ); sub( /"$/, "" ); text = $0; open = 1; next } open && /^"/ { sub( /^"/, "" ); sub( /"$/, "" ); text = text $0; next } { if ( text != "" ) print text; text = ""; open = 0 } END { if ( text != "" ) print text }' "$pot")"
	if printf '%s\n' "$strings" | grep -n '—' ; then
		echo "Gate 8: an em dash in the POT."; status=1
	fi
	if printf '%s\n' "$strings" | grep -nE '!' ; then
		echo "Gate 8: an exclamation mark in the POT."; status=1
	fi
	if [ -f "$tools/copy-words.txt" ]; then
		while IFS= read -r word; do
			[ -z "$word" ] && continue
			case "$word" in \#*) continue ;; esac
			if printf '%s\n' "$strings" | grep -niE "(^|[^a-z])${word}([^a-z]|$)" ; then
				echo "Gate 8: banned word '${word}' in the POT."; status=1
			fi
		done < "$tools/copy-words.txt"
	fi
fi

# 9 and 10. The release workflow (tools/gate-release.awk). 9: a job that holds a secret runs gh and nothing of the
# repository's or of any package, so nothing installed can reach the token that publishes to the repository every
# shop's updater trusts (D-043); an allow-list of commands, not a list of tools to refuse (D-048). 10: the job that
# runs the release dry run installs what the script needs first.
release=.github/workflows/release.yml
if [ -f "$release" ]; then
	hits="$(awk -f "$tools/gate-release.awk" "$release")"
	if [ -n "$hits" ]; then
		echo "$hits"; status=1
	fi
fi

if [ "$status" -eq 0 ]; then echo "Gates: clean."; fi
exit "$status"
