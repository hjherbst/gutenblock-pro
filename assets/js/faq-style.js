/**
 * FAQ Slide-Animation für .is-style-faq Details-Blöcke.
 *
 * Interceptet den nativen Details-Toggle und animiert die Höhe
 * des .gbp-faq-body-Wrappers (via PHP hinzugefügt).
 */
( function () {
	'use strict';

	function initFaqItem( details ) {
		var summary = details.querySelector( ':scope > summary' );
		var body    = details.querySelector( ':scope > .gbp-faq-body' );

		if ( ! summary || ! body || details.dataset.gbpFaqInit ) return;
		details.dataset.gbpFaqInit = '1';

		// Initialzustand: geschlossene Details ausblenden
		if ( ! details.open ) {
			body.style.height   = '0px';
			body.style.overflow = 'hidden';
		}

		summary.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			if ( details.open ) {
				// Schließen animieren
				body.style.height   = body.scrollHeight + 'px';
				body.style.overflow = 'hidden';
				body.offsetHeight;  // Reflow erzwingen
				body.style.transition = 'height 0.35s ease';
				body.style.height     = '0px';
				body.addEventListener( 'transitionend', function onClose() {
					body.removeEventListener( 'transitionend', onClose );
					details.removeAttribute( 'open' );
					body.style.transition = '';
				} );
			} else {
				// Öffnen animieren
				details.setAttribute( 'open', '' );
				var targetH           = body.scrollHeight;
				body.style.height     = '0px';
				body.style.overflow   = 'hidden';
				body.offsetHeight;  // Reflow erzwingen
				body.style.transition = 'height 0.35s ease';
				body.style.height     = targetH + 'px';
				body.addEventListener( 'transitionend', function onOpen() {
					body.removeEventListener( 'transitionend', onOpen );
					body.style.height     = 'auto';
					body.style.overflow   = '';
					body.style.transition = '';
				} );
			}
		} );
	}

	function init() {
		document.querySelectorAll( '.wp-block-details.is-style-faq' ).forEach( initFaqItem );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
