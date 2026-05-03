/**
 * Offer v3 - Frontend Script
 */

(function () {
	'use strict';

	function initOfferV3() {
		const elements = document.querySelectorAll('.gb-pattern-offer-v3');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initOfferV3);
	} else {
		initOfferV3();
	}
})();
