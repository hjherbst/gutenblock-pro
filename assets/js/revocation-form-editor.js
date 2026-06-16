/**
 * Revocation Form Block – editor registration (standalone, no build step).
 *
 * Dynamic block: the frontend HTML is rendered by PHP (render_callback in
 * inc/class-revocation-form.php). The editor shows a static preview plus the
 * field/wording/text/consent/delivery settings in the inspector.
 */
( function( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var RichText = wp.blockEditor.RichText;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var useEffect = wp.element.useEffect;

	var i18n = window.gutenblockProRevocationFormEditor || {};
	var itemsLabels = i18n.itemsLabels || {};

	var keywords = i18n.lang === 'de'
		? [ 'Widerruf', 'Widerrufsformular', 'Rückgabe', 'Vertrag' ]
		: [ 'Withdrawal', 'Revocation', 'Return', 'Contract' ];

	function help( text ) {
		return el( 'span', { className: 'gbp-rf-help' }, text );
	}

	function Edit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;

		useEffect( function() {
			if ( ! a.formId ) {
				set( { formId: 'gbprf-' + Math.random().toString( 36 ).slice( 2, 10 ) } );
			}
		}, [] );

		var blockProps = useBlockProps( { className: 'gbp-revocation-form-wrap' } );
		var reqMark = el( 'span', { className: 'gbp-rf-req' }, ' *' );
		var itemsLabel = itemsLabels[ a.contractType ] || itemsLabels.neutral || '';
		var cols = 1 + ( a.showName ? 1 : 0 ) + ( a.showOrderNumber ? 1 : 0 );

		var inspector = el( InspectorControls, {},
			el( PanelBody, { title: i18n.panelFields, initialOpen: true },
				el( ToggleControl, {
					label: i18n.showName,
					checked: !! a.showName,
					onChange: function( v ) { set( { showName: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				a.showName && el( ToggleControl, {
					label: i18n.nameRequired,
					checked: !! a.nameRequired,
					onChange: function( v ) { set( { nameRequired: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				el( ToggleControl, {
					label: i18n.showOrderNumber,
					checked: !! a.showOrderNumber,
					onChange: function( v ) { set( { showOrderNumber: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				a.showOrderNumber && el( ToggleControl, {
					label: i18n.orderNumberRequired,
					checked: !! a.orderNumberRequired,
					onChange: function( v ) { set( { orderNumberRequired: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				el( ToggleControl, {
					label: i18n.showDates,
					checked: !! a.showDates,
					onChange: function( v ) { set( { showDates: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				el( ToggleControl, {
					label: i18n.showAddress,
					checked: !! a.showAddress,
					onChange: function( v ) { set( { showAddress: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				el( ToggleControl, {
					label: i18n.showReason,
					checked: !! a.showReason,
					onChange: function( v ) { set( { showReason: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				el( 'p', { style: { fontSize: '12px', color: '#757575', marginTop: '8px' } }, i18n.fieldsNote )
			),
			el( PanelBody, { title: i18n.panelWording, initialOpen: false },
				el( SelectControl, {
					label: i18n.contractType,
					value: a.contractType || 'neutral',
					options: [
						{ label: i18n.optNeutral, value: 'neutral' },
						{ label: i18n.optGoods, value: 'goods' },
						{ label: i18n.optDigital, value: 'digital' },
						{ label: i18n.optService, value: 'service' },
					],
					onChange: function( v ) { set( { contractType: v } ); },
					help: i18n.contractTypeHelp,
					__nextHasNoMarginBottom: true,
				} )
			),
			el( PanelBody, { title: i18n.panelTexts, initialOpen: false },
				el( TextControl, {
					label: i18n.submitLabel,
					value: a.submitLabel || '',
					onChange: function( v ) { set( { submitLabel: v } ); },
					placeholder: i18n.submit,
					help: i18n.submitHelp,
					__nextHasNoMarginBottom: true,
				} ),
				el( TextareaControl, {
					label: i18n.successLabel,
					value: a.successMessage || '',
					onChange: function( v ) { set( { successMessage: v } ); },
					placeholder: i18n.success,
					help: i18n.successHelp,
					rows: 3,
					__nextHasNoMarginBottom: true,
				} )
			),
			el( PanelBody, { title: i18n.panelConsent, initialOpen: false },
				el( ToggleControl, {
					label: i18n.showConsent,
					checked: !! a.showConsent,
					onChange: function( v ) { set( { showConsent: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				a.showConsent && el( 'p', { style: { fontSize: '12px', color: '#757575', marginTop: '8px' } }, i18n.consentEditHint )
			),
			el( PanelBody, { title: i18n.panelDelivery, initialOpen: false },
				el( ToggleControl, {
					label: i18n.sendConfirmation,
					checked: a.sendConfirmation !== false,
					onChange: function( v ) { set( { sendConfirmation: v } ); },
					__nextHasNoMarginBottom: true,
				} ),
				el( 'p', { style: { fontSize: '12px', color: '#757575', marginTop: '8px' } }, i18n.deliveryNote )
			)
		);

		// Editor preview (mirrors the PHP render markup).
		var topFields = [];
		if ( a.showName ) {
			topFields.push( el( 'p', { className: 'gbp-rf-field', key: 'name' },
				el( 'label', {}, i18n.name, a.nameRequired && reqMark ),
				el( 'input', { type: 'text', disabled: true } )
			) );
		}
		topFields.push( el( 'p', { className: 'gbp-rf-field', key: 'email' },
			el( 'label', {}, i18n.email, ' ', reqMark ),
			el( 'input', { type: 'email', disabled: true } ),
			help( i18n.emailHelp )
		) );
		if ( a.showOrderNumber ) {
			topFields.push( el( 'p', { className: 'gbp-rf-field', key: 'order' },
				el( 'label', {}, i18n.orderNumber, a.orderNumberRequired && reqMark ),
				el( 'input', { type: 'text', disabled: true } ),
				help( i18n.orderNumberHelp )
			) );
		}

		var preview = el( 'div', { className: 'gbp-revocation-form' },
			el( 'div', { className: 'gbp-rf-row gbp-rf-row--fields', 'data-cols': cols }, topFields ),
			a.showDates && el( 'div', { className: 'gbp-rf-row gbp-rf-row--fields', 'data-cols': 2 },
				el( 'p', { className: 'gbp-rf-field' },
					el( 'label', {}, i18n.orderDate ),
					el( 'input', { type: 'date', disabled: true } )
				),
				el( 'p', { className: 'gbp-rf-field' },
					el( 'label', {}, i18n.receiptDate ),
					el( 'input', { type: 'date', disabled: true } )
				)
			),
			el( 'div', { className: 'gbp-rf-row' },
				el( 'p', { className: 'gbp-rf-field' },
					el( 'label', {}, itemsLabel ),
					el( 'textarea', { rows: 3, disabled: true } ),
					help( i18n.itemsHelp )
				)
			),
			a.showAddress && el( 'div', { className: 'gbp-rf-row' },
				el( 'p', { className: 'gbp-rf-field' },
					el( 'label', {}, i18n.address ),
					el( 'textarea', { rows: 2, disabled: true } )
				)
			),
			a.showReason && el( 'div', { className: 'gbp-rf-row' },
				el( 'p', { className: 'gbp-rf-field' },
					el( 'label', {}, i18n.reason ),
					el( 'textarea', { rows: 4, disabled: true } ),
					help( i18n.reasonHelp )
				)
			),
			a.showConsent && el( 'div', { className: 'gbp-rf-row gbp-rf-consent' },
				el( 'label', { className: 'gbp-rf-consent-label' },
					el( 'input', { type: 'checkbox', disabled: true } ),
					el( RichText, {
						tagName: 'span',
						className: 'gbp-rf-consent-text',
						value: a.consentHtml,
						onChange: function( v ) { set( { consentHtml: v } ); },
						placeholder: i18n.consent,
						allowedFormats: [ 'core/link' ],
					} )
				)
			),
			el( 'div', { className: 'gbp-rf-row gbp-rf-submit-row wp-block-buttons' },
				el( 'div', { className: 'wp-block-button' },
					el( 'button', { type: 'button', className: 'gbp-rf-submit wp-block-button__link wp-element-button', disabled: true },
						a.submitLabel || i18n.submit
					)
				)
			)
		);

		return el( Fragment, {},
			inspector,
			el( 'div', blockProps, preview )
		);
	}

	registerBlockType( 'gutenblock-pro/revocation-form', {
		apiVersion: 2,
		title: i18n.blockTitle || 'Withdrawal Form',
		description: i18n.blockDescription || '',
		category: 'design',
		keywords: keywords,
		icon: 'undo',
		supports: {
			align: [ 'wide', 'full' ],
			html: false,
		},
		attributes: {
			showName: { type: 'boolean', default: true },
			nameRequired: { type: 'boolean', default: false },
			showOrderNumber: { type: 'boolean', default: true },
			orderNumberRequired: { type: 'boolean', default: true },
			showDates: { type: 'boolean', default: false },
			showAddress: { type: 'boolean', default: false },
			showReason: { type: 'boolean', default: true },
			showConsent: { type: 'boolean', default: true },
			contractType: { type: 'string', default: 'neutral' },
			consentHtml: { type: 'string', default: '' },
			submitLabel: { type: 'string', default: '' },
			successMessage: { type: 'string', default: '' },
			sendConfirmation: { type: 'boolean', default: true },
			formId: { type: 'string', default: '' },
		},
		edit: Edit,
		save: function() { return null; },
	} );
} )( window.wp );
