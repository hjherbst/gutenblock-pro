/**
 * Flexible Heading Block – Gruppierte H1/H2 mit gestaltbaren Span-Teilen.
 *
 * @package GutenBlockPro
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import Save from './save';
import './editor.scss';

registerBlockType( 'gutenblock-pro/flexible-heading', {
	title: __( 'Flexible Heading', 'gutenblock-pro' ),
	description: __(
		'Gruppierte Überschrift (H1/H2) mit individuell gestaltbaren Textteilen.',
		'gutenblock-pro'
	),
	category: 'text',
	keywords: [
		__( 'Überschrift', 'gutenblock-pro' ),
		__( 'Heading', 'gutenblock-pro' ),
		__( 'Kreativ', 'gutenblock-pro' ),
	],
	icon: 'heading',
	supports: {
		align: [ 'wide', 'full' ],
		html: false,
		anchor: true,
		typography: {
			lineHeight: true,
			letterSpacing: true,
			__experimentalFontFamily: true,
		},
		color: {
			text: false,
			background: false,
		},
		spacing: {
			margin: true,
			padding: true,
		},
	},
	attributes: {
		level: {
			type: 'number',
			default: 1,
		},
		textAlign: {
			type: 'string',
		},
	},
	edit: Edit,
	save: Save,
} );
