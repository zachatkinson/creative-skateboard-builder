<?php
/**
 * The Build tab's notices for a settings field refused alone.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Admin;

use CreativeBranding\SkateboardBuilder\Admin\BuildTab;
use CreativeBranding\SkateboardBuilder\Service\KitEditor;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use ReflectionMethod;

/**
 * D-385: a settings field the editor refuses alone gets a notice of its own. Each field it may refuse alone names
 * itself, and a field it never refuses alone falls back on the sentence for the whole settings, which is true of any.
 */
final class BuildTabNoticesTest extends TestCase {

	public function test_each_field_refused_alone_has_a_sentence_of_its_own_and_any_other_the_whole_settings(): void {
		$fields = ( new ReflectionClassConstant( KitEditor::class, 'OWN_FIELDS' ) )->getValue();
		self::assertIsArray( $fields );
		$whole = self::call( 'refused', KitEditor::SETTINGS );
		self::assertSame( $whole, self::call( 'refusedField', 'start_button' ), 'a field never refused alone: the whole settings' );
		self::assertSame( $whole, self::call( 'refusedField', '' ), 'and a problem that is no one field\'s' );

		$sentences = array();
		foreach ( $fields as $field ) {
			self::assertIsString( $field );
			$sentences[ $field ] = self::call( 'refusedField', $field );
			self::assertNotSame( $whole, $sentences[ $field ], $field . ': its own sentence, not the whole settings\'' );
		}
		self::assertSame( $sentences, array_unique( $sentences ), 'no two fields share a sentence' );
		self::assertSame( 'The kit\'s assembly time in business days was not saved. The Build tab shows it as you typed it, with what to put right.', $sentences['lead_days'] ?? null );
	}

	/**
	 * One of the tab's sentences.
	 *
	 * @param string $method The sentence's method.
	 * @param string $key    Its document or field.
	 */
	private static function call( string $method, string $key ): string {
		$sentence = ( new ReflectionMethod( BuildTab::class, $method ) )->invoke( null, $key );
		self::assertIsString( $sentence );
		return $sentence;
	}
}
