/**
 * Material Icon Block – Tab "Eigene SVG": Liste aus Mediabibliothek + Upload
 *
 * @package GutenBlockPro
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { MediaUpload } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';

const SVG_MIME = 'image/svg+xml';

function ajaxGet( params ) {
	const base = window.gutenblockProConfig?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
	const qs = new URLSearchParams( params ).toString();
	return fetch( `${ base }?${ qs }`, { credentials: 'include' } ).then( ( r ) => r.json() );
}

export default function CustomSvgTab( { onSelect, onClose } ) {
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ svgList, setSvgList ] = useState( [] );
	const [ listLoading, setListLoading ] = useState( true );

	useEffect( () => {
		setListLoading( true );
		const isSvg = ( m ) => m.mime_type === SVG_MIME || ( m.source_url && m.source_url.toLowerCase().endsWith( '.svg' ) );
		const done = ( items ) => {
			setSvgList( Array.isArray( items ) ? items.filter( isSvg ) : [] );
			setListLoading( false );
		};
		apiFetch( { path: '/wp/v2/media?per_page=50&mime_type=image/svg+xml' } )
			.then( done )
			.catch( () => {
				apiFetch( { path: '/wp/v2/media?per_page=100' } )
					.then( done )
					.catch( () => done( [] ) );
			} );
	}, [] );

	const fetchMarkup = ( attachmentId ) => {
		if ( ! attachmentId ) return;
		setError( '' );
		setLoading( true );
		ajaxGet( { action: 'gutenblock_pro_svg_markup', attachment_id: attachmentId } )
			.then( ( res ) => {
				if ( res && res.success && res.data && res.data.markup ) {
					onSelect( { id: attachmentId, markup: res.data.markup } );
					if ( onClose ) onClose();
				} else {
					setError( __( 'SVG konnte nicht geladen werden.', 'gutenblock-pro' ) );
				}
			} )
			.catch( () => {
				setError( __( 'Fehler beim Laden des SVG.', 'gutenblock-pro' ) );
			} )
			.finally( () => setLoading( false ) );
	};

	const onMediaSelect = ( media ) => {
		if ( media && media.id && ( media.mime === SVG_MIME || ( media.url && media.url.toLowerCase().endsWith( '.svg' ) ) ) ) {
			fetchMarkup( media.id );
			setSvgList( ( prev ) => [ media, ...prev.filter( ( m ) => m.id !== media.id ) ] );
		} else {
			setError( __( 'Bitte eine SVG-Datei wählen.', 'gutenblock-pro' ) );
		}
	};

	return (
		<div className="gutenblock-material-icon-custom-svg-tab">
			<p className="components-base-control__help" style={ { marginBottom: 12 } }>
				{ __( 'SVG aus der Mediabibliothek wählen oder neu hochladen. Nur SVG-Dateien.', 'gutenblock-pro' ) }
			</p>
			<MediaUpload
				onSelect={ onMediaSelect }
				allowedTypes={ [ SVG_MIME ] }
				multiple={ false }
				value={ 0 }
				render={ ( { open } ) => (
					<Button variant="primary" onClick={ open } disabled={ loading } isBusy={ loading } style={ { marginBottom: 12 } }>
						{ loading ? __( 'Laden…', 'gutenblock-pro' ) : __( 'SVG hochladen oder auswählen', 'gutenblock-pro' ) }
					</Button>
				) }
			/>
			{ listLoading ? (
				<p className="gutenblock-material-icon-custom-svg-list-loading" style={ { marginTop: 12, color: '#757575' } }>
					{ __( 'Lade SVGs aus der Mediabibliothek…', 'gutenblock-pro' ) }
				</p>
			) : svgList.length > 0 ? (
				<div className="gutenblock-material-icon-custom-svg-grid" role="listbox" aria-label={ __( 'Eigene SVGs', 'gutenblock-pro' ) } style={ { marginTop: 12, display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(80px, 1fr))', gap: 8 } }>
					{ svgList.map( ( media ) => (
						<button
							key={ media.id }
							type="button"
							className="gutenblock-material-icon-grid-item"
							onClick={ () => fetchMarkup( media.id ) }
							disabled={ loading }
							role="option"
							aria-label={ media.title?.rendered || media.slug || __( 'SVG', 'gutenblock-pro' ) }
							title={ media.title?.rendered || media.slug || '' }
							style={ { padding: 8, border: '1px solid #ddd', borderRadius: 4, background: '#fff', cursor: 'pointer', minHeight: 80 } }
						>
							{ media.source_url ? (
								<img src={ media.source_url } alt="" style={ { width: 32, height: 32, objectFit: 'contain', display: 'block', margin: '0 auto 4px' } } />
							) : (
								<span style={ { display: 'block', width: 32, height: 32, margin: '0 auto 4px', background: '#eee', borderRadius: 2 } } />
							) }
							<span style={ { fontSize: 11, display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }>
								{ ( media.title?.rendered || media.slug || __( 'SVG', 'gutenblock-pro' ) ).replace( /<[^>]+>/g, '' ) }
							</span>
						</button>
					) ) }
				</div>
			) : (
				<p className="gutenblock-material-icon-custom-svg-empty" style={ { marginTop: 12, color: '#757575' } }>
					{ __( 'Noch keine SVG-Dateien in der Mediabibliothek. Über den Button oben hochladen.', 'gutenblock-pro' ) }
				</p>
			) }
			{ error && (
				<p className="gutenblock-material-icon-custom-svg-error" style={ { color: '#b32d2e', marginTop: 8 } } role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}
