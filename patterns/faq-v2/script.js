/**
 * FAQ v2 - Frontend Script
 */

(function () {
	'use strict';

	function initFaqV2() {
		const elements = document.querySelectorAll('.gb-pattern-faq-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFaqV2);
	} else {
		initFaqV2();
	}
})();
