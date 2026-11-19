<?php
/**
 * A build a customer keeps under My builds.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Saved;

use DateTimeImmutable;

/**
 * One row of `{prefix}cbuild_builds` (spec: Data model, Saved builds): the customer's picks under a UUID, with the
 * kit, assembly on or off, the name they gave it, and when it was saved and last changed, in UTC. The picks are IDs,
 * checked again whenever the build is opened or priced.
 */
final readonly class SavedBuild {

	/**
	 * Constructor.
	 *
	 * @param string             $uuid     The build's UUID.
	 * @param int                $user     The customer's user ID.
	 * @param int                $kit      The kit product's ID.
	 * @param array<string, int> $picks    The part's ID for each slot picked, in step order.
	 * @param bool               $assembly Whether the shop assembles it.
	 * @param string             $name     The name the customer gave it, or empty.
	 * @param DateTimeImmutable  $created  When it was first saved.
	 * @param DateTimeImmutable  $updated  When it was last saved or renamed.
	 * @param SavedPrices|null   $prices   From 1.0.0, what it cost when saved; null for a build saved before.
	 */
	public function __construct(
		public string $uuid,
		public int $user,
		public int $kit,
		public array $picks,
		public bool $assembly,
		public string $name,
		public DateTimeImmutable $created,
		public DateTimeImmutable $updated,
		public ?SavedPrices $prices = null
	) {}

	/**
	 * The same build, with other picks, assembly and name, saved at that moment.
	 *
	 * @param int                $kit      The kit product's ID.
	 * @param array<string, int> $picks    The picks.
	 * @param bool               $assembly Whether the shop assembles it.
	 * @param string             $name     The name.
	 * @param DateTimeImmutable  $at       When.
	 * @param SavedPrices|null   $prices   What it costs as saved now, or null to keep the prices it has.
	 */
	public function with( int $kit, array $picks, bool $assembly, string $name, DateTimeImmutable $at, ?SavedPrices $prices = null ): self {
		return new self( $this->uuid, $this->user, $kit, $picks, $assembly, $name, $this->created, $at, $prices ?? $this->prices );
	}
}
