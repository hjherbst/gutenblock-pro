/**
 * Ausrichtung – Editor-Erweiterung
 * Panel für Group, Row, Stack: mobil links & Raster-Inhalte zentrieren.
 */
( function ( wp ) {
	'use strict';

	var addFilter                    = wp.hooks.addFilter;
	var createElement                = wp.element.createElement;
	var Fragment                   = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls          = wp.blockEditor.InspectorControls;
	var PanelBody                  = wp.components.PanelBody;
	var ToggleControl              = wp.components.ToggleControl;
	var __                         = wp.i18n.__;

	var ATTR            = 'mobileAlignLeft';
	var CLASS_NAME      = 'gbp-mobile-left';
	var ATTR_GRID       = 'gridItemsCenter';
	var CLASS_GRID      = 'gbp-grid-items-center';
	var SUPPORTED       = [ 'core/group', 'core/row', 'core/stack' ];

	function isGridLayout( attributes ) {
		var layout = ( attributes && attributes.layout ) || {};
		return layout.type === 'grid';
	}

	// 1. Attribute registrieren
	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/mobile-align-attr',
		function ( settings, name ) {
			if ( SUPPORTED.indexOf( name ) === -1 ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			attrs[ ATTR ] = { type: 'boolean', default: false };
			attrs[ ATTR_GRID ] = { type: 'boolean', default: false };
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	// 2. Steuerung in der Seitenleiste
	addFilter(
		'editor.BlockEdit',
		'gutenblock-pro/mobile-align-control',
		createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( SUPPORTED.indexOf( props.name ) === -1 ) {
					return createElement( BlockEdit, props );
				}

				var mobileOn = !! props.attributes[ ATTR ];
				var gridOn   = !! props.attributes[ ATTR_GRID ];
				var showGrid = isGridLayout( props.attributes );

				var panelChildren = [
					createElement( ToggleControl, {
						key: 'mobile',
						label: __( 'Links ausrichten (Mobil)', 'gutenblock-pro' ),
						help: __(
							'Zentrierte oder rechtsbündige Inhalte werden auf Mobilgeräten (≤ 781 px) linksbündig ausgerichtet.',
							'gutenblock-pro'
						),
						checked: mobileOn,
						onChange: function ( val ) {
							var patch = {};
							patch[ ATTR ] = val;
							props.setAttributes( patch );
						},
					} ),
				];

				if ( showGrid ) {
					panelChildren.push(
						createElement( ToggleControl, {
							key: 'grid',
							label: __( 'Raster-Inhalte zentrieren', 'gutenblock-pro' ),
							help: __(
								'Zentriert die direkten Kindelemente im Raster horizontal und vertikal in ihrer Rasterzelle.',
								'gutenblock-pro'
							),
							checked: gridOn,
							onChange: function ( val ) {
								var patch = {};
								patch[ ATTR_GRID ] = val;
								props.setAttributes( patch );
							},
						} )
					);
				}

				return createElement(
					Fragment,
					{},
					createElement( BlockEdit, props ),
					createElement(
						InspectorControls,
						{},
						createElement(
							PanelBody,
							{
								title: __( 'Ausrichtung', 'gutenblock-pro' ),
								initialOpen: false,
							},
							panelChildren
						)
					)
				);
			};
		}, 'withMobileAlignControl' )
	);

	// 3. Klassen im Editor-Canvas
	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/mobile-align-editor-class',
		createHigherOrderComponent( function ( BlockListBlock ) {
			return function ( props ) {
				if ( SUPPORTED.indexOf( props.name ) === -1 ) {
					return createElement( BlockListBlock, props );
				}
				var extra = [];
				if ( props.attributes[ ATTR ] ) {
					extra.push( CLASS_NAME );
				}
				if ( props.attributes[ ATTR_GRID ] && isGridLayout( props.attributes ) ) {
					extra.push( CLASS_GRID );
				}
				if ( ! extra.length ) {
					return createElement( BlockListBlock, props );
				}
				var existing = props.className || '';
				var merged   = existing ? existing + ' ' + extra.join( ' ' ) : extra.join( ' ' );
				return createElement(
					BlockListBlock,
					Object.assign( {}, props, { className: merged } )
				);
			};
		}, 'withMobileAlignEditorClass' )
	);

} )( window.wp );
