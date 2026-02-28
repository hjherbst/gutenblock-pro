( function( wp ) {
	var addFilter = wp.hooks.addFilter;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var __ = wp.i18n.__;

	var ATTRS = {
		horizontalScroll: 'horizontalScroll',
		hScrollDesktop: 'hScrollDesktop',
		hScrollTablet: 'hScrollTablet',
		hScrollMobile: 'hScrollMobile',
		hScrollPeekDesktop: 'hScrollPeekDesktop',
		hScrollPeekTablet: 'hScrollPeekTablet',
		hScrollPeekMobile: 'hScrollPeekMobile',
		hScrollDots: 'hScrollDots',
		hScrollArrows: 'hScrollArrows',
	};

	var defaultAttrs = {
		horizontalScroll: { type: 'boolean', default: false },
		hScrollDesktop: { type: 'integer', default: 3 },
		hScrollTablet: { type: 'integer', default: 2 },
		hScrollMobile: { type: 'integer', default: 1 },
		hScrollPeekDesktop: { type: 'integer', default: 40 },
		hScrollPeekTablet: { type: 'integer', default: 30 },
		hScrollPeekMobile: { type: 'integer', default: 0 },
		hScrollDots: { type: 'boolean', default: true },
		hScrollArrows: { type: 'boolean', default: true },
		hScrollInfinite: { type: 'boolean', default: false },
	};

	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/horizontal-scroll-attr',
		function( settings, name ) {
			if ( name !== 'core/columns' ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			Object.keys( defaultAttrs ).forEach( function( key ) {
				attrs[ key ] = defaultAttrs[ key ];
			} );
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	var withControl = createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'core/columns' ) {
				return createElement( BlockEdit, props );
			}
			var attrs = props.attributes || {};
			var active = attrs.horizontalScroll || false;

			return createElement(
				Fragment,
				{},
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					{},
					createElement(
						PanelBody,
						{ title: __( 'Horizontal Scroll', 'gutenblock-pro' ), initialOpen: false },
						createElement( ToggleControl, {
							label: __( 'Horizontal Scroll aktivieren', 'gutenblock-pro' ),
							checked: active,
							onChange: function( val ) {
								props.setAttributes( { horizontalScroll: val } );
							},
						} ),
						active && createElement( Fragment, {},
							createElement( RangeControl, {
								label: __( 'Sichtbare Spalten (Desktop)', 'gutenblock-pro' ),
								value: attrs.hScrollDesktop ?? 3,
								onChange: function( val ) { props.setAttributes( { hScrollDesktop: val } ); },
								min: 1,
								max: 6,
							} ),
							createElement( RangeControl, {
								label: __( 'Sichtbare Spalten (Tablet)', 'gutenblock-pro' ),
								value: attrs.hScrollTablet ?? 2,
								onChange: function( val ) { props.setAttributes( { hScrollTablet: val } ); },
								min: 1,
								max: 6,
							} ),
							createElement( RangeControl, {
								label: __( 'Sichtbare Spalten (Mobile)', 'gutenblock-pro' ),
								value: attrs.hScrollMobile ?? 1,
								onChange: function( val ) { props.setAttributes( { hScrollMobile: val } ); },
								min: 1,
								max: 6,
							} ),
							createElement( RangeControl, {
								label: __( 'Peek in px (Desktop)', 'gutenblock-pro' ),
								value: attrs.hScrollPeekDesktop ?? 40,
								onChange: function( val ) { props.setAttributes( { hScrollPeekDesktop: val } ); },
								min: 0,
								max: 200,
							} ),
							createElement( RangeControl, {
								label: __( 'Peek in px (Tablet)', 'gutenblock-pro' ),
								value: attrs.hScrollPeekTablet ?? 30,
								onChange: function( val ) { props.setAttributes( { hScrollPeekTablet: val } ); },
								min: 0,
								max: 200,
							} ),
							createElement( RangeControl, {
								label: __( 'Peek in px (Mobile)', 'gutenblock-pro' ),
								value: attrs.hScrollPeekMobile ?? 0,
								onChange: function( val ) { props.setAttributes( { hScrollPeekMobile: val } ); },
								min: 0,
								max: 200,
							} ),
							createElement( ToggleControl, {
								label: __( 'Dots anzeigen', 'gutenblock-pro' ),
								checked: attrs.hScrollDots !== false,
								onChange: function( val ) { props.setAttributes( { hScrollDots: val } ); },
							} ),
							createElement( ToggleControl, {
								label: __( 'Pfeile anzeigen', 'gutenblock-pro' ),
								checked: attrs.hScrollArrows !== false,
								onChange: function( val ) { props.setAttributes( { hScrollArrows: val } ); },
							} ),
							createElement( ToggleControl, {
								label: __( 'Infinite Scroll', 'gutenblock-pro' ),
								checked: attrs.hScrollInfinite || false,
								onChange: function( val ) { props.setAttributes( { hScrollInfinite: val } ); },
								help: __( 'Scrollen looped endlos vor und zurück', 'gutenblock-pro' ),
							} )
						)
					)
				)
			);
		};
	}, 'withHorizontalScrollControl' );

	addFilter( 'editor.BlockEdit', 'gutenblock-pro/horizontal-scroll-control', withControl );

	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/horizontal-scroll-editor-class',
		createHigherOrderComponent( function( BlockListBlock ) {
			return function( props ) {
				if ( props.name !== 'core/columns' || ! props.attributes.horizontalScroll ) {
					return createElement( BlockListBlock, props );
				}
				var attrs = props.attributes || {};
				var extra = 'has-horizontal-scroll';
				var existing = props.className || '';
				var merged = existing ? existing + ' ' + extra : extra;
				var blockEl = createElement(
					BlockListBlock,
					Object.assign( {}, props, { className: merged } )
				);
				var showDots = attrs.hScrollDots !== false;
				var showArrows = attrs.hScrollArrows !== false;
				if ( ! showDots && ! showArrows ) {
					return blockEl;
				}
				var colCount = ( props.block && props.block.innerBlocks && props.block.innerBlocks.length ) || 0;
				var desktop = attrs.hScrollDesktop ?? 3;
				var dotCount = colCount > 0 ? Math.max( 1, Math.ceil( colCount / desktop ) ) : 1;
				var navParts = [];
				if ( showDots ) {
					var dotButtons = [];
					for ( var i = 0; i < dotCount; i++ ) {
						dotButtons.push( createElement( 'button', {
							key: i,
							type: 'button',
							className: i === 0 ? 'is-active' : '',
							'aria-label': 'Seite ' + ( i + 1 )
						} ) );
					}
					navParts.push( createElement( 'div', { key: 'dots', className: 'gb-hscroll-dots', role: 'tablist' }, dotButtons ) );
				}
				if ( showArrows ) {
					navParts.push( createElement( 'div', { key: 'arrows', className: 'gb-hscroll-arrows' },
						createElement( 'button', { type: 'button', className: 'gb-hscroll-prev', 'aria-label': __( 'Zurück', 'gutenblock-pro' ) }, '←' ),
						createElement( 'button', { type: 'button', className: 'gb-hscroll-next', 'aria-label': __( 'Weiter', 'gutenblock-pro' ) }, '→' )
					) );
				}
				var align = attrs.align || '';
				var wrapperClass = 'gb-hscroll-wrapper';
				if ( 'wide' === align ) wrapperClass += ' alignwide';
				else if ( 'full' === align ) wrapperClass += ' alignfull';
				return createElement( 'div', { className: wrapperClass },
					blockEl,
					createElement( 'div', { className: 'gb-hscroll-nav' }, navParts )
				);
			};
		}, 'withHorizontalScrollEditorClass' )
	);
} )( window.wp );
