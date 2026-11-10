<?php
/**
 * WP-CLI commands: wp gunsnprices parse|caliber|fill.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

use GunsNPricesFirearmSpecs\Vendor\GunsNPrices\Parser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse gun and ammo titles and fill product specs.
 */
final class Cli {

	/**
	 * Parses a product title.
	 *
	 * ## OPTIONS
	 *
	 * <title>
	 * : Product title.
	 *
	 * [--sku=<sku>]
	 * : SKU or part number.
	 *
	 * [--brand=<brand>]
	 * : Manufacturer, if the title does not name it.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp gunsnprices parse 'Glock 19 Gen 5 9mm 4.02" 15rd Pistol'
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function parse( $args, $assoc_args ) {
		$specs  = Specs::parse(
			$args[0],
			array(
				'sku'   => isset( $assoc_args['sku'] ) ? $assoc_args['sku'] : '',
				'mpn'   => isset( $assoc_args['sku'] ) ? $assoc_args['sku'] : '',
				'brand' => isset( $assoc_args['brand'] ) ? $assoc_args['brand'] : '',
			)
		);
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
			return;
		}
		$rows = array();
		foreach ( $specs as $field => $value ) {
			$rows[] = array(
				'field' => $field,
				'value' => $value,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}

	/**
	 * Normalizes a caliber name.
	 *
	 * ## OPTIONS
	 *
	 * <value>
	 * : Caliber as written in a feed or title, for example "308 WIN (7.62X51 NATO)".
	 *
	 * ## EXAMPLES
	 *
	 *     wp gunsnprices caliber '5.56 NATO'
	 *
	 * @param array $args Positional arguments.
	 */
	public function caliber( $args ) {
		$c = Parser::normalizeCaliber( $args[0] );
		if ( ! $c ) {
			\WP_CLI::error( 'No caliber found.' );
		}
		\WP_CLI::line( $c['name'] . "\t" . $c['id'] . "\t" . $c['type'] );
	}

	/**
	 * Fills product specs from product titles.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Comma-separated product IDs. Default: all products.
	 *
	 * [--overwrite]
	 * : Replace values that are already filled in.
	 *
	 * [--batch-size=<n>]
	 * : Products per query.
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp gunsnprices fill
	 *     wp gunsnprices fill --ids=12,15 --overwrite
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function fill( $args, $assoc_args ) {
		if ( ! Plugin::has_woocommerce() ) {
			\WP_CLI::error( 'WooCommerce is not active.' );
		}
		$overwrite = ! empty( $assoc_args['overwrite'] );
		$batch     = isset( $assoc_args['batch-size'] ) ? max( 1, absint( $assoc_args['batch-size'] ) ) : 100;
		$ids       = isset( $assoc_args['ids'] ) ? array_filter( array_map( 'absint', explode( ',', $assoc_args['ids'] ) ) ) : array();

		$done    = 0;
		$updated = 0;
		$page    = 1;
		do {
			$query = array(
				'status'   => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'limit'    => $batch,
				'page'     => $page,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'paginate' => true,
			);
			if ( $ids ) {
				$query['include'] = $ids;
			}
			$result = wc_get_products( $query );
			foreach ( $result->products as $product ) {
				++$done;
				if ( Specs::fill_product( $product, $overwrite ) ) {
					++$updated;
				}
			}
			++$page;
		} while ( $page <= (int) $result->max_num_pages );

		\WP_CLI::success( sprintf( '%d products processed, %d updated.', $done, $updated ) );
	}
}
