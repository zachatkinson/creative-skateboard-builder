<?php
/**
 * Uninstall: removes the two tables, every option and transient, the scheduled actions and the object-cache group, on
 * every site of a network, and the network's cached update manifest. Deactivation leaves all of it in place. Order
 * item meta is kept: it is part of the shop's records (spec: Uninstall).
 *
 * The work is in Install\Uninstaller so the integration suite can prove it leaves nothing; this file only reaches it.
 * Without the autoloader (a checkout where Composer never ran) it loads the three files the uninstaller is made of, so
 * there is one uninstaller either way. It must parse on PHP 7.4 (D-023). It often runs with
 * WooCommerce already deactivated, so Action Scheduler's rows are removed by SQL when Action Scheduler is not loaded
 * (D-024).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Support/Options.php';
	require_once __DIR__ . '/src/Support/Tables.php';
	require_once __DIR__ . '/src/Install/Uninstaller.php';
}

\CreativeBranding\SkateboardBuilder\Install\Uninstaller::run();
