/**
 * Hero v1 - Frontend Script
 */

(function () {
	'use strict';

	function initHeroV1() {
		const elements = document.querySelectorAll('.gb-pattern-hero-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initHeroV1);
	} else {
		initHeroV1();
	}
})();
