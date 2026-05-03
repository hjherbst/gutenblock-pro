/**
 * GutenBlock Pro - Admin JavaScript
 */

(function ($) {
	'use strict';

	let codeEditor = null;

	/**
	 * Initialize CodeMirror editor
	 */
	function initCodeEditor() {
		if (document.getElementById('gutenblock-pro-pattern-meta-panel')) {
			return;
		}
		const textarea = document.getElementById('gutenblock-pro-code-editor');
		if (!textarea) return;

		const type = textarea.dataset.type || 'pattern';
		const pattern = textarea.dataset.pattern;
		const block = textarea.dataset.block;
		const file = textarea.dataset.file;
		const fileType = textarea.dataset.fileType || (file === 'script' ? 'javascript' : (file === 'content' ? 'html' : 'css'));

		// Build AJAX data
		const ajaxData = {
			action: 'gutenblock_pro_get_file_content',
			nonce: gutenblockProAdmin.nonce,
			type: type,
			file: file,
		};

		if (type === 'block') {
			ajaxData.block = block;
		} else {
			ajaxData.pattern = pattern;
		}

		// Load file content
		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: ajaxData,
			success: function (response) {
				if (response.success) {
					textarea.value = response.data.content;

					// Initialize CodeMirror
					const editorSettings = wp.codeEditor.defaultSettings
						? _.clone(wp.codeEditor.defaultSettings)
						: {};

					editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
						mode: fileType === 'javascript' ? 'javascript' : fileType === 'html' ? 'htmlmixed' : 'css',
						lineNumbers: true,
						lineWrapping: true,
						indentUnit: 2,
						tabSize: 2,
					});

					codeEditor = wp.codeEditor.initialize(textarea, editorSettings);

					// Show "Angepasst" indicator when custom overrides exist
					if (response.data.has_custom) {
						$('.custom-indicator').show();
					}
				}
			},
		});
	}

	/**
	 * Save file content
	 */
	function saveFile() {
		const textarea = document.getElementById('gutenblock-pro-code-editor');
		if (!textarea || !codeEditor) return;

		const type = textarea.dataset.type || 'pattern';
		const pattern = textarea.dataset.pattern;
		const block = textarea.dataset.block;
		const file = textarea.dataset.file;
		const content = codeEditor.codemirror.getValue();
		const $status = $('.save-status');
		const $button = $('#save-file');

		$button.prop('disabled', true);
		$status.removeClass('error').text('Speichert...');

		// Build AJAX data
		const ajaxData = {
			action: 'gutenblock_pro_save_file',
			nonce: gutenblockProAdmin.nonce,
			type: type,
			file: file,
			content: content,
		};

		if (type === 'block') {
			ajaxData.block = block;
		} else {
			ajaxData.pattern = pattern;
		}

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: ajaxData,
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success) {
					$status.text(gutenblockProAdmin.strings.saved + ' (' + response.data.size + ')');
					setTimeout(function () {
						$status.text('');
					}, 3000);
					$('.custom-indicator').show();
				} else {
					$status.addClass('error').text(gutenblockProAdmin.strings.error);
				}
			},
			error: function () {
				$button.prop('disabled', false);
				$status.addClass('error').text(gutenblockProAdmin.strings.error);
			},
		});
	}

	function savePatternMeta() {
		const panel = document.getElementById('gutenblock-pro-pattern-meta-panel');
		if (!panel) return;

		const pattern = panel.getAttribute('data-pattern');
		const $status = $('.gutenblock-pro-meta-actions .save-status');
		const $button = $('#save-pattern-meta');

		$button.prop('disabled', true);
		$status.removeClass('error').text('Speichert...');

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_save_pattern_meta',
				nonce: gutenblockProAdmin.nonce,
				pattern: pattern,
				title: $('#gbp-meta-title').val(),
				description: $('#gbp-meta-description').val(),
				ai_hint: $('#gbp-meta-ai-hint').val(),
				type: $('#gbp-meta-type').val(),
				group: $('#gbp-meta-group').val(),
				keywords: $('#gbp-meta-keywords').val(),
				premium: $('#gbp-meta-premium').is(':checked') ? '1' : '',
			},
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success) {
					$status.text(gutenblockProAdmin.strings.saved + (response.data.size ? ' (' + response.data.size + ')' : ''));
					$('.gutenblock-pro-meta-custom').show();
					setTimeout(function () {
						$status.text('');
					}, 3000);
				} else {
					$status.addClass('error').text(gutenblockProAdmin.strings.error);
				}
			},
			error: function () {
				$button.prop('disabled', false);
				$status.addClass('error').text(gutenblockProAdmin.strings.error);
			},
		});
	}

	function resetPatternMeta() {
		const $button = $('#reset-pattern-meta');
		const pattern = $button.data('pattern');
		if (!pattern || !confirm(gutenblockProAdmin.strings.confirmResetMeta)) {
			return;
		}
		$button.prop('disabled', true);
		const $status = $('.gutenblock-pro-meta-actions .save-status');
		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_reset_pattern_meta',
				nonce: gutenblockProAdmin.nonce,
				pattern: pattern,
			},
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success) {
					window.location.reload();
				} else {
					$status.addClass('error').text(gutenblockProAdmin.strings.error);
				}
			},
			error: function () {
				$button.prop('disabled', false);
				$status.addClass('error').text(gutenblockProAdmin.strings.error);
			},
		});
	}

	/**
	 * Toggle pattern premium status
	 */
	$(document).on('change', '.premium-toggle-input', function () {
		const $toggle = $(this);
		const slug = $toggle.data('slug');
		const premium = $toggle.is(':checked');
		const $card = $toggle.closest('.pattern-card');

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_update_premium',
				nonce: gutenblockProAdmin.nonce,
				pattern: slug,
				premium: premium,
			},
			success: function (response) {
				if (response.success) {
					if (premium) {
						if (!$card.find('.premium-badge').length) {
							$card.find('h3').append('<span class="premium-badge" title="Premium Pattern">plus</span>');
						}
					} else {
						$card.find('.premium-badge').remove();
					}
				} else {
					// Revert toggle on error
					$toggle.prop('checked', !premium);
					alert(response.data?.message || 'Fehler beim Aktualisieren');
				}
			},
			error: function () {
				// Revert toggle on error
				$toggle.prop('checked', !premium);
				alert('Fehler beim Aktualisieren');
			},
		});
	});

	/**
	 * Toggle pattern enabled/disabled
	 */
	function togglePattern($toggle) {
		const pattern = $toggle.data('slug');
		const enabled = $toggle.is(':checked');
		const $card = $toggle.closest('.pattern-card');

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_save_settings',
				nonce: gutenblockProAdmin.nonce,
				pattern: pattern,
				enabled: enabled,
			},
			success: function (response) {
				if (response.success) {
					$card.toggleClass('disabled', !enabled);
				} else {
					// Revert toggle on error
					$toggle.prop('checked', !enabled);
				}
			},
			error: function () {
				// Revert toggle on error
				$toggle.prop('checked', !enabled);
			},
		});
	}

	/**
	 * Reset block variant style to plugin default
	 */
	function resetBlockStyle() {
		const $button = $('#reset-block-style');
		const block = $button.data('block');

		if (!block || !confirm(gutenblockProAdmin.strings.confirmReset)) {
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_reset_block_style',
				nonce: gutenblockProAdmin.nonce,
				block: block,
			},
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success && codeEditor) {
					codeEditor.codemirror.setValue(response.data.content);
					$('.custom-indicator').hide();
					$('.save-status').text(gutenblockProAdmin.strings.saved);
					setTimeout(function () {
						$('.save-status').text('');
					}, 3000);
				}
			},
			error: function () {
				$button.prop('disabled', false);
				$('.save-status').addClass('error').text(gutenblockProAdmin.strings.error);
			},
		});
	}

	/**
	 * Reset pattern file to plugin default
	 */
	function resetPatternFile() {
		const $button = $('#reset-pattern-file');
		const pattern = $button.data('pattern');
		const file = $button.data('file');

		if (!pattern || !confirm(gutenblockProAdmin.strings.confirmReset)) {
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_reset_pattern_file',
				nonce: gutenblockProAdmin.nonce,
				pattern: pattern,
				file: file,
			},
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success && codeEditor) {
					codeEditor.codemirror.setValue(response.data.content);
					$('.custom-indicator').hide();
					$('.save-status').text(gutenblockProAdmin.strings.saved);
					setTimeout(function () {
						$('.save-status').text('');
					}, 3000);
				}
			},
			error: function () {
				$button.prop('disabled', false);
				$('.save-status').addClass('error').text(gutenblockProAdmin.strings.error);
			},
		});
	}

	/**
	 * Adopt current editor content as plugin original (dev mode only)
	 */
	function adoptAsOriginal() {
		const $button = $('#adopt-as-original');
		const type = $button.data('type');
		const item = $button.data('item');
		const file = $button.data('file');

		if (!item || !codeEditor) return;
		if (!confirm(gutenblockProAdmin.strings.confirmAdopt)) return;

		const content = codeEditor.codemirror.getValue();
		const $status = $('.save-status');

		$button.prop('disabled', true);
		$status.removeClass('error').text('…');

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_adopt_as_original',
				nonce: gutenblockProAdmin.nonce,
				type: type,
				item: item,
				file: file,
				content: content,
			},
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success) {
					$('.custom-indicator').hide();
					$status.text(gutenblockProAdmin.strings.adopted + ' (' + response.data.size + ')');
					setTimeout(function () { $status.text(''); }, 3000);
				} else {
					$status.addClass('error').text(gutenblockProAdmin.strings.error);
				}
			},
			error: function () {
				$button.prop('disabled', false);
				$status.addClass('error').text(gutenblockProAdmin.strings.error);
			},
		});
	}

	/**
	 * Keyboard shortcut for saving (Ctrl+S / Cmd+S)
	 */
	function handleKeyboardShortcuts(e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			if (document.getElementById('gutenblock-pro-pattern-meta-panel')) {
				savePatternMeta();
			} else {
				saveFile();
			}
		}
	}

	/**
	 * Delete pattern
	 */
	function deletePattern($button) {
		const slug = $button.data('slug');
		const name = $button.data('name');

		if (!confirm('Pattern "' + name + '" wirklich löschen?\n\nDieser Vorgang kann nicht rückgängig gemacht werden!')) {
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_delete_pattern',
				nonce: gutenblockProAdmin.nonce,
				pattern: slug,
			},
			success: function (response) {
				if (response.success) {
					// Remove the card from DOM with animation
					$button.closest('.pattern-card').fadeOut(300, function () {
						$(this).remove();
					});
				} else {
					alert('Fehler: ' + (response.data?.message || 'Unbekannter Fehler'));
					$button.prop('disabled', false);
				}
			},
			error: function () {
				alert('Fehler beim Löschen des Patterns');
				$button.prop('disabled', false);
			},
		});
	}

	/**
	 * Update pattern group
	 */
	function updateGroup($select) {
		const slug = $select.data('slug');
		const group = $select.val();
		const $card = $select.closest('.pattern-card');

		$select.prop('disabled', true);

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_update_group',
				nonce: gutenblockProAdmin.nonce,
				pattern: slug,
				group: group,
			},
			success: function (response) {
				$select.prop('disabled', false);
				if (response.success) {
					// Brief flash to indicate success
					$select.css('background-color', '#d4edda');
					setTimeout(function () {
						$select.css('background-color', '');
					}, 500);
				} else {
					alert('Fehler: ' + (response.data?.message || 'Unbekannter Fehler'));
				}
			},
			error: function () {
				$select.prop('disabled', false);
				alert('Fehler beim Speichern der Gruppe');
			},
		});
	}

	/**
	 * Initialize
	 */
	/**
	 * Tone-Swatches in der Pattern-Übersicht: Hover/Klick wechselt iframe-src.
	 * Speichert die Original-URL aus data-base-url, fügt &tone=X als Query-Param.
	 */
	function bindToneSwatches() {
		$(document).on('mouseenter focus click', '.gbp-tone-swatch', function (e) {
			if (e.type === 'click') {
				e.preventDefault();
				e.stopPropagation();
			}
			const $btn = $(this);
			const $wrap = $btn.closest('.gbp-tone-swatches');
			const slug = $wrap.data('pattern');
			const tone = $btn.data('tone');
			if (!slug || !tone) return;

			// Aktiven Swatch markieren
			$wrap.find('.gbp-tone-swatch').removeClass('is-active');
			$btn.addClass('is-active');

			// iframe der zugehörigen Karte finden
			const $iframe = $wrap.closest('.pattern-card').find('.pattern-card-iframe');
			if (!$iframe.length) return;
			const baseUrl = $iframe.data('base-url') || $iframe.attr('src');
			if (!baseUrl) return;
			// Tone-Parameter ergänzen oder ersetzen
			const sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
			const cleaned = baseUrl.replace(/([?&])tone=[^&]*/g, '');
			const newUrl = cleaned + sep + 'tone=' + encodeURIComponent(tone);
			if ($iframe.attr('src') !== newUrl) {
				$iframe.attr('src', newUrl);
			}
		});
	}

	$(document).ready(function () {
		initCodeEditor();
		bindToneSwatches();

		$('#save-file').on('click', saveFile);
		$('#save-pattern-meta').on('click', savePatternMeta);
		$('#reset-pattern-meta').on('click', resetPatternMeta);

		// Reset buttons
		$('#reset-block-style').on('click', resetBlockStyle);
		$('#reset-pattern-file').on('click', resetPatternFile);

		// Adopt as original (dev mode)
		$('#adopt-as-original').on('click', adoptAsOriginal);

		// Pattern toggle
		$('.pattern-toggle').on('change', function () {
			togglePattern($(this));
		});

		// Delete pattern
		$('.delete-pattern').on('click', function () {
			deletePattern($(this));
		});


		// Group dropdown change
		$('.group-dropdown').on('change', function () {
			updateGroup($(this));
		});

		// Sidebar tab switching
		$('.sidebar-tab').on('click', function () {
			const type = $(this).data('type');
			const currentUrl = new URL(window.location.href);
			currentUrl.searchParams.set('type', type);
			// Remove pattern/block param to reset selection
			currentUrl.searchParams.delete('pattern');
			currentUrl.searchParams.delete('block');
			window.location.href = currentUrl.toString();
		});

		// Keyboard shortcuts
		$(document).on('keydown', handleKeyboardShortcuts);
	});
})(jQuery);

