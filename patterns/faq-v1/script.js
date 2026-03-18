/**
 * FAQ v1 - Frontend Script
 */

(function () {
	'use strict';

	function initFaqV1() {
		const elements = document.querySelectorAll('.gb-pattern-faq-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFaqV1);
	} else {
		initFaqV1();
	}
})();
