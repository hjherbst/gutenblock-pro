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
		let licenseKey = input.val().trim().toUpperCase();

		if (!licenseKey) {
			showMessage('#gb-license-message', 'error', 'Bitte Lizenzschlüssel eingeben');
			return;
		}

		// Normalize: Remove any extra spaces and invalid characters
		licenseKey = licenseKey.replace(/\s+/g, '').replace(/[^A-Z0-9-]/g, '');

		// Validate format: GBPRO-XXXX-XXXX-XXXX (each segment exactly 4 chars, total 20 chars)
		const pattern = /^GBPRO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/;
		if (!pattern.test(licenseKey)) {
			const parts = licenseKey.split('-');
			let errorMsg = 'Ungültiges Format. Erwartet: GBPRO-XXXX-XXXX-XXXX';
			
			// Provide helpful error message
			if (licenseKey.length < 20) {
				errorMsg = 'Lizenzschlüssel unvollständig. Bitte alle Zeichen eingeben (20 Zeichen).';
			} else if (parts.length !== 4) {
				errorMsg = 'Ungültiges Format. Bitte Format verwenden: GBPRO-XXXX-XXXX-XXXX';
			} else if (parts[1]?.length !== 4 || parts[2]?.length !== 4 || parts[3]?.length !== 4) {
				errorMsg = 'Jedes Segment muss genau 4 Zeichen haben.';
			}
			
			console.error('License key validation failed:', {
				original: input.val(),
				normalized: licenseKey,
				length: licenseKey.length,
				parts: parts,
				partsLengths: parts.map(p => p?.length),
				patternMatch: pattern.test(licenseKey)
			});
			
			showMessage('#gb-license-message', 'error', errorMsg);
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
		
		// Auto-add dashes at correct positions
		if (value.length > 5 && value.charAt(5) !== '-') {
			value = value.slice(0, 5) + '-' + value.slice(5);
		}
		if (value.length > 10 && value.charAt(10) !== '-') {
			value = value.slice(0, 10) + '-' + value.slice(10);
		}
		if (value.length > 15 && value.charAt(15) !== '-') {
			value = value.slice(0, 15) + '-' + value.slice(15);
		}
		
		// Allow up to 20 characters (GBPRO-XXXX-XXXX-XXXX)
		if (value.length > 20) {
			value = value.slice(0, 20);
		}
		$(this).val(value);
	});

	/**
	 * Adjust custom prompt textarea height to match API prompt height
	 */
	function adjustTextareaHeights() {
		$('.gb-prompts-table tbody tr').each(function() {
			const $row = $(this);
			const $apiPrompt = $row.find('.gb-prompt-text');
			const $customPrompt = $row.find('.gb-custom-prompt');
			
			if ($apiPrompt.length && $customPrompt.length) {
				// Get the height of the API prompt cell
				const apiPromptHeight = $apiPrompt.outerHeight();
				
				// Set the textarea height to match (accounting for padding)
				if (apiPromptHeight > 0) {
					$customPrompt.css('height', apiPromptHeight + 'px');
				}
			}
		});
	}

	// Adjust heights on page load
	$(document).ready(function() {
		adjustTextareaHeights();
	});

	// Adjust heights when window is resized
	$(window).on('resize', adjustTextareaHeights);

	/**
	 * Save Custom Prompts
	 */
	$('#gb-save-custom-prompts').on('click', function() {
		const button = $(this);
		const originalText = button.text();
		
		// Collect all custom prompts
		const customPrompts = {};
		$('.gb-custom-prompt').each(function() {
			const fieldId = $(this).data('field-id');
			const prompt = $(this).val().trim();
			if (fieldId && prompt) {
				customPrompts[fieldId] = prompt;
			}
		});

		button.prop('disabled', true).text('Speichere...');

		$.post(ajaxUrl, {
			action: 'gutenblock_pro_save_custom_prompts',
			nonce: nonce,
			custom_prompts: customPrompts
		})
		.done(function(response) {
			if (response.success) {
				showMessage('#gb-prompts-message', 'success', response.data.message + ' (' + response.data.count + ' Prompts)');
				// Re-adjust heights after save
				setTimeout(adjustTextareaHeights, 100);
			} else {
				showMessage('#gb-prompts-message', 'error', response.data.message);
			}
		})
		.fail(function() {
			showMessage('#gb-prompts-message', 'error', 'Netzwerkfehler beim Speichern');
		})
		.always(function() {
			button.prop('disabled', false).text(originalText);
		});
	});

	// Add spinning animation for update icon
	$('<style>')
		.text('.dashicons.spinning { animation: dashicons-spin 1s linear infinite; } @keyframes dashicons-spin { 100% { transform: rotate(360deg); } }')
		.appendTo('head');

})(jQuery);
