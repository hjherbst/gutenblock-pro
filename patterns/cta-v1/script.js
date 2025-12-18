/**
 * Cta v1 - Frontend Script
 */

(function () {
	'use strict';

	function initCtaV1() {
		const elements = document.querySelectorAll('.gb-pattern-cta-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCtaV1);
	} else {
		initCtaV1();
	}
})();
