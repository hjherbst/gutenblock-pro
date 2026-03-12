/**
 * Sticky Feature Block – Editor
 *
 * @package GutenBlockPro
 */

import { useBlockProps } from '@wordpress/block-editor';
import { InspectorControls, MediaUpload, MediaUploadCheck, RichText } from '@wordpress/block-editor';
import { PanelBody, Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const DEFAULT_ITEM = { heading: '', subline: '', imageId: 0, imageUrl: '', imageAlt: '' };

const ASPECT_RATIO_OPTIONS = [
	{ value: 'auto', label: __( 'Automatisch', 'gutenblock-pro' ) },
	{ value: '1-1', label: '1:1' },
	{ value: '4-3', label: '4:3' },
	{ value: '16-9', label: '16:9' },
	{ value: '3-2', label: '3:2' },
	{ value: '2-1', label: '2:1' },
];

export default function Edit( { attributes, setAttributes } ) {
	const { items = [ DEFAULT_ITEM ], imagePosition = 'right', aspectRatio = 'auto' } = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-gutenblock-pro-sticky-feature gb-sticky-feature gbp-sticky-feature-edit',
	} );

	const updateItem = ( index, field, value ) => {
		const next = items.map( ( item, i ) =>
			i === index ? { ...item, [ field ]: value } : item
		);
		setAttributes( { items: next } );
	};

	const addItem = () => {
		setAttributes( { items: [ ...items, { ...DEFAULT_ITEM } ] } );
	};

	const removeItem = ( index ) => {
		if ( items.length <= 1 ) return;
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
	};

	const previewImageUrl = items.find( ( it ) => it.imageUrl )?.imageUrl || null;
	const aspectRatioClass = aspectRatio !== 'auto' ? `gbp-sticky-feature__media-figure--${ aspectRatio }` : '';

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'gutenblock-pro' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Bildposition', 'gutenblock-pro' ) }
						value={ imagePosition }
						options={ [
							{ value: 'left', label: __( 'Links', 'gutenblock-pro' ) },
							{ value: 'right', label: __( 'Rechts', 'gutenblock-pro' ) },
						] }
						onChange={ ( v ) => setAttributes( { imagePosition: v } ) }
					/>
					<SelectControl
						label={ __( 'Seitenverhältnis Bild', 'gutenblock-pro' ) }
						value={ aspectRatio }
						options={ ASPECT_RATIO_OPTIONS }
						onChange={ ( v ) => setAttributes( { aspectRatio: v } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Bilder pro Item', 'gutenblock-pro' ) } initialOpen={ true }>
					<p className="components-base-control__help" style={ { marginBottom: 12 } }>
						{ __( 'Weise jedem Text-Item ein Bild zu. Es wird beim Scrollen neben dem jeweiligen Text angezeigt.', 'gutenblock-pro' ) }
					</p>
					{ items.map( ( item, index ) => (
						<div key={ index } className="gbp-sticky-feature-inspector-item" style={ { marginBottom: 16, paddingBottom: 16, borderBottom: '1px solid #e0e0e0' } }>
							<strong style={ { display: 'block', marginBottom: 8 } }>
								{ __( 'Item', 'gutenblock-pro' ) } { index + 1 }
							</strong>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) => {
										const next = [ ...items ];
										next[ index ] = {
											...next[ index ],
											imageId: media.id,
											imageUrl: media.url || '',
											imageAlt: media.alt || '',
										};
										setAttributes( { items: next } );
									} }
									allowedTypes={ [ 'image' ] }
									value={ item.imageId }
									render={ ( { open } ) => (
										<div>
											{ item.imageUrl && (
												<div style={ { marginBottom: 8, borderRadius: 4, overflow: 'hidden', border: '1px solid #e0e0e0' } }>
													<img
														src={ item.imageUrl }
														alt=""
														style={ { width: '100%', height: 80, objectFit: 'cover', display: 'block' } }
													/>
												</div>
											) }
											<div style={ { display: 'flex', gap: 8, flexWrap: 'wrap' } }>
												<Button variant="secondary" isSmall onClick={ open }>
													{ item.imageUrl ? __( 'Bild ändern', 'gutenblock-pro' ) : __( 'Bild wählen', 'gutenblock-pro' ) }
												</Button>
												{ item.imageUrl && (
													<Button
														variant="tertiary"
														isDestructive
														isSmall
														onClick={ () => {
														const next = [ ...items ];
														next[ index ] = { ...next[ index ], imageId: 0, imageUrl: '', imageAlt: '' };
														setAttributes( { items: next } );
													} }
													>
														{ __( 'Entfernen', 'gutenblock-pro' ) }
													</Button>
												) }
											</div>
										</div>
									) }
								/>
							</MediaUploadCheck>
						</div>
					) ) }
					<Button variant="secondary" isSmall onClick={ addItem } style={ { marginTop: 8 } }>
						{ __( 'Item hinzufügen', 'gutenblock-pro' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="gbp-sticky-feature__container">
					<div className={ `gbp-sticky-feature__grid-1 gbp-sticky-feature__grid-1--image-${ imagePosition }` }>
						{ imagePosition === 'left' && (
							<div className="gbp-sticky-feature__media-col gbp-sticky-feature-display-md" aria-hidden="true">
								<div className={ `gbp-sticky-feature__media-placeholder ${ aspectRatioClass }` }>
									{ previewImageUrl ? (
										<img src={ previewImageUrl } alt="" style={ { width: '100%', height: '100%', objectFit: 'cover', display: 'block' } } />
									) : (
										<span className="gbp-sticky-feature__placeholder-label">
											{ __( 'Bilder in der Sidebar pro Item zuweisen', 'gutenblock-pro' ) }
										</span>
									) }
								</div>
							</div>
						) }
						<div className="gbp-sticky-feature__content-col">
							<ul className="gbp-sticky-feature__content-list gbp-sticky-feature__grid-2">
								{ items.map( ( item, index ) => (
									<li key={ index } className="gbp-sticky-feature__content-item">
										<RichText
											tagName="h3"
											className="gbp-sticky-feature__title"
											value={ item.heading }
											onChange={ ( v ) => updateItem( index, 'heading', v ) }
											placeholder={ __( 'Überschrift', 'gutenblock-pro' ) }
										/>
										<RichText
											tagName="p"
											className="gbp-sticky-feature__subline"
											value={ item.subline }
											onChange={ ( v ) => updateItem( index, 'subline', v ) }
											placeholder={ __( 'Subline / Beschreibung', 'gutenblock-pro' ) }
										/>
										{ items.length > 1 && (
											<Button
												variant="tertiary"
												isDestructive
												isSmall
												onClick={ () => removeItem( index ) }
												style={ { marginTop: 8 } }
											>
												{ __( 'Item entfernen', 'gutenblock-pro' ) }
											</Button>
										) }
									</li>
								) ) }
							</ul>
						</div>
						{ imagePosition === 'right' && (
							<div className="gbp-sticky-feature__media-col gbp-sticky-feature-display-md" aria-hidden="true">
								<div className={ `gbp-sticky-feature__media-placeholder ${ aspectRatioClass }` }>
									{ previewImageUrl ? (
										<img src={ previewImageUrl } alt="" style={ { width: '100%', height: '100%', objectFit: 'cover', display: 'block' } } />
									) : (
										<span className="gbp-sticky-feature__placeholder-label">
											{ __( 'Bilder in der Sidebar pro Item zuweisen', 'gutenblock-pro' ) }
										</span>
									) }
								</div>
							</div>
						) }
					</div>
				</div>
				<div style={ { marginTop: 12 } }>
					<Button variant="secondary" isSmall onClick={ addItem }>
						{ __( '+ Item hinzufügen', 'gutenblock-pro' ) }
					</Button>
				</div>
			</div>
		</>
	);
}
