<?php
/**
 * Why one of a kit's documents was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * The codes the Build tab turns into a notice beside the field (spec: Admin).
 */
enum DocumentError: string {

	case NotAnObject    = 'document_not_an_object';
	case UnknownVersion = 'document_unknown_version';
	case Missing        = 'document_field_missing';
	case WrongType      = 'document_wrong_type';
	case OutOfRange     = 'document_out_of_range';
	case BadMeasure     = 'document_bad_measure';
	case BadKey         = 'document_bad_key';
	case Duplicate      = 'document_duplicate';
	case DeckNotFirst   = 'slots_deck_not_first';
	case OutOfOrder     = 'rules_out_of_order';
	case UnknownSlot    = 'presets_unknown_slot';
	case SlotNotFilled  = 'presets_slot_not_filled';
}
