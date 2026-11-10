<?php
/**
 * Wires the plugin's parts to WordPress and WooCommerce hooks.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/**
	 * Runs on plugins_loaded.
	 */
	public static function init() {
		Shortcode::init();
		add_action( 'init', array( __CLASS__, 'register_block' ) );

		if ( is_admin() ) {
			Settings::init();
			Tools_Page::init();
			add_filter( 'plugin_action_links_' . plugin_basename( GNPFS_FILE ), array( __CLASS__, 'action_links' ) );
		}

		if ( self::has_woocommerce() ) {
			Product_Tab::init();
			Bulk::init();
			Frontend::init();
			add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product_save' ), 20, 2 );
			add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_save' ), 20, 2 );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'gunsnprices', Cli::class );
		}
	}

	/**
	 * True when WooCommerce is active and loaded.
	 *
	 * @return bool
	 */
	public static function has_woocommerce() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Declares compatibility with WooCommerce features (the plugin stores nothing on orders).
	 */
	public static function declare_wc_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GNPFS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', GNPFS_FILE, true );
		}
	}

	/**
	 * Registers the Cost per round block (dynamic, rendered by the shortcode code).
	 */
	public static function register_block() {
		register_block_type(
			GNPFS_DIR . 'blocks/cost-per-round',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);
	}

	/**
	 * Front-end output of the Cost per round block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$html = Shortcode::render( (array) $attributes );
		if ( '' === $html ) {
			return '';
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	/**
	 * Adds Settings and Tools links on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$own = array(
			'<a href="' . esc_url( admin_url( 'options-general.php?page=gnpfs-settings' ) ) . '">' . esc_html__( 'Settings', 'gunsnprices-firearm-specs' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'tools.php?page=gnpfs-tools' ) ) . '">' . esc_html__( 'Caliber tool', 'gunsnprices-firearm-specs' ) . '</a>',
		);
		return array_merge( $own, $links );
	}

	/**
	 * Fills the specs of a product the first time it is saved (admin, REST API, CSV import), if enabled,
	 * and keeps the synced attributes in line with values edited on the product screen.
	 *
	 * @param int         $product_id Product ID.
	 * @param \WC_Product $product    Product object.
	 */
	public static function on_product_save( $product_id, $product = null ) {
		static $running = array();
		if ( isset( $running[ $product_id ] ) ) {
			return;
		}
		if ( ! $product instanceof \WC_Product ) {
			$product = wc_get_product( $product_id );
		}
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return;
		}
		$running[ $product_id ] = true;

		if ( Settings::get( 'autofill' ) && ! Specs::was_parsed( $product_id ) ) {
			Specs::fill_product( $product, false );
		} elseif ( Product_Tab::was_saved( $product_id ) ) {
			Attributes::sync( $product );
		}

		unset( $running[ $product_id ] );
	}
}
