/**
 * Teaser Grid v2 - Frontend Script
 */

(function () {
	'use strict';

	function initTeaserGridV2() {
		const elements = document.querySelectorAll('.gb-pattern-teaser-grid-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTeaserGridV2);
	} else {
		initTeaserGridV2();
	}
})();
