/**
 * Format type: Marker (text highlighter)
 *
 * @package GutenBlockPro
 */

import { useState, useCallback } from '@wordpress/element';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { registerFormatType, applyFormat, removeFormat } from '@wordpress/rich-text';
import { ColorPickerPopover, DEFAULT_COLOR } from './popover';

const FORMAT_NAME = 'gutenblock-pro/marker';
const ALLOWED_BLOCKS = [ 'core/heading', 'core/paragraph' ];

function parseStyleColor( styleStr ) {
	if ( ! styleStr || typeof styleStr !== 'string' ) return DEFAULT_COLOR;
	const m = styleStr.match( /--gb-fmt-color:\s*([^;]+)/ );
	return m ? m[1].trim() : DEFAULT_COLOR;
}

function getActiveFormatAttrs( value ) {
	if ( ! value?.formats ) return null;
	for ( let i = value.start; i < value.end; i++ ) {
		const formats = value.formats[ i ];
		if ( ! formats ) continue;
		const f = formats.find( ( x ) => x.type === FORMAT_NAME );
		if ( f && f.attributes ) return f.attributes;
	}
	return null;
}

function MarkerFormatEdit( { isActive, value, onChange } ) {
	const [ showPopover, setShowPopover ] = useState( false );
	const selectedBlockName = useSelect( ( select ) => {
		const sel = select( 'core/block-editor' ).getSelectedBlock();
		return sel ? sel.name : null;
	}, [] );

	const handleApply = useCallback(
		( color ) => {
			// Store as CSS variable; CSS will use color-mix for gradient
			const style = `--gb-fmt-color:${ color };`;
			onChange(
				applyFormat( value, {
					type: FORMAT_NAME,
					attributes: { style },
				}, value.start, value.end )
			);
			setShowPopover( false );
		},
		[ value, onChange ]
	);

	const hasTextFormats = typeof window !== 'undefined' && window.gutenblockProConfig?.hasTextFormats;
	if ( ! hasTextFormats || ! selectedBlockName || ! ALLOWED_BLOCKS.includes( selectedBlockName ) ) {
		return null;
	}

	const activeAttrs = getActiveFormatAttrs( value );
	const defaultColor = activeAttrs?.style ? parseStyleColor( activeAttrs.style ) : DEFAULT_COLOR;

	return (
		<>
			<RichTextToolbarButton
				icon="admin-appearance"
				title={ isActive ? __( 'Marker entfernen', 'gutenblock-pro' ) : __( 'Marker (Textmarker)', 'gutenblock-pro' ) }
				onClick={ () => {
					if ( isActive ) {
						onChange( removeFormat( value, FORMAT_NAME ) );
						setShowPopover( false );
					} else {
						setShowPopover( ( v ) => ! v );
					}
				} }
				isActive={ isActive }
			/>
			{ showPopover && (
				<ColorPickerPopover
					onApply={ ( color ) => handleApply( color ) }
					defaultColor={ defaultColor }
				/>
			) }
		</>
	);
}

registerFormatType( FORMAT_NAME, {
	title: __( 'Marker', 'gutenblock-pro' ),
	tagName: 'span',
	className: 'gb-fmt-marker',
	attributes: {
		style: 'style',
	},
	edit: MarkerFormatEdit,
} );
