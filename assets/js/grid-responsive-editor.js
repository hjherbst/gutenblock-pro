( function( wp ) {
	var addFilter        = wp.hooks.addFilter;
	var createElement    = wp.element.createElement;
	var Fragment         = wp.element.Fragment;
	var createHOC        = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody        = wp.components.PanelBody;
	var RangeControl     = wp.components.RangeControl;
	var __               = wp.i18n.__;

	var ATTR_TABLET = 'gbpGridColsTablet';
	var ATTR_MOBILE = 'gbpGridColsMobile';

	// Register attributes on core/group
	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/grid-responsive-attr',
		function( settings, name ) {
			if ( name !== 'core/group' ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			attrs[ ATTR_TABLET ] = { type: 'integer', default: 0 };
			attrs[ ATTR_MOBILE ] = { type: 'integer', default: 0 };
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	// Add sidebar panel only when layout.type === 'grid'
	addFilter(
		'editor.BlockEdit',
		'gutenblock-pro/grid-responsive-control',
		createHOC( function( BlockEdit ) {
			return function( props ) {
				if ( props.name !== 'core/group' ) {
					return createElement( BlockEdit, props );
				}

				var layout = ( props.attributes && props.attributes.layout ) || {};
				if ( layout.type !== 'grid' ) {
					return createElement( BlockEdit, props );
				}

				var colsTablet = props.attributes[ ATTR_TABLET ] || 0;
				var colsMobile = props.attributes[ ATTR_MOBILE ] || 0;

				return createElement(
					Fragment,
					{},
					createElement( BlockEdit, props ),
					createElement(
						InspectorControls,
						{},
						createElement(
							PanelBody,
							{ title: __( 'Responsive Spalten', 'gutenblock-pro' ), initialOpen: true },
							createElement( 'p', {
								style: { fontSize: '12px', color: '#757575', margin: '0 0 12px' }
							}, __( 'Desktop-Spalten über das Standard-Grid-Layout einstellen. Hier Tablet/Mobile überschreiben (0 = unverändert).', 'gutenblock-pro' ) ),
							createElement( RangeControl, {
								label: __( 'Spalten Tablet (≤781px)', 'gutenblock-pro' ),
								value: colsTablet,
								onChange: function( val ) {
									props.setAttributes( { [ ATTR_TABLET ]: val || 0 } );
								},
								min: 0,
								max: 6,
								allowReset: true,
								resetFallbackValue: 0,
								help: colsTablet === 0 ? __( 'Nicht überschrieben', 'gutenblock-pro' ) : '',
							} ),
							createElement( RangeControl, {
								label: __( 'Spalten Mobile (≤600px)', 'gutenblock-pro' ),
								value: colsMobile,
								onChange: function( val ) {
									props.setAttributes( { [ ATTR_MOBILE ]: val || 0 } );
								},
								min: 0,
								max: 6,
								allowReset: true,
								resetFallbackValue: 0,
								help: colsMobile === 0 ? __( 'Nicht überschrieben', 'gutenblock-pro' ) : '',
							} )
						)
					)
				);
			};
		}, 'withGridResponsiveControl' )
	);
} )( window.wp );
