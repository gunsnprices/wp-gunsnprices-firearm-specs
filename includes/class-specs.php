<?php
/**
 * Parsing titles into specs and storing them on products.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

use GunsNPricesFirearmSpecs\Vendor\GunsNPrices\Parser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec fields, the parser call and product meta.
 */
final class Specs {

	/** Meta key prefix. */
	const META = '_gnpfs_';

	/** Meta key holding the time of the last fill from the title. */
	const META_PARSED = '_gnpfs_parsed';

	/**
	 * Editable spec fields: key => type. Stored as _gnpfs_<key>.
	 *
	 * @return array<string, string>
	 */
	public static function fields() {
		return array(
			'kind'          => 'kind',
			'caliber'       => 'text',
			'brand'         => 'text',
			'model'         => 'text',
			'mpn'           => 'text',
			'capacity'      => 'int',
			'barrel_length' => 'float',
			'rounds'        => 'int',
		);
	}

	/**
	 * Fields filled from the title (the part number is only an input).
	 *
	 * @return string[]
	 */
	public static function parsed_fields() {
		return array( 'kind', 'caliber', 'brand', 'model', 'capacity', 'barrel_length', 'rounds' );
	}

	/**
	 * Human labels.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'kind'          => __( 'Product type', 'gunsnprices-firearm-specs' ),
			'caliber'       => __( 'Caliber', 'gunsnprices-firearm-specs' ),
			'brand'         => __( 'Brand', 'gunsnprices-firearm-specs' ),
			'model'         => __( 'Model', 'gunsnprices-firearm-specs' ),
			'mpn'           => __( 'Part number (MPN)', 'gunsnprices-firearm-specs' ),
			'capacity'      => __( 'Magazine capacity', 'gunsnprices-firearm-specs' ),
			'barrel_length' => __( 'Barrel length', 'gunsnprices-firearm-specs' ),
			'rounds'        => __( 'Rounds per box', 'gunsnprices-firearm-specs' ),
		);
	}

	/**
	 * Parses a title. Returns every field of fields() except mpn, as strings ('' when not found).
	 *
	 * @param string $title Product title.
	 * @param array  $hints sku, mpn, brand (manufacturer), kind (firearm|ammo).
	 * @return array<string, string>
	 */
	public static function parse( $title, array $hints = array() ) {
		$opts = array();
		foreach ( array( 'sku', 'mpn', 'brand', 'kind' ) as $key ) {
			if ( isset( $hints[ $key ] ) && '' !== (string) $hints[ $key ] ) {
				$opts[ $key ] = (string) $hints[ $key ];
			}
		}

		$info = Parser::parseTitle( (string) $title, $opts );

		$out = array(
			'kind'          => $info['kind'],
			'caliber'       => $info['caliber'] ? $info['caliber']['name'] : '',
			'brand'         => (string) $info['brand'],
			'model'         => $info['model'] ? $info['model']['model'] : '',
			'capacity'      => null !== $info['capacity'] ? (string) $info['capacity'] : '',
			'barrel_length' => null !== $info['barrelLength'] ? self::format_number( $info['barrelLength'] ) : '',
			'rounds'        => null !== $info['rounds'] ? (string) $info['rounds'] : '',
		);

		// Without a single spec there is nothing to say about the product type.
		$found = array_filter( array( $out['caliber'], $out['model'], $out['capacity'], $out['barrel_length'], $out['rounds'] ), 'strlen' );
		if ( ! $found && empty( $opts['kind'] ) ) {
			$out['kind'] = '';
		}

		/**
		 * Filters the specs parsed from a title.
		 *
		 * @param array  $out   Specs: kind, caliber, brand, model, capacity, barrel_length, rounds.
		 * @param string $title The title.
		 * @param array  $hints sku, mpn, brand, kind.
		 * @param array  $info  Full result of the gunsnprices parser.
		 */
		return apply_filters( 'gnpfs_parsed_specs', $out, $title, $hints, $info );
	}

	/**
	 * A number without trailing zeros: 4.0 => "4", 4.50 => "4.5".
	 *
	 * @param float|int|string $number Number.
	 * @return string
	 */
	public static function format_number( $number ) {
		$s = number_format( (float) $number, 2, '.', '' );
		return rtrim( rtrim( $s, '0' ), '.' );
	}

	/**
	 * Cleans one field value.
	 *
	 * @param string $field Field key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public static function clean( $field, $value ) {
		$types = self::fields();
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || ! isset( $types[ $field ] ) ) {
			return '';
		}
		switch ( $types[ $field ] ) {
			case 'kind':
				return in_array( $value, array( 'firearm', 'ammo', 'none' ), true ) ? $value : '';
			case 'int':
				$n = absint( $value );
				return $n > 0 ? (string) $n : '';
			case 'float':
				$n = (float) str_replace( ',', '.', $value );
				return $n > 0 ? self::format_number( $n ) : '';
			default:
				return mb_substr( sanitize_text_field( $value ), 0, 200 );
		}
	}

	/**
	 * Specs saved on a product, as strings.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	public static function get( $product_id ) {
		$out = array();
		foreach ( array_keys( self::fields() ) as $field ) {
			$out[ $field ] = (string) get_post_meta( $product_id, self::META . $field, true );
		}
		return $out;
	}

	/**
	 * Saves specs on a product. Empty values delete the meta.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $values     field => value.
	 */
	public static function save( $product_id, array $values ) {
		foreach ( $values as $field => $value ) {
			$value = self::clean( $field, $value );
			if ( '' === $value ) {
				delete_post_meta( $product_id, self::META . $field );
			} else {
				update_post_meta( $product_id, self::META . $field, $value );
			}
		}
	}

