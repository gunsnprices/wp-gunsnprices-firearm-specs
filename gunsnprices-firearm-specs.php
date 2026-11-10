<?php
/**
 * Plugin Name:       GunsNPrices Firearm Specs
 * Plugin URI:        https://gunsnprices.com
 * Description:       Reads caliber, brand and model, magazine capacity, barrel length and rounds per box from gun and ammo product titles. Shows cost per round for ammo in WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            GunsNPrices
 * Author URI:        https://gunsnprices.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gunsnprices-firearm-specs
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package GunsNPricesFirearmSpecs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GNPFS_VERSION', '0.1.0' );
define( 'GNPFS_FILE', __FILE__ );
define( 'GNPFS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GNPFS_URL', plugin_dir_url( __FILE__ ) );

require_once GNPFS_DIR . 'includes/autoload.php';

add_action( 'before_woocommerce_init', array( 'GunsNPricesFirearmSpecs\Plugin', 'declare_wc_compatibility' ) );
add_action( 'plugins_loaded', array( 'GunsNPricesFirearmSpecs\Plugin', 'init' ) );
