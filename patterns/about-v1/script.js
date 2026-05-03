/**
 * About v1 - Frontend Script
 */

(function () {
	'use strict';

	function initAboutV1() {
		const elements = document.querySelectorAll('.gb-pattern-about-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAboutV1);
	} else {
		initAboutV1();
	}
})();
