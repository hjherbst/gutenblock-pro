/**
 * GutenBlock Pro – Consent Manager (frontend).
 *
 * Renders a lean consent banner with two categories (analytics, marketing),
 * persists the decision in the `gb_consent` cookie, and loads tracking scripts
 * only after the matching category is granted. Nothing is requested before
 * consent. When a GTM container is configured the banner loads GTM only;
 * otherwise it loads the direct snippets (GA4, Meta, Google Ads, LinkedIn).
 *
 * Any element with `data-gbp-consent="open"` or the CSS class `.consent-settings`
 * (e.g. a footer link or a link inside the privacy policy) re-opens the banner
 * straight in its settings view so the visitor can change or withdraw consent
 * at any time.
 */
(function () {
	'use strict';

	var cfg = window.gutenblockProConsent;
	if (!cfg) {
		return;
	}

	var DENIED = { analytics: false, marketing: false };
	var loaded = { analytics: false, marketing: false, gtm: false };

	// ── Cookie helpers ──────────────────────────────────────────────────────

	function readConsent() {
		var match = document.cookie.match(
			new RegExp('(?:^|; )' + cfg.cookieName + '=([^;]*)')
		);
		if (!match) {
			return null;
		}
		try {
			var parsed = JSON.parse(decodeURIComponent(match[1]));
			return {
				analytics: parsed.analytics === true,
				marketing: parsed.marketing === true,
				ts: typeof parsed.ts === 'number' ? parsed.ts : 0,
			};
		} catch (e) {
			return null;
		}
	}

	function writeConsent(state) {
		var value = JSON.stringify({
			analytics: state.analytics === true,
			marketing: state.marketing === true,
			ts: Date.now(),
		});
		var expires = new Date(
			Date.now() + cfg.ttlDays * 24 * 60 * 60 * 1000
		).toUTCString();
		var secure = location.protocol === 'https:' ? '; Secure' : '';
		document.cookie =
			cfg.cookieName +
			'=' +
			encodeURIComponent(value) +
			'; Expires=' +
			expires +
			'; Path=/; SameSite=Lax' +
			secure;
	}

	// ── gtag / dataLayer ────────────────────────────────────────────────────

	function gtag() {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push(arguments);
	}

	function updateConsentMode(state) {
		if (!cfg.consentMode) {
			return;
		}
		gtag('consent', 'update', {
			analytics_storage: state.analytics ? 'granted' : 'denied',
			ad_storage: state.marketing ? 'granted' : 'denied',
			ad_user_data: state.marketing ? 'granted' : 'denied',
			ad_personalization: state.marketing ? 'granted' : 'denied',
		});
	}

	// ── Script loaders ──────────────────────────────────────────────────────

	function injectScript(src, async) {
		var el = document.createElement('script');
		el.src = src;
		el.async = async !== false;
		document.head.appendChild(el);
		return el;
	}

	function loadGtm() {
		if (loaded.gtm || !cfg.gtmId) {
			return;
		}
		loaded.gtm = true;
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			'gtm.start': Date.now(),
			event: 'gtm.js',
		});
		injectScript(
			'https://www.googletagmanager.com/gtm.js?id=' +
				encodeURIComponent(cfg.gtmId)
		);
	}

	function loadGa4() {
		if (loaded.analytics || !cfg.ga4Id) {
			return;
		}
		loaded.analytics = true;
		injectScript(
			'https://www.googletagmanager.com/gtag/js?id=' +
				encodeURIComponent(cfg.ga4Id)
		);
		gtag('js', new Date());
		gtag('config', cfg.ga4Id);
	}

	function loadMetaPixel() {
		if (!cfg.metaPixelId) {
			return;
		}
		/* eslint-disable */
		!(function (f, b, e, v, n, t, s) {
			if (f.fbq) return;
			n = f.fbq = function () {
				n.callMethod
					? n.callMethod.apply(n, arguments)
					: n.queue.push(arguments);
			};
			if (!f._fbq) f._fbq = n;
			n.push = n;
			n.loaded = !0;
			n.version = '2.0';
			n.queue = [];
			t = b.createElement(e);
			t.async = !0;
			t.src = v;
			s = b.getElementsByTagName(e)[0];
			s.parentNode.insertBefore(t, s);
		})(
			window,
			document,
			'script',
			'https://connect.facebook.net/en_US/fbevents.js'
		);
		/* eslint-enable */
		window.fbq('init', cfg.metaPixelId);
		window.fbq('track', 'PageView');
	}

	function loadGoogleAds() {
		if (!cfg.googleAdsId) {
			return;
		}
		// Reuse gtag.js (shared with GA4); load the library if GA4 didn't.
		if (!loaded.analytics) {
			injectScript(
				'https://www.googletagmanager.com/gtag/js?id=' +
					encodeURIComponent(cfg.googleAdsId)
			);
			gtag('js', new Date());
		}
		gtag('config', cfg.googleAdsId);
		if (cfg.googleAdsLabel) {
			gtag('event', 'conversion', {
				send_to: cfg.googleAdsId + '/' + cfg.googleAdsLabel,
			});
		}
	}

	function loadLinkedIn() {
		if (!cfg.linkedinPartnerId) {
			return;
		}
		window._linkedin_partner_id = cfg.linkedinPartnerId;
		window._linkedin_data_partner_ids =
			window._linkedin_data_partner_ids || [];
		window._linkedin_data_partner_ids.push(cfg.linkedinPartnerId);
		injectScript('https://snap.licdn.com/li.lms-analytics/insight.min.js');
	}

	function applyConsent(state) {
		updateConsentMode(state);

		// GTM path: a single container manages all tags. It is loaded once any
		// category is granted; tag firing inside GTM respects Consent Mode.
		if (cfg.useGtm) {
			if (state.analytics || state.marketing) {
				loadGtm();
			}
			return;
		}

		// Direct path.
		if (state.analytics) {
			loadGa4();
		}
		if (state.marketing) {
			loadMetaPixel();
			loadGoogleAds();
			loadLinkedIn();
			loaded.marketing = true;
		}
	}

	// ── Banner UI ───────────────────────────────────────────────────────────

	var bannerEl = null;
	var backdropEl = null;

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text != null) {
			node.textContent = text;
		}
		return node;
	}

	function buildBanner(initial) {
		var s = cfg.strings;

		var overlay = el('div', 'gbp-consent');
		var box = el('div', 'gbp-consent__box');
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-live', 'polite');
		box.setAttribute('aria-label', s.title);

		box.appendChild(el('h2', 'gbp-consent__title', s.title));

		var body = el('p', 'gbp-consent__text', s.body);
		if (cfg.privacyUrl) {
			body.appendChild(document.createTextNode(' '));
			var link = el('a', 'gbp-consent__link', s.privacy);
			link.href = cfg.privacyUrl;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			body.appendChild(link);
		}
		box.appendChild(body);

		// Category toggles (hidden until "Settings" is opened).
		var options = el('div', 'gbp-consent__options');
		options.hidden = true;

		var analyticsToggle = buildToggle(
			'analytics',
			s.analytics,
			s.analyticsDsc,
			initial.analytics
		);
		var marketingToggle = buildToggle(
			'marketing',
			s.marketing,
			s.marketingDsc,
			initial.marketing
		);
		options.appendChild(analyticsToggle.row);
		options.appendChild(marketingToggle.row);
		box.appendChild(options);

		// Actions.
		var actions = el('div', 'gbp-consent__actions');

		var rejectBtn = el(
			'button',
			'gbp-consent__btn gbp-consent__btn--ghost',
			s.rejectAll
		);
		rejectBtn.type = 'button';
		rejectBtn.addEventListener('click', function () {
			decide({ analytics: false, marketing: false });
		});

		var customizeBtn = el(
			'button',
			'gbp-consent__btn gbp-consent__btn--ghost',
			s.customize
		);
		customizeBtn.type = 'button';

		var saveBtn = el(
			'button',
			'gbp-consent__btn gbp-consent__btn--ghost',
			s.save
		);
		saveBtn.type = 'button';
		saveBtn.hidden = true;
		saveBtn.addEventListener('click', function () {
			decide({
				analytics: analyticsToggle.input.checked,
				marketing: marketingToggle.input.checked,
			});
		});

		function expandSettings() {
			options.hidden = false;
			customizeBtn.hidden = true;
			saveBtn.hidden = false;
		}
		customizeBtn.addEventListener('click', expandSettings);

		var acceptBtn = el(
			'button',
			'gbp-consent__btn gbp-consent__btn--primary',
			s.acceptAll
		);
		acceptBtn.type = 'button';
		acceptBtn.addEventListener('click', function () {
			decide({ analytics: true, marketing: true });
		});

		actions.appendChild(rejectBtn);
		actions.appendChild(customizeBtn);
		actions.appendChild(saveBtn);
		actions.appendChild(acceptBtn);
		box.appendChild(actions);

		// Expose the settings view so reopen triggers can jump straight to it.
		overlay.expandSettings = expandSettings;

		overlay.appendChild(box);
		return overlay;
	}

	function buildToggle(key, label, description, checked) {
		var row = el('label', 'gbp-consent__option');
		var input = el('input', 'gbp-consent__checkbox');
		input.type = 'checkbox';
		input.checked = !!checked;
		input.setAttribute('data-category', key);

		var textWrap = el('span', 'gbp-consent__option-text');
		textWrap.appendChild(el('span', 'gbp-consent__option-label', label));
		textWrap.appendChild(
			el('span', 'gbp-consent__option-desc', description)
		);

		row.appendChild(input);
		row.appendChild(textWrap);
		return { row: row, input: input };
	}

	function showBackdrop() {
		if (!cfg.backdrop) {
			return;
		}
		if (!backdropEl) {
			backdropEl = el('div', 'gbp-consent-backdrop');
			document.body.appendChild(backdropEl);
			void backdropEl.offsetWidth;
		}
		backdropEl.classList.add('is-visible');
	}

	function hideBackdrop() {
		if (backdropEl) {
			backdropEl.classList.remove('is-visible');
		}
	}

	function showBanner(initial, expand) {
		showBackdrop();
		if (bannerEl) {
			if (expand && bannerEl.expandSettings) {
				bannerEl.expandSettings();
			}
			bannerEl.classList.add('is-visible');
			return;
		}
		bannerEl = buildBanner(initial || DENIED);
		document.body.appendChild(bannerEl);
		if (expand && bannerEl.expandSettings) {
			bannerEl.expandSettings();
		}
		// Force reflow so the entrance transition runs.
		void bannerEl.offsetWidth;
		bannerEl.classList.add('is-visible');
	}

	function hideBanner() {
		hideBackdrop();
		if (bannerEl) {
			bannerEl.classList.remove('is-visible');
		}
	}

	function decide(state) {
		var previous = readConsent();
		writeConsent(state);

		// When enabled, reload as soon as a returning visitor changes their
		// choice: revoked categories cannot be unloaded at runtime, so a fresh
		// page load is the only reliable way to stop already-injected scripts.
		if (
			cfg.reloadOnChange &&
			previous &&
			(previous.analytics !== state.analytics ||
				previous.marketing !== state.marketing)
		) {
			hideBanner();
			location.reload();
			return;
		}

		applyConsent(state);
		hideBanner();
	}

	// ── Init ────────────────────────────────────────────────────────────────

	function bindReopenTriggers() {
		document.addEventListener('click', function (ev) {
			var trigger = ev.target.closest
				? ev.target.closest(
						'[data-gbp-consent="open"], .consent-settings'
				  )
				: null;
			if (trigger) {
				ev.preventDefault();
				// Reopen straight into the settings view so visitors can
				// review and change their stored choice right away.
				showBanner(readConsent() || DENIED, true);
			}
		});
	}

	function start() {
		bindReopenTriggers();

		// GTM is cookieless without tags, so it may load before consent when
		// configured. Tags inside GTM still honour Consent Mode (denied until
		// the visitor opts in).
		if (cfg.useGtm && cfg.gtmAlways) {
			loadGtm();
		}

		var existing = readConsent();
		if (existing) {
			// Returning visitor: apply their stored choice silently.
			applyConsent(existing);
		} else {
			showBanner(DENIED);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
