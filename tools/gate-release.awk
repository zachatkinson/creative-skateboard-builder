# Gates 9 and 10 on .github/workflows/release.yml (tools/gates.sh; D-043, D-048).
#
# Gate 9. A job that holds a secret runs gh and nothing of the repository's or of any package. It is an allow-list:
# such a job may use actions/download-artifact and no other action, may not name a shell, and every command its
# scripts run must be one of: gh, test, echo, printf, base64, jq, exit, true. A secret named outside a job (a
# workflow-level env) reaches every job, so it fails too.
#
# Gate 10. The job that runs tools/release-dry-run.sh installs Composer's and npm's packages first, as the CI job
# that runs the same script does: the script loads the plugin's autoloader.
#
# Portable awk (BWK awk on macOS, mawk and gawk on Linux): no gawk extensions.

function indent_of( line,    n ) {
	n = match( line, /[^ ]/ )
	return n ? n - 1 : length( line )
}

function allowed( word ) {
	return word ~ /^(gh|test|echo|printf|base64|jq|exit|true)$/
}

function end_word() {
	if ( word == "" ) return
	if ( cmdpos ) {
		if ( word ~ /^(if|then|else|elif|fi|!)$/ ) {
			# A keyword: the next word is still a command.
		} else if ( word ~ /^[A-Za-z_][A-Za-z0-9_]*=/ ) {
			# An assignment: the next word is still a command (VAR=x gh ...), or there is none.
		} else {
			if ( ! allowed( word ) ) bad[job] = bad[job] "\n  runs: " word
			cmdpos = 0
		}
	}
	word = ""
}

# One line of a script. The quote and substitution state carries from line to line.
function scan( line,    i, c, n, top ) {
	n = length( line )
	for ( i = 1; i <= n; i++ ) {
		c   = substr( line, i, 1 )
		top = stack[depth]
		if ( top == "S" ) {
			if ( c == "'" ) depth--
			continue
		}
		if ( c == "$" && substr( line, i + 1, 1 ) == "(" ) {
			# The word the substitution sits in is kept, and its own first word is a command.
			saved[depth]     = cmdpos
			savedword[depth] = word
			stack[++depth]   = "C"
			cmdpos = 1
			word   = ""
			i++
			continue
		}
		if ( c == "$" && substr( line, i + 1, 1 ) == "{" ) {
			# A parameter expansion: to its closing brace.
			while ( i <= n && substr( line, i, 1 ) != "}" ) i++
			if ( top != "D" ) word = word "x"
			continue
		}
		if ( top == "D" ) {
			if ( c == "\\" ) { i++; continue }
			if ( c == "\"" ) depth--
			continue
		}
		# Normal text, at the top or inside $( ).
		if ( c == "\\" ) { word = word "x"; i++; continue }
		if ( c == "'" ) { word = word "<quoted>"; stack[++depth] = "S"; continue }
		if ( c == "\"" ) { word = word "<quoted>"; stack[++depth] = "D"; continue }
		if ( c == ")" && top == "C" ) {
			end_word()
			depth--
			cmdpos = saved[depth]
			word   = savedword[depth] "x"
			continue
		}
		if ( c == " " || c == "\t" ) { end_word(); continue }
		# A redirection to a descriptor (2>&1) is part of its word, not a new command.
		if ( c == "&" && substr( line, i - 1, 1 ) == ">" ) { word = word c; continue }
		if ( c == ";" || c == "|" || c == "&" || c == "{" || c == "}" || c == "(" || c == ")" ) {
			end_word()
			cmdpos = 1
			continue
		}
		if ( c == "#" && word == "" ) break
		word = word c
	}
	end_word()
	# A new line is a new command, unless this one ended in a backslash or inside quotes.
	if ( line !~ /\\$/ && stack[depth] != "S" && stack[depth] != "D" ) cmdpos = 1
}

function start_script() {
	depth = 0
	stack[0] = "N"
	cmdpos = 1
	word = ""
}

/^jobs:/ { injobs = 1; next }

# Above the jobs: a secret here is in every job's reach.
! injobs {
	if ( $0 !~ /^[[:space:]]*#/ && $0 ~ /secrets[.\[]/ ) outside = outside "\n  " $0
	next
}

/^  [A-Za-z0-9_-]+:[[:space:]]*(#.*)?$/ {
	job = $1
	sub( /:.*$/, "", job )
	inrun = 0
	next
}

job == "" { next }

{
	if ( $0 ~ /^[[:space:]]*#/ ) next
	if ( $0 ~ /^[[:space:]]*$/ ) next

	if ( inrun && indent_of( $0 ) > runindent ) {
		scan( $0 )
		next
	}
	inrun = 0

	if ( $0 ~ /secrets[.\[]/ ) secret[job] = 1
	if ( $0 ~ /uses:/ && $0 !~ /uses:[[:space:]]*actions\/download-artifact@/ ) bad[job] = bad[job] "\n  " $0
	if ( $0 ~ /^[[:space:]]*(-[[:space:]]+)?shell:/ ) bad[job] = bad[job] "\n  " $0

	# Gate 10.
	if ( $0 ~ /uses:[[:space:]]*ramsey\/composer-install@/ ) composer[job] = 1
	if ( $0 ~ /run:[[:space:]]*npm ci[[:space:]]*$/ ) npm[job] = 1

	if ( $0 ~ /^[[:space:]]*(-[[:space:]]+)?run:/ ) {
		rest = $0
		sub( /^[[:space:]]*(-[[:space:]]+)?run:[[:space:]]*/, "", rest )
		start_script()
		if ( rest ~ /^[|>][+-]?[[:space:]]*$/ ) {
			inrun     = 1
			runindent = indent_of( $0 )
		} else {
			scan( rest )
			if ( rest ~ /release-dry-run\.sh/ ) dryrun( job )
		}
	}
}

# Inside a block script, the dry run's line is seen by the block rule above; this catches it there too.
inrun && /release-dry-run\.sh/ { dryrun( job ) }

function dryrun( j ) {
	if ( ! composer[j] || ! npm[j] ) ten = ten "\n  job " j
}

END {
	if ( outside != "" ) print "Gate 9: a secret is named outside a job of release.yml, where every job can read it:" outside
	for ( j in secret ) if ( bad[j] != "" ) print "Gate 9: job " j " of release.yml holds a secret and runs more than gh:" bad[j]
	if ( ten != "" ) print "Gate 10: release.yml runs tools/release-dry-run.sh without installing Composer's and npm's packages first:" ten
}
