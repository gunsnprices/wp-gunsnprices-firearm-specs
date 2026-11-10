<?php
/**
 * Removes the plugin's settings when it is deleted, and the specs saved on products if the site owner asked for it.
 * Product attributes and their terms are store data and are kept.
 *
 * @package GunsNPricesFirearmSpecs
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleans one site.
 */
function gnpfs_uninstall_site() {
	$settings = get_option( 'gnpfs_settings', array() );
	if ( is_array( $settings ) && ! empty( $settings['delete_data'] ) ) {
		$fields = array( 'kind', 'caliber', 'brand', 'model', 'mpn', 'capacity', 'barrel_length', 'rounds', 'parsed' );
		foreach ( $fields as $field ) {
			delete_post_meta_by_key( '_gnpfs_' . $field );
		}
	}
	delete_option( 'gnpfs_settings' );
}

if ( is_multisite() ) {
	$gnpfs_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $gnpfs_site_ids as $gnpfs_site_id ) {
		switch_to_blog( $gnpfs_site_id );
		gnpfs_uninstall_site();
		restore_current_blog();
	}
} else {
	gnpfs_uninstall_site();
}