	/**
	 * True once the product was filled from its title.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function was_parsed( $product_id ) {
		return '' !== (string) get_post_meta( $product_id, self::META_PARSED, true );
	}

	/**
	 * Parser hints taken from a product: SKU, part number and brand.
	 *
	 * @param \WC_Product $product Product.
	 * @param array       $saved   Saved specs.
	 * @return array
	 */
	public static function hints( $product, array $saved ) {
		$brand = $saved['brand'];
		if ( '' === $brand && taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $product->get_id(), 'product_brand' );
			if ( is_array( $terms ) && $terms ) {
				$brand = $terms[0]->name;
			}
		}
		$hints = array(
			'sku'   => $product->get_sku(),
			'mpn'   => $saved['mpn'],
			'brand' => $brand,
			'kind'  => in_array( $saved['kind'], array( 'firearm', 'ammo' ), true ) ? $saved['kind'] : '',
		);

		/**
		 * Filters the hints passed to the parser for a product (sku, mpn, brand, kind).
		 *
		 * @param array       $hints   Hints.
		 * @param \WC_Product $product Product.
		 */
		return apply_filters( 'gnpfs_product_hints', $hints, $product );
	}

	/**
	 * The product title as saved in the database. During some saves (REST API, CLI) the product object still holds
	 * the slashed input (4.02\" instead of 4.02"), which would hide inch marks from the parser.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function product_title( $product ) {
		$title = (string) get_post_field( 'post_title', $product->get_id(), 'raw' );
		return '' !== $title ? $title : $product->get_name();
	}

	/**
	 * Fills a product's specs from its title.
	 *
	 * @param \WC_Product $product   Product.
	 * @param bool        $overwrite Replace values that are already set (only with values the parser found).
	 * @return bool True if any value changed.
	 */
	public static function fill_product( $product, $overwrite = false ) {
		$id    = $product->get_id();
		$saved = self::get( $id );

		update_post_meta( $id, self::META_PARSED, time() );

		if ( 'none' === $saved['kind'] ) {
			return false;
		}

		$parsed  = self::parse( self::product_title( $product ), self::hints( $product, $saved ) );
		$changes = array();
		foreach ( self::parsed_fields() as $field ) {
			$new = self::clean( $field, isset( $parsed[ $field ] ) ? $parsed[ $field ] : '' );
			if ( '' === $new || $new === $saved[ $field ] ) {
				continue;
			}
			if ( $overwrite || '' === $saved[ $field ] ) {
				$changes[ $field ] = $new;
			}
		}

		if ( $changes ) {
			self::save( $id, $changes );
		}
		$synced = Attributes::sync( $product );

		/**
		 * Fires after a product was filled from its title.
		 *
		 * @param \WC_Product $product Product.
		 * @param array       $changes Fields that changed.
		 */
		do_action( 'gnpfs_product_filled', $product, $changes );

		return (bool) $changes || $synced;
	}

	/**
	 * Cost per round, or null.
	 *
	 * @param float|string $price  Price.
	 * @param int|string   $rounds Rounds per box.
	 * @return float|null
	 */
	public static function cost_per_round( $price, $rounds ) {
		$price  = (float) $price;
		$rounds = (int) $rounds;
		if ( $price <= 0 || $rounds <= 0 ) {
			return null;
		}
		return $price / $rounds;
	}

	/**
	 * Display value of a spec ("4.02 in", "15 rounds").
	 *
	 * @param string $field Field key.
	 * @param string $value Stored value.
	 * @return string Plain text.
	 */
	public static function display_value( $field, $value ) {
		if ( '' === (string) $value ) {
			return '';
		}
		switch ( $field ) {
			case 'barrel_length':
				/* translators: %s: barrel length in inches. */
				return sprintf( __( '%s in', 'gunsnprices-firearm-specs' ), $value );
			case 'capacity':
				/* translators: %s: number of rounds in the magazine. */
				return sprintf( _n( '%s round', '%s rounds', (int) $value, 'gunsnprices-firearm-specs' ), number_format_i18n( (int) $value ) );
			case 'rounds':
				return number_format_i18n( (int) $value );
			case 'kind':
				$kinds = array(
					'firearm' => __( 'Firearm', 'gunsnprices-firearm-specs' ),
					'ammo'    => __( 'Ammunition', 'gunsnprices-firearm-specs' ),
					'none'    => __( 'Not a firearm or ammunition', 'gunsnprices-firearm-specs' ),
				);
				return isset( $kinds[ $value ] ) ? $kinds[ $value ] : '';
			default:
				return (string) $value;
		}
	}
}
