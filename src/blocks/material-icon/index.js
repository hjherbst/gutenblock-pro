/**
 * Material Icon Block – Registrierung
 *
 * @package GutenBlockPro
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import './editor.scss';

registerBlockType( 'gutenblock-pro/material-icon', {
	title: __( 'Material Icon', 'gutenblock-pro' ),
	description: __( 'Google Material Symbol als Inline-SVG mit Suche, Größe und Farbe.', 'gutenblock-pro' ),
	category: 'media',
	keywords: [ __( 'Icon', 'gutenblock-pro' ), __( 'SVG', 'gutenblock-pro' ), __( 'Material', 'gutenblock-pro' ) ],
	icon: 'admin-customizer',
	attributes: {
		icon: { type: 'string', default: '' },
		style: { type: 'string', default: 'outlined' },
		weight: { type: 'number', default: 400 },
		size: { type: 'number', default: 48 },
		color: { type: 'string', default: '#000000' },
		colorSlug: { type: 'string', default: '' },
		svgPath: { type: 'string', default: '' },
		iconSource: { type: 'string', default: 'material' },
		customSvgId: { type: 'number', default: 0 },
		customSvgMarkup: { type: 'string', default: '' },
	},
	supports: {
		html: false,
		align: true,
		spacing: {
			margin: false,
			padding: false,
		},
	},
	edit: Edit,
	save: () => null,
} );
