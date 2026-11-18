<?php
/**
 * A build in the customer's order emails.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Order\EmailHooks;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WC_Email;
use WC_Order;
use WP_Locale_Switcher;

/**
 * Roadmap, milestone 8: "the composed picture in the HTML emails, with the build's description as its alt text, and
 * the description alone in plain-text emails; the lead time and the returns notice in the emails, read from the kit
 * item's snapshot"; both of WooCommerce's HTML layouts.
 */
final class OrderEmailTest extends CartTestCase {

	public function set_up(): void {
		parent::set_up();
		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_ASSEMBLY,
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15',
				'lead_time'      => 'Assembled builds ship in 3 days.',
				'returns_notice' => 'An assembled board comes back as a whole.',
			)
		);
		$kit->save();
		$this->service( EmailHooks::class )->register();
		// WooCommerce makes its mailer once, and hooks the order's details on in its constructor
		// (includes/class-wc-emails.php:239, :246);
		// the suite puts the hooks back as they were before each test, so a mailer made in an earlier test has lost
		// them.
		$mailer = WC()->mailer();
		if ( false === has_action( 'woocommerce_email_order_details', array( $mailer, 'order_details' ) ) ) {
			$mailer->__construct();
		}
		update_option( 'woocommerce_ship_to_countries', 'disabled' );
		update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes' ) );
		WC()->payment_gateways()->init();
	}

	public function tear_down(): void {
		as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . \CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		delete_option( 'woocommerce_feature_email_improvements_enabled' );
		delete_option( 'woocommerce_ship_to_countries' );
		delete_option( 'woocommerce_cheque_settings' );
		WC()->payment_gateways()->init();
		WC()->session->set( 'order_awaiting_payment', null );
		parent::tear_down();
	}

	/**
	 * An order of the usual build, assembled or not, by the classic checkout.
	 */
	private function order( bool $assembly ): WC_Order {
		$response = $this->store(
			array(
				'id'       => $this->kit,
				'quantity' => 1,
				'cbuild'   => array(
					'picks'    => $this->picks(),
					'assembly' => $assembly,
				),
			)
		);
		self::assertSame( 201, $response->get_status() );
		$id = WC()->checkout()->create_order(
			array(
				'billing_first_name' => 'Sam',
				'billing_last_name'  => 'Shopper',
				'billing_email'      => 'shopper+1@example.com',
				'payment_method'     => 'cheque',
			)
		);
		self::assertIsInt( $id );
		$order = wc_get_order( $id );
		self::assertInstanceOf( WC_Order::class, $order );
		return $order;
	}

	/**
	 * The customer's processing email for the order, as HTML or plain text.
	 */
	private static function email( WC_Order $order, bool $plain ): string {
		$email = WC()->mailer()->get_emails()['WC_Email_Customer_Processing_Order'] ?? null;
		self::assertInstanceOf( WC_Email::class, $email );
		$email->object = $order;
		return $plain ? $email->get_content_plain() : $email->get_content_html();
	}

	public function test_a_plain_text_email_says_the_build_in_words_and_its_lead_time_and_notice_once(): void {
		$order = $this->order( true );
		$text  = self::email( $order, true );
		self::assertStringContainsString( 'The build: Deck: Deck', $text );
		self::assertSame( 1, substr_count( $text, 'Assembled builds ship in 3 days.' ) );
		self::assertSame( 1, substr_count( $text, 'An assembled board comes back as a whole.' ) );
		self::assertStringNotContainsString( '<img', $text );
	}

	public function test_both_html_layouts_show_the_picture_with_a_short_alt_as_the_parts_follow(): void {
		$order = $this->order( true );
		$kit   = null;
		foreach ( $order->get_items() as $item ) {
			if ( Options::ROLE_KIT === $item->get_meta( Options::ORDER_ROLE ) ) {
				$kit = $item;
			}
		}
		self::assertNotNull( $kit );
		// A deck with a layer image, so there is a board to draw (as CartPictureTest).
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) \CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
		$pictures = $this->service( ComposedPictures::class );
		$picks    = $this->picks();
		self::assertGreaterThan( 0, $pictures->compose( $this->kit, $picks ), 'GD draws the picture here' );
		foreach ( array( 'no', 'yes' ) as $improved ) {
			update_option( 'woocommerce_feature_email_improvements_enabled', $improved );
			$html = self::email( $order, false );
			// Cowork's 0.8.0 review, F4: the parts are listed under the picture, so its alt says what it is and no more.
			self::assertMatchesRegularExpression( '/<img src="[^"]+" alt="The build, as drawn" width="300" height="300"/', $html, 'layout ' . $improved );
			self::assertStringNotContainsString( 'alt="The build: ', $html, 'layout ' . $improved );
			self::assertSame( 1, substr_count( $html, 'Assembled builds ship in 3 days.' ), 'layout ' . $improved );
			self::assertStringContainsString( 'cbuild-order-part', $html, 'parts marked as under their kit' );
		}
	}

	public function test_a_picture_not_made_yet_is_asked_for_and_the_email_goes_without(): void {
		$order = $this->order( false );
		$html  = self::email( $order, false );
		self::assertStringNotContainsString( 'cbuild-email-picture', $html );
		self::assertNotSame( array(), self::waiting( ComposedPictures::JOB ) );
		self::assertStringNotContainsString( 'Assembled builds ship in 3 days.', $html, 'not assembled: no lead time' );
	}

	public function test_the_order_page_lists_parts_under_their_kit_with_the_builds_picture(): void {
		$order = $this->order( true );
		$deck  = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) \CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
		$this->service( ComposedPictures::class )->compose( $this->kit, $this->picks() );
		ob_start();
		woocommerce_order_details_table( $order->get_id() );
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'cbuild-order-part', $html );
		self::assertMatchesRegularExpression( '/<img src="[^"]+" alt="The build, as drawn"/', $html, 'F4: the parts follow, so the alt does not repeat them' );
	}

	/**
	 * The kit's assembly words, as the shop owner wrote them.
	 *
	 * @param string $lead The lead time.
	 */
	private function leadTime( string $lead ): void {
		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_ASSEMBLY,
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15',
				'lead_time'      => $lead,
				'returns_notice' => 'An assembled board comes back as a whole.',
			)
		);
		$kit->save();
	}

	public function test_a_plain_text_email_keeps_an_apostrophe(): void {
		$this->leadTime( "Livraison d'ici 3 jours." );
		$order = $this->order( true );
		$text  = self::email( $order, true );
		self::assertStringContainsString( "Livraison d'ici 3 jours.", $text );
	}

	/**
	 * Roadmap, milestone 8: "Emails in English and French." WooCommerce writes a customer's email in the site's
	 * language (includes/emails/class-wc-email.php:443, `wc_switch_to_site_locale()`), or the one a translation plugin
	 * chooses there; the plugin's words and the slots' labels follow it, the part names are as sold.
	 */
	public function test_an_email_written_in_french_names_the_build_in_french_in_every_form(): void {
		$order  = $this->order( true );
		$deck   = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) \CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
		$this->service( ComposedPictures::class )->compose( $this->kit, $this->picks() );
		add_filter( 'get_available_languages', static fn( array $languages ): array => array_merge( $languages, array( 'fr_CA' ) ) );
		$french = array(
			'The build: %s.'      => 'La planche : %s.',
			'The build, as drawn' => 'Le montage, tel que dessiné',
			'%1$s: %2$s'          => '%1$s : %2$s',
		);
		add_filter(
			'gettext_creative-skateboard-builder',
			static fn( string $translation, string $text ): string => 'fr_CA' === determine_locale() ? ( $french[ $text ] ?? $translation ) : $translation,
			10,
			2
		);
		// A translation plugin's answer for the shop's own label, as it would give it in French.
		add_filter( 'cbuild_slot_label', static fn( string $label, string $slot ): string => 'fr_CA' === determine_locale() && 'deck' === $slot ? 'Plateau' : $label, 10, 2 );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		self::assertTrue( switch_to_locale( 'fr_CA' ) );
		try {
			$text = self::email( $order, true );
			$html = array();
			foreach ( array( 'no', 'yes' ) as $improved ) {
				update_option( 'woocommerce_feature_email_improvements_enabled', $improved );
				$html[ $improved ] = self::email( $order, false );
			}
		} finally {
			restore_previous_locale();
		}
		self::assertStringContainsString( 'La planche : Plateau : Deck; ', $text );
		foreach ( $html as $improved => $page ) {
			self::assertStringContainsString( 'alt="Le montage, tel que dessiné"', $page, 'layout ' . $improved );
		}
	}
}
