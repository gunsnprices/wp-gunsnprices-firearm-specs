<?php
/**
 * Settings → Firearm Specs.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options stored in the gnpfs_settings option, and the settings screen.
 */
final class Settings {

	const OPTION = 'gnpfs_settings';
	const PAGE   = 'gnpfs-settings';

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'cpr_display'     => 'everywhere',
			'cpr_decimals'    => 2,
			'show_specs'      => 1,
			'autofill'        => 1,
			'sync_attributes' => 0,
			'sync_fields'     => array( 'caliber', 'capacity', 'barrel_length', 'rounds' ),
			'credit'          => 0,
			'delete_data'     => 0,
		);
	}

	/**
	 * One setting, or all of them.
	 *
	 * @param string|null $key Setting name.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$saved = get_option( self::OPTION, array() );
		$all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		if ( null === $key ) {
			return $all;
		}
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'after_update' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'after_add' ), 10, 2 );
	}

	/**
	 * Adds the page under Settings.
	 */
	public static function menu() {
		$hook = add_options_page(
			__( 'GunsNPrices Firearm Specs', 'gunsnprices-firearm-specs' ),
			__( 'Firearm Specs', 'gunsnprices-firearm-specs' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		add_action( 'admin_print_scripts-' . $hook, array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Scripts for the "fill all products" box.
	 */
	public static function enqueue() {
		if ( ! Plugin::has_woocommerce() ) {
			return;
		}
		wp_enqueue_script( 'gnpfs-admin', GNPFS_URL . 'assets/admin.js', array(), GNPFS_VERSION, true );
		wp_localize_script(
			'gnpfs-admin',
			'gnpfsAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'fillNonce' => wp_create_nonce( 'gnpfs_fill_batch' ),
				'i18n'      => array(
					/* translators: 1: products done, 2: products in total. */
					'progress' => __( 'Processed %1$d of %2$d products…', 'gunsnprices-firearm-specs' ),
					/* translators: 1: products processed, 2: products whose specs changed. */
					'done'     => __( 'Done: %1$d products processed, %2$d updated.', 'gunsnprices-firearm-specs' ),
					'error'    => __( 'The request failed. Reload the page and try again.', 'gunsnprices-firearm-specs' ),
				),
			)
		);
	}

	/**
	 * Registers the option and its fields.
	 */
	public static function register() {
		register_setting(
			'gnpfs_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'gnpfs_wc', __( 'WooCommerce products', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'section_wc' ), self::PAGE );
		add_settings_section( 'gnpfs_front', __( 'Store front', 'gunsnprices-firearm-specs' ), '__return_false', self::PAGE );
		add_settings_section( 'gnpfs_data', __( 'Data', 'gunsnprices-firearm-specs' ), '__return_false', self::PAGE );

		add_settings_field( 'autofill', __( 'New products', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'field_checkbox' ), self::PAGE, 'gnpfs_wc', array( 'key' => 'autofill', 'label' => __( 'Fill empty specs from the title when a product is saved for the first time (admin, REST API, CSV import)', 'gunsnprices-firearm-specs' ) ) );
		add_settings_field( 'sync_attributes', __( 'Product attributes', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'field_sync' ), self::PAGE, 'gnpfs_wc' );

		add_settings_field( 'cpr_display', __( 'Cost per round', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'field_cpr' ), self::PAGE, 'gnpfs_front' );
		add_settings_field( 'show_specs', __( 'Specs table', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'field_checkbox' ), self::PAGE, 'gnpfs_front', array( 'key' => 'show_specs', 'label' => __( 'Show the specs in the "Additional information" tab of the product page', 'gunsnprices-firearm-specs' ) ) );
		add_settings_field( 'credit', __( 'Credit link', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'field_checkbox' ), self::PAGE, 'gnpfs_front', array( 'key' => 'credit', 'label' => __( 'Add a "Specs: GunsNPrices" link under the specs table (off by default)', 'gunsnprices-firearm-specs' ) ) );

		add_settings_field( 'delete_data', __( 'Uninstall', 'gunsnprices-firearm-specs' ), array( __CLASS__, 'field_checkbox' ), self::PAGE, 'gnpfs_data', array( 'key' => 'delete_data', 'label' => __( 'Delete the specs saved on products when the plugin is deleted (attributes and their terms are kept)', 'gunsnprices-firearm-specs' ) ) );
	}

	/**
	 * Cleans the submitted values.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['cpr_display']  = isset( $input['cpr_display'] ) && in_array( $input['cpr_display'], array( 'everywhere', 'single', 'off' ), true ) ? $input['cpr_display'] : 'everywhere';
		$out['cpr_decimals'] = isset( $input['cpr_decimals'] ) ? min( 4, max( 2, absint( $input['cpr_decimals'] ) ) ) : 2;
		foreach ( array( 'show_specs', 'autofill', 'sync_attributes', 'credit', 'delete_data' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		$fields             = isset( $input['sync_fields'] ) && is_array( $input['sync_fields'] ) ? array_map( 'sanitize_key', $input['sync_fields'] ) : array();
		$out['sync_fields'] = array_values( array_intersect( array_keys( Attributes::taxonomies() ), $fields ) );
		return $out;
	}

	/**
	 * Creates the attribute taxonomies once syncing is switched on.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public static function after_update( $old_value, $value ) {
		if ( is_array( $value ) && ! empty( $value['sync_attributes'] ) && Plugin::has_woocommerce() ) {
			foreach ( (array) $value['sync_fields'] as $field ) {
				Attributes::ensure_taxonomy( $field );
			}
		}
	}

	/**
	 * Same as after_update() for the first save.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public static function after_add( $option, $value ) {
		self::after_update( null, $value );
	}

	/**
	 * Intro of the WooCommerce section.
	 */
	public static function section_wc() {
		if ( ! Plugin::has_woocommerce() ) {
			echo '<p>' . esc_html__( 'WooCommerce is not active. These settings apply once it is; the shortcode, the block and the caliber tool work without it.', 'gunsnprices-firearm-specs' ) . '</p>';
		}
	}

	/**
	 * A checkbox.
	 *
	 * @param array $args key, label.
	 */
	public static function field_checkbox( $args ) {
		$key = $args['key'];
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			checked( (int) self::get( $key ), 1, false ),
			esc_html( $args['label'] )
		);
	}

	/**
	 * Attribute sync: on/off and which specs.
	 */
	public static function field_sync() {
		$selected = (array) self::get( 'sync_fields' );
		printf(
			'<label><input type="checkbox" name="%1$s[sync_attributes]" value="1" %2$s> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( (int) self::get( 'sync_attributes' ), 1, false ),
			esc_html__( 'Copy the specs into global product attributes, so shoppers can filter by them', 'gunsnprices-firearm-specs' )
		);
		echo '<fieldset style="margin:8px 0 0 24px">';
		foreach ( Attributes::taxonomies() as $field => $info ) {
			printf(
				'<label style="display:block"><input type="checkbox" name="%1$s[sync_fields][]" value="%2$s" %3$s> %4$s <code>%5$s</code></label>',
				esc_attr( self::OPTION ),
				esc_attr( $field ),
				checked( in_array( $field, $selected, true ), true, false ),
				esc_html( $info['label'] ),
				esc_html( 'pa_' . $info['slug'] )
			);
		}
		echo '</fieldset><p class="description">' . esc_html__( 'Missing attributes are created under Products → Attributes when you save. Existing attributes with the same slug are reused. Attributes used for variations are never changed.', 'gunsnprices-firearm-specs' ) . '</p>';
	}

	/**
	 * Cost per round: where and how many decimals.
	 */
	public static function field_cpr() {
		$current = self::get( 'cpr_display' );
		$choices = array(
			'everywhere' => __( 'Next to the price of ammo on product pages and in shop lists', 'gunsnprices-firearm-specs' ),
			'single'     => __( 'On product pages only', 'gunsnprices-firearm-specs' ),
			'off'        => __( 'Do not show', 'gunsnprices-firearm-specs' ),
		);
		foreach ( $choices as $value => $label ) {
			printf(
				'<label style="display:block"><input type="radio" name="%1$s[cpr_display]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '<label style="display:block;margin-top:6px">' . esc_html__( 'Decimals:', 'gunsnprices-firearm-specs' ) . ' <select name="' . esc_attr( self::OPTION ) . '[cpr_decimals]">';
		foreach ( array( 2, 3, 4 ) as $d ) {
			printf( '<option value="%1$d" %2$s>%1$d</option>', (int) $d, selected( (int) self::get( 'cpr_decimals' ), $d, false ) );
		}
		echo '</select></label><p class="description">' . esc_html__( 'Shown for simple ammo products that have a price and a rounds-per-box value, for example "(0.44 per round)".', 'gunsnprices-firearm-specs' ) . '</p>';
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'GunsNPrices Firearm Specs', 'gunsnprices-firearm-specs' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'gnpfs_settings_group' );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form>';

		if ( Plugin::has_woocommerce() && current_user_can( 'edit_products' ) ) {
			echo '<hr><h2>' . esc_html__( 'Fill specs for all products', 'gunsnprices-firearm-specs' ) . '</h2>';
			echo '<p>' . esc_html__( 'Reads the title, SKU and part number of every product and saves the specs it finds. Values you entered by hand are kept unless you tick the box. For a few products at a time use Products → Bulk actions → "Fill specs from title"; on the command line use "wp gunsnprices fill".', 'gunsnprices-firearm-specs' ) . '</p>';
			echo '<p><label><input type="checkbox" id="gnpfs-fill-overwrite"> ' . esc_html__( 'Replace values that are already filled in', 'gunsnprices-firearm-specs' ) . '</label></p>';
			echo '<p><button type="button" class="button button-secondary" id="gnpfs-fill-all">' . esc_html__( 'Fill specs now', 'gunsnprices-firearm-specs' ) . '</button> <span id="gnpfs-fill-status" aria-live="polite"></span></p>';
		}

		echo '<hr><p class="description">';
		printf(
			/* translators: %s: link to gunsnprices.com */
			esc_html__( 'Model dictionaries and caliber list: %s, CC BY 4.0. No data leaves your site: the parser runs locally.', 'gunsnprices-firearm-specs' ),
			'<a href="https://gunsnprices.com" target="_blank" rel="noopener">GunsNPrices</a>'
		);
		echo '</p></div>';
	}
}
