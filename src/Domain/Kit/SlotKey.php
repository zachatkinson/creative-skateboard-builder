<?php
/**
 * The slot keys the rules know.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * A rule finds its two parts by their slots' keys (spec: Vocabulary, Slot). A kit may have slots with other keys;
 * no rule reads them.
 */
final class SlotKey {

	public const string DECK     = 'deck';
	public const string TRUCKS   = 'trucks';
	public const string WHEELS   = 'wheels';
	public const string RISERS   = 'risers';
	public const string BEARINGS = 'bearings';
	public const string HARDWARE = 'hardware';
	public const string GRIP     = 'grip';
	public const string SPACERS  = 'spacers';
}
