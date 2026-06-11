/**
 * Contact Form Block – editor preview + inspector controls.
 *
 * @package GutenBlockPro
 */

import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

const DEFAULTS = {
	consent: __(
		'Ich stimme zu, dass meine Angaben zur Bearbeitung meiner Anfrage verarbeitet werden.',
		'gutenblock-pro'
	),
	submit: __( 'Absenden', 'gutenblock-pro' ),
	success: __( 'Vielen Dank! Ihre Nachricht wurde gesendet.', 'gutenblock-pro' ),
};

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
				<PanelBody title={ __( 'Felder', 'gutenblock-pro' ) } initialOpen={ true }>
					<ToggleControl
						label={ __( 'Name anzeigen', 'gutenblock-pro' ) }
						checked={ showName }
						onChange={ ( v ) => setAttributes( { showName: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showName && (
						<ToggleControl
							label={ __( 'Name als Pflichtfeld', 'gutenblock-pro' ) }
							checked={ nameRequired }
							onChange={ ( v ) => setAttributes( { nameRequired: v } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<ToggleControl
						label={ __( 'Telefon anzeigen', 'gutenblock-pro' ) }
						checked={ showPhone }
						onChange={ ( v ) => setAttributes( { showPhone: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showPhone && (
						<ToggleControl
							label={ __( 'Telefon als Pflichtfeld', 'gutenblock-pro' ) }
							checked={ phoneRequired }
							onChange={ ( v ) => setAttributes( { phoneRequired: v } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<p style={ { fontSize: '12px', color: '#757575', marginTop: '8px' } }>
						{ __( 'E-Mail und Nachricht sind immer aktiv und Pflichtfelder.', 'gutenblock-pro' ) }
					</p>
				</PanelBody>

				<PanelBody title={ __( 'Einwilligung', 'gutenblock-pro' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Checkbox anzeigen', 'gutenblock-pro' ) }
						checked={ showConsent }
						onChange={ ( v ) => setAttributes( { showConsent: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showConsent && (
						<TextareaControl
							label={ __( 'Checkbox-Text (HTML erlaubt)', 'gutenblock-pro' ) }
							value={ consentHtml }
							onChange={ ( v ) => setAttributes( { consentHtml: v } ) }
							placeholder={ DEFAULTS.consent }
							help={ __(
								'HTML ist erlaubt, z. B. ein Link: <a href="/datenschutz">Datenschutz</a>. Gerade Anführungszeichen verwenden.',
								'gutenblock-pro'
							) }
							rows={ 5 }
							__nextHasNoMarginBottom
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Texte', 'gutenblock-pro' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Button-Text', 'gutenblock-pro' ) }
						value={ submitLabel }
						onChange={ ( v ) => setAttributes( { submitLabel: v } ) }
						placeholder={ DEFAULTS.submit }
						help={ __( 'Leer = automatisch je nach Sprache.', 'gutenblock-pro' ) }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __( 'Erfolgsmeldung', 'gutenblock-pro' ) }
						value={ successMessage }
						onChange={ ( v ) => setAttributes( { successMessage: v } ) }
						placeholder={ DEFAULTS.success }
						help={ __( 'Ersetzt das Formular nach dem Absenden. Leer = Standard.', 'gutenblock-pro' ) }
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
									{ __( 'Name', 'gutenblock-pro' ) }
									{ nameRequired && reqMark }
								</label>
								<input type="text" disabled />
							</p>
						) }
						<p className="gbp-cf-field">
							<label>
								{ __( 'E-Mail-Adresse', 'gutenblock-pro' ) }
								{ reqMark }
							</label>
							<input type="email" disabled />
						</p>
						{ showPhone && (
							<p className="gbp-cf-field">
								<label>
									{ __( 'Telefonnummer', 'gutenblock-pro' ) }
									{ phoneRequired && reqMark }
								</label>
								<input type="tel" disabled />
							</p>
						) }
					</div>

					<div className="gbp-cf-row">
						<p className="gbp-cf-field">
							<label>
								{ __( 'Nachricht', 'gutenblock-pro' ) }
								{ reqMark }
							</label>
							<textarea rows={ 6 } disabled />
						</p>
					</div>

					{ showConsent && (
						<div className="gbp-cf-row gbp-cf-consent">
							<label className="gbp-cf-consent-label">
								<input type="checkbox" disabled />
								<span
									className="gbp-cf-consent-text"
									dangerouslySetInnerHTML={ {
										__html: consentHtml || DEFAULTS.consent,
									} }
								/>
							</label>
						</div>
					) }

					<div className="gbp-cf-row gbp-cf-submit-row">
						<button type="button" className="gbp-cf-submit wp-element-button" disabled>
							{ submitLabel || DEFAULTS.submit }
						</button>
					</div>
				</div>
			</div>
		</>
	);
}
