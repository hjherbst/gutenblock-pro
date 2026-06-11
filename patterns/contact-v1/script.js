/**
 * Contact v1 - Frontend Script
 */

(function () {
	'use strict';

	function initContactV1() {
		const elements = document.querySelectorAll('.gb-pattern-contact-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initContactV1);
	} else {
		initContactV1();
	}
})();
