/**
 * FAQ v4 - Frontend Script
 */

(function () {
	'use strict';

	function initFaqV4() {
		const elements = document.querySelectorAll('.gb-pattern-faq-v4');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFaqV4);
	} else {
		initFaqV4();
	}
})();
