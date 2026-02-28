/**
 * GutenBlock Pro – Horizontal Scroll Frontend
 * Handles dots, arrows, and scroll snap for .has-horizontal-scroll columns.
 */
(function() {
	'use strict';

	function getCols( el ) {
		var mq = window.matchMedia( '(max-width: 600px)' );
		if ( mq.matches ) return parseInt( el.dataset.hscrollMobile || '1', 10 );
		mq = window.matchMedia( '(max-width: 781px)' );
		if ( mq.matches ) return parseInt( el.dataset.hscrollTablet || '2', 10 );
		return parseInt( el.dataset.hscrollDesktop || '3', 10 );
	}

	function getColumnCount( el ) {
		return el.querySelectorAll( ':scope > .wp-block-column' ).length;
	}

	function initInstance( wrapper ) {
		var scrollEl = wrapper.querySelector( '.has-horizontal-scroll' );
		if ( ! scrollEl ) return;

		var infinite = scrollEl.dataset.hscrollInfinite === 'true';
		var originalWidth = 0;
		if ( infinite ) {
			var cols = scrollEl.querySelectorAll( ':scope > .wp-block-column' );
			if ( cols.length > 0 ) {
				var fragEnd = document.createDocumentFragment();
				var fragStart = document.createDocumentFragment();
				for ( var i = 0; i < cols.length; i++ ) {
					fragEnd.appendChild( cols[i].cloneNode( true ) );
					fragStart.appendChild( cols[i].cloneNode( true ) );
				}
				originalWidth = scrollEl.scrollWidth;
				scrollEl.appendChild( fragEnd );
				scrollEl.insertBefore( fragStart, scrollEl.firstChild );
				scrollEl.scrollLeft = originalWidth;
			}
		}

		var dots = wrapper.querySelector( '.gb-hscroll-dots' );
		var prevBtn = wrapper.querySelector( '.gb-hscroll-prev' );
		var nextBtn = wrapper.querySelector( '.gb-hscroll-next' );
		var columns = scrollEl.querySelectorAll( ':scope > .wp-block-column' );
		var colCount = infinite ? columns.length / 3 : columns.length;
		if ( colCount === 0 ) return;

		var colsVisible = getCols( scrollEl );
		var dotCount = Math.max( 1, Math.ceil( colCount / colsVisible ) );

		function updateDots() {
			if ( ! dots ) return;
			dots.innerHTML = '';
			var scrollLeft = scrollEl.scrollLeft;
			if ( infinite && originalWidth > 0 ) {
				scrollLeft = ( ( scrollLeft - originalWidth ) % originalWidth + originalWidth ) % originalWidth;
			}
			var firstCol = infinite ? columns[colCount] : columns[0];
			var secondCol = infinite ? columns[colCount + 1] : columns[1];
			var firstWidth = firstCol ? firstCol.offsetWidth : 0;
			var gap = firstCol && secondCol ? ( secondCol.getBoundingClientRect().left - firstCol.getBoundingClientRect().right ) : 0;
			var step = firstWidth + gap;
			var idx = step > 0 ? Math.round( scrollLeft / step ) : 0;
			idx = Math.min( idx, dotCount - 1 );
			idx = Math.max( 0, idx );

			for ( var i = 0; i < dotCount; i++ ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = i === idx ? 'is-active' : '';
				btn.setAttribute( 'aria-label', 'Seite ' + ( i + 1 ) );
				btn.setAttribute( 'aria-current', i === idx ? 'true' : 'false' );
				btn.addEventListener( 'click', function( pageIndex ) {
					return function() {
						var targetScroll = pageIndex * step * colsVisible;
						if ( infinite && originalWidth > 0 ) {
							targetScroll += originalWidth;
						}
						scrollEl.scrollTo( { left: targetScroll, behavior: 'smooth' } );
					};
				}( i ) );
				dots.appendChild( btn );
			}
		}

		function onScroll() {
			if ( ! dots ) return;
			var scrollLeft = scrollEl.scrollLeft;
			if ( infinite && originalWidth > 0 ) {
				scrollLeft = ( ( scrollLeft - originalWidth ) % originalWidth + originalWidth ) % originalWidth;
			}
			var firstCol = infinite ? columns[colCount] : columns[0];
			var secondCol = infinite ? columns[colCount + 1] : columns[1];
			var firstWidth = firstCol ? firstCol.offsetWidth : 0;
			var gap = firstCol && secondCol ? ( secondCol.getBoundingClientRect().left - firstCol.getBoundingClientRect().right ) : 0;
			var step = firstWidth + gap;
			if ( step <= 0 ) return;
			var idx = Math.round( scrollLeft / step );
			idx = Math.min( idx, dotCount - 1 );
			idx = Math.max( 0, idx );
			var btns = dots.querySelectorAll( 'button' );
			btns.forEach( function( b, i ) {
				b.classList.toggle( 'is-active', i === idx );
				b.setAttribute( 'aria-current', i === idx ? 'true' : 'false' );
			} );
		}

		if ( infinite && originalWidth > 0 ) {
			scrollEl.addEventListener( 'scroll', function() {
				var sl = scrollEl.scrollLeft;
				if ( sl >= 2 * originalWidth ) {
					scrollEl.scrollLeft = sl - originalWidth;
				} else if ( sl <= 0 ) {
					scrollEl.scrollLeft = sl + originalWidth;
				}
			} );
		}

		if ( dots ) {
			updateDots();
			scrollEl.addEventListener( 'scroll', onScroll );
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function() {
				var w = scrollEl.offsetWidth;
				scrollEl.scrollBy( { left: -w, behavior: 'smooth' } );
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function() {
				var w = scrollEl.offsetWidth;
				scrollEl.scrollBy( { left: w, behavior: 'smooth' } );
			} );
		}
	}

	function init() {
		var wrappers = document.querySelectorAll( '.gb-hscroll-wrapper' );
		wrappers.forEach( initInstance );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
