/**
 * Offer v2 - Frontend Script
 */

(function () {
	'use strict';

	function initOfferV2() {
		const elements = document.querySelectorAll('.gb-pattern-offer-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initOfferV2);
	} else {
		initOfferV2();
	}
})();
