/**
 * Text Columns v2 - Frontend Script
 */

(function () {
	'use strict';

	function initTextColumnsV2() {
		const elements = document.querySelectorAll('.gb-pattern-text-columns-v2');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTextColumnsV2);
	} else {
		initTextColumnsV2();
	}
})();
