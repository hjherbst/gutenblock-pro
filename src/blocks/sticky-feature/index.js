/**
 * Sticky Feature Block – Section mit Sticky-Bild und scroll-synchronisierten Text-Items.
 *
 * @package GutenBlockPro
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import Save from './save';
import './editor.scss';

const defaultItems = [
	{ heading: '', subline: '', imageId: 0, imageUrl: '', imageAlt: '' },
];

registerBlockType( 'gutenblock-pro/sticky-feature', {
	title: __( 'Sticky Feature', 'gutenblock-pro' ),
	description: __(
		'Section mit Sticky-Bild und scroll-synchronisierten Text-Items (Überschrift + Subline).',
		'gutenblock-pro'
	),
	category: 'design',
	keywords: [
		__( 'Sticky', 'gutenblock-pro' ),
		__( 'Feature', 'gutenblock-pro' ),
		__( 'Section', 'gutenblock-pro' ),
	],
	icon: 'layout',
	attributes: {
		items: {
			type: 'array',
			default: defaultItems,
		},
		imagePosition: {
			type: 'string',
			default: 'right',
		},
		aspectRatio: {
			type: 'string',
			default: 'auto',
		},
	},
	supports: {
		align: [ 'wide', 'full' ],
		html: false,
	},
	edit: Edit,
	save: Save,
} );
