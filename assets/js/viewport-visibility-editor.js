( function( wp ) {
	var addFilter = wp.hooks.addFilter;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	var SUPPORTED = [ 'core/group', 'core/button', 'core/buttons' ];

	var defaultAttrs = {
		gbpHideDesktop: { type: 'boolean', default: false },
		gbpHideTablet: { type: 'boolean', default: false },
		gbpHideMobile: { type: 'boolean', default: false },
	};

	function isSupported( name ) {
		return SUPPORTED.indexOf( name ) !== -1;
	}

	// Register attributes on supported blocks.
	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/viewport-visibility-attr',
		function( settings, name ) {
			if ( ! isSupported( name ) ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			Object.keys( defaultAttrs ).forEach( function( key ) {
				if ( ! attrs[ key ] ) {
					attrs[ key ] = defaultAttrs[ key ];
				}
			} );
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	// Inspector panel.
	var withControl = createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( ! isSupported( props.name ) ) {
				return createElement( BlockEdit, props );
			}
			var attrs = props.attributes || {};

			return createElement(
				Fragment,
				{},
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					{},
					createElement(
						PanelBody,
						{ title: __( 'Viewport visibilty', 'gutenblock-pro' ), initialOpen: false },
						createElement(
							'p',
							{ style: { marginTop: 0, color: '#757575' } },
							__( 'Hide block on:', 'gutenblock-pro' )
						),
						createElement( ToggleControl, {
							label: __( 'Desktop', 'gutenblock-pro' ),
							checked: attrs.gbpHideDesktop || false,
							onChange: function( val ) { props.setAttributes( { gbpHideDesktop: val } ); },
						} ),
						createElement( ToggleControl, {
							label: __( 'Tablet', 'gutenblock-pro' ),
							checked: attrs.gbpHideTablet || false,
							onChange: function( val ) { props.setAttributes( { gbpHideTablet: val } ); },
						} ),
						createElement( ToggleControl, {
							label: __( 'Mobile', 'gutenblock-pro' ),
							checked: attrs.gbpHideMobile || false,
							onChange: function( val ) { props.setAttributes( { gbpHideMobile: val } ); },
						} )
					)
				)
			);
		};
	}, 'withViewportVisibilityControl' );

	addFilter( 'editor.BlockEdit', 'gutenblock-pro/viewport-visibility-control', withControl );

	// Editor indicator: keep block visible, add badge listing hidden viewports.
	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/viewport-visibility-indicator',
		createHigherOrderComponent( function( BlockListBlock ) {
			return function( props ) {
				if ( ! isSupported( props.name ) ) {
					return createElement( BlockListBlock, props );
				}
				var attrs = props.attributes || {};
				var hidden = [];
				if ( attrs.gbpHideDesktop ) { hidden.push( __( 'Desktop', 'gutenblock-pro' ) ); }
				if ( attrs.gbpHideTablet ) { hidden.push( __( 'Tablet', 'gutenblock-pro' ) ); }
				if ( attrs.gbpHideMobile ) { hidden.push( __( 'Mobile', 'gutenblock-pro' ) ); }
				if ( ! hidden.length ) {
					return createElement( BlockListBlock, props );
				}
				var existing = props.className || '';
				var className = existing ? existing + ' gbp-vv-has-rule' : 'gbp-vv-has-rule';
				var wrapperProps = Object.assign( {}, props.wrapperProps, {
					'data-gbp-vv': hidden.join( ', ' ),
				} );
				return createElement(
					BlockListBlock,
					Object.assign( {}, props, { className: className, wrapperProps: wrapperProps } )
				);
			};
		}, 'withViewportVisibilityIndicator' )
	);
} )( window.wp );
