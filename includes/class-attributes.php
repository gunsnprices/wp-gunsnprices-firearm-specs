<?php
/**
 * Optional copy of the specs into global WooCommerce attributes (pa_caliber, ...), for layered navigation filters.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global attribute sync.
 */
final class Attributes {

	/**
	 * Spec field => attribute slug, label and term ordering.
	 *
	 * @return array<string, array{slug: string, label: string, order_by: string}>
	 */
	public static function taxonomies() {
		$map = array(
			'caliber'       => array(
				'slug'     => 'caliber',
				'label'    => __( 'Caliber', 'gunsnprices-firearm-specs' ),
				'order_by' => 'name',
			),
			'brand'         => array(
				'slug'     => 'brand',
				'label'    => __( 'Brand', 'gunsnprices-firearm-specs' ),
				'order_by' => 'name',
			),
			'model'         => array(
				'slug'     => 'model',
				'label'    => __( 'Model', 'gunsnprices-firearm-specs' ),
				'order_by' => 'name',
			),
			'capacity'      => array(
				'slug'     => 'capacity',
				'label'    => __( 'Magazine capacity', 'gunsnprices-firearm-specs' ),
				'order_by' => 'name_num',
			),
			'barrel_length' => array(
				'slug'     => 'barrel-length',
				'label'    => __( 'Barrel length', 'gunsnprices-firearm-specs' ),
				'order_by' => 'name_num',
			),
			'rounds'        => array(
				'slug'     => 'rounds-per-box',
				'label'    => __( 'Rounds per box', 'gunsnprices-firearm-specs' ),
				'order_by' => 'name_num',
			),
		);

		/**
		 * Filters the attribute used for each spec. Slugs are used without the pa_ prefix.
		 *
		 * @param array $map Spec field => slug, label, order_by.
		 */
		return apply_filters( 'gnpfs_attribute_taxonomies', $map );
	}

	/**
	 * Makes sure the global attribute for a spec exists and its taxonomy is registered. Returns the taxonomy name.
	 *
	 * @param string $field Spec field.
	 * @return string Taxonomy name (pa_...), or '' on failure.
	 */
	public static function ensure_taxonomy( $field ) {
		$map = self::taxonomies();
		if ( ! isset( $map[ $field ] ) || ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
			return '';
		}
		$slug     = wc_sanitize_taxonomy_name( $map[ $field ]['slug'] );
		$taxonomy = wc_attribute_taxonomy_name( $slug );

		if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
			$id = wc_create_attribute(
				array(
					'name'         => $map[ $field ]['label'],
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => $map[ $field ]['order_by'],
					'has_archives' => false,
				)
			);
			if ( is_wp_error( $id ) ) {
				return '';
			}
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			// WooCommerce registers attribute taxonomies on init; one created in this request needs it here.
			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'hierarchical' => false,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			);
		}
		return $taxonomy;
	}

	/**
	 * Term name for a spec value.
	 *
	 * @param string $field Spec field.
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function term_name( $field, $value ) {
		if ( 'barrel_length' === $field && '' !== $value ) {
			return $value . '"';
		}
		return $value;
	}

	/**
	 * Copies the product's specs into the enabled global attributes. Saves the product if anything changed.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool True if the product's attributes changed.
	 */
	public static function sync( $product ) {
		if ( ! Settings::get( 'sync_attributes' ) || ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return false;
		}
		$specs = Specs::get( $product->get_id() );
		if ( 'none' === $specs['kind'] ) {
			return false;
		}

		$attributes = $product->get_attributes();
		$changed    = false;

		foreach ( (array) Settings::get( 'sync_fields' ) as $field ) {
			if ( ! isset( $specs[ $field ] ) || '' === $specs[ $field ] ) {
				continue;
			}
			$taxonomy = self::ensure_taxonomy( $field );
			if ( '' === $taxonomy ) {
				continue;
			}
			$existing = isset( $attributes[ $taxonomy ] ) ? $attributes[ $taxonomy ] : null;
			if ( $existing && $existing->get_variation() ) {
				continue;
			}

			$name = self::term_name( $field, $specs[ $field ] );
			$term = term_exists( $name, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $name, $taxonomy );
			}
			if ( is_wp_error( $term ) || empty( $term['term_id'] ) ) {
				continue;
			}
			$term_id = (int) $term['term_id'];

			if ( $existing && array( $term_id ) === array_map( 'intval', $existing->get_options() ) ) {
				continue;
			}

			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array( $term_id ) );
			$attribute->set_position( $existing ? $existing->get_position() : count( $attributes ) );
			$attribute->set_visible( $existing ? $existing->get_visible() : true );
			$attribute->set_variation( false );

			$attributes[ $taxonomy ] = $attribute;
			$changed                 = true;
		}

		if ( $changed ) {
			$product->set_attributes( $attributes );
			$product->save();
		}
		return $changed;
	}
}
