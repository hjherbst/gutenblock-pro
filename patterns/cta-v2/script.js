/**
 * CTA v2 - Frontend Script
 */

(function () {
	'use strict';

	function initCtaV2() {
		const elements = document.querySelectorAll('.gb-pattern-cta-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCtaV2);
	} else {
		initCtaV2();
	}
})();
