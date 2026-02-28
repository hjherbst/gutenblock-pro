/**
 * Teaser v5 - Frontend Script
 */

(function () {
	'use strict';

	function initTeaserV5() {
		const elements = document.querySelectorAll('.gb-pattern-teaser-v5');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTeaserV5);
	} else {
		initTeaserV5();
	}
})();
