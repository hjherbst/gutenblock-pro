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
		const textarea = document.getElementById('gutenblock-pro-code-editor');
		if (!textarea) return;

		const pattern = textarea.dataset.pattern;
		const file = textarea.dataset.file;
		const type = textarea.dataset.type;

		// Load file content
		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_get_file_content',
				nonce: gutenblockProAdmin.nonce,
				pattern: pattern,
				file: file,
			},
			success: function (response) {
				if (response.success) {
					textarea.value = response.data.content;

					// Initialize CodeMirror
					const editorSettings = wp.codeEditor.defaultSettings
						? _.clone(wp.codeEditor.defaultSettings)
						: {};

					editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
						mode: type === 'javascript' ? 'javascript' : type === 'html' ? 'htmlmixed' : 'css',
						lineNumbers: true,
						lineWrapping: true,
						indentUnit: 2,
						tabSize: 2,
					});

					codeEditor = wp.codeEditor.initialize(textarea, editorSettings);
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

		const pattern = textarea.dataset.pattern;
		const file = textarea.dataset.file;
		const content = codeEditor.codemirror.getValue();
		const $status = $('.save-status');
		const $button = $('#save-file');

		$button.prop('disabled', true);
		$status.removeClass('error').text('Speichert...');

		$.ajax({
			url: gutenblockProAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gutenblock_pro_save_file',
				nonce: gutenblockProAdmin.nonce,
				pattern: pattern,
				file: file,
				content: content,
			},
			success: function (response) {
				$button.prop('disabled', false);
				if (response.success) {
					$status.text(gutenblockProAdmin.strings.saved + ' (' + response.data.size + ')');
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
	 * Keyboard shortcut for saving (Ctrl+S / Cmd+S)
	 */
	function handleKeyboardShortcuts(e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			saveFile();
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
	$(document).ready(function () {
		// Initialize code editor
		initCodeEditor();

		// Save button click
		$('#save-file').on('click', saveFile);

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

		// Keyboard shortcuts
		$(document).on('keydown', handleKeyboardShortcuts);
	});
})(jQuery);

