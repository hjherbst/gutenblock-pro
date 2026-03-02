( function( wp ) {
	var addFilter = wp.hooks.addFilter;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	var ATTR_NAME = 'stackMode';
	var ATTR_LINKBOX = 'linkbox';

	var options = [
		{ label: __( 'Standard (mobil stapeln)', 'gutenblock-pro' ), value: '' },
		{ label: __( 'Immer stapeln', 'gutenblock-pro' ), value: 'always' },
		{ label: __( 'Reverse stapeln', 'gutenblock-pro' ), value: 'reverse' },
	];

	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/media-text-stack-attr',
		function( settings, name ) {
			if ( name !== 'core/media-text' ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			attrs[ ATTR_NAME ] = { type: 'string', default: '' };
			attrs[ ATTR_LINKBOX ] = { type: 'boolean', default: false };
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	var withStackControl = createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'core/media-text' ) {
				return createElement( BlockEdit, props );
			}
			var value = props.attributes[ ATTR_NAME ] || '';
			return createElement(
				Fragment,
				{},
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					{},
					createElement(
						PanelBody,
						{ title: __( 'Stapel-Verhalten', 'gutenblock-pro' ), initialOpen: false },
						createElement( SelectControl, {
							label: __( 'Layout', 'gutenblock-pro' ),
							value: value,
							options: options,
							onChange: function( val ) {
								props.setAttributes( { [ ATTR_NAME ]: val } );
							},
						} ),
						createElement( ToggleControl, {
							label: __( 'Linkbox', 'gutenblock-pro' ),
							help: __( 'Gesamten Bereich als Link nutzen (falls ein Button mit Link vorhanden ist).', 'gutenblock-pro' ),
							checked: props.attributes[ ATTR_LINKBOX ] || false,
							onChange: function( checked ) {
								props.setAttributes( { [ ATTR_LINKBOX ]: checked } );
							},
						} )
					)
				)
			);
		};
	}, 'withMediaTextStackControl' );

	addFilter( 'editor.BlockEdit', 'gutenblock-pro/media-text-stack-control', withStackControl );

	// Klasse im Editor anwenden, damit die Vorschau das Stapel-Layout zeigt
	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/media-text-stack-editor-class',
		createHigherOrderComponent( function( BlockListBlock ) {
			return function( props ) {
				if ( props.name !== 'core/media-text' ) {
					return createElement( BlockListBlock, props );
				}
				var mode = ( props.attributes && props.attributes[ ATTR_NAME ] ) || '';
				if ( mode !== 'always' && mode !== 'reverse' ) {
					return createElement( BlockListBlock, props );
				}
				var extra = 'gbp-stack-' + mode;
				var existing = props.className || '';
				var merged = existing ? existing + ' ' + extra : extra;
				return createElement(
					BlockListBlock,
					Object.assign( {}, props, { className: merged } )
				);
			};
		}, 'withMediaTextStackEditorClass' )
	);
} )( window.wp );
