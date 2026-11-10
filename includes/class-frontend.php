<?php
/**
 * Store front: cost per round next to ammo prices and the specs rows in "Additional information".
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store front output. Reads saved specs only; the parser is not loaded on the front end.
 */
final class Frontend {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
		add_filter( 'woocommerce_display_product_attributes', array( __CLASS__, 'attribute_rows' ), 20, 2 );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tabs' ), 98 );
	}

	/**
	 * A money amount as plain text in the store currency ("$0.44").
	 *
	 * @param float $amount   Amount.
	 * @param int   $decimals Decimals.
	 * @return string Text, may contain HTML entities for the currency symbol.
	 */
	public static function format_money( $amount, $decimals = 2 ) {
		if ( function_exists( 'wc_price' ) ) {
			return trim( wp_strip_all_tags( wc_price( $amount, array( 'decimals' => $decimals ) ) ) );
		}
		return '$' . number_format_i18n( $amount, $decimals );
	}

	/**
	 * Cost per round of a product, or null.
	 *
	 * @param \WC_Product $product Product.
	 * @return float|null
	 */
	public static function product_cost_per_round( $product ) {
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'simple' ) ) {
			return null;
		}
		$id = $product->get_id();
		if ( 'ammo' !== get_post_meta( $id, Specs::META . 'kind', true ) ) {
			return null;
		}
		$rounds = (int) get_post_meta( $id, Specs::META . 'rounds', true );
		if ( $rounds <= 0 || '' === $product->get_price() ) {
			return null;
		}
		return Specs::cost_per_round( wc_get_price_to_display( $product ), $rounds );
	}

	/**
	 * Adds "(0.44 per round)" after the price of ammo.
	 *
	 * @param string      $html    Price HTML.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		$where = Settings::get( 'cpr_display' );
		if ( 'off' === $where || '' === $html ) {
			return $html;
		}
		if ( 'single' === $where && ! ( function_exists( 'is_product' ) && is_product() && get_queried_object_id() === $product->get_id() ) ) {
			return $html;
		}
		$cpr = self::product_cost_per_round( $product );
		if ( null === $cpr ) {
			return $html;
		}
		$text = sprintf(
			/* translators: %s: price of one round, for example $0.44. */
			__( '(%s per round)', 'gunsnprices-firearm-specs' ),
			self::format_money( $cpr, (int) Settings::get( 'cpr_decimals' ) )
		);

		/**
		 * Filters the cost-per-round text added after the price.
		 *
		 * @param string      $text    Text, for example "($0.44 per round)".
		 * @param float       $cpr     Cost per round.
		 * @param \WC_Product $product Product.
		 */
		$text = apply_filters( 'gnpfs_cost_per_round_text', $text, $cpr, $product );

		return $html . ' <span class="gnpfs-cost-per-round">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Rows to show for a product: field => [label, value], skipping specs already shown as attributes.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function rows( $product ) {
		$specs = Specs::get( $product->get_id() );
		if ( 'none' === $specs['kind'] ) {
			return array();
		}
		$fields = 'ammo' === $specs['kind']
			? array( 'brand', 'caliber', 'rounds' )
			: array( 'brand', 'model', 'caliber', 'capacity', 'barrel_length' );

		$labels     = Specs::labels();
		$taxonomies = Attributes::taxonomies();
		$attributes = $product->get_attributes();
		$rows       = array();
		foreach ( $fields as $field ) {
			if ( '' === $specs[ $field ] ) {
				continue;
			}
			if ( isset( $taxonomies[ $field ] ) ) {
				$taxonomy = wc_attribute_taxonomy_name( $taxonomies[ $field ]['slug'] );
				if ( isset( $attributes[ $taxonomy ] ) && $attributes[ $taxonomy ]->get_visible() ) {
					continue;
				}
			}
			if ( 'brand' === $field && ( isset( $attributes['pa_brand'] ) || has_term( '', 'product_brand', $product->get_id() ) ) ) {
				continue;
			}
			$rows[ $field ] = array(
				'label' => $labels[ $field ],
				'value' => Specs::display_value( $field, $specs[ $field ] ),
			);
		}
		return $rows;
	}

	/**
	 * Adds the specs to the "Additional information" table.
	 *
	 * @param array       $product_attributes Rows.
	 * @param \WC_Product $product            Product.
	 * @return array
	 */
	public static function attribute_rows( $product_attributes, $product ) {
		if ( ! Settings::get( 'show_specs' ) || ! $product instanceof \WC_Product ) {
			return $product_attributes;
		}
		$rows = self::rows( $product );
		if ( ! $rows ) {
			return $product_attributes;
		}
		$own = array();
		foreach ( $rows as $field => $row ) {
			$own[ 'gnpfs_' . $field ] = array(
				'label' => esc_html( $row['label'] ),
				'value' => esc_html( $row['value'] ),
			);
		}
		if ( Settings::get( 'credit' ) ) {
			$own['gnpfs_credit'] = array(
				'label' => esc_html__( 'Specs', 'gunsnprices-firearm-specs' ),
				'value' => '<a href="https://gunsnprices.com">GunsNPrices</a>',
			);
		}
		return array_merge( $own, $product_attributes );
	}

	/**
	 * WooCommerce shows "Additional information" only for products with attributes or dimensions; show it for specs too.
	 *
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public static function tabs( $tabs ) {
		global $product;
		if ( isset( $tabs['additional_information'] ) || ! Settings::get( 'show_specs' ) || ! $product instanceof \WC_Product || ! self::rows( $product ) ) {
			return $tabs;
		}
		$tabs['additional_information'] = array(
			'title'    => __( 'Additional information', 'gunsnprices-firearm-specs' ),
			'priority' => 20,
			'callback' => 'woocommerce_product_additional_information_tab',
		);
		return $tabs;
	}
}
