/**
 * Format type: Freihand-Unterstreichen (freehand SVG underline)
 *
 * @package GutenBlockPro
 */

import { useState, useCallback } from '@wordpress/element';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { registerFormatType, applyFormat, removeFormat } from '@wordpress/rich-text';
import { ColorPickerPopover, DEFAULT_COLOR, DEFAULT_UNDERLINE_WIDTH } from './popover';

const FORMAT_NAME = 'gutenblock-pro/underline';
const ALLOWED_BLOCKS = [ 'core/heading', 'core/paragraph' ];

// SVG path for wavy underline (one variant; we can add more via data-variant later)
function buildUnderlineSvgUrl( color, width ) {
	const safeColor = typeof color === 'string' ? color : DEFAULT_COLOR;
	const safeWidth = typeof width === 'number' && ! isNaN( width ) ? Math.max( 2, Math.min( 10, width ) ) : DEFAULT_UNDERLINE_WIDTH;
	const svg = `<svg viewBox="0 0 300 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 15 Q150 0 295 15" stroke="${ safeColor }" stroke-width="${ safeWidth }" fill="none" stroke-linecap="round"/></svg>`;
	return `url("data:image/svg+xml,${ encodeURIComponent( svg ) }")`;
}

function getActiveFormatAttrs( value ) {
	if ( ! value || ! Array.isArray( value.formats ) || typeof value.start !== 'number' || typeof value.end !== 'number' ) {
		return null;
	}
	const start = Math.max( 0, value.start );
	const end = Math.min( value.end, value.formats.length );
	for ( let i = start; i < end; i++ ) {
		const formats = value.formats[ i ];
		if ( ! formats ) continue;
		const f = formats.find( ( x ) => x && x.type === FORMAT_NAME );
		if ( f && f.attributes ) return f.attributes;
	}
	return null;
}

function parseStyleBgImage( styleStr ) {
	if ( ! styleStr || typeof styleStr !== 'string' ) return null;
	const m = styleStr.match( /background-image:\s*url\(["']?data:image\/svg\+xml[^"']+["']?\)/ );
	return m ? m[0] : null;
}

function parseColorFromSvgUrl( urlStr ) {
	try {
		if ( ! urlStr || typeof urlStr !== 'string' ) return DEFAULT_COLOR;
		const decoded = decodeURIComponent( urlStr );
		const hexMatch = decoded.match( /stroke=["']([^"']+)["']/ );
		const color = hexMatch ? hexMatch[1] : DEFAULT_COLOR;
		return typeof color === 'string' ? color : DEFAULT_COLOR;
	} catch ( e ) {
		return DEFAULT_COLOR;
	}
}

function parseWidthFromSvgUrl( urlStr ) {
	try {
		if ( ! urlStr || typeof urlStr !== 'string' ) return DEFAULT_UNDERLINE_WIDTH;
		const decoded = decodeURIComponent( urlStr );
		const wMatch = decoded.match( /stroke-width=["'](\d+)["']/ );
		const n = wMatch ? parseInt( wMatch[1], 10 ) : DEFAULT_UNDERLINE_WIDTH;
		return typeof n === 'number' && ! isNaN( n ) ? Math.max( 2, Math.min( 10, n ) ) : DEFAULT_UNDERLINE_WIDTH;
	} catch ( e ) {
		return DEFAULT_UNDERLINE_WIDTH;
	}
	}

function UnderlineFormatEdit( { isActive, value, onChange } ) {
	const [ showPopover, setShowPopover ] = useState( false );
	const selectedBlockName = useSelect( ( select ) => {
		try {
			const sel = select( 'core/block-editor' ).getSelectedBlock();
			return sel ? sel.name : null;
		} catch ( e ) {
			return null;
		}
	}, [] );

	const handleApply = useCallback(
		( color, _variant, width ) => {
			setShowPopover( false );
			if ( ! value || typeof value !== 'object' || ! Array.isArray( value.formats ) || typeof value.text !== 'string' ) {
				return;
			}
			const start = Math.max( 0, Math.min( Number( value.start ), value.text.length ) );
			const end = Math.max( start, Math.min( Number( value.end ), value.text.length ) );
			if ( start >= end ) {
				return;
			}
			let styleStr;
			try {
				styleStr = `background-image: ${ buildUnderlineSvgUrl( color, width ) }; background-repeat: no-repeat; background-size: 100% 0.4em; background-position: 0 100%; padding-bottom: 0.15em;`;
			} catch ( e ) {
				return;
			}
			try {
				onChange(
					applyFormat( value, {
						type: FORMAT_NAME,
						attributes: { style: styleStr },
					}, start, end )
				);
			} catch ( err ) {
				// Block/selection state invalid – avoid crash
			}
		},
		[ value, onChange ]
	);

	const hasTextFormats = typeof window !== 'undefined' && window.gutenblockProConfig?.hasTextFormats;
	if ( ! hasTextFormats || ! selectedBlockName || ! ALLOWED_BLOCKS.includes( selectedBlockName ) ) {
		return null;
	}

	let defaultColor = DEFAULT_COLOR;
	let defaultWidth = DEFAULT_UNDERLINE_WIDTH;
	try {
		const activeAttrs = value ? getActiveFormatAttrs( value ) : null;
		const bgImage = activeAttrs?.style ? parseStyleBgImage( activeAttrs.style ) : null;
		if ( bgImage && activeAttrs?.style ) {
			defaultColor = parseColorFromSvgUrl( activeAttrs.style );
			defaultWidth = parseWidthFromSvgUrl( activeAttrs.style );
		}
	} catch ( e ) {
		// keep defaults
	}

	return (
		<>
			<RichTextToolbarButton
				icon="editor-underline"
				title={ isActive ? __( 'Freihand-Unterstreichung entfernen', 'gutenblock-pro' ) : __( 'Freihand unterstreichen', 'gutenblock-pro' ) }
				onClick={ () => {
					if ( isActive ) {
						onChange( removeFormat( value, FORMAT_NAME ) );
						setShowPopover( false );
					} else if ( value && typeof value === 'object' && Array.isArray( value.formats ) && typeof value.text === 'string' ) {
						setShowPopover( ( v ) => ! v );
					}
				} }
				isActive={ isActive }
			/>
			{ showPopover && value && Array.isArray( value.formats ) && typeof value.text === 'string' && (
				<ColorPickerPopover
					onApply={ handleApply }
					defaultColor={ defaultColor }
					defaultWidth={ defaultWidth }
					showWidth
					widthLabel={ __( 'Strichstärke', 'gutenblock-pro' ) }
				/>
			) }
		</>
	);
}

registerFormatType( FORMAT_NAME, {
	title: __( 'Freihand unterstreichen', 'gutenblock-pro' ),
	tagName: 'span',
	className: 'gb-fmt-underline',
	attributes: {
		style: 'style',
	},
	edit: UnderlineFormatEdit,
} );
