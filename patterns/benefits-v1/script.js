/**
 * Benefits v1 - Frontend Script
 */

(function () {
	'use strict';

	function initBenefitsV1() {
		const elements = document.querySelectorAll('.gb-pattern-benefits-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBenefitsV1);
	} else {
		initBenefitsV1();
	}
})();
