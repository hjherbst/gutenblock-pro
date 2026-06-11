/**
 * Contact Form Block – editor preview + inspector controls.
 *
 * @package GutenBlockPro
 */

import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

const i18n = window.gutenblockProContactFormEditor || {};

export default function Edit( { attributes, setAttributes } ) {
	const {
		showName,
		nameRequired,
		showPhone,
		phoneRequired,
		showConsent,
		consentHtml,
		submitLabel,
		successMessage,
		formId,
	} = attributes;

	// Assign a stable id once so the rendered form has unique field ids.
	useEffect( () => {
		if ( ! formId ) {
			setAttributes( {
				formId: 'gbpcf-' + Math.random().toString( 36 ).slice( 2, 10 ),
			} );
		}
	}, [] );

	const blockProps = useBlockProps( { className: 'gbp-contact-form-wrap' } );

	const cols = 1 + ( showName ? 1 : 0 ) + ( showPhone ? 1 : 0 );
	const reqMark = <span className="gbp-cf-req"> *</span>;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ i18n.panelFields } initialOpen={ true }>
					<ToggleControl
						label={ i18n.showName }
						checked={ showName }
						onChange={ ( v ) => setAttributes( { showName: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showName && (
						<ToggleControl
							label={ i18n.nameRequired }
							checked={ nameRequired }
							onChange={ ( v ) => setAttributes( { nameRequired: v } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<ToggleControl
						label={ i18n.showPhone }
						checked={ showPhone }
						onChange={ ( v ) => setAttributes( { showPhone: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showPhone && (
						<ToggleControl
							label={ i18n.phoneRequired }
							checked={ phoneRequired }
							onChange={ ( v ) => setAttributes( { phoneRequired: v } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<p style={ { fontSize: '12px', color: '#757575', marginTop: '8px' } }>
						{ i18n.fieldsNote }
					</p>
				</PanelBody>

				<PanelBody title={ i18n.panelConsent } initialOpen={ false }>
					<ToggleControl
						label={ i18n.showConsent }
						checked={ showConsent }
						onChange={ ( v ) => setAttributes( { showConsent: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showConsent && (
						<p style={ { fontSize: '12px', color: '#757575', marginTop: '8px' } }>
							{ i18n.consentEditHint }
						</p>
					) }
				</PanelBody>

				<PanelBody title={ i18n.panelTexts } initialOpen={ false }>
					<TextControl
						label={ i18n.submitLabel }
						value={ submitLabel }
						onChange={ ( v ) => setAttributes( { submitLabel: v } ) }
						placeholder={ i18n.submit }
						help={ i18n.submitHelp }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ i18n.successLabel }
						value={ successMessage }
						onChange={ ( v ) => setAttributes( { successMessage: v } ) }
						placeholder={ i18n.success }
						help={ i18n.successHelp }
						rows={ 3 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="gbp-contact-form">
					<div
						className="gbp-cf-row gbp-cf-row--fields"
						data-cols={ cols }
					>
						{ showName && (
							<p className="gbp-cf-field">
								<label>
									{ i18n.name }
									{ nameRequired && reqMark }
								</label>
								<input type="text" disabled />
							</p>
						) }
						<p className="gbp-cf-field">
							<label>
								{ i18n.email }
								{ reqMark }
							</label>
							<input type="email" disabled />
						</p>
						{ showPhone && (
							<p className="gbp-cf-field">
								<label>
									{ i18n.phone }
									{ phoneRequired && reqMark }
								</label>
								<input type="tel" disabled />
							</p>
						) }
					</div>

					<div className="gbp-cf-row">
						<p className="gbp-cf-field">
							<label>
								{ i18n.message }
								{ reqMark }
							</label>
							<textarea rows={ 6 } disabled />
						</p>
					</div>

					{ showConsent && (
						<div className="gbp-cf-row gbp-cf-consent">
							<label className="gbp-cf-consent-label">
								<input type="checkbox" disabled />
								<RichText
									tagName="span"
									className="gbp-cf-consent-text"
									value={ consentHtml }
									onChange={ ( v ) => setAttributes( { consentHtml: v } ) }
									placeholder={ i18n.consent }
									allowedFormats={ [ 'core/link' ] }
								/>
							</label>
						</div>
					) }

					<div className="gbp-cf-row gbp-cf-submit-row wp-block-buttons">
						<div className="wp-block-button">
							<button type="button" className="gbp-cf-submit wp-block-button__link wp-element-button" disabled>
								{ submitLabel || i18n.submit }
							</button>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
