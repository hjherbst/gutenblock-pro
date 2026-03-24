/**
 * Heading Part Block – Inline-Span innerhalb eines Flexible Heading.
 *
 * @package GutenBlockPro
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import Save from './save';

registerBlockType( 'gutenblock-pro/heading-part', {
	title: __( 'Heading Part', 'gutenblock-pro' ),
	description: __(
		'Inline-Textteil innerhalb eines Flexible Heading – mit eigener Typografie und Farbe.',
		'gutenblock-pro'
	),
	category: 'text',
	icon: 'editor-textcolor',
	parent: [ 'gutenblock-pro/flexible-heading' ],
	supports: {
		html: false,
		reusable: false,
		color: {
			text: true,
			background: false,
			gradients: false,
		},
		typography: {
			fontSize: true,
			lineHeight: true,
			fontFamily: true,
			__experimentalFontFamily: true,
			fontStyle: true,
			fontWeight: true,
			__experimentalFontWeight: true,
			textTransform: true,
			letterSpacing: true,
			textDecoration: true,
		},
		spacing: {
			padding: false,
			margin: false,
		},
	},
	attributes: {
		content: {
			type: 'string',
			source: 'html',
			selector: 'span',
			default: '',
		},
	},
	edit: Edit,
	save: Save,
} );
