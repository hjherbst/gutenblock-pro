/**
 * GutenBlock Pro – Reiter (Tabs) block, editor side.
 *
 * Authored in plain wp.element.createElement (no JSX / no build step) to match
 * the rest of the plugin's editor scripts.
 */
( function ( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var createBlock       = wp.blocks.createBlock;
	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
	var InnerBlocks       = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useSelect         = wp.data.useSelect;
	var useDispatch       = wp.data.useDispatch;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var Button            = wp.components.Button;
	var __                = wp.i18n.__;

	var TAB_BLOCK = 'gutenblock-pro/tab';

	function defaultTabTitle( index ) {
		return __( 'Reiter', 'gutenblock-pro' ) + ' ' + ( index + 1 );
	}

	// ── Parent block: gutenblock-pro/tabs ─────────────────────────────────────
	registerBlockType( 'gutenblock-pro/tabs', {
		edit: function ( props ) {
			var clientId      = props.clientId;
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;
			var activeTab     = attributes.activeTab || 0;

			var childBlocks = useSelect(
				function ( select ) {
					return select( 'core/block-editor' ).getBlocks( clientId );
				},
				[ clientId ]
			);

			var editorDispatch = useDispatch( 'core/block-editor' );

			// Keep activeTab within range when tabs get removed.
			if ( childBlocks.length > 0 && activeTab > childBlocks.length - 1 ) {
				activeTab = childBlocks.length - 1;
			}

			var blockProps = useBlockProps( {
				className: 'gbp-tabs is-editor' + ( attributes.showNextOnDesktop ? ' has-next-desktop' : '' )
			} );

			var innerBlocksProps = useInnerBlocksProps(
				{ className: 'gbp-tabs__panels' },
				{
					allowedBlocks: [ TAB_BLOCK ],
					orientation: 'vertical',
					renderAppender: false,
					templateLock: false,
					template: [
						[ TAB_BLOCK, { title: __( 'Reiter 1', 'gutenblock-pro' ) }, [ [ 'core/paragraph', { placeholder: __( 'Inhalt des Reiters …', 'gutenblock-pro' ) } ] ] ],
						[ TAB_BLOCK, { title: __( 'Reiter 2', 'gutenblock-pro' ) } ],
						[ TAB_BLOCK, { title: __( 'Reiter 3', 'gutenblock-pro' ) } ]
					]
				}
			);

			function selectTab( index ) {
				setAttributes( { activeTab: index } );
			}

			function addTab() {
				var index = childBlocks.length;
				var block = createBlock(
					TAB_BLOCK,
					{ title: defaultTabTitle( index ) },
					[ createBlock( 'core/paragraph' ) ]
				);
				editorDispatch.insertBlock( block, index, clientId, false );
				setAttributes( { activeTab: index } );
			}

			var tabButtons = childBlocks.map( function ( block, index ) {
				var title = ( block.attributes && block.attributes.title ) || defaultTabTitle( index );
				return el(
					Button,
					{
						key: block.clientId,
						className: 'gbp-tabs__tab' + ( index === activeTab ? ' is-active' : '' ),
						variant: index === activeTab ? 'primary' : 'tertiary',
						onClick: function () {
							selectTab( index );
						}
					},
					title
				);
			} );

			var nav = el(
				'div',
				{ className: 'gbp-tabs__nav', role: 'tablist' },
				tabButtons,
				el( Button, {
					className: 'gbp-tabs__add',
					icon: 'plus',
					label: __( 'Reiter hinzufügen', 'gutenblock-pro' ),
					showTooltip: true,
					onClick: addTab
				} )
			);

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Reiter-Einstellungen', 'gutenblock-pro' ), initialOpen: true },
						el( ToggleControl, {
							label: __( '„Nächster Reiter“-Link auch auf Desktop', 'gutenblock-pro' ),
							help: __( 'Der Link unter dem Inhalt wird auf Mobilgeräten immer angezeigt. Aktiviere dies, um ihn zusätzlich auf dem Desktop einzublenden.', 'gutenblock-pro' ),
							checked: !! attributes.showNextOnDesktop,
							onChange: function ( value ) {
								setAttributes( { showNextOnDesktop: !! value } );
							}
						} )
					)
				),
				el(
					'div',
					blockProps,
					nav,
					el( 'div', innerBlocksProps )
				)
			);
		},
		save: function () {
			// Dynamic block – PHP builds the markup. We only persist the inner
			// tab blocks so their content survives in the post.
			return el( InnerBlocks.Content );
		}
	} );

	// ── Child block: gutenblock-pro/tab ───────────────────────────────────────
	registerBlockType( TAB_BLOCK, {
		edit: function ( props ) {
			var clientId      = props.clientId;
			var context       = props.context || {};
			var activeTab     = context[ 'gutenblock-pro/activeTab' ] || 0;

			var index = useSelect(
				function ( select ) {
					return select( 'core/block-editor' ).getBlockIndex( clientId );
				},
				[ clientId ]
			);

			var isActive = index === activeTab;

			var blockProps = useBlockProps( {
				className: 'gbp-tabs__panel' + ( isActive ? ' is-active' : '' ),
				// Keep inactive panels in the DOM but hidden, like the frontend.
				style: isActive ? {} : { display: 'none' }
			} );

			var innerBlocksProps = useInnerBlocksProps( blockProps, {
				templateLock: false,
				template: [ [ 'core/paragraph', { placeholder: __( 'Inhalt des Reiters …', 'gutenblock-pro' ) } ] ]
			} );

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Reiter', 'gutenblock-pro' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Reiter-Titel', 'gutenblock-pro' ),
							value: props.attributes.title || '',
							placeholder: defaultTabTitle( index ),
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							}
						} )
					)
				),
				el( 'div', innerBlocksProps )
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		}
	} );
} )( window.wp );
