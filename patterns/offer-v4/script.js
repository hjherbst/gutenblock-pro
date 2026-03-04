/**
 * Offer v4 - Frontend Script
 */

(function () {
	'use strict';

	function initOfferV4() {
		const elements = document.querySelectorAll('.gb-pattern-offer-v4');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initOfferV4);
	} else {
		initOfferV4();
	}
})();
