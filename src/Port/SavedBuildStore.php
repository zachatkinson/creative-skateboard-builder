<?php
/**
 * Where saved builds are kept.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The saved builds' table. Every read and every change names the customer, and touches no row of anyone else's
 * (spec: Security, "read and changed only by the customer who owns them, checked on every request").
 *
 * @since 0.8.0
 */
interface SavedBuildStore {

	/**
	 * The most builds a customer keeps (D-217): a new one past it is not written.
	 *
	 * @since 1.0.0
	 */
	public const int MOST = 100;

	/**
	 * A customer's builds, the last saved first.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 * @return list<SavedBuild>
	 */
	public function of( int $user ): array;

	/**
	 * One of a customer's builds, or null when they have none with that UUID.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function find( int $user, string $uuid ): ?SavedBuild;

	/**
	 * How many builds a customer has.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 */
	public function count( int $user ): int;

	/**
	 * Keep a build: a new row for a UUID the table lacks, or the customer's own row written again.
	 *
	 * @since 0.8.0
	 *
	 * @param SavedBuild $build The build.
	 * @return bool Whether the table holds it now: false where the write failed, or its UUID is another customer's.
	 */
	public function put( SavedBuild $build ): bool;

	/**
	 * Delete one of a customer's builds. False when they have none with that UUID.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function delete( int $user, string $uuid ): bool;

	/**
	 * Delete every build of a customer's, and say how many there were.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 */
	public function forget( int $user ): int;
}
