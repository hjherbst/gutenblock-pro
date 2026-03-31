/**
 * Mobile Ausrichtung – Editor-Erweiterung
 * Fügt Group, Row und Stack einen Toggle "Links ausrichten (Mobil)" hinzu.
 */
( function ( wp ) {
	'use strict';

	var addFilter           = wp.hooks.addFilter;
	var createElement       = wp.element.createElement;
	var Fragment            = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls   = wp.blockEditor.InspectorControls;
	var PanelBody           = wp.components.PanelBody;
	var ToggleControl       = wp.components.ToggleControl;
	var __                  = wp.i18n.__;

	var ATTR            = 'mobileAlignLeft';
	var CLASS_NAME      = 'gbp-mobile-left';
	var SUPPORTED       = [ 'core/group', 'core/row', 'core/stack' ];

	// 1. Attribut registrieren
	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/mobile-align-attr',
		function ( settings, name ) {
			if ( SUPPORTED.indexOf( name ) === -1 ) return settings;
			var attrs = Object.assign( {}, settings.attributes );
			attrs[ ATTR ] = { type: 'boolean', default: false };
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	// 2. Toggle in der Seitenleiste
	addFilter(
		'editor.BlockEdit',
		'gutenblock-pro/mobile-align-control',
		createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( SUPPORTED.indexOf( props.name ) === -1 ) {
					return createElement( BlockEdit, props );
				}

				var isEnabled = !! props.attributes[ ATTR ];

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
								title: __( 'Mobile Ausrichtung', 'gutenblock-pro' ),
								initialOpen: false,
							},
							createElement( ToggleControl, {
								label: __( 'Links ausrichten (Mobil)', 'gutenblock-pro' ),
								help: __( 'Zentrierte oder rechtsbündige Inhalte werden auf Mobilgeräten (≤ 781 px) linksbündig ausgerichtet.', 'gutenblock-pro' ),
								checked: isEnabled,
								onChange: function ( val ) {
									props.setAttributes( { [ ATTR ]: val } );
								},
							} )
						)
					)
				);
			};
		}, 'withMobileAlignControl' )
	);

	// 3. Klasse im Editor-Canvas anzeigen
	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/mobile-align-editor-class',
		createHigherOrderComponent( function ( BlockListBlock ) {
			return function ( props ) {
				if ( SUPPORTED.indexOf( props.name ) === -1 ) {
					return createElement( BlockListBlock, props );
				}
				if ( ! props.attributes[ ATTR ] ) {
					return createElement( BlockListBlock, props );
				}
				var existing = props.className || '';
				var merged   = existing ? existing + ' ' + CLASS_NAME : CLASS_NAME;
				return createElement(
					BlockListBlock,
					Object.assign( {}, props, { className: merged } )
				);
			};
		}, 'withMobileAlignEditorClass' )
	);

} )( window.wp );
