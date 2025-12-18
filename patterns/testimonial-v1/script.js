/**
 * Testimonial v1 - Frontend Script
 */

(function () {
	'use strict';

	function initTestimonialV1() {
		const elements = document.querySelectorAll('.gb-pattern-testimonial-v1');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTestimonialV1);
	} else {
		initTestimonialV1();
	}
})();
