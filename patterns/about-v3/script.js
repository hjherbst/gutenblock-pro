/**
 * About v3 - Frontend Script
 */

(function () {
	'use strict';

	function initAboutV3() {
		const elements = document.querySelectorAll('.gb-pattern-about-v3');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAboutV3);
	} else {
		initAboutV3();
	}
})();
