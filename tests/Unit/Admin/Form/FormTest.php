<?php
/**
 * The admin kit's description of a form: its fields, its cards and the server's errors, as the script reads them.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Admin\Form;

use CreativeBranding\SkateboardBuilder\Admin\Form\Card;
use CreativeBranding\SkateboardBuilder\Admin\Form\Errors;
use CreativeBranding\SkateboardBuilder\Admin\Form\Field;
use CreativeBranding\SkateboardBuilder\Admin\Form\Form;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M15's principles 1 to 3 (docs/research/admin-ux-2026.md): one description of each field, in PHP, printed for the
 * script; the script writes each value into the field's own input name, so the same POST and the server's checks run;
 * the server's errors come back to their fields.
 */
final class FormTest extends TestCase {

	public function test_a_field_describes_itself_for_the_script_with_only_what_it_has(): void {
		$field = Field::of( 'lead_days', 'cbuild_settings[lead_days]', 'Assembly time', 'integer' )
			->help( 'Business days to assemble a board.' )
			->rules( required: true, min: 0, max: 30 )
			->suffix( 'days' )
			->value( '3' );
		self::assertSame(
			array(
				'id'          => 'lead_days',
				'name'        => 'cbuild_settings[lead_days]',
				'label'       => 'Assembly time',
				'type'        => 'integer',
				'value'       => '3',
				'description' => 'Business days to assemble a board.',
				'rules'       => array(
					'required' => true,
					'min'      => 0,
					'max'      => 30,
				),
				'suffix'      => 'days',
			),
			$field->toArray()
		);
		self::assertSame(
			array(
				'id'    => 'name',
				'name'  => 'name',
				'label' => 'Name',
				'type'  => 'text',
				'value' => '',
			),
			Field::of( 'name', 'name', 'Name', 'text' )->toArray(),
			'a bare field carries nothing it was not given'
		);
	}

	public function test_a_field_with_choices_a_control_a_read_only_source_and_a_condition(): void {
		$field = Field::of( 'unit', 'map[0][unit]', 'Unit', 'text' )
			->control( 'select' )
			->choices( array( 'mm' => 'Millimetres', 'in' => 'Inches' ) )
			->placeholder( 'From the parent' )
			->visibleWhen( 'spec', array( 'deck_width', 'wheelbase' ) );
		$array = $field->toArray();
		self::assertSame( 'select', $array['control'] ?? null );
		self::assertSame(
			array(
				array( 'value' => 'mm', 'label' => 'Millimetres' ),
				array( 'value' => 'in', 'label' => 'Inches' ),
			),
			$array['elements'] ?? null
		);
		self::assertSame( 'From the parent', $array['placeholder'] ?? null );
		self::assertSame( array( 'spec' => array( 'deck_width', 'wheelbase' ) ), $array['visibleWhen'] ?? null );

		$read = Field::of( 'width', 'specs[deck_width]', 'Width', 'text' )->readOnly( 'From the attribute Width' )->toArray();
		self::assertTrue( $read['readOnly'] ?? null );
		self::assertSame( 'From the attribute Width', $read['source'] ?? null );
	}

	public function test_a_field_is_immutable(): void {
		$bare = Field::of( 'name', 'name', 'Name', 'text' );
		$bare->help( 'Shown to shoppers.' );
		self::assertArrayNotHasKey( 'description', $bare->toArray() );
	}

	/**
	 * @return iterable<string, array{0: callable(): mixed, 1: string}>
	 */
	public static function refused(): iterable {
		yield 'an id that is no slug' => array( static fn() => Field::of( 'Lead days', 'x', 'X', 'text' ), 'id' );
		yield 'an empty input name' => array( static fn() => Field::of( 'x', '', 'X', 'text' ), 'name' );
		yield 'an empty label' => array( static fn() => Field::of( 'x', 'x', ' ', 'text' ), 'label' );
		yield 'a type DataForm has not' => array( static fn() => Field::of( 'x', 'x', 'X', 'colour' ), 'type' );
		yield 'a control DataForm has not' => array( static fn() => Field::of( 'x', 'x', 'X', 'text' )->control( 'slider' ), 'control' );
		yield 'help over 140 characters' => array( static fn() => Field::of( 'x', 'x', 'X', 'text' )->help( str_repeat( 'a', 141 ) ), '140' );
		yield 'a minimum over the maximum' => array( static fn() => Field::of( 'x', 'x', 'X', 'integer' )->rules( min: 5, max: 4 ), 'min' );
		yield 'a card with no fields' => array( static fn() => Card::of( 'c', 'C', array() ), 'field' );
		yield 'a card naming a field the form has not' => array(
			static fn() => Form::of( 'f', array( Field::of( 'a', 'a', 'A', 'text' ) ), array( Card::of( 'c', 'C', array( 'b' ) ) ) ),
			'b',
		);
		yield 'a closed card summed up by a field the form has not' => array(
			static fn() => Form::of( 'f', array( Field::of( 'a', 'a', 'A', 'text' ) ), array( Card::of( 'c', 'C', array( 'a' ) )->closed( 'z' ) ) ),
			'z',
		);
		yield 'two fields with one id' => array(
			static fn() => Form::of( 'f', array( Field::of( 'a', 'a', 'A', 'text' ), Field::of( 'a', 'b', 'B', 'text' ) ), array() ),
			'a',
		);
		yield 'a condition on a field the form has not' => array(
			static fn() => Form::of( 'f', array( Field::of( 'a', 'a', 'A', 'text' )->visibleWhen( 'z', array( '1' ) ) ), array() ),
			'z',
		);
	}

