/**
 * Teaser v4 - Frontend Script
 */

(function () {
	'use strict';

	function initTeaserV4() {
		const elements = document.querySelectorAll('.gb-pattern-teaser-v4');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTeaserV4);
	} else {
		initTeaserV4();
	}
})();
