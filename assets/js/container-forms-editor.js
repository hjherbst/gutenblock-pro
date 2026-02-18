( function( wp ) {
	var addFilter = wp.hooks.addFilter;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;

	var ATTR_NAME = 'containerForm';
	var CLASS_PREFIX = 'has-container-form-';

	var options = [
		{ label: '—', value: '' },
		{ label: 'Welle oben', value: 'wave-top' },
		{ label: 'Welle unten', value: 'wave-bottom' },
		{ label: 'Welle oben + unten', value: 'wave-both' },
		{ label: 'Diagonale oben', value: 'diagonal-top' },
		{ label: 'Diagonale unten', value: 'diagonal-bottom' },
		{ label: 'Diagonale oben + unten', value: 'diagonal-both' },
		{ label: 'Bogen oben', value: 'curve-top' },
		{ label: 'Bogen unten', value: 'curve-bottom' },
		{ label: 'Bogen oben + unten', value: 'curve-both' },
		{ label: 'Spitze oben', value: 'arrow-top' },
		{ label: 'Spitze unten', value: 'arrow-bottom' },
		{ label: 'Spitze oben + unten', value: 'arrow-both' },
		{ label: 'Zickzack oben', value: 'zigzag-top' },
		{ label: 'Zickzack unten', value: 'zigzag-bottom' },
		{ label: 'Zickzack oben + unten', value: 'zigzag-both' },
		{ label: 'Asymmetric unten', value: 'asymmetric-bottom' },
		{ label: 'Layered Wave unten', value: 'layered-bottom' },
	];

	addFilter(
		'blocks.registerBlockType',
		'gutenblock-pro/container-form-attr',
		function( settings, name ) {
			if ( name !== 'core/group' ) {
				return settings;
			}
			var attrs = Object.assign( {}, settings.attributes );
			attrs[ ATTR_NAME ] = { type: 'string', default: '' };
			return Object.assign( {}, settings, { attributes: attrs } );
		}
	);

	var withControl = createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'core/group' ) {
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
						{ title: 'Container-Form', initialOpen: false },
						createElement( SelectControl, {
							label: 'Form',
							value: value,
							options: options,
							onChange: function( val ) {
								var attrs = {};
								attrs[ ATTR_NAME ] = val;
								props.setAttributes( attrs );
							},
						} )
					)
				)
			);
		};
	}, 'withContainerFormControl' );

	addFilter( 'editor.BlockEdit', 'gutenblock-pro/container-form-control', withControl );

	addFilter(
		'editor.BlockListBlock',
		'gutenblock-pro/container-form-editor-class',
		createHigherOrderComponent( function( BlockListBlock ) {
			return function( props ) {
				if ( props.name !== 'core/group' || ! props.attributes[ ATTR_NAME ] ) {
					return createElement( BlockListBlock, props );
				}
				var extra = CLASS_PREFIX + props.attributes[ ATTR_NAME ];
				var existing = props.className || '';
				var merged = existing ? existing + ' ' + extra : extra;
				return createElement(
					BlockListBlock,
					Object.assign( {}, props, { className: merged } )
				);
			};
		}, 'withContainerFormEditorClass' )
	);
} )( window.wp );