	/**
	 * A description that would draw a broken form is refused where it is written, not on a shop's screen.
	 *
	 * @param callable(): mixed $make     What makes it.
	 * @param string            $mentions What the refusal names.
	 */
	#[DataProvider( 'refused' )]
	public function test_a_description_that_cannot_be_drawn_is_refused( callable $make, string $mentions ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/' . preg_quote( $mentions, '/' ) . '/' );
		$make();
	}

	public function test_a_form_lists_its_fields_and_its_cards_for_the_script(): void {
		$form = Form::of(
			'cbuild-settings',
			array(
				Field::of( 'spec', 'spec', 'Spec', 'text' ),
				Field::of( 'proxy', 'proxy', 'Trusted header', 'text' ),
				Field::of( 'proxy_state', 'proxy_state', 'Visitor requests', 'text' )->readOnly( 'Set below' )->value( 'Off' ),
			),
			array(
				Card::of( 'specs', 'Where specs come from', array( 'spec' ) ),
				Card::of( 'advanced', 'Advanced: visitor requests', array( 'proxy' ) )->closed( 'proxy_state' ),
			)
		);
		$array = $form->toArray();
		self::assertSame( 'cbuild-settings', $array['id'] );
		self::assertSame( array( 'spec', 'proxy', 'proxy_state' ), array_column( $array['fields'], 'id' ) );
		self::assertSame(
			array(
				array(
					'id'     => 'specs',
					'label'  => 'Where specs come from',
					'fields' => array( 'spec' ),
					'open'   => true,
				),
				array(
					'id'      => 'advanced',
					'label'   => 'Advanced: visitor requests',
					'fields'  => array( 'proxy' ),
					'open'    => false,
					'summary' => 'proxy_state',
				),
			),
			$array['cards']
		);
		self::assertSame( array(), $array['errors'] );
	}

	public function test_the_servers_errors_go_to_their_fields_and_a_card_with_one_opens(): void {
		$form   = Form::of(
			'f',
			array( Field::of( 'a', 'a', 'A', 'integer' ), Field::of( 'b', 'b', 'B', 'text' ) ),
			array( Card::of( 'one', 'One', array( 'a' ) ), Card::of( 'two', 'Two', array( 'b' ) )->closed( 'b' ) )
		);
		$errors = Errors::none()->with( 'b', 'Use letters only.' )->with( 'b', 'Second word.' )->with( 'gone', 'Not a field.' );
		self::assertSame( array( 'b' => 'Use letters only.' ), $errors->for( $form )->toArray(), 'the first message a field has, and only the form\'s own fields' );
		$array = $form->failed( $errors )->toArray();
		self::assertSame( array( 'b' => 'Use letters only.' ), $array['errors'] );
		self::assertTrue( $array['cards'][1]['open'], 'a closed card with an error opens' );
		self::assertTrue( Errors::none()->isEmpty() );
		self::assertFalse( $errors->isEmpty() );
	}

	public function test_a_form_warns_before_the_page_is_left_unless_its_page_warns_itself(): void {
		$form = Form::of( 'f', array( Field::of( 'a', 'a', 'A', 'text' ) ), array() );
		self::assertTrue( $form->toArray()['leave'], 'the kit warns' );
		$host = $form->leftToHost();
		self::assertFalse( $host->toArray()['leave'], 'WooCommerce\'s Settings warn themselves' );
		self::assertTrue( $form->toArray()['leave'], 'the form it was called on is as it was' );
		self::assertFalse( $host->failed( Errors::none()->with( 'a', 'No.' ) )->toArray()['leave'], 'kept with the refusals' );
	}
}
