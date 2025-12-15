/**
 * GutenBlock Pro - AI Settings JavaScript
 */
(function($) {
	'use strict';

	const { ajaxUrl, nonce, strings } = gutenblockProAI;

	/**
	 * Show message
	 */
	function showMessage(container, type, message) {
		$(container)
			.removeClass('hidden success error info')
			.addClass(type)
			.text(message);
	}

	/**
	 * Activate License
	 */
	$('#gb-activate-license').on('click', function() {
		const button = $(this);
		const input = $('#gb-license-key');
		const licenseKey = input.val().trim().toUpperCase();

		if (!licenseKey) {
			showMessage('#gb-license-message', 'error', 'Bitte Lizenzschlüssel eingeben');
			return;
		}

		// Validate format
		if (!/^GBPRO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(licenseKey)) {
			showMessage('#gb-license-message', 'error', 'Ungültiges Format. Erwartet: GBPRO-XXXX-XXXX-XXXX');
			return;
		}

		button.prop('disabled', true).text(strings.activating);

		$.post(ajaxUrl, {
			action: 'gutenblock_pro_activate_license',
			nonce: nonce,
			license_key: licenseKey
		})
		.done(function(response) {
			if (response.success) {
				showMessage('#gb-license-message', 'success', response.data.message);
				// Reload page to show updated license status
				setTimeout(() => window.location.reload(), 1500);
			} else {
				showMessage('#gb-license-message', 'error', response.data.message);
				button.prop('disabled', false).text('Lizenz aktivieren');
			}
		})
		.fail(function() {
			showMessage('#gb-license-message', 'error', 'Netzwerkfehler. Bitte erneut versuchen.');
			button.prop('disabled', false).text('Lizenz aktivieren');
		});
	});

	/**
	 * Deactivate License
	 */
	$('#gb-deactivate-license').on('click', function() {
		if (!confirm('Lizenz wirklich deaktivieren? Du kannst sie jederzeit erneut aktivieren.')) {
			return;
		}

		const button = $(this);
		button.prop('disabled', true).text(strings.deactivating);

		$.post(ajaxUrl, {
			action: 'gutenblock_pro_deactivate_license',
			nonce: nonce
		})
		.done(function(response) {
			if (response.success) {
				showMessage('#gb-license-message', 'success', response.data.message);
				setTimeout(() => window.location.reload(), 1500);
			} else {
				showMessage('#gb-license-message', 'error', response.data.message);
				button.prop('disabled', false).text('Lizenz deaktivieren');
			}
		})
		.fail(function() {
			showMessage('#gb-license-message', 'error', 'Netzwerkfehler');
			button.prop('disabled', false).text('Lizenz deaktivieren');
		});
	});

	/**
	 * Refresh Prompts
	 */
	$('#gb-refresh-prompts').on('click', function() {
		const button = $(this);
		const originalHtml = button.html();
		
		button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> ' + strings.refreshing);

		$.post(ajaxUrl, {
			action: 'gutenblock_pro_refresh_prompts',
			nonce: nonce
		})
		.done(function(response) {
			if (response.success) {
				showMessage('#gb-prompts-message', 'success', response.data.message + ' (' + response.data.count + ' Prompts)');
				setTimeout(() => window.location.reload(), 1500);
			} else {
				showMessage('#gb-prompts-message', 'error', response.data.message);
			}
		})
		.fail(function() {
			showMessage('#gb-prompts-message', 'error', 'Netzwerkfehler');
		})
		.always(function() {
			button.prop('disabled', false).html(originalHtml);
		});
	});

	/**
	 * Auto-format license key input
	 */
	$('#gb-license-key').on('input', function() {
		let value = $(this).val().toUpperCase().replace(/[^A-Z0-9-]/g, '');
		
		// Auto-add dashes
		if (value.length === 5 && value.charAt(4) !== '-') {
			value = value.slice(0, 5) + '-' + value.slice(5);
		} else if (value.length === 10 && value.charAt(9) !== '-') {
			value = value.slice(0, 10) + '-' + value.slice(10);
		} else if (value.length === 15 && value.charAt(14) !== '-') {
			value = value.slice(0, 15) + '-' + value.slice(15);
		}
		
		$(this).val(value.slice(0, 19)); // Max length: GBPRO-XXXX-XXXX-XXXX
	});

	// Add spinning animation for update icon
	$('<style>')
		.text('.dashicons.spinning { animation: dashicons-spin 1s linear infinite; } @keyframes dashicons-spin { 100% { transform: rotate(360deg); } }')
		.appendTo('head');

})(jQuery);
