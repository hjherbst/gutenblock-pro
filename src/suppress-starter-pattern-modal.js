/**
 * Suppress WordPress core "Choose a pattern" on new pages.
 * GutenBlock builds content via the FSE Agent and its own pattern modal.
 *
 * Important: match by pattern-browser UI only — never by modal title.
 * Titles like "Create pattern" / "Muster erstellen" also contain those words
 * and must stay open for the native unsynced-pattern flow.
 */

import { dispatch, select, subscribe } from '@wordpress/data';

const PREF_SCOPE = 'core';
const PREF_KEY = 'enableChoosePatternModal';

const GB_MODAL_MARKERS = [
	'gutenblock-pro-pattern-modal',
	'gutenblock-pro-pattern-creator-modal',
	'gutenblock-material-icon-modal',
];

/** DOM markers that only appear in the starter "Choose a pattern" dialog. */
const STARTER_PATTERN_BROWSER =
	'.block-editor-block-patterns-list, .block-editor-patterns__grid, .block-editor-block-patterns-explorer';

function isGutenBlockModal(frame) {
	if (!frame) {
		return false;
	}
	return GB_MODAL_MARKERS.some(
		(marker) =>
			frame.classList.contains(marker) ||
			frame.closest(`.${marker}`) ||
			frame.querySelector(`.${marker}`)
	);
}

function disableStarterPatternPreference() {
	try {
		const prefs = select('core/preferences');
		const store = dispatch('core/preferences');
		if (!prefs || !store?.set) {
			return;
		}
		if (prefs.get(PREF_SCOPE, PREF_KEY) !== false) {
			store.set(PREF_SCOPE, PREF_KEY, false);
		}
	} catch (e) {
		// preferences store unavailable on older WordPress versions
	}
}

function tryCloseStarterPatternModal(frame) {
	if (!frame || isGutenBlockModal(frame)) {
		return false;
	}

	// Require an actual pattern browser — title matching closed "Create pattern".
	if (!frame.querySelector(STARTER_PATTERN_BROWSER)) {
		return false;
	}

	const closeBtn = frame.querySelector(
		'button.components-modal__header-button, button[aria-label="Close"], button[aria-label="Schließen"]'
	);
	closeBtn?.click();
	return true;
}

function watchForStarterPatternModal() {
	const observer = new MutationObserver(() => {
		document.querySelectorAll('.components-modal__frame').forEach((frame) => {
			tryCloseStarterPatternModal(frame);
		});
	});
	if (document.body) {
		observer.observe(document.body, { childList: true, subtree: true });
	}
}

function initSuppressStarterPatternModal() {
	disableStarterPatternPreference();

	let applied = false;
	const unsub = subscribe(() => {
		if (applied) {
			return;
		}
		try {
			if (select('core/preferences')) {
				disableStarterPatternPreference();
				applied = true;
				unsub();
			}
		} catch (e) {
			// keep waiting for the preferences store
		}
	});

	watchForStarterPatternModal();
}

initSuppressStarterPatternModal();
