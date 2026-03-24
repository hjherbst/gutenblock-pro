/**
 * Format type: Sketch Highlight (hand-drawn oval circle around text)
 *
 * @package GutenBlockPro
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { registerFormatType, applyFormat, removeFormat } from '@wordpress/rich-text';
import { ColorPickerPopover, DEFAULT_COLOR, DEFAULT_CIRCLE_WIDTH } from './popover';

const FORMAT_NAME = 'gutenblock-pro/circle';
const ALLOWED_BLOCKS = [ 'core/heading', 'core/paragraph', 'gutenblock-pro/heading-part' ];

const VARIANT_OPTIONS = [
	{ value: '1', label: 'Oval' },
	{ value: '2', label: 'Unterstreichung 1' },
	{ value: '3', label: 'Unterstreichung 2' },
];

// SVG variants – paths and viewBoxes per variant.
// preserveAspectRatio="none" lets each shape scale freely to fit the text.
const SVG_VARIANTS = {
	1: {
		viewBox: '0 0 288 69',
		path: 'M73.4982 12.2704C49.5518 13.6803 1.84858 27.5981 2.00017 36.468C2.30176 54.1142 66.0252 67.3344 144.33 65.9961C222.636 64.6578 285.87 49.2678 285.568 31.6216C285.267 13.9753 221.543 0.755118 143.238 2.09341C111.855 2.62976 57.386 4.55751 39.5499 12.8506',
		// Circle wraps the whole text → full padding, full bg-size
		style: ( url ) => `background-image: ${ url }; background-repeat: no-repeat; background-size: 100% 100%; padding: 0.15em 0.4em;`,
	},
	2: {
		viewBox: '0 0 212 12',
		path: 'M6.8209 9.50743C-32.4804 -1.205 209.292 3.05965 208.618 4.93455',
		style: ( url ) => `background-image: ${ url }; background-repeat: no-repeat; background-size: 100% 0.35em; background-position: 0 100%; padding-bottom: 0.25em;`,
	},
	3: {
		viewBox: '0 0 216 12',
		path: 'M2.5 3.6043C182.5 1.6043 212.5 2.77097 205 3.6043L16.5 8.60449H213',
		style: ( url ) => `background-image: ${ url }; background-repeat: no-repeat; background-size: 100% 0.35em; background-position: 0 100%; padding-bottom: 0.25em;`,
	},
};

function buildSketchSvgUrl( color, strokeWidth, variant ) {
	const safeColor = typeof color === 'string' ? color : DEFAULT_COLOR;
	const safeWidth = typeof strokeWidth === 'number' && ! isNaN( strokeWidth )
		? Math.max( 1, Math.min( 8, strokeWidth ) )
		: DEFAULT_CIRCLE_WIDTH;
	const v = SVG_VARIANTS[ variant ] || SVG_VARIANTS[ 1 ];
	const svg = `<svg viewBox="${ v.viewBox }" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="${ v.path }" stroke="${ safeColor }" stroke-width="${ safeWidth }" stroke-linecap="round"/></svg>`;
	return `url("data:image/svg+xml,${ encodeURIComponent( svg ) }")`;
}

function buildSketchStyle( color, strokeWidth, variant ) {
	const variantNum = parseInt( variant, 10 ) || 1;
	const v = SVG_VARIANTS[ variantNum ] || SVG_VARIANTS[ 1 ];
	const url = buildSketchSvgUrl( color, strokeWidth, variantNum );
	return v.style( url );
}

function parseStyleBgImage( styleStr ) {
	if ( ! styleStr || typeof styleStr !== 'string' ) return null;
	return styleStr.includes( 'background-image' ) ? styleStr : null;
}

function parseColorFromStyle( styleStr ) {
	if ( ! styleStr ) return DEFAULT_COLOR;
	try {
		const decoded = decodeURIComponent( styleStr );
		const m = decoded.match( /stroke=["']([^"']+)["']/ );
		return m ? m[1] : DEFAULT_COLOR;
	} catch ( e ) {
		return DEFAULT_COLOR;
	}
}

function parseWidthFromStyle( styleStr ) {
	if ( ! styleStr ) return DEFAULT_CIRCLE_WIDTH;
	try {
		const decoded = decodeURIComponent( styleStr );
		const m = decoded.match( /stroke-width=["'](\d+)["']/ );
		const n = m ? parseInt( m[1], 10 ) : DEFAULT_CIRCLE_WIDTH;
		return isNaN( n ) ? DEFAULT_CIRCLE_WIDTH : Math.max( 1, Math.min( 8, n ) );
	} catch ( e ) {
		return DEFAULT_CIRCLE_WIDTH;
	}
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

function CircleFormatEdit( { isActive, value, onChange } ) {
	const [ showPopover, setShowPopover ] = useState( false );

	// All hooks before any early return
	const selectedBlockName = useSelect( ( select ) => {
		const sel = select( 'core/block-editor' ).getSelectedBlock();
		return sel ? sel.name : null;
	}, [] );

	const themeColors = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return settings.colors || [];
	}, [] );

	// Contrast color: prefer slug 'contrast', fallback last in palette
	const contrastColor = useMemo( () => {
		if ( ! themeColors.length ) return DEFAULT_COLOR;
		const contrast = themeColors.find( ( c ) => c.slug === 'contrast' );
		if ( contrast ) return contrast.color;
		return themeColors[ themeColors.length - 1 ]?.color || DEFAULT_COLOR;
	}, [ themeColors ] );

	const handleApply = useCallback(
		( color, variant, width ) => {
			const variantNum = parseInt( variant, 10 ) || 1;
			const style = buildSketchStyle( color, width, variantNum );
			onChange(
				applyFormat( value, {
					type: FORMAT_NAME,
					attributes: {
						style,
						'data-variant': String( variantNum ),
					},
				}, value.start, value.end )
			);
			setShowPopover( false );
		},
		[ value, onChange ]
	);

	// Render a live SVG preview of the selected variant
	const renderPreview = ( selectedVariant, selectedColor, selectedWidth ) => {
		const variantNum = parseInt( selectedVariant, 10 ) || 1;
		const v = SVG_VARIANTS[ variantNum ] || SVG_VARIANTS[ 1 ];
		const safeColor = typeof selectedColor === 'string' && selectedColor ? selectedColor : DEFAULT_COLOR;
		const safeWidth = typeof selectedWidth === 'number' && ! isNaN( selectedWidth ) ? selectedWidth : DEFAULT_CIRCLE_WIDTH;
		return (
			<svg
				viewBox={ v.viewBox }
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
				style={ { width: '100%', height: variantNum === 2 ? '14px' : '40px', display: 'block' } }
			>
				<path d={ v.path } stroke={ safeColor } strokeWidth={ safeWidth } strokeLinecap="round" />
			</svg>
		);
	};

	const hasTextFormats = typeof window !== 'undefined' && window.gutenblockProConfig?.hasTextFormats;
	if ( ! hasTextFormats || ! selectedBlockName || ! ALLOWED_BLOCKS.includes( selectedBlockName ) ) {
		return null;
	}

	const activeAttrs = getActiveFormatAttrs( value );
	const defaultColor = activeAttrs?.style ? parseColorFromStyle( activeAttrs.style ) : contrastColor;
	const defaultVariant = activeAttrs?.[ 'data-variant' ] || '1';
	const defaultWidth = activeAttrs?.style ? parseWidthFromStyle( activeAttrs.style ) : DEFAULT_CIRCLE_WIDTH;

	return (
		<>
			<RichTextToolbarButton
				icon="admin-generic"
				title={ isActive ? __( 'Sketch Highlight entfernen', 'gutenblock-pro' ) : __( 'Sketch Highlight', 'gutenblock-pro' ) }
				onClick={ () => setShowPopover( ( v ) => ! v ) }
				isActive={ isActive }
			/>
			{ showPopover && (
				<ColorPickerPopover
					onApply={ handleApply }
					onRemove={ isActive ? () => {
						onChange( removeFormat( value, FORMAT_NAME ) );
						setShowPopover( false );
					} : null }
					defaultColor={ defaultColor }
					defaultVariant={ defaultVariant }
					defaultWidth={ defaultWidth }
					themeColors={ themeColors }
					showVariant
					showWidth
					variantOptions={ VARIANT_OPTIONS }
					variantLabel={ __( 'Form', 'gutenblock-pro' ) }
					widthLabel={ __( 'Strichstärke', 'gutenblock-pro' ) }
					widthMin={ 1 }
					widthMax={ 8 }
					renderPreview={ renderPreview }
				/>
			) }
		</>
	);
}

registerFormatType( FORMAT_NAME, {
	title: __( 'Sketch Highlight', 'gutenblock-pro' ),
	tagName: 'span',
	className: 'gb-fmt-circle',
	attributes: {
		style: 'style',
		'data-variant': 'data-variant',
	},
	edit: CircleFormatEdit,
} );
