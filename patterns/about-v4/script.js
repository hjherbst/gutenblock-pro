/**
 * About v4 - Frontend Script
 */

(function () {
	'use strict';

	function initAboutV4() {
		const elements = document.querySelectorAll('.gb-section-about-v4');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAboutV4);
	} else {
		initAboutV4();
	}
})();
