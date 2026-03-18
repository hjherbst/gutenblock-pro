/**
 * Benefits v4 - Frontend Script
 */

(function () {
	'use strict';

	function initBenefitsV4() {
		const elements = document.querySelectorAll('.gb-pattern-benefits-v4');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBenefitsV4);
	} else {
		initBenefitsV4();
	}
})();
