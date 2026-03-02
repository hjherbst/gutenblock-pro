/**
 * GutenBlock Pro - Button styles click animation (Simple, Arrow Circle)
 */
(function () {
	document.querySelectorAll(
		'.wp-block-button.is-style-button-simple .wp-block-button__link, .wp-block-button.is-style-button-arrow-circle .wp-block-button__link'
	).forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			var el = e.currentTarget;
			el.classList.add('fly');
			setTimeout(function () {
				el.classList.remove('fly');
			}, 400);
		});
	});
})();
