<?php
/**
 * Which of a shop's three sources a spec's value came from.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * A part's spec is filled in on the part itself, read from an attribute the shop mapped, or taken from the spec table
 * and size the part names (spec: Data model, Part specs). The values are what the index's `source` column holds.
 *
 * @since 0.3.0
 */
enum Origin: string {

	case Field     = 'field';
	case Attribute = 'attribute';
	case Table     = 'table';
}
