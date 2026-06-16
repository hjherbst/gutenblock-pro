/**
 * FAQ Slide-Animation für .is-style-faq und .is-style-faq-plus Details-Blöcke.
 *
 * Interceptet den nativen Details-Toggle und animiert die Höhe
 * des .gbp-faq-body-Wrappers (via PHP hinzugefügt).
 */
( function () {
	'use strict';

	var TRANSITION = 'height 0.35s ease, margin-top 0.35s ease, margin-bottom 0.35s ease';

	function getTargetMargins( body ) {
		var prevTop = body.style.marginTop;
		var prevBottom = body.style.marginBottom;

		body.style.marginTop = '';
		body.style.marginBottom = '';

		var styles = getComputedStyle( body );
		var margins = {
			top: parseFloat( styles.marginTop ) || 0,
			bottom: parseFloat( styles.marginBottom ) || 0,
		};

		body.style.marginTop = prevTop;
		body.style.marginBottom = prevBottom;

		return margins;
	}

	function setClosed( body ) {
		body.style.height = '0px';
		body.style.overflow = 'hidden';
		body.style.marginTop = '0px';
		body.style.marginBottom = '0px';
	}

	function initFaqItem( details ) {
		var summary = details.querySelector( ':scope > summary' );
		var body    = details.querySelector( ':scope > .gbp-faq-body' );

		if ( ! summary || ! body || details.dataset.gbpFaqInit ) return;
		details.dataset.gbpFaqInit = '1';

		if ( ! details.open ) {
			setClosed( body );
		}

		summary.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			if ( details.open ) {
				var margins = getTargetMargins( body );

				body.style.height = body.scrollHeight + 'px';
				body.style.overflow = 'hidden';
				body.offsetHeight; // Reflow erzwingen
				body.style.transition = TRANSITION;
				body.style.height = '0px';
				body.style.marginTop = '0px';
				body.style.marginBottom = '0px';

				body.addEventListener( 'transitionend', function onClose( ev ) {
					if ( ev.target !== body || ev.propertyName !== 'height' ) return;
					body.removeEventListener( 'transitionend', onClose );
					details.removeAttribute( 'open' );
					body.style.transition = '';
				} );
			} else {
				details.setAttribute( 'open', '' );
				var targetMargins = getTargetMargins( body );
				var targetH       = body.scrollHeight;

				body.style.height = '0px';
				body.style.marginTop = '0px';
				body.style.marginBottom = '0px';
				body.style.overflow = 'hidden';
				body.offsetHeight; // Reflow erzwingen
				body.style.transition = TRANSITION;
				body.style.height = targetH + 'px';
				body.style.marginTop = targetMargins.top + 'px';
				body.style.marginBottom = targetMargins.bottom + 'px';

				body.addEventListener( 'transitionend', function onOpen( ev ) {
					if ( ev.target !== body || ev.propertyName !== 'height' ) return;
					body.removeEventListener( 'transitionend', onOpen );
					body.style.height = 'auto';
					body.style.overflow = '';
					body.style.marginTop = '';
					body.style.marginBottom = '';
					body.style.transition = '';
				} );
			}
		} );
	}

	function init() {
		document.querySelectorAll( '.wp-block-details.is-style-faq, .wp-block-details.is-style-faq-plus' ).forEach( initFaqItem );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
