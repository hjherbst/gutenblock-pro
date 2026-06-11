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
import Edit from './edit';
import './editor.scss';

const i18n = window.gutenblockProContactFormEditor || {};
const keywords = i18n.lang === 'de'
	? [ 'Kontakt', 'Formular', 'E-Mail' ]
	: [ 'Contact', 'Form', 'Email' ];

registerBlockType( 'gutenblock-pro/contact-form', {
	apiVersion: 2,
	title: i18n.blockTitle || 'Contact Form',
	description: i18n.blockDescription || '',
	category: 'design',
	keywords,
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
