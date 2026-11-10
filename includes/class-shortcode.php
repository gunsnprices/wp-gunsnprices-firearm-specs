<?php
/**
 * [cost_per_round] shortcode, also used to render the Cost per round block.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

use GunsNPricesFirearmSpecs\Vendor\GunsNPrices\Parser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cost per round shortcode.
 */
final class Shortcode {

	/**
	 * Registers [gnpfs_cost_per_round] and, unless another plugin owns the name, [cost_per_round].
	 */
	public static function init() {
		add_shortcode( 'gnpfs_cost_per_round', array( __CLASS__, 'shortcode' ) );
		if ( ! shortcode_exists( 'cost_per_round' ) ) {
			add_shortcode( 'cost_per_round', array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes.
	 * @return string HTML.
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'price'    => '',
				'rounds'   => '',
				'title'    => '',
				'id'       => '',
				'decimals' => '2',
				'currency' => '$',
			),
			$atts,
			'cost_per_round'
		);
		return self::render( $atts );
	}

	/**
	 * Computes and formats the cost per round.
	 *
	 * Sources, in order: price and rounds given; rounds read from title; a WooCommerce product (id, or the current
	 * product inside a product page or loop).
	 *
	 * @param array $atts price, rounds, title, id, decimals, currency.
	 * @return string HTML, '' when it cannot be computed.
	 */
	public static function render( array $atts ) {
		$price    = self::number( isset( $atts['price'] ) ? $atts['price'] : '' );
		$rounds   = absint( isset( $atts['rounds'] ) ? $atts['rounds'] : 0 );
		$title    = isset( $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : '';
		$decimals = min( 4, max( 0, absint( isset( $atts['decimals'] ) ? $atts['decimals'] : 2 ) ) );
		$currency = isset( $atts['currency'] ) ? sanitize_text_field( (string) $atts['currency'] ) : '$';

		if ( ! $rounds && '' !== $title ) {
			$rounds = (int) Parser::roundsPerBox( $title );
		}

		if ( ( ! $price || ! $rounds ) && Plugin::has_woocommerce() ) {
			$product = null;
			if ( ! empty( $atts['id'] ) ) {
				$product = wc_get_product( absint( $atts['id'] ) );
			} elseif ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
				$product = $GLOBALS['product'];
			}
			if ( $product instanceof \WC_Product ) {
				if ( ! $price && '' !== $product->get_price() ) {
					$price = (float) wc_get_price_to_display( $product );
				}
				if ( ! $rounds ) {
					$rounds = (int) get_post_meta( $product->get_id(), Specs::META . 'rounds', true );
				}
			}
		}

		$cpr = Specs::cost_per_round( $price, $rounds );
		if ( null === $cpr ) {
			return '';
		}

		if ( Plugin::has_woocommerce() ) {
			$amount = Frontend::format_money( $cpr, $decimals );
		} else {
			$amount = $currency . number_format_i18n( $cpr, $decimals );
		}

		/* translators: %s: price of one round, for example $0.44. */
		$text = sprintf( __( '%s per round', 'gunsnprices-firearm-specs' ), $amount );
		return '<span class="gnpfs-cost-per-round">' . esc_html( $text ) . '</span>';
	}

	/**
	 * A price from user input ("$1,299.99" => 1299.99).
	 *
	 * @param mixed $value Input.
	 * @return float
	 */
	private static function number( $value ) {
		$value = preg_replace( '/[^0-9.]/', '', (string) $value );
		return '' === $value ? 0.0 : (float) $value;
	}
}
