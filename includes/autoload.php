<?php
/**
 * Class autoloader for the plugin and its bundled copy of the gunsnprices parser.
 *
 * GunsNPricesFirearmSpecs\Some_Class           => includes/class-some-class.php
 * GunsNPricesFirearmSpecs\Vendor\GunsNPrices\X => lib/gunsnprices/src/X.php
 *
 * @package GunsNPricesFirearmSpecs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class_name ) {
		$vendor = 'GunsNPricesFirearmSpecs\\Vendor\\GunsNPrices\\';
		$own    = 'GunsNPricesFirearmSpecs\\';

		if ( 0 === strpos( $class_name, $vendor ) ) {
			$relative = str_replace( '\\', '/', substr( $class_name, strlen( $vendor ) ) );
			$file     = GNPFS_DIR . 'lib/gunsnprices/src/' . $relative . '.php';
		} elseif ( 0 === strpos( $class_name, $own ) ) {
			$relative = substr( $class_name, strlen( $own ) );
			if ( false !== strpos( $relative, '\\' ) ) {
				return;
			}
			$file = GNPFS_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		} else {
			return;
		}

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
