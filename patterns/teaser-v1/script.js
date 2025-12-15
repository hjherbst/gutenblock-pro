/**
 * Teaser v1 - Frontend Script
 */

(function () {
	'use strict';

	function initTeaserV1() {
		const elements = document.querySelectorAll('.gb-pattern-teaser-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTeaserV1);
	} else {
		initTeaserV1();
	}
})();
