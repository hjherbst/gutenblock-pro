/**
 * Teaser Grid v4 - Frontend Script
 */

(function () {
	'use strict';

	function initTeaserGridV4() {
		const elements = document.querySelectorAll('.gb-pattern-teaser-grid-v4');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTeaserGridV4);
	} else {
		initTeaserGridV4();
	}
})();
