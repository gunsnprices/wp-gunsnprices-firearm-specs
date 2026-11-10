<?php
/**
 * Tools → Firearm Specs: parse titles and normalize caliber names in bulk, without WooCommerce.
 *
 * @package GunsNPricesFirearmSpecs
 */

namespace GunsNPricesFirearmSpecs;

use GunsNPricesFirearmSpecs\Vendor\GunsNPrices\Parser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The caliber normalizer page.
 */
final class Tools_Page {

	const PAGE      = 'gnpfs-tools';
	const MAX_LINES = 500;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Adds the page under Tools.
	 */
	public static function menu() {
		$hook = add_management_page(
			__( 'Firearm Specs: caliber normalizer', 'gunsnprices-firearm-specs' ),
			__( 'Firearm Specs', 'gunsnprices-firearm-specs' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		add_action( 'admin_print_styles-' . $hook, array( __CLASS__, 'styles' ) );
	}

	/**
	 * Page styles.
	 */
	public static function styles() {
		wp_enqueue_style( 'gnpfs-admin', GNPFS_URL . 'assets/admin.css', array(), GNPFS_VERSION );
	}

	/**
	 * Submitted lines, or an empty array.
	 *
	 * @return array{0: string[], 1: string}
	 */
	private static function submitted() {
		if ( ! isset( $_POST['gnpfs_tools_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gnpfs_tools_nonce'] ) ), 'gnpfs_tools' ) ) {
			return array( array(), 'titles' );
		}
		$mode  = isset( $_POST['gnpfs_mode'] ) && 'calibers' === sanitize_key( wp_unslash( $_POST['gnpfs_mode'] ) ) ? 'calibers' : 'titles';
		$text  = isset( $_POST['gnpfs_lines'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gnpfs_lines'] ) ) : '';
		$lines = array();
		foreach ( preg_split( '/\R/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$lines[] = mb_substr( $line, 0, 300 );
			}
			if ( count( $lines ) >= self::MAX_LINES ) {
				break;
			}
		}
		return array( $lines, $mode );
	}

	/**
	 * Result rows.
	 *
	 * @param string[] $lines Input lines.
	 * @param string   $mode  titles|calibers.
	 * @return array{0: string[], 1: array<int, string[]>} Header and rows (plain text).
	 */
	private static function results( array $lines, $mode ) {
		$types = self::caliber_types();
		if ( 'calibers' === $mode ) {
			$head = array( __( 'Input', 'gunsnprices-firearm-specs' ), __( 'Caliber', 'gunsnprices-firearm-specs' ), __( 'ID', 'gunsnprices-firearm-specs' ), __( 'Type', 'gunsnprices-firearm-specs' ) );
			$rows = array();
			foreach ( $lines as $line ) {
				$c      = Parser::normalizeCaliber( $line );
				$rows[] = array( $line, $c ? $c['name'] : '', $c ? $c['id'] : '', $c && isset( $types[ $c['type'] ] ) ? $types[ $c['type'] ] : '' );
			}
			return array( $head, $rows );
		}

		$head = array(
			__( 'Title', 'gunsnprices-firearm-specs' ),
			__( 'Product type', 'gunsnprices-firearm-specs' ),
			__( 'Caliber', 'gunsnprices-firearm-specs' ),
			__( 'Brand', 'gunsnprices-firearm-specs' ),
			__( 'Model', 'gunsnprices-firearm-specs' ),
			__( 'Magazine capacity', 'gunsnprices-firearm-specs' ),
			__( 'Barrel length (in)', 'gunsnprices-firearm-specs' ),
			__( 'Rounds per box', 'gunsnprices-firearm-specs' ),
		);
		$rows = array();
		foreach ( $lines as $line ) {
			$s      = Specs::parse( $line );
			$rows[] = array( $line, Specs::display_value( 'kind', $s['kind'] ), $s['caliber'], $s['brand'], $s['model'], $s['capacity'], $s['barrel_length'], $s['rounds'] );
		}
		return array( $head, $rows );
	}

	/**
	 * Caliber type labels.
	 *
	 * @return array<string, string>
	 */
	private static function caliber_types() {
		return array(
			'handgun'      => __( 'Handgun', 'gunsnprices-firearm-specs' ),
			'rifle'        => __( 'Rifle', 'gunsnprices-firearm-specs' ),
			'rimfire'      => __( 'Rimfire', 'gunsnprices-firearm-specs' ),
			'shotgun'      => __( 'Shotgun', 'gunsnprices-firearm-specs' ),
			'airgun'       => __( 'Airgun', 'gunsnprices-firearm-specs' ),
			'muzzleloader' => __( 'Muzzleloader', 'gunsnprices-firearm-specs' ),
		);
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		list( $lines, $mode ) = self::submitted();
		$example              = "Glock 19 Gen 5 9mm 4.02\" 15rd Pistol\nFederal American Eagle 9mm 115gr FMJ 50 Rounds\nRuger 10/22 Carbine .22 LR 18.5\" 10+1\nMossberg 590 Persuader 12/20 BL/SY 3\"";

		echo '<div class="wrap gnpfs-tools"><h1>' . esc_html__( 'Caliber normalizer', 'gunsnprices-firearm-specs' ) . '</h1>';
		echo '<p>' . esc_html__( 'Paste product titles or caliber names, one per line (up to 500). Titles give caliber, brand and model, magazine capacity, barrel length and rounds per box; caliber names ("9mm Luger", "5.56 NATO", "12 GA") are matched to one standard name. Everything runs on your server.', 'gunsnprices-firearm-specs' ) . '</p>';

		echo '<form method="post">';
		wp_nonce_field( 'gnpfs_tools', 'gnpfs_tools_nonce' );
		echo '<p><label class="screen-reader-text" for="gnpfs-lines">' . esc_html__( 'Lines', 'gunsnprices-firearm-specs' ) . '</label>';
		echo '<textarea id="gnpfs-lines" name="gnpfs_lines" rows="8" class="large-text code" placeholder="' . esc_attr( $example ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea></p>';
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'What the lines are', 'gunsnprices-firearm-specs' ) . '</legend>';
		printf( '<label><input type="radio" name="gnpfs_mode" value="titles" %s> %s</label> &nbsp; ', checked( $mode, 'titles', false ), esc_html__( 'Product titles', 'gunsnprices-firearm-specs' ) );
		printf( '<label><input type="radio" name="gnpfs_mode" value="calibers" %s> %s</label>', checked( $mode, 'calibers', false ), esc_html__( 'Caliber names', 'gunsnprices-firearm-specs' ) );
		echo '</fieldset>';
		submit_button( __( 'Parse', 'gunsnprices-firearm-specs' ) );
		echo '</form>';

		if ( $lines ) {
			list( $head, $rows ) = self::results( $lines, $mode );
			echo '<h2>' . esc_html__( 'Results', 'gunsnprices-firearm-specs' ) . '</h2>';
			echo '<table class="widefat striped gnpfs-results"><thead><tr>';
			foreach ( $head as $cell ) {
				echo '<th scope="col">' . esc_html( $cell ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			$tsv = array( implode( "\t", $head ) );
			foreach ( $rows as $row ) {
				echo '<tr>';
				foreach ( $row as $cell ) {
					echo '<td>' . esc_html( $cell ) . '</td>';
				}
				echo '</tr>';
				$tsv[] = implode( "\t", str_replace( array( "\t", "\n" ), ' ', $row ) );
			}
			echo '</tbody></table>';
			echo '<p><label for="gnpfs-tsv">' . esc_html__( 'Tab-separated, ready to paste into a spreadsheet:', 'gunsnprices-firearm-specs' ) . '</label></p>';
			echo '<textarea id="gnpfs-tsv" rows="6" class="large-text code" readonly>' . esc_textarea( implode( "\n", $tsv ) ) . '</textarea>';
		}

		$calibers = Parser::listCalibers();
		$types    = self::caliber_types();
		echo '<h2>' . esc_html__( 'Caliber list', 'gunsnprices-firearm-specs' ) . '</h2><details><summary>';
		/* translators: %d: number of calibers. */
		echo esc_html( sprintf( __( 'Show all %d calibers', 'gunsnprices-firearm-specs' ), count( $calibers ) ) );
		echo '</summary><table class="widefat striped gnpfs-calibers"><thead><tr><th scope="col">' . esc_html__( 'Caliber', 'gunsnprices-firearm-specs' ) . '</th><th scope="col">' . esc_html__( 'ID', 'gunsnprices-firearm-specs' ) . '</th><th scope="col">' . esc_html__( 'Type', 'gunsnprices-firearm-specs' ) . '</th></tr></thead><tbody>';
		foreach ( $calibers as $c ) {
			echo '<tr><td>' . esc_html( $c['name'] ) . '</td><td><code>' . esc_html( $c['id'] ) . '</code></td><td>' . esc_html( isset( $types[ $c['type'] ] ) ? $types[ $c['type'] ] : $c['type'] ) . '</td></tr>';
		}
		echo '</tbody></table></details>';

		echo '<p class="description">';
		printf(
			/* translators: %s: link to gunsnprices.com */
			esc_html__( 'Model dictionaries and caliber list: %s, CC BY 4.0.', 'gunsnprices-firearm-specs' ),
			'<a href="https://gunsnprices.com" target="_blank" rel="noopener">GunsNPrices</a>'
		);
		echo '</p></div>';
	}
}
