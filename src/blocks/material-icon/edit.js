/**
 * Material Icon Block – Editor mit Such-Modal und Inspector
 *
 * @package GutenBlockPro
 */

import { useState, useEffect, useCallback, useRef, useMemo } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button, RangeControl, ColorPalette, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import IconSearch from './icon-search';
import CustomSvgTab from './custom-svg-tab';

function ajaxGet( params ) {
	const base = window.gutenblockProConfig?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
	const qs = new URLSearchParams( params ).toString();
	return fetch( `${ base }?${ qs }`, { credentials: 'include' } ).then( ( r ) => r.json() );
}

const VIEWBOX = '0 -960 960 960';

function getPathFromData( data ) {
	if ( ! data ) return '';
	if ( data.outlined?.outline?.w400 ) return data.outlined.outline.w400;
	return '';
}

function SvgPreview( { path, size, color } ) {
	if ( ! path ) return null;
	return (
		<span className="wp-block-gutenblock-pro-material-icon" style={ { display: 'inline-block', width: size + 'px', height: size + 'px' } }>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox={ VIEWBOX } width={ size } height={ size } fill={ color } aria-hidden="true">
				<path d={ path } />
			</svg>
		</span>
	);
}

function CustomSvgPreview( { markup, size, color } ) {
	if ( ! markup ) return null;
	return (
		<span
			className="wp-block-gutenblock-pro-material-icon"
			style={ { display: 'inline-block', width: size + 'px', height: size + 'px', color } }
		>
			<div
				className="gutenblock-custom-svg-preview-inner"
				dangerouslySetInnerHTML={ { __html: markup } }
				style={ { width: '100%', height: '100%', lineHeight: 0 } }
			/>
		</span>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { icon, size, color, colorSlug, svgPath, iconSource, customSvgId, customSvgMarkup } = attributes;
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ pathData, setPathData ] = useState( null );
	const [ loadingPaths, setLoadingPaths ] = useState( false );

	const themeColors = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return settings.colors || [];
	}, [] );

	const displayColor = useMemo( () => {
		if ( colorSlug && themeColors.length ) {
			const preset = themeColors.find( ( c ) => c.slug === colorSlug );
			if ( preset && preset.color ) return preset.color;
		}
		return color || '#000000';
	}, [ colorSlug, color, themeColors ] );

	const blockProps = useBlockProps( {
		className: 'wp-block-gutenblock-pro-material-icon-wrapper',
	} );

	const fetchPaths = useCallback( ( iconName ) => {
		if ( ! iconName ) return;
		setLoadingPaths( true );
		ajaxGet( { action: 'gutenblock_pro_icon_paths', icon: iconName } )
			.then( ( res ) => {
				if ( res && res.success && res.data ) {
					setPathData( res.data );
					setAttributes( { svgPath: getPathFromData( res.data ) } );
				}
			} )
			.catch( () => setPathData( null ) )
			.finally( () => setLoadingPaths( false ) );
	}, [ setAttributes ] );

	useEffect( () => {
		if ( iconSource === 'material' && icon && ! pathData && ! loadingPaths ) {
			fetchPaths( icon );
		}
	}, [ iconSource, icon, pathData, loadingPaths, fetchPaths ] );

	const onSelectIcon = useCallback(
		( iconName ) => {
			setAttributes( { iconSource: 'material', icon: iconName, customSvgId: 0, customSvgMarkup: '' } );
			setIsModalOpen( false );
			setPathData( null );
			fetchPaths( iconName );
		},
		[ setAttributes, fetchPaths ]
	);

	const onSelectCustomSvg = useCallback(
		( { id, markup } ) => {
			setAttributes( { iconSource: 'custom', customSvgId: id, customSvgMarkup: markup, icon: '', svgPath: '' } );
			setIsModalOpen( false );
		},
		[ setAttributes ]
	);

	const hasMaterialIcon = iconSource !== 'custom' && !! icon;
	const hasCustomSvg = iconSource === 'custom' && !! customSvgMarkup;
	const hasIcon = hasMaterialIcon || hasCustomSvg;
	const pathCacheRef = useRef( {} );

	const getPathForIcon = useCallback( ( iconName ) => {
		const cache = pathCacheRef.current;
		if ( cache[ iconName ] !== undefined ) {
			return Promise.resolve( cache[ iconName ] );
		}
		return ajaxGet( { action: 'gutenblock_pro_icon_paths', icon: iconName } ).then( ( res ) => {
			if ( res && res.success && res.data ) {
				const path = getPathFromData( res.data );
				cache[ iconName ] = path;
				return path;
			}
			cache[ iconName ] = null;
			return null;
		} ).catch( () => {
			cache[ iconName ] = null;
			return null;
		} );
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon-Einstellungen', 'gutenblock-pro' ) } initialOpen={ true }>
					<RangeControl
						label={ __( 'Größe (px)', 'gutenblock-pro' ) }
						value={ size }
						onChange={ ( v ) => setAttributes( { size: v } ) }
						min={ 16 }
						max={ 192 }
						step={ 4 }
					/>
					<div className="gutenblock-material-icon-color-control">
						<span className="components-base-control__label">{ __( 'Farbe', 'gutenblock-pro' ) }</span>
						<ColorPalette
							colors={ themeColors }
							value={ colorSlug ? ( themeColors.find( ( c ) => c.slug === colorSlug )?.color || color ) : color }
							onChange={ ( value ) => {
								if ( value ) {
									const preset = themeColors.find( ( c ) => c.color === value );
									setAttributes( preset ? { color: preset.color, colorSlug: preset.slug } : { color: value, colorSlug: '' } );
								} else {
									setAttributes( { color: '#000000', colorSlug: '' } );
								}
							} }
							clearable
						/>
					</div>
					<Button variant="secondary" isSmall onClick={ () => setIsModalOpen( true ) } style={ { marginTop: '8px' } }>
						{ hasIcon ? __( 'Icon wechseln', 'gutenblock-pro' ) : __( 'Icon wählen', 'gutenblock-pro' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ hasIcon ? (
					<div className="gutenblock-material-icon-preview">
						{ hasCustomSvg ? (
							<CustomSvgPreview markup={ customSvgMarkup } size={ size } color={ displayColor } />
						) : loadingPaths ? (
							<span className="gutenblock-material-icon-loading">{ __( 'Laden…', 'gutenblock-pro' ) }</span>
						) : svgPath ? (
							<SvgPreview path={ svgPath } size={ size } color={ displayColor } />
						) : (
							<div className="gutenblock-material-icon-error">
								<span>{ __( 'Icon konnte nicht geladen werden.', 'gutenblock-pro' ) }</span>
								<Button variant="secondary" isSmall onClick={ () => fetchPaths( icon ) }>
									{ __( 'Erneut laden', 'gutenblock-pro' ) }
								</Button>
							</div>
						) }
					</div>
				) : (
					<div className="gutenblock-material-icon-placeholder">
						<Button variant="secondary" onClick={ () => setIsModalOpen( true ) }>
							{ __( 'Icon wählen', 'gutenblock-pro' ) }
						</Button>
					</div>
				) }
			</div>

			{ isModalOpen && (
				<div className="gutenblock-material-icon-modal-overlay" role="dialog" aria-modal="true" aria-label={ __( 'Icon auswählen', 'gutenblock-pro' ) }>
					<div className="gutenblock-material-icon-modal">
						<div className="gutenblock-material-icon-modal-header">
							<h2>{ __( 'Icon auswählen', 'gutenblock-pro' ) }</h2>
							<Button variant="secondary" onClick={ () => setIsModalOpen( false ) }>
								{ __( 'Schließen', 'gutenblock-pro' ) }
							</Button>
						</div>
						<div className="gutenblock-material-icon-modal-body">
							<TabPanel
								className="gutenblock-material-icon-tabs"
								tabs={ [
									{ name: 'material', title: __( 'Material Symbols', 'gutenblock-pro' ) },
									{ name: 'custom', title: __( 'Eigene SVG', 'gutenblock-pro' ) },
								] }
							>
								{ ( tab ) => (
									tab.name === 'material' ? (
										<IconSearch onSelect={ onSelectIcon } getPathForIcon={ getPathForIcon } viewBox={ VIEWBOX } />
									) : (
										<CustomSvgTab onSelect={ onSelectCustomSvg } onClose={ () => setIsModalOpen( false ) } />
									)
								) }
							</TabPanel>
						</div>
					</div>
				</div>
			) }
		</>
	);
}
