<?php
/**
 * "Firearm & ammo specs" tab in the WooCommerce product data box.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product edit screen.
 */
final class Product_Tab {

	/**
	 * Products saved from the edit screen in this request.
	 *
	 * @var array<int, bool>
	 */
	private static $saved = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		// Before WooCommerce saves the product (priority 10), so the save hooks see the new values.
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_gnpfs_parse_title', array( __CLASS__, 'ajax_parse' ) );
	}

	/**
	 * True if the product's specs were saved from the edit screen in this request.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function was_saved( $product_id ) {
		return isset( self::$saved[ $product_id ] );
	}

	/**
	 * Adds the tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		$tabs['gnpfs_specs'] = array(
			'label'    => __( 'Firearm & ammo specs', 'gunsnprices-firearm-specs' ),
			'target'   => 'gnpfs_specs_data',
			'class'    => array(),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Scripts and styles on the product edit screen.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_post_type() ) {
			return;
		}
		wp_enqueue_style( 'gnpfs-admin', GNPFS_URL . 'assets/admin.css', array(), GNPFS_VERSION );
		wp_enqueue_script( 'gnpfs-admin', GNPFS_URL . 'assets/admin.js', array(), GNPFS_VERSION, true );
		wp_localize_script(
			'gnpfs-admin',
			'gnpfsAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'parseNonce' => wp_create_nonce( 'gnpfs_parse_title' ),
				'i18n'       => array(
					'nothing' => __( 'Nothing found in the title.', 'gunsnprices-firearm-specs' ),
					/* translators: %d: number of fields filled. */
					'filled'  => __( '%d fields filled. Save the product to keep them.', 'gunsnprices-firearm-specs' ),
					'error'   => __( 'The request failed. Reload the page and try again.', 'gunsnprices-firearm-specs' ),
				),
			)
		);
	}

	/**
	 * The panel.
	 */
	public static function panel() {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		$saved      = Specs::get( $product_id );
		$labels     = Specs::labels();

		$suggested = array();
		$product   = $product_id ? wc_get_product( $product_id ) : null;
		if ( $product && 'auto-draft' !== get_post_status( $product_id ) && '' !== $product->get_name() ) {
			$suggested = Specs::parse( Specs::product_title( $product ), Specs::hints( $product, $saved ) );
		}

		echo '<div id="gnpfs_specs_data" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( 'gnpfs_save_specs', 'gnpfs_specs_nonce' );

		echo '<div class="options_group">';
		echo '<p class="form-field gnpfs-fill-row"><button type="button" class="button" id="gnpfs-fill-from-title">' . esc_html__( 'Fill from title', 'gunsnprices-firearm-specs' ) . '</button> <span class="gnpfs-fill-status" aria-live="polite"></span>';
		echo '<span class="description">' . esc_html__( 'Reads the product name, SKU and part number above. Grey hints show what the title says; empty fields are filled from the title the first time the product is saved.', 'gunsnprices-firearm-specs' ) . '</span></p>';

		woocommerce_wp_select(
			array(
				'id'      => 'gnpfs_kind',
				'label'   => $labels['kind'],
				'value'   => $saved['kind'],
				'options' => array(
					''        => __( 'Detect from the title', 'gunsnprices-firearm-specs' ),
					'firearm' => __( 'Firearm', 'gunsnprices-firearm-specs' ),
					'ammo'    => __( 'Ammunition', 'gunsnprices-firearm-specs' ),
					'none'    => __( 'Not a firearm or ammunition (no specs)', 'gunsnprices-firearm-specs' ),
				),
			)
		);
		echo '</div><div class="options_group">';

		$inputs = array(
			'caliber'       => array( 'type' => 'text' ),
			'brand'         => array( 'type' => 'text' ),
			'model'         => array( 'type' => 'text' ),
			'mpn'           => array(
				'type'        => 'text',
				'description' => __( 'Optional. Helps with titles that do not name the model, for example Glock part numbers.', 'gunsnprices-firearm-specs' ),
			),
			'capacity'      => array(
				'type'  => 'number',
				'attrs' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'barrel_length' => array(
				'type'  => 'number',
				'attrs' => array(
					'min'  => '0',
					'step' => '0.01',
				),
				'label' => __( 'Barrel length (in)', 'gunsnprices-firearm-specs' ),
			),
			'rounds'        => array(
				'type'  => 'number',
				'attrs' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
		);

		foreach ( $inputs as $field => $input ) {
			$hint = isset( $suggested[ $field ] ) ? (string) $suggested[ $field ] : '';
			woocommerce_wp_text_input(
				array(
					'id'                => 'gnpfs_' . $field,
					'label'             => isset( $input['label'] ) ? $input['label'] : $labels[ $field ],
					'value'             => $saved[ $field ],
					'type'              => $input['type'],
					'placeholder'       => $hint,
					'desc_tip'          => isset( $input['description'] ),
					'description'       => isset( $input['description'] ) ? $input['description'] : '',
					'custom_attributes' => isset( $input['attrs'] ) ? $input['attrs'] : array(),
				)
			);
		}
		echo '</div>';

		if ( $product && 'ammo' === ( '' !== $saved['kind'] ? $saved['kind'] : ( isset( $suggested['kind'] ) ? $suggested['kind'] : '' ) ) ) {
			$rounds = '' !== $saved['rounds'] ? $saved['rounds'] : ( isset( $suggested['rounds'] ) ? $suggested['rounds'] : '' );
			$cpr    = Specs::cost_per_round( $product->get_price(), $rounds );
			if ( null !== $cpr ) {
				echo '<div class="options_group"><p class="form-field"><label>' . esc_html__( 'Cost per round', 'gunsnprices-firearm-specs' ) . '</label>';
				echo '<span>' . wp_kses_post( Frontend::format_money( $cpr, (int) Settings::get( 'cpr_decimals' ) ) ) . '</span></p></div>';
			}
		}
		echo '</div>';
	}

	/**
	 * Saves the panel.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save( $product_id ) {
		if ( ! isset( $_POST['gnpfs_specs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gnpfs_specs_nonce'] ) ), 'gnpfs_save_specs' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}
		$values = array();
		foreach ( array_keys( Specs::fields() ) as $field ) {
			$values[ $field ] = isset( $_POST[ 'gnpfs_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'gnpfs_' . $field ] ) ) : '';
		}
		Specs::save( $product_id, $values );
		self::$saved[ (int) $product_id ] = true;
	}

	/**
	 * AJAX: parses the title, SKU and part number currently in the form.
	 */
	public static function ajax_parse() {
		check_ajax_referer( 'gnpfs_parse_title', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( null, 403 );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$hints = array();
		foreach ( array( 'sku', 'mpn', 'brand', 'kind' ) as $key ) {
			$hints[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		if ( ! in_array( $hints['kind'], array( 'firearm', 'ammo' ), true ) ) {
			$hints['kind'] = '';
		}
		wp_send_json_success( Specs::parse( $title, $hints ) );
	}
}
