/**
 * Hero v2 - Frontend Script
 */

(function () {
	'use strict';

	function initHeroV2() {
		const elements = document.querySelectorAll('.gb-pattern-hero-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initHeroV2);
	} else {
		initHeroV2();
	}
})();
