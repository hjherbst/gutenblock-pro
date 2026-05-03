( function( wp ) {
	var addFilter          = wp.hooks.addFilter;
	var createElement      = wp.element.createElement;
	var Fragment           = wp.element.Fragment;
	var createHOC          = wp.compose.createHigherOrderComponent;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var PanelBody          = wp.components.PanelBody;
	var RangeControl       = wp.components.RangeControl;
	var __                 = wp.i18n.__;

	var ATTR_TABLET    = 'gbpGridColsTablet';
	var ATTR_MOBILE    = 'gbpGridColsMobile';
	var ATTR_ALIGN_TOP = 'gbpGridAlignTop';

	// ── Attribute registrieren ────────────────────────────────────────────────
	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/grid-responsive-attr',
		function( settings, name ) {
			if ( name !== 'core/group' ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			attrs[ ATTR_TABLET ]    = { type: 'integer', default: 0 };
			attrs[ ATTR_MOBILE ]    = { type: 'integer', default: 0 };
			attrs[ ATTR_ALIGN_TOP ] = { type: 'boolean', default: false };
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	// ── Inspector-Controls (Sidebar) ──────────────────────────────────────────
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
						// ── Responsive Spalten ────────────────────────────────────────────
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
								min: 0, max: 6, allowReset: true, resetFallbackValue: 0,
								help: colsTablet === 0 ? __( 'Nicht überschrieben', 'gutenblock-pro' ) : '',
							} ),
							createElement( RangeControl, {
								label: __( 'Spalten Mobile (≤600px)', 'gutenblock-pro' ),
								value: colsMobile,
								onChange: function( val ) {
									props.setAttributes( { [ ATTR_MOBILE ]: val || 0 } );
								},
								min: 0, max: 6, allowReset: true, resetFallbackValue: 0,
								help: colsMobile === 0 ? __( 'Nicht überschrieben', 'gutenblock-pro' ) : '',
							} )
						)
					)
				);
			};
		}, 'withGridResponsiveControl' )
	);

	// ── Editor-Vorschau: align-items: start im Block-Wrapper ─────────────────
	// Der PHP render_block-Filter greift nur im Frontend.
	// Für den Editor (inkl. FSE) wenden wir den Style via BlockListBlock an.
	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/grid-align-top-editor',
		createHOC( function( BlockListBlock ) {
			return function( props ) {
				if ( props.name !== 'core/group' ) {
					return createElement( BlockListBlock, props );
				}
				var layout = ( props.attributes && props.attributes.layout ) || {};
				if ( layout.type !== 'grid' ) {
					return createElement( BlockListBlock, props );
				}
				if ( ! props.attributes[ ATTR_ALIGN_TOP ] ) {
					return createElement( BlockListBlock, props );
				}
				// Inline-Style auf den Block-Wrapper anwenden
				var wrapperProps = Object.assign(
					{},
					props.wrapperProps,
					{
						style: Object.assign(
							{},
							( props.wrapperProps && props.wrapperProps.style ) || {},
							{ alignItems: 'start' }
						),
					}
				);
				return createElement( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
			};
		}, 'withGridAlignTopEditor' )
	);

} )( window.wp );
