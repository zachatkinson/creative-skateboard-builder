<?php
/**
 * Lighthouse CI's medians, as a Markdown table: one row a page, its LCP, TBT and CLS against the gates in
 * lighthouserc.cjs. CI writes it to the job's summary whether the gates passed or not, since Lighthouse CI prints
 * values only for an assertion that failed (#74, D-299). It reads the reports `npm run lhci` leaves in tmp/lhci.
 *
 * Run: php tools/lhci-summary.php [tmp/lhci]
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

$cbuild_dir = rtrim( $argv[1] ?? 'tmp/lhci', '/' );

// The gates in lighthouserc.cjs, for the table's last line.
$cbuild_audits = array(
	'largest-contentful-paint' => array( 'LCP', 'ms', 2500 ),
	'total-blocking-time'      => array( 'TBT', 'ms', 200 ),
	'cumulative-layout-shift'  => array( 'CLS', '', 0.1 ),
);

/**
 * The median of a list of numbers.
 *
 * @param list<float> $values The values.
 */
function cbuild_median( array $values ): float {
	sort( $values );
	$count = count( $values );
	$mid   = intdiv( $count, 2 );
	return 0 === $count % 2 ? ( $values[ $mid - 1 ] + $values[ $mid ] ) / 2 : $values[ $mid ];
}

$cbuild_rows = array();
foreach ( glob( "{$cbuild_dir}/*/*.report.json" ) ?: array() as $cbuild_file ) {
	$cbuild_report = json_decode( (string) file_get_contents( $cbuild_file ), true );
	if ( ! is_array( $cbuild_report ) || ! isset( $cbuild_report['audits'] ) ) {
		continue;
	}
	$cbuild_page = basename( dirname( $cbuild_file ) );
	foreach ( array_keys( $cbuild_audits ) as $cbuild_id ) {
		$cbuild_value = $cbuild_report['audits'][ $cbuild_id ]['numericValue'] ?? null;
		if ( is_numeric( $cbuild_value ) ) {
			$cbuild_rows[ $cbuild_page ][ $cbuild_id ][] = (float) $cbuild_value;
		}
	}
}

if ( array() === $cbuild_rows ) {
	echo "### Lighthouse\n\nNo report was found in {$cbuild_dir}.\n";
	exit( 0 );
}

ksort( $cbuild_rows );
$cbuild_out = "### Lighthouse medians\n\n| Page | Runs |";
foreach ( $cbuild_audits as $cbuild_audit ) {
	$cbuild_out .= " {$cbuild_audit[0]} |";
}
$cbuild_out .= "\n| --- | --- |" . str_repeat( ' --- |', count( $cbuild_audits ) ) . "\n";
foreach ( $cbuild_rows as $cbuild_page => $cbuild_values ) {
	$cbuild_runs = max( array_map( 'count', $cbuild_values ) );
	$cbuild_out .= "| {$cbuild_page} | {$cbuild_runs} |";
	foreach ( $cbuild_audits as $cbuild_id => $cbuild_audit ) {
		if ( ! isset( $cbuild_values[ $cbuild_id ] ) ) {
			$cbuild_out .= ' |';
			continue;
		}
		$cbuild_median = cbuild_median( $cbuild_values[ $cbuild_id ] );
		$cbuild_shown  = '' === $cbuild_audit[1] ? number_format( $cbuild_median, 3 ) : number_format( $cbuild_median ) . ' ' . $cbuild_audit[1];
		$cbuild_mark   = $cbuild_median > $cbuild_audit[2] ? ' (over)' : '';
		$cbuild_out   .= " {$cbuild_shown}{$cbuild_mark} |";
	}
	$cbuild_out .= "\n";
}
$cbuild_out .= "\nGates: LCP 2,500 ms, TBT 200 ms, CLS 0.1, each on the median of the runs.\n";
echo $cbuild_out;
