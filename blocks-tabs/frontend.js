/**
 * GutenBlock Pro – Reiter (Tabs) block, frontend behaviour.
 *
 * Progressive enhancement: without JS every panel stays reachable (only the
 * first is visible via markup). With JS we get an accessible ARIA tab widget
 * incl. keyboard navigation (Arrow keys, Home, End).
 */
( function () {
	'use strict';

	function activate( tabs, tablist, panels, targetIndex, setFocus ) {
		tabs.forEach( function ( tab, index ) {
			var selected = index === targetIndex;
			tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', selected ? '0' : '-1' );
			tab.classList.toggle( 'is-active', selected );
			if ( selected ) {
				if ( setFocus ) {
					tab.focus();
				}
				// Keep the active pill visible in the horizontally scrolling nav.
				if ( typeof tab.scrollIntoView === 'function' ) {
					tab.scrollIntoView( { block: 'nearest', inline: 'center' } );
				}
			}
		} );

		panels.forEach( function ( panel, index ) {
			var selected = index === targetIndex;
			panel.classList.toggle( 'is-active', selected );
			if ( selected ) {
				panel.removeAttribute( 'hidden' );
			} else {
				panel.setAttribute( 'hidden', '' );
			}
		} );
	}

	function initTabs( root ) {
		var tablist = root.querySelector( '.gbp-tabs__nav' );
		var panelsWrap = root.querySelector( '.gbp-tabs__panels' );
		if ( ! tablist || ! panelsWrap ) {
			return;
		}

		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '.gbp-tabs__tab' ) );
		var panels = Array.prototype.slice.call( panelsWrap.querySelectorAll( '.gbp-tabs__panel' ) );
		if ( tabs.length === 0 ) {
			return;
		}

		// "Next tab" links inside the panels (mobile affordance).
		var nextLinks = Array.prototype.slice.call( panelsWrap.querySelectorAll( '.gbp-tabs__next' ) );
		nextLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				var target = parseInt( link.getAttribute( 'data-gbp-tab-target' ), 10 );
				if ( isNaN( target ) || target < 0 || target >= tabs.length ) {
					return;
				}
				activate( tabs, tablist, panels, target, false );
				// Bring the top of the tab widget back into view so the reader
				// starts at the beginning of the freshly revealed panel.
				if ( typeof root.scrollIntoView === 'function' ) {
					root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} );
		} );

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				activate( tabs, tablist, panels, index, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var newIndex = null;
				switch ( event.key ) {
					case 'ArrowRight':
					case 'ArrowDown':
						newIndex = ( index + 1 ) % tabs.length;
						break;
					case 'ArrowLeft':
					case 'ArrowUp':
						newIndex = ( index - 1 + tabs.length ) % tabs.length;
						break;
					case 'Home':
						newIndex = 0;
						break;
					case 'End':
						newIndex = tabs.length - 1;
						break;
					default:
						return;
				}
				event.preventDefault();
				activate( tabs, tablist, panels, newIndex, true );
			} );
		} );
	}

	function initAll() {
		var roots = document.querySelectorAll( '.gbp-tabs' );
		Array.prototype.forEach.call( roots, initTabs );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
