<?php
/**
 * Bulk actions on Products and the "fill all products" batch on the settings page.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filling many products at once.
 */
final class Bulk {

	/** Products per AJAX batch. */
	const BATCH = 25;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'wp_ajax_gnpfs_fill_batch', array( __CLASS__, 'ajax_batch' ) );
	}

	/**
	 * Adds the bulk actions.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function actions( $actions ) {
		$actions['gnpfs_fill']   = __( 'Fill specs from title', 'gunsnprices-firearm-specs' );
		$actions['gnpfs_refill'] = __( 'Re-read specs from title (replace)', 'gunsnprices-firearm-specs' );
		return $actions;
	}

	/**
	 * Runs a bulk action. WordPress checks the bulk-posts nonce before calling this.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action.
	 * @param int[]  $post_ids    Selected products.
	 * @return string
	 */
	public static function handle( $redirect_to, $action, $post_ids ) {
		if ( 'gnpfs_fill' !== $action && 'gnpfs_refill' !== $action ) {
			return $redirect_to;
		}
		$updated = 0;
		$done    = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$product = wc_get_product( $post_id );
			if ( ! $product ) {
				continue;
			}
			++$done;
			if ( Specs::fill_product( $product, 'gnpfs_refill' === $action ) ) {
				++$updated;
			}
		}
		return add_query_arg(
			array(
				'gnpfs_done'    => $done,
				'gnpfs_updated' => $updated,
			),
			remove_query_arg( array( 'gnpfs_done', 'gnpfs_updated' ), $redirect_to )
		);
	}

	/**
	 * Result notice after a bulk action.
	 */
	public static function notice() {
		$screen = get_current_screen();
		// Only reads counts for a message; nothing is changed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $screen || 'edit-product' !== $screen->id || ! isset( $_GET['gnpfs_done'], $_GET['gnpfs_updated'] ) ) {
			return;
		}
		$done    = absint( $_GET['gnpfs_done'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = absint( $_GET['gnpfs_updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sprintf(
			/* translators: 1: products processed, 2: products whose specs changed. */
			_n( 'Specs: %1$d product processed, %2$d updated.', 'Specs: %1$d products processed, %2$d updated.', $done, 'gunsnprices-firearm-specs' ),
			$done,
			$updated
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * AJAX: fills one batch of products, oldest first.
	 */
	public static function ajax_batch() {
		check_ajax_referer( 'gnpfs_fill_batch', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( null, 403 );
		}
		$page      = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$overwrite = ! empty( $_POST['overwrite'] );

		$result = wc_get_products(
			array(
				'status'   => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'limit'    => self::BATCH,
				'page'     => $page,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'paginate' => true,
			)
		);

		$updated = 0;
		foreach ( $result->products as $product ) {
			if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
				continue;
			}
			if ( Specs::fill_product( $product, $overwrite ) ) {
				++$updated;
			}
		}

		wp_send_json_success(
			array(
				'processed' => count( $result->products ),
				'updated'   => $updated,
				'total'     => (int) $result->total,
				'more'      => $page < (int) $result->max_num_pages,
				'next'      => $page + 1,
			)
		);
	}
}
