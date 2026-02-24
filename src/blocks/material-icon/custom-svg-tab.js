/**
 * Material Icon Block – Tab "Eigene SVG": Upload + Auswahl aus Mediabibliothek
 *
 * @package GutenBlockPro
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { MediaUpload } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';

const SVG_MIME = 'image/svg+xml';

export default function CustomSvgTab( { onSelect, onClose } ) {
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const fetchMarkup = ( attachmentId ) => {
		if ( ! attachmentId ) return;
		setError( '' );
		setLoading( true );
		const url = `${ window.ajaxurl || '/wp-admin/admin-ajax.php' }?action=gutenblock_pro_svg_markup&attachment_id=${ attachmentId }`;
		apiFetch( { url } )
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

	return (
		<div className="gutenblock-material-icon-custom-svg-tab">
			<p className="components-base-control__help" style={ { marginBottom: 12 } }>
				{ __( 'SVG aus der Mediabibliothek wählen oder neu hochladen. Nur SVG-Dateien.', 'gutenblock-pro' ) }
			</p>
			<MediaUpload
				onSelect={ ( media ) => {
					if ( media && media.id && ( media.mime === SVG_MIME || ( media.url && media.url.toLowerCase().endsWith( '.svg' ) ) ) ) {
						fetchMarkup( media.id );
					} else {
						setError( __( 'Bitte eine SVG-Datei wählen.', 'gutenblock-pro' ) );
					}
				} }
				allowedTypes={ [ SVG_MIME ] }
				multiple={ false }
				value={ 0 }
				render={ ( { open } ) => (
					<Button variant="primary" onClick={ open } disabled={ loading } isBusy={ loading } style={ { marginBottom: 12 } }>
						{ loading ? __( 'Laden…', 'gutenblock-pro' ) : __( 'SVG hochladen oder auswählen', 'gutenblock-pro' ) }
					</Button>
				) }
			/>
			{ error && (
				<p className="gutenblock-material-icon-custom-svg-error" style={ { color: '#b32d2e', marginTop: 8 } } role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}
