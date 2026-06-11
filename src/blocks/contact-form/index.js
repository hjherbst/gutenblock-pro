/**
 * Contact Form Block – native, configurable contact form.
 *
 * Dynamic block: the frontend HTML is rendered by PHP (render_callback in
 * inc/class-contact-form.php). The editor shows a static preview with the
 * field/consent/button settings in the inspector.
 *
 * @package GutenBlockPro
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import './editor.scss';

registerBlockType( 'gutenblock-pro/contact-form', {
	apiVersion: 2,
	title: __( 'Kontaktformular', 'gutenblock-pro' ),
	description: __(
		'Schlankes, sicheres Kontaktformular mit konfigurierbaren Feldern, Spam-Schutz und E-Mail-Versand.',
		'gutenblock-pro'
	),
	category: 'design',
	keywords: [
		__( 'Kontakt', 'gutenblock-pro' ),
		__( 'Formular', 'gutenblock-pro' ),
		__( 'Contact', 'gutenblock-pro' ),
		__( 'Form', 'gutenblock-pro' ),
		__( 'Email', 'gutenblock-pro' ),
	],
	icon: 'email',
	supports: {
		align: [ 'wide', 'full' ],
		html: false,
	},
	attributes: {
		showName: { type: 'boolean', default: true },
		nameRequired: { type: 'boolean', default: false },
		showPhone: { type: 'boolean', default: true },
		phoneRequired: { type: 'boolean', default: false },
		showConsent: { type: 'boolean', default: true },
		consentHtml: { type: 'string', default: '' },
		submitLabel: { type: 'string', default: '' },
		successMessage: { type: 'string', default: '' },
		formId: { type: 'string', default: '' },
	},
	edit: Edit,
	save: () => null,
} );
