#!/usr/bin/env bash
# The grep gates (CLAUDE.md: composer gates). Each fails the run:
#   1. SQL outside src/Repository/ and src/Install/, and SQL built from strings anywhere.
#   2. Banned functions.
#   3. date(), time() and mktime() outside Adapter\SystemClock (times go through Port\Clock, in UTC).
#   4. A file without declare( strict_types=1 ).
#   5. An outbound request anywhere but Updates\HttpManifestSource (and the package download in Updates\Updater).
#   6. WordPress or WooCommerce in src/Domain.
#   7. Anything from WooCommerce's Internal namespace.
#   8. An em dash, or a word from tools/copy-words.txt, in the POT file (docs/STANDARDS.md: Copy).
# Run: composer gates
set -euo pipefail
cd "$(dirname "$0")/.."
status=0
main=creative-skateboard-builder.php
php_files=(src "$main" uninstall.php)

# 1. $wpdb calls outside src/Repository/ and src/Install/.
hits="$(grep -rnE '\$wpdb->(query|get_results|get_row|get_var|get_col|insert|update|delete|replace)\(' src --include='*.php' | grep -vE '^src/(Repository|Install)/' || true)"
if [ -n "$hits" ]; then
	echo "Gate 1: direct SQL outside src/Repository/ and src/Install/:"; echo "$hits"; status=1
fi
# 1b. Interpolated or concatenated SQL handed to $wpdb without prepare(). A statement assembled in a variable first
# passes here, so the repositories take only prepare()d strings and PHPCS and the review carry the rule.
hits="$(grep -rnE '\$wpdb->(query|get_results|get_row|get_var|get_col)\(\s*("[^"]*\$|'"'"'[^'"'"']*'"'"'\s*\.)' src --include='*.php' | grep -v 'prepare(' || true)"
if [ -n "$hits" ]; then
	echo "Gate 1b: string-built SQL (use \$wpdb->prepare()):"; echo "$hits"; status=1
fi

# 2. Banned functions.
hits="$(grep -rnE '(^|[^a-zA-Z_>$])(eval|extract|unserialize|maybe_unserialize|query_posts|create_function|base64_decode|exec|shell_exec|system|passthru|popen|proc_open|var_dump|print_r|var_export|error_log)\s*\(' "${php_files[@]}" --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
if [ -n "$hits" ]; then
	echo "Gate 2: banned function:"; echo "$hits"; status=1
fi

# 3. date(), time() and mktime(): the Clock port is the only clock.
hits="$(grep -rnE '(^|[^a-zA-Z_>$])(date|time|mktime|gmdate|current_time)\s*\(' "${php_files[@]}" --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' | grep -vE '^src/Adapter/SystemClock\.php:' || true)"
if [ -n "$hits" ]; then
	echo "Gate 3: a clock call outside Adapter\\SystemClock; use Port\\Clock:"; echo "$hits"; status=1
fi

# 4. declare( strict_types=1 ) in every PHP file the plugin ships.
missing="$(grep -rL 'declare( strict_types=1 );' "${php_files[@]}" --include='*.php' | grep -v '/index.php$' || true)"
if [ -n "$missing" ]; then
	echo "Gate 4: missing declare( strict_types=1 ):"; echo "$missing"; status=1
fi

# 5. No remote requests but the update manifest (Updates\HttpManifestSource). The package itself is downloaded by
#    WordPress's upgrader (download_url() through Updates\Updater's injected downloader).
hits="$(grep -rnE 'wp_(safe_)?remote_(get|post|request|head)|curl_init|fsockopen|download_url\(|file_get_contents\(\s*["'"'"']https?:' "${php_files[@]}" --include='*.php' \
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

# 7. Nothing private of WooCommerce's (docs/STANDARDS.md: nothing private, internal or undocumented).
hits="$(grep -rnE 'Automattic\\WooCommerce\\Internal\\' src --include='*.php' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|#)' || true)"
if [ -n "$hits" ]; then
	echo "Gate 7: WooCommerce's Internal namespace:"; echo "$hits"; status=1
fi

# 8. The copy gate on the POT: no em dash, no banned word (tools/copy-words.txt), no exclamation mark in a string.
pot=languages/creative-skateboard-builder.pot
if [ -f "$pot" ]; then
	strings="$(grep -E '^(msgid|msgid_plural|msgstr) "' "$pot" | sed -E 's/^[a-z_]+ "//; s/"$//' | grep -v '^$' || true)"
	if printf '%s\n' "$strings" | grep -n '—' ; then
		echo "Gate 8: an em dash in the POT."; status=1
	fi
	if printf '%s\n' "$strings" | grep -nE '!' ; then
		echo "Gate 8: an exclamation mark in the POT."; status=1
	fi
	if [ -f tools/copy-words.txt ]; then
		while IFS= read -r word; do
			[ -z "$word" ] && continue
			case "$word" in \#*) continue ;; esac
			if printf '%s\n' "$strings" | grep -niE "(^|[^a-z])${word}([^a-z]|$)" ; then
				echo "Gate 8: banned word '${word}' in the POT."; status=1
			fi
		done < tools/copy-words.txt
	fi
fi

if [ "$status" -eq 0 ]; then echo "Gates: clean."; fi
exit "$status"
