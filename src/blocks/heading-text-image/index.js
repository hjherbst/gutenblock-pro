/**
 * Heading Text Image – Extends core/heading (and optionally custom heading blocks)
 * to allow a background-image as text fill (background-clip: text).
 *
 * Attributes are saved as block attributes; PHP render_block applies the inline
 * styles server-side (avoiding kses stripping of background-image).
 *
 * @package GutenBlockPro
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { PanelBody, Button, SelectControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Add your custom block name here when it's registered (e.g. 'gutenblock-pro/flexible-heading')
const TARGET_BLOCKS = [
	'core/heading',
];

const SIZE_OPTIONS = [
	{ value: 'cover', label: __( 'Abdeckend (cover)', 'gutenblock-pro' ) },
	{ value: 'contain', label: __( 'Einpassen (contain)', 'gutenblock-pro' ) },
	{ value: '100% 100%', label: __( 'Gestreckt', 'gutenblock-pro' ) },
	{ value: 'auto', label: __( 'Originalgröße', 'gutenblock-pro' ) },
];

const POSITION_OPTIONS = [
	{ value: 'center center', label: __( 'Mitte', 'gutenblock-pro' ) },
	{ value: 'top center', label: __( 'Oben Mitte', 'gutenblock-pro' ) },
	{ value: 'bottom center', label: __( 'Unten Mitte', 'gutenblock-pro' ) },
	{ value: 'left center', label: __( 'Links', 'gutenblock-pro' ) },
	{ value: 'right center', label: __( 'Rechts', 'gutenblock-pro' ) },
];

// ── 1. Register custom block attributes ──────────────────────────────────────

addFilter(
	'blocks.registerBlockType',
	'gutenblock-pro/heading-text-image-attrs',
	( settings, name ) => {
		if ( ! TARGET_BLOCKS.includes( name ) ) return settings;
		return {
			...settings,
			attributes: {
				...settings.attributes,
				gbTextImageUrl: { type: 'string', default: '' },
				gbTextImageId: { type: 'number', default: 0 },
				gbTextImageSize: { type: 'string', default: 'cover' },
				gbTextImagePosition: { type: 'string', default: 'center center' },
			},
		};
	}
);

// ── 2. Editor preview: apply/clear styles on the block DOM element ────────────

/**
 * Returns the document that contains the block editor canvas.
 * In WP 6.3+ the canvas is rendered inside an iframe; older WP / non-iframe
 * fallbacks use the outer document directly.
 */
function getEditorDocument() {
	const iframe =
		document.querySelector( 'iframe[name="editor-canvas"]' ) ||
		document.querySelector( 'iframe.editor-canvas__iframe' );
	if ( iframe?.contentDocument?.body ) {
		return iframe.contentDocument;
	}
	return document;
}

function syncEditorStyle( clientId, url, size, position ) {
	if ( ! clientId ) return;
	const el = getEditorDocument().querySelector( `[data-block="${ clientId }"]` );
	if ( ! el ) return;

	if ( url ) {
		Object.assign( el.style, {
			backgroundImage: `url("${ url }")`,
			backgroundClip: 'text',
			webkitBackgroundClip: 'text',
			color: 'transparent',
			webkitTextFillColor: 'transparent',
			backgroundSize: size || 'cover',
			backgroundPosition: position || 'center center',
			backgroundRepeat: 'no-repeat',
		} );
	} else {
		[
			'backgroundImage', 'backgroundClip', 'webkitBackgroundClip',
			'color', 'webkitTextFillColor', 'backgroundSize',
			'backgroundPosition', 'backgroundRepeat',
		].forEach( ( prop ) => { el.style[ prop ] = ''; } );
	}
}

// ── 3. BlockEdit HOC: InspectorControls panel + live editor preview ───────────

const withTextImageControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes, setAttributes, clientId } = props;
		const isTarget = TARGET_BLOCKS.includes( name );

		// Hooks must always be called – conditionally using them inside is fine
		const {
			gbTextImageUrl = '',
			gbTextImageId = 0,
			gbTextImageSize = 'cover',
			gbTextImagePosition = 'center center',
		} = attributes || {};

		useEffect( () => {
			if ( ! isTarget ) return;
			syncEditorStyle( clientId, gbTextImageUrl, gbTextImageSize, gbTextImagePosition );
		}, [ isTarget, clientId, gbTextImageUrl, gbTextImageSize, gbTextImagePosition ] );

		if ( ! isTarget ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Bild als Textfüllung', 'gutenblock-pro' ) }
						initialOpen={ false }
					>
						<p style={ { fontSize: '12px', color: '#757575', marginBottom: '12px', marginTop: 0 } }>
							{ __( 'Das gewählte Bild ersetzt die Textfarbe (background-clip: text).', 'gutenblock-pro' ) }
						</p>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										gbTextImageUrl: media.url,
										gbTextImageId: media.id,
									} )
								}
								allowedTypes={ [ 'image' ] }
								value={ gbTextImageId }
								render={ ( { open } ) => (
									<div>
										{ gbTextImageUrl && (
											<div style={ { marginBottom: '8px', borderRadius: '4px', overflow: 'hidden', border: '1px solid #e0e0e0' } }>
												<img
													src={ gbTextImageUrl }
													style={ { width: '100%', height: '64px', objectFit: 'cover', display: 'block' } }
													alt=""
												/>
											</div>
										) }
										<div style={ { display: 'flex', gap: '8px', marginBottom: gbTextImageUrl ? '12px' : 0 } }>
											<Button variant="secondary" onClick={ open }>
												{ gbTextImageUrl
													? __( 'Bild ändern', 'gutenblock-pro' )
													: __( 'Bild wählen', 'gutenblock-pro' ) }
											</Button>
											{ gbTextImageUrl && (
												<Button
													variant="tertiary"
													isDestructive
													onClick={ () =>
														setAttributes( { gbTextImageUrl: '', gbTextImageId: 0 } )
													}
												>
													{ __( 'Entfernen', 'gutenblock-pro' ) }
												</Button>
											) }
										</div>
										{ gbTextImageUrl && (
											<>
												<SelectControl
													label={ __( 'Bildgröße', 'gutenblock-pro' ) }
													value={ gbTextImageSize }
													options={ SIZE_OPTIONS }
													onChange={ ( v ) => setAttributes( { gbTextImageSize: v } ) }
												/>
												<SelectControl
													label={ __( 'Position', 'gutenblock-pro' ) }
													value={ gbTextImagePosition }
													options={ POSITION_OPTIONS }
													onChange={ ( v ) => setAttributes( { gbTextImagePosition: v } ) }
												/>
											</>
										) }
									</div>
								) }
							/>
						</MediaUploadCheck>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withTextImageControls' );

addFilter(
	'editor.BlockEdit',
	'gutenblock-pro/heading-text-image-edit',
	withTextImageControls
);
