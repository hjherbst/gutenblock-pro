/**
 * Shared popover for color/variant/width selection (Text Formats)
 *
 * @package GutenBlockPro
 */

import { useState } from '@wordpress/element';
import { Popover, ColorPicker, ColorPalette, RangeControl, SelectControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const DEFAULT_COLOR = '#e63946';
const DEFAULT_UNDERLINE_WIDTH = 4;
const DEFAULT_CIRCLE_WIDTH = 2;
const DEFAULT_MARKER_COLOR = 'rgb(255 243 205 / 70%)';

/**
 * ColorPickerPopover – color and optional variant/width, then Apply.
 *
 * @param {Object}   props
 * @param {Function} props.onApply     Called with (color, variant, width) when Apply is clicked.
 * @param {string}   [props.defaultColor]  Initial color.
 * @param {string}   [props.defaultVariant] Initial variant (e.g. '1').
 * @param {number}   [props.defaultWidth]   Initial width (underline).
 * @param {boolean} [props.showVariant]    Show variant 1/2/3 selector.
 * @param {boolean} [props.showWidth]      Show width RangeControl (underline).
 * @param {string}  [props.variantLabel]   Label for variant control.
 * @param {string}  [props.widthLabel]     Label for width control.
 * @param {number}   [props.variantMax]      Max variant when using RangeControl (default 3).
 * @param {Array}    [props.variantOptions]  Array of {value,label} – shows SelectControl instead of RangeControl.
 * @param {Array}    [props.themeColors]     If set, show theme ColorPalette instead of ColorPicker.
 * @param {number}   [props.widthMin]        Min for width control (default 2).
 * @param {number}   [props.widthMax]        Max for width control (default 10).
 * @param {Function} [props.renderPreview]   (variant, color, width) => ReactNode – live SVG preview.
 * @param {Function} [props.onRemove]        If provided, shows a remove button.
 */
export function ColorPickerPopover( {
	onApply,
	onRemove = null,
	defaultColor = DEFAULT_COLOR,
	defaultVariant = '1',
	defaultWidth = DEFAULT_UNDERLINE_WIDTH,
	showVariant = false,
	showWidth = false,
	variantLabel = __( 'Variante', 'gutenblock-pro' ),
	widthLabel = __( 'Strichstärke', 'gutenblock-pro' ),
	variantMax = 3,
	variantOptions = null,
	themeColors = null,
	widthMin = 2,
	widthMax = 10,
	renderPreview = null,
} ) {
	const [ color, setColor ] = useState( defaultColor );
	const [ variant, setVariant ] = useState( defaultVariant );
	const [ width, setWidth ] = useState( defaultWidth );

	const handleApply = () => {
		onApply( color, variant, width );
	};

	return (
		<Popover className="gb-fmt-popover" focusOnMount="firstElement">
			<div className="gb-fmt-popover-inner" style={ { padding: '12px', minWidth: '240px' } }>
				<div className="gb-fmt-popover-color">
					<span className="components-base-control__label">{ __( 'Farbe', 'gutenblock-pro' ) }</span>
					{ themeColors && Array.isArray( themeColors ) && themeColors.length > 0 ? (
						<ColorPalette
							colors={ themeColors }
							value={ typeof color === 'string' ? color : defaultColor }
							onChange={ ( value ) => setColor( value || defaultColor ) }
							clearable={ false }
						/>
					) : (
						<ColorPicker
							color={ typeof color === 'string' ? color : defaultColor }
							onChange={ ( c ) => {
								const next = typeof c === 'string' ? c : ( c && typeof c.hex === 'string' ? c.hex : color );
								setColor( next || defaultColor );
							} }
							enableAlpha={ false }
							defaultValue={ defaultColor }
						/>
					) }
				</div>
				{ showVariant && (
					<div className="gb-fmt-popover-variant">
						{ variantOptions ? (
							<SelectControl
								label={ variantLabel }
								value={ variant }
								options={ variantOptions }
								onChange={ ( v ) => setVariant( v ) }
							/>
						) : (
							<RangeControl
								label={ variantLabel }
								value={ Math.min( variantMax, Math.max( 1, parseInt( variant, 10 ) || 1 ) ) }
								onChange={ ( v ) => setVariant( String( typeof v === 'number' ? v : 1 ) ) }
								min={ 1 }
								max={ variantMax }
								step={ 1 }
							/>
						) }
					</div>
				) }
				{ showVariant && renderPreview && (
					<div
						className="gb-fmt-popover-preview"
						style={ {
							marginTop: '4px',
							marginBottom: '4px',
							padding: '10px 14px',
							background: '#f6f7f7',
							borderRadius: '4px',
							border: '1px solid #e0e0e0',
						} }
					>
						{ renderPreview( variant, color, width ) }
					</div>
				) }
				{ showWidth && (
					<div className="gb-fmt-popover-width">
						<RangeControl
							label={ widthLabel }
							value={ typeof width === 'number' && ! isNaN( width ) ? width : defaultWidth }
							onChange={ ( v ) => setWidth( typeof v === 'number' ? v : defaultWidth ) }
							min={ widthMin }
							max={ widthMax }
							step={ 1 }
						/>
					</div>
				) }
				<div style={ { display: 'flex', gap: '8px', marginTop: '8px' } }>
					<Button variant="primary" isPrimary onClick={ handleApply }>
						{ __( 'Anwenden', 'gutenblock-pro' ) }
					</Button>
					{ onRemove && (
						<Button variant="tertiary" isDestructive onClick={ onRemove }>
							{ __( 'Entfernen', 'gutenblock-pro' ) }
						</Button>
					) }
				</div>
			</div>
		</Popover>
	);
}

export { DEFAULT_COLOR, DEFAULT_UNDERLINE_WIDTH, DEFAULT_CIRCLE_WIDTH, DEFAULT_MARKER_COLOR };
