/**
 * Material Icon Block – Suchfeld mit Debounce und Icon-Grid (SVG-Vorschau)
 *
 * @package GutenBlockPro
 */

import { useState, useMemo, useCallback, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SearchControl } from '@wordpress/components';

// Icon-Index beim Build einbinden (clientseitige Suche)
import iconIndex from '@material-symbols-svg/metadata/icon-index.json';

const DEBOUNCE_MS = 200;
const MAX_PATHS_TO_FETCH = 60;
const GRID_ICON_SIZE = 32;

/**
 * Debounced search term.
 */
function useDebouncedValue( value, delay ) {
	const [ debounced, setDebounced ] = useState( value );

	useEffect( () => {
		const id = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( id );
	}, [ value, delay ] );

	return debounced;
}

/**
 * Filter icon index by search string (name + searchTerms).
 */
function filterIcons( index, query ) {
	if ( ! query || query.length < 2 ) {
		return Object.keys( index ).slice( 0, 100 );
	}
	const q = query.toLowerCase().trim();
	const keys = Object.keys( index );
	const matched = keys.filter( ( key ) => {
		const entry = index[ key ];
		if ( entry.name && entry.name.toLowerCase().includes( q ) ) return true;
		if ( entry.searchTerms && Array.isArray( entry.searchTerms ) ) {
			if ( entry.searchTerms.some( ( t ) => String( t ).toLowerCase().includes( q ) ) ) return true;
		}
		return false;
	} );
	return matched.slice( 0, 200 );
}

/**
 * Kleine SVG-Vorschau für ein Icon (path + viewBox).
 */
function IconThumb( { path, viewBox, size = GRID_ICON_SIZE, color = '#444' } ) {
	if ( ! path ) return null;
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox={ viewBox }
			width={ size }
			height={ size }
			fill={ color }
			aria-hidden="true"
			className="gutenblock-material-icon-thumb"
		>
			<path d={ path } />
		</svg>
	);
}

/**
 * IconSearch – Suchfeld + Grid mit SVG-Vorschau, ruft onSelect( iconName ) auf.
 */
export default function IconSearch( { onSelect, getPathForIcon, viewBox = '0 -960 960 960' } ) {
	const [ search, setSearch ] = useState( '' );
	const [ pathCache, setPathCache ] = useState( {} );
	const debouncedSearch = useDebouncedValue( search, DEBOUNCE_MS );

	const iconNames = useMemo( () => filterIcons( iconIndex, debouncedSearch ), [ debouncedSearch ] );
	const pendingRef = useRef( new Set() );
	const searchGenerationRef = useRef( 0 );

	// Bei neuer Suche: Cache und laufende Fetches zurücksetzen, damit nur aktuelle Treffer geladen werden
	useEffect( () => {
		searchGenerationRef.current += 1;
		setPathCache( {} );
		pendingRef.current.clear();
	}, [ debouncedSearch ] );

	// Pfade nur für die aktuell angezeigte Liste laden (erste N Icons der Suchergebnisse)
	useEffect( () => {
		if ( ! getPathForIcon || ! iconNames.length ) return;
		const generation = searchGenerationRef.current;
		const toFetch = iconNames
			.filter( ( name ) => pathCache[ name ] === undefined && ! pendingRef.current.has( name ) )
			.slice( 0, MAX_PATHS_TO_FETCH );
		if ( toFetch.length === 0 ) return;
		toFetch.forEach( ( name ) => {
			pendingRef.current.add( name );
			getPathForIcon( name ).then( ( path ) => {
				if ( generation !== searchGenerationRef.current ) return;
				pendingRef.current.delete( name );
				setPathCache( ( prev ) => ( path != null ? { ...prev, [ name ]: path } : prev ) );
			} );
		} );
	}, [ iconNames.join( ',' ), getPathForIcon, pathCache ] );

	const handleSelect = useCallback(
		( name ) => {
			if ( onSelect ) onSelect( name );
		},
		[ onSelect ]
	);

	return (
		<div className="gutenblock-material-icon-search">
			<SearchControl
				value={ search }
				onChange={ setSearch }
				placeholder={ __( 'Icon suchen (z.B. home, arrow)…', 'gutenblock-pro' ) }
				__nextHasNoMarginBottom
			/>
			<div className="gutenblock-material-icon-grid" role="listbox" aria-label={ __( 'Icons', 'gutenblock-pro' ) }>
				{ iconNames.length === 0 ? (
					<p className="gutenblock-material-icon-grid-empty">
						{ debouncedSearch.length >= 2
							? __( 'Keine Icons gefunden.', 'gutenblock-pro' )
							: __( 'Mindestens 2 Zeichen eingeben.', 'gutenblock-pro' ) }
					</p>
				) : (
					iconNames.map( ( name ) => (
						<button
							key={ name }
							type="button"
							className="gutenblock-material-icon-grid-item"
							onClick={ () => handleSelect( name ) }
							role="option"
							aria-label={ name }
							title={ name }
						>
							{ pathCache[ name ] ? (
								<IconThumb path={ pathCache[ name ] } viewBox={ viewBox } size={ GRID_ICON_SIZE } />
							) : (
								<span className="gutenblock-material-icon-grid-loading" aria-hidden="true" />
							) }
							<span className="gutenblock-material-icon-grid-label">{ name }</span>
						</button>
					) )
				) }
			</div>
		</div>
	);
}

export { filterIcons, iconIndex };
