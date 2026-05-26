/**
 * Text Columns v3 - Frontend Script
 */

(function () {
	'use strict';

	function initTextColumnsV3() {
		const elements = document.querySelectorAll('.gb-pattern-text-columns-v3');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTextColumnsV3);
	} else {
		initTextColumnsV3();
	}
})();
