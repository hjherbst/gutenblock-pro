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
const MAX_PATHS_TO_FETCH = 20;
const GRID_ICON_SIZE = 32;

/** Plugin custom icons (not in @material-symbols-svg), e.g. filled star */
const CUSTOM_ICONS = [
	{ name: 'rate_star_filled', searchTerms: [ 'star', 'filled', 'rating', 'favorit' ] },
];

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
 * Returns empty when query length < 2 to avoid loading 60+ paths on modal open.
 * Merges plugin custom icons (e.g. rate_star_filled) when they match the query.
 */
function filterIcons( index, query ) {
	const q = ! query ? '' : query.toLowerCase().trim();
	if ( q.length < 2 ) {
		return [];
	}
	const fromIndex = Object.keys( index ).filter( ( key ) => {
		const entry = index[ key ];
		if ( entry.name && entry.name.toLowerCase().includes( q ) ) return true;
		if ( entry.searchTerms && Array.isArray( entry.searchTerms ) ) {
			if ( entry.searchTerms.some( ( t ) => String( t ).toLowerCase().includes( q ) ) ) return true;
		}
		return false;
	} );
	const customMatched = CUSTOM_ICONS.filter(
		( c ) =>
			c.name.toLowerCase().includes( q ) ||
			( c.searchTerms && c.searchTerms.some( ( t ) => t.toLowerCase().includes( q ) ) )
	).map( ( c ) => c.name );
	const combined = [ ...new Set( [ ...customMatched, ...fromIndex ] ) ];
	return combined.slice( 0, 200 );
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
 * getPathForIcon returns { path, viewBox } or null.
 */
export default function IconSearch( { onSelect, getPathForIcon, defaultViewBox = '0 -960 960 960' } ) {
	const [ search, setSearch ] = useState( '' );
	const [ pathCacheVersion, setPathCacheVersion ] = useState( 0 );
	const pathCacheRef = useRef( {} );
	const pendingRef = useRef( new Set() );
	const debouncedSearch = useDebouncedValue( search, DEBOUNCE_MS );

	const iconNames = useMemo( () => filterIcons( iconIndex, debouncedSearch ), [ debouncedSearch ] );

	// Pfade für die aktuell angezeigte Liste laden (in Batches à MAX_PATHS_TO_FETCH).
	// pathCacheVersion in deps: nach jedem Batch erneut ausführen, damit die nächsten 20 geladen werden.
	useEffect( () => {
		if ( ! getPathForIcon || ! iconNames.length ) return;
		const cache = pathCacheRef.current;
		const toFetch = iconNames
			.filter( ( name ) => cache[ name ] === undefined && ! pendingRef.current.has( name ) )
			.slice( 0, MAX_PATHS_TO_FETCH );
		if ( toFetch.length === 0 ) return;
		toFetch.forEach( ( name ) => {
			pendingRef.current.add( name );
			getPathForIcon( name ).then( ( data ) => {
				pendingRef.current.delete( name );
				if ( data != null && data.path ) {
					cache[ name ] = { path: data.path, viewBox: data.viewBox || defaultViewBox };
					setPathCacheVersion( ( v ) => v + 1 );
				}
			} );
		} );
	}, [ iconNames.join( ',' ), pathCacheVersion, getPathForIcon, defaultViewBox ] );

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
					iconNames.map( ( name ) => {
						const cached = pathCacheRef.current[ name ];
						return (
							<button
								key={ name }
								type="button"
								className="gutenblock-material-icon-grid-item"
								onClick={ () => handleSelect( name ) }
								role="option"
								aria-label={ name }
								title={ name }
							>
								{ cached ? (
									<IconThumb
										path={ cached.path }
										viewBox={ cached.viewBox || defaultViewBox }
										size={ GRID_ICON_SIZE }
									/>
								) : (
									<span className="gutenblock-material-icon-grid-loading" aria-hidden="true" />
								) }
								<span className="gutenblock-material-icon-grid-label">{ name }</span>
							</button>
						);
					} )
				) }
			</div>
		</div>
	);
}

export { filterIcons, iconIndex };
