/**
 * About v2 - Frontend Script
 */

(function () {
	'use strict';

	function initAboutV2() {
		const elements = document.querySelectorAll('.gb-pattern-about-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAboutV2);
	} else {
		initAboutV2();
	}
})();
