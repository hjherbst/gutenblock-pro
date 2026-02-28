/**
 * Teaser v2 - Frontend Script
 */

(function () {
	'use strict';

	function initTeaserV2() {
		const elements = document.querySelectorAll('.gb-pattern-teaser-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTeaserV2);
	} else {
		initTeaserV2();
	}
})();
