/**
 * Cost per round block, editor side. Plain JavaScript (no build step); the block is rendered on the server.
 *
 * @package GunsNPricesFirearmSpecs
 */
( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var NAME = 'gunsnprices/cost-per-round';

	function Empty() {
		return el(
			'p',
			{ className: 'gnpfs-block-placeholder' },
			__( 'Cost per round: enter the box price and rounds per box (or a title with the round count) in the block settings.', 'gunsnprices-firearm-specs' )
		);
	}

	blocks.registerBlockType( NAME, {
		edit: function ( props ) {
			var a = props.attributes;
			var blockProps = blockEditor.useBlockProps();

			function field( key, label, help, type ) {
				return el( components.TextControl, {
					key: key,
					label: label,
					help: help,
					type: type || 'text',
					value: a[ key ] || '',
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: function ( value ) {
						var change = {};
						change[ key ] = value;
						props.setAttributes( change );
					}
				} );
			}

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Cost per round', 'gunsnprices-firearm-specs' ) },
						field( 'price', __( 'Box price', 'gunsnprices-firearm-specs' ), __( 'For example 21.99', 'gunsnprices-firearm-specs' ) ),
						field( 'rounds', __( 'Rounds per box', 'gunsnprices-firearm-specs' ), '', 'number' ),
						field( 'title', __( 'Or a product title', 'gunsnprices-firearm-specs' ), __( 'Rounds per box are read from it, for example "Federal 9mm 115gr FMJ 50 Rounds".', 'gunsnprices-firearm-specs' ) ),
						field( 'id', __( 'Or a WooCommerce product ID', 'gunsnprices-firearm-specs' ), __( 'Uses the product price and its saved rounds per box.', 'gunsnprices-firearm-specs' ), 'number' ),
						field( 'decimals', __( 'Decimals', 'gunsnprices-firearm-specs' ), '', 'number' ),
						field( 'currency', __( 'Currency symbol', 'gunsnprices-firearm-specs' ), __( 'Used when WooCommerce is not active.', 'gunsnprices-firearm-specs' ) )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: NAME,
						attributes: a,
						EmptyResponsePlaceholder: Empty
					} )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
