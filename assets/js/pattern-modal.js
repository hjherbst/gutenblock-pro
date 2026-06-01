/**
 * GutenBlock Pro - Pattern Modal Browser
 * Creates a modal interface for browsing patterns and pages (similar to Elementor)
 */

(function (wp) {
	'use strict';

	const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;
	const { registerPlugin } = wp.plugins;
	const { 
		Modal, 
		Button, 
		SearchControl,
		Spinner
	} = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { insertBlocks } = wp.blocks;
	// Use new API if available, fallback to old
	const { PluginMoreMenuItem } = (wp.editor && wp.editor.PluginMoreMenuItem) 
		? wp.editor 
		: (wp.editPost || {});

	/**
	 * Pattern Modal Component
	 */
	const TONE_COLORS = {
		neutral: { fill: '#f6f7f7', border: '#c3c4c7', label: 'Neutral' },
		dark:    { fill: '#1e1e1e', border: '#1e1e1e', label: 'Dark' },
		soft:    { fill: '#e8f0fe', border: '#9ab8e8', label: 'Soft' },
	};

	function LazyPatternPreview({ previewUrl, staticUrl, tone }) {
		const ref = useRef(null);
		const iframeRef = useRef(null);
		const [isVisible, setIsVisible] = useState(false);
		const [hasFallenBack, setHasFallenBack] = useState(false);

		useEffect(() => {
			if (isVisible) return;
			const node = ref.current;
			if (!node) return;

			if (!('IntersectionObserver' in window)) {
				setIsVisible(true);
				return;
			}

			const observer = new IntersectionObserver((entries) => {
				if (entries.some((entry) => entry.isIntersecting)) {
					setIsVisible(true);
					observer.disconnect();
				}
			}, {
				root: document.querySelector('.gutenblock-pro-modal-content'),
				rootMargin: '500px 0px',
				threshold: 0.01,
			});
			observer.observe(node);
			return () => observer.disconnect();
		}, [isVisible]);

		// Prefer the static cache URL (served by the web server with no PHP); fall
		// back to the legacy admin-ajax endpoint when the cache hasn't been warmed
		// yet OR if the iframe fails to load (file deleted/missing).
		const effectiveSrc = (staticUrl && !hasFallenBack) ? staticUrl : previewUrl;

		// Detect 404 on the static file: same-origin iframe lets us inspect the
		// loaded document. WordPress 404 page contains body class `error404`; if
		// we see that or an empty document, fall back to the legacy endpoint.
		const handleIframeLoad = (event) => {
			if (!staticUrl || hasFallenBack) return;
			try {
				const doc = event.target.contentDocument;
				if (!doc) return;
				const isError = doc.body && doc.body.classList && doc.body.classList.contains('error404');
				const isEmpty = !doc.body || doc.body.children.length === 0;
				if (isError || isEmpty) {
					setHasFallenBack(true);
				}
			} catch (_e) {
				// Cross-origin (shouldn't happen here) — leave the static URL alone.
			}
		};

		return el('div', {
			ref,
			className: 'gutenblock-pro-modal-pattern-preview'
		}, isVisible ? el('iframe', {
			ref: iframeRef,
			key: tone + (hasFallenBack ? ':fallback' : ''),
			src: effectiveSrc,
			sandbox: 'allow-same-origin allow-scripts',
			loading: 'lazy',
			tabIndex: -1,
			onLoad: handleIframeLoad,
		}) : el('div', {
			className: 'gutenblock-pro-modal-pattern-preview-placeholder',
			'aria-hidden': true
		}));
	}

	/**
	 * In-memory cache of the preview manifest returned by the warm-previews
	 * endpoint. Keyed by `${slug}__${tone}` → static URL. Survives the lifetime
	 * of the editor session so subsequent modal opens are instant.
	 */
	const previewManifestCache = new Map();
	let warmPromise = null;

	/**
	 * Trigger a single bulk request that materialises static cache files for
	 * every pattern/tone we want to preview. The server amortises one WordPress
	 * bootstrap across all entries; first call after a plugin update writes the
	 * files to disk, every subsequent call just confirms the manifest.
	 */
	function warmPreviewCache(entries) {
		if (!entries || !entries.length) return Promise.resolve(previewManifestCache);

		// De-duplicate: only ask the server for entries we don't already have.
		const missing = entries.filter(({ slug, tone }) => !previewManifestCache.has(`${slug}__${tone}`));
		if (missing.length === 0) return Promise.resolve(previewManifestCache);

		// Coalesce concurrent requests during the same modal-open burst.
		if (warmPromise) return warmPromise;

		const body = new URLSearchParams();
		body.set('action', 'gutenblock_pro_warm_previews');
		missing.forEach(({ slug, tone }, idx) => {
			body.append(`patterns[${idx}][slug]`, slug);
			body.append(`patterns[${idx}][tone]`, tone || 'neutral');
		});

		warmPromise = fetch(gutenblockProModal.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		})
			.then((r) => (r.ok ? r.json() : null))
			.then((resp) => {
				if (resp && resp.success && resp.data && resp.data.manifest) {
					Object.entries(resp.data.manifest).forEach(([key, url]) => {
						previewManifestCache.set(key, url);
					});
				}
				return previewManifestCache;
			})
			.catch(() => previewManifestCache)
			.finally(() => { warmPromise = null; });

		return warmPromise;
	}

	/**
	 * Liest den aktuellen Editor-Kontext aus dem block-editor-Store.
	 * Rückgabewerte:
	 *  - 'template'      → Site Editor: `wp_template` oder `wp_template_part`.
	 *    Pattern-Modal zeigt dort NUR Header/Footer-Patterns.
	 *  - 'post'          → klassischer Post-/Page-/CPT-Editor.
	 *    Pattern-Modal blendet Header/Footer-Patterns aus.
	 *  - 'unknown'       → noch nicht entscheidbar (Verhalten wie 'post').
	 */
	function detectEditorScope() {
		try {
			const editorStore = wp.data.select('core/editor');
			const postType = editorStore && typeof editorStore.getCurrentPostType === 'function'
				? editorStore.getCurrentPostType()
				: null;
			if (postType === 'wp_template' || postType === 'wp_template_part') {
				return 'template';
			}
			if (postType) {
				return 'post';
			}
		} catch (e) {
			// Editor-Store noch nicht ready oder nicht verfügbar — Default unten.
		}
		return 'unknown';
	}

	const HEADER_FOOTER_GROUPS = ['header', 'footer'];

	function PatternModal({ isOpen, onClose, category = null }) {
		const [patterns, setPatterns] = useState([]);
		const [loading, setLoading] = useState(true);
		const [searchTerm, setSearchTerm] = useState('');
		const [selectedCategory, setSelectedCategory] = useState(category || 'sections');
		const [refreshKey, setRefreshKey] = useState(0);
		const [cacheClearing, setCacheClearing] = useState(false);
		// Welche Tone-Variante ist pro Pattern aktiv (für Hover-Preview & Click-Insert)
		const [activeTones, setActiveTones] = useState({});
		// Manifest of static-cache URLs returned by warmPreviewCache. Empty until
		// the warm-previews endpoint has resolved; LazyPatternPreview gracefully
		// falls back to the legacy admin-ajax URL while we wait.
		const [previewManifest, setPreviewManifest] = useState(() => Object.fromEntries(previewManifestCache));
		const { insertBlocks: insertBlocksAction } = useDispatch('core/block-editor');
		const editorScope = detectEditorScope();
		const isTemplateScope = editorScope === 'template';
		// In Template-Scope nur „Sections"-Tab (Header/Footer) — Pages ausblenden.
		useEffect(() => {
			if (isTemplateScope && selectedCategory !== 'sections') {
				setSelectedCategory('sections');
			}
		}, [isTemplateScope, selectedCategory]);

		// Get all registered patterns
		useEffect(() => {
			if (!isOpen) return;

			// Kontext (template vs. post) ist Bestandteil des Cache-Keys, damit
			// ein Wechsel zwischen Site-Editor und Beitrags-Editor nicht aus
			// dem falschen Cache liest.
			const CACHE_KEY = 'gbp_patterns_v2_' + editorScope;
			const CACHE_TTL = 5 * 60 * 1000; // 5 Minuten

			const filterByScope = (allPatterns) => {
				if (isTemplateScope) {
					// Site-Editor: ausschließlich Header/Footer-Patterns.
					const onlyChrome = allPatterns.filter((p) =>
						HEADER_FOOTER_GROUPS.indexOf((p.group || '').toLowerCase()) !== -1
					);
					return {
						sections: onlyChrome,
						pages: [],
						all: onlyChrome,
					};
				}
				// Post-/Page-/CPT-Editor: Header/Footer-Patterns ausblenden —
				// die gehören in den Template-Editor.
				const filtered = allPatterns.filter((p) =>
					HEADER_FOOTER_GROUPS.indexOf((p.group || '').toLowerCase()) === -1
				);
				return {
					sections: filtered.filter((p) => p.type !== 'page'),
					pages: filtered.filter((p) => p.type === 'page'),
					all: filtered,
				};
			};

			// sessionStorage-Cache prüfen
			try {
				const cached = sessionStorage.getItem(CACHE_KEY);
				if (cached) {
					const { data, ts } = JSON.parse(cached);
					if (Date.now() - ts < CACHE_TTL) {
						setPatterns(data);
						setLoading(false);
						return;
					}
				}
			} catch (e) { /* sessionStorage nicht verfügbar */ }

			setLoading(true);
			
			// Fetch patterns via AJAX to get all data including preview URLs
			fetch(gutenblockProModal.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'gutenblock_pro_get_patterns_for_modal',
					nonce: gutenblockProModal.nonce || ''
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success && data.data) {
					const allPatterns = data.data.patterns || [];
					const patternsData = filterByScope(allPatterns);

					setPatterns(patternsData);

					// In sessionStorage cachen
					try {
						sessionStorage.setItem(CACHE_KEY, JSON.stringify({ data: patternsData, ts: Date.now() }));
					} catch (e) { /* sessionStorage voll oder nicht verfügbar */ }
				}
				setLoading(false);
			})
			.catch(error => {
				console.error('Error loading patterns:', error);
				setLoading(false);
			});
		}, [isOpen, refreshKey, editorScope, isTemplateScope]);

		// Once patterns are known, fire a single bulk warm-up so the iframe srcs
		// can switch from the slow admin-ajax route to plain static files served
		// directly by the web server. Idempotent across modal re-opens.
		useEffect(() => {
			if (!isOpen) return;
			const all = patterns.all || [];
			if (!all.length) return;

			const entries = [];
			all.forEach((p) => {
				const slug = p.slug || (p.name || '').replace('gutenblock-pro/', '');
				if (!slug) return;
				const tones = Array.isArray(p.tones) && p.tones.length ? p.tones : ['neutral'];
				tones.forEach((t) => entries.push({ slug, tone: t }));
			});

			warmPreviewCache(entries).then((manifest) => {
				if (!manifest || manifest.size === 0) return;
				setPreviewManifest(Object.fromEntries(manifest));
			});
		}, [isOpen, patterns]);

		const handleClearCache = () => {
			setCacheClearing(true);
			try {
				// Alte und neue Cache-Keys (alle Scopes) entfernen.
				sessionStorage.removeItem('gbp_patterns_v1');
				sessionStorage.removeItem('gbp_patterns_v2_template');
				sessionStorage.removeItem('gbp_patterns_v2_post');
				sessionStorage.removeItem('gbp_patterns_v2_unknown');
			} catch (e) {}

			fetch(gutenblockProModal.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'gutenblock_pro_clear_preview_cache',
					nonce: gutenblockProModal.clearCacheNonce || ''
				})
			})
			.finally(() => {
				setCacheClearing(false);
				setPatterns([]);
				setRefreshKey(k => k + 1);
			});
		};

		// Filter patterns by search term (for pages view)
		const filteredPatterns = patterns[selectedCategory]?.filter(pattern => {
			if (!searchTerm) return true;
			const term = searchTerm.toLowerCase();
			return (
				pattern.title?.toLowerCase().includes(term) ||
				pattern.description?.toLowerCase().includes(term) ||
				(pattern.keywords && pattern.keywords.some(k => k.toLowerCase().includes(term)))
			);
		}) || [];

		// Group sections by category (avoid duplicates)
		const groupedSections = {};
		const seenPatterns = new Set();
		
		if (selectedCategory === 'sections' && patterns.sections) {
			patterns.sections.forEach(pattern => {
				// Skip if already seen (avoid duplicates)
				const patternKey = pattern.slug || pattern.name;
				if (seenPatterns.has(patternKey)) {
					return;
				}
				seenPatterns.add(patternKey);
				
				// Use group from pattern data, or 'other' if no group
				const group = (pattern.group && pattern.group.trim()) ? pattern.group : 'other';
				
				if (!groupedSections[group]) {
					groupedSections[group] = [];
				}
				groupedSections[group].push(pattern);
			});
		}

		const handleInsertPattern = (pattern) => {
			const isPremium = pattern.premium === true;
			const hasAccess = pattern.hasAccess !== false;

			if (isPremium && !hasAccess) {
				const upgradeUrl = gutenblockProModal.upgradeUrl || 'https://app.gutenblock.com/licenses';
				if (window.confirm('Dieses Pattern benötigt GutenBlock Pro.\n\nMöchtest du jetzt upgraden?')) {
					window.open(upgradeUrl, '_blank');
				}
				return;
			}
			if (!pattern.content) return;

			const tone = activeTones[pattern.slug] || 'neutral';

			const insertContent = (markup) => {
				try {
					const blocks = wp.blocks.parse(markup);
					insertBlocksAction(blocks);
					if (pattern.type === 'page') {
						onClose();
					}
				} catch (error) {
					console.error('Error inserting pattern:', error);
				}
			};

			// Neutral → Original-Content direkt einfügen
			if (tone === 'neutral') {
				insertContent(pattern.content);
				return;
			}

			// Tone-Variante via AJAX holen (mit injizierten Farb-Klassen)
			fetch(gutenblockProModal.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'gutenblock_pro_get_pattern_tone_content',
					nonce: gutenblockProModal.nonce || '',
					pattern: pattern.slug,
					tone: tone,
				}),
			})
				.then((r) => r.json())
				.then((data) => {
					if (data && data.success && data.data && data.data.content) {
						insertContent(data.data.content);
					} else {
						console.error('Tone-Variant fetch failed', data);
						insertContent(pattern.content); // Fallback: neutral
					}
				})
				.catch((err) => {
					console.error('Tone-Variant fetch error', err);
					insertContent(pattern.content);
				});
		};

		const renderPatternPreview = (pattern) => {
			const patternSlug = pattern.slug || pattern.name?.replace('gutenblock-pro/', '') || '';
			
			if (!patternSlug) {
				return el('div', {
					className: 'gutenblock-pro-modal-pattern-preview',
					style: {
						height: '300px',
						background: '#f0f0f1',
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						color: '#646970'
					}
				}, 'Keine Vorschau');
			}

			const tone = activeTones[patternSlug] || 'neutral';
			const previewUrl = gutenblockProModal.ajaxUrl +
				'?action=gutenblock_pro_preview_pattern&pattern=' +
				encodeURIComponent(patternSlug) +
				'&tone=' + encodeURIComponent(tone) +
				'&_wpnonce=' + encodeURIComponent(gutenblockProModal.nonce || '');

			// staticUrl is supplied by the warm-previews manifest once it has
			// resolved. When present, it bypasses WordPress entirely; the legacy
			// previewUrl remains the fallback for cache misses or first-time use.
			const manifestKey = `${patternSlug}__${tone}`;
			const staticUrl = previewManifest[manifestKey];

			return el(LazyPatternPreview, {
				previewUrl,
				staticUrl,
				tone,
			});
		};

		const renderToneSwatches = (pattern) => {
			const tones = Array.isArray(pattern.tones) ? pattern.tones : ['neutral'];
			if (tones.length <= 1) return null;
			const active = activeTones[pattern.slug] || 'neutral';

			return el('div', {
				className: 'gutenblock-pro-tone-swatches',
				onClick: (e) => e.stopPropagation(), // Klick auf Swatch nicht als Insert-Click werten
			}, tones.map((t) => {
				const cfg = TONE_COLORS[t] || TONE_COLORS.neutral;
				return el('button', {
					key: t,
					type: 'button',
					className: 'gutenblock-pro-tone-swatch' + (active === t ? ' is-active' : ''),
					style: { background: cfg.fill, borderColor: cfg.border },
					title: cfg.label,
					'aria-label': cfg.label,
					onMouseEnter: () => setActiveTones((prev) => ({ ...prev, [pattern.slug]: t })),
					onFocus: () => setActiveTones((prev) => ({ ...prev, [pattern.slug]: t })),
					onClick: (e) => {
						e.preventDefault();
						e.stopPropagation();
						setActiveTones((prev) => ({ ...prev, [pattern.slug]: t }));
					},
				});
			}));
		};

		const renderPatternCard = (pattern) => {
			const isPremium = pattern.premium === true;
			const hasAccess = pattern.hasAccess !== false; // Default to true if not set
			const isLocked = isPremium && !hasAccess;

			return el('div', {
				key: pattern.name,
				className: 'gutenblock-pro-modal-pattern-card' + (isLocked ? ' gutenblock-pro-pattern-locked' : ''),
				onClick: () => {
					if (isLocked) {
						// Show upgrade notice on click
						const upgradeUrl = gutenblockProModal.upgradeUrl || 'https://gutenblock.com/licenses';
						if (window.confirm('Dieses Pattern benötigt GutenBlock Pro.\n\nMöchtest du jetzt upgraden?')) {
							window.open(upgradeUrl, '_blank');
						}
					} else {
						handleInsertPattern(pattern);
					}
				}
			}, [
				renderPatternPreview(pattern),
				el('div', {
					className: 'gutenblock-pro-modal-pattern-info'
				}, [
					el('div', {
						className: 'gutenblock-pro-modal-pattern-title-row'
				}, [
					el('h3', {
						className: 'gutenblock-pro-modal-pattern-title'
					}, pattern.title || pattern.name),
						isPremium && el('span', {
							className: 'gutenblock-pro-pattern-badge gutenblock-pro-pattern-badge-premium'
						}, 'plus')
					]),
					pattern.description && el('p', {
						className: 'gutenblock-pro-modal-pattern-description'
					}, pattern.description),
					renderToneSwatches(pattern)
				])
			]);
		};

		const renderSectionsView = () => {
			if (loading) {
				return el('div', {
					className: 'gutenblock-pro-modal-loading'
				}, el(Spinner));
			}

			if (Object.keys(groupedSections).length === 0) {
				return el('div', {
					className: 'gutenblock-pro-modal-empty'
				}, 'Keine Sections gefunden.');
			}

			// Sort groups by predefined order
			const sortedGroups = Object.keys(groupedSections).sort((a, b) => {
				const orderA = Object.keys(gutenblockProModal.groups || {}).indexOf(a);
				const orderB = Object.keys(gutenblockProModal.groups || {}).indexOf(b);
				if (orderA === -1 && orderB === -1) return 0;
				if (orderA === -1) return 1;
				if (orderB === -1) return -1;
				return orderA - orderB;
			});

			return sortedGroups.map(group => {
				const groupLabel = (gutenblockProModal.groups && gutenblockProModal.groups[group]) 
					? gutenblockProModal.groups[group] 
					: (group === 'other' ? 'Weitere' : group);
				const groupPatterns = searchTerm 
					? groupedSections[group].filter(p => {
						const term = searchTerm.toLowerCase();
						return (
							p.title?.toLowerCase().includes(term) ||
							p.description?.toLowerCase().includes(term) ||
							(p.keywords && p.keywords.some(k => k.toLowerCase().includes(term)))
						);
					})
					: groupedSections[group];

				if (groupPatterns.length === 0) return null;

				return el('div', {
					key: group,
					className: 'gutenblock-pro-modal-group'
				}, [
					el('h2', {
						className: 'gutenblock-pro-modal-group-title'
					}, groupLabel),
					el('div', {
						className: 'gutenblock-pro-modal-patterns-grid'
					}, groupPatterns.map(renderPatternCard))
				]);
			});
		};

		const renderPagesView = () => {
			if (loading) {
				return el('div', {
					className: 'gutenblock-pro-modal-loading'
				}, el(Spinner));
			}

			if (filteredPatterns.length === 0) {
				return el('div', {
					className: 'gutenblock-pro-modal-empty'
				}, 'Keine Seiten gefunden.');
			}

			return el('div', {
				className: 'gutenblock-pro-modal-patterns-grid'
			}, filteredPatterns.map(renderPatternCard));
		};

		if (!isOpen) return null;

		return el(Modal, {
			title: el('div', {
				className: 'gutenblock-pro-modal-header'
			}, [
				el(SearchControl, {
					value: searchTerm,
					onChange: setSearchTerm,
					placeholder: 'Patterns durchsuchen...',
					className: 'gutenblock-pro-modal-search',
					__nextHasNoMarginBottom: true
				}),
				el('div', {
					className: 'gutenblock-pro-modal-tabs'
				}, isTemplateScope ? [
					// Template-Editor: nur Header/Footer-Patterns, daher keine
					// Tab-Umschaltung — wir zeigen sie als feste Label-Pille.
					el('span', {
						className: 'gutenblock-pro-modal-tab-static'
					}, 'Header & Footer')
				] : [
					el(Button, {
						onClick: () => setSelectedCategory('sections'),
						variant: selectedCategory === 'sections' ? 'primary' : 'secondary',
						className: 'gutenblock-pro-modal-tab-button'
					}, 'Sections'),
					el(Button, {
						onClick: () => setSelectedCategory('pages'),
						variant: selectedCategory === 'pages' ? 'primary' : 'secondary',
						className: 'gutenblock-pro-modal-tab-button'
					}, 'Seiten')
				]),
				gutenblockProModal.isAdmin && el('button', {
					onClick: handleClearCache,
					disabled: cacheClearing || loading,
					style: {
						marginLeft: 'auto',
						fontSize: '12px',
						background: 'none',
						border: 'none',
						padding: '0',
						cursor: 'pointer',
						color: '#787c82',
						textDecoration: 'underline',
						fontFamily: 'inherit'
					}
				}, cacheClearing ? 'Wird geladen…' : '↺ Cache erneuern'),
			el('a', {
					href: 'https://gutenblock.com/gutenblock-pro',
					target: '_blank',
					rel: 'noopener noreferrer',
					className: 'gutenblock-pro-modal-link',
					style: {
						marginLeft: gutenblockProModal.isAdmin ? '1rem' : 'auto',
						fontSize: '13px',
						textDecoration: 'none',
						color: '#2271b1',
						fontWeight: '500'
					}
				}, 'GutenBlock →')
			]),
			onRequestClose: onClose,
			className: 'gutenblock-pro-pattern-modal',
			size: 'large'
		}, [
			el('div', {
				className: 'gutenblock-pro-modal-content'
			}, selectedCategory === 'sections' ? renderSectionsView() : renderPagesView())
		]);
	}

	/**
	 * Plugin to add Pattern Browser to More Tools menu
	 */
	function PatternBrowserMenuItem() {
		const [isOpen, setIsOpen] = useState(false);

		return el(Fragment, null, [
			PluginMoreMenuItem && el(PluginMoreMenuItem, {
				icon: 'layout',
				onClick: () => setIsOpen(true)
			}, 'GutenBlock'),
			el(PatternModal, {
				isOpen: isOpen,
				onClose: () => setIsOpen(false)
			})
		]);
	}

	// Global modal state manager
	const globalModalManager = {
		setIsOpen: null,
		open: function() {
			if (this.setIsOpen) {
				this.setIsOpen(true);
			} else {
				// Fallback: dispatch event
				document.dispatchEvent(new CustomEvent('gutenblock-pro-open-modal'));
			}
		}
	};

	// Also create a standalone modal component that can be triggered externally
	function StandalonePatternModal() {
		const [isOpen, setIsOpen] = useState(false);

		// Register global opener and listen for events
		useEffect(() => {
			// Store setter in global manager
			globalModalManager.setIsOpen = setIsOpen;
			
			// Store setter function globally
			const openModal = () => {
				setIsOpen(true);
			};
			
			window.gutenblockProOpenModal = openModal;
			
			// Listen for custom event
			const handleOpenModal = () => {
				setIsOpen(true);
			};
			document.addEventListener('gutenblock-pro-open-modal', handleOpenModal);
			
			return () => {
				document.removeEventListener('gutenblock-pro-open-modal', handleOpenModal);
				globalModalManager.setIsOpen = null;
				delete window.gutenblockProOpenModal;
			};
		}, []);

		return el(PatternModal, {
			isOpen: isOpen,
			onClose: () => setIsOpen(false)
		});
	}

	// Register plugin
	if (registerPlugin) {
		// Always register standalone modal component first (for toolbar button)
		registerPlugin('gutenblock-pro-pattern-modal-standalone', {
			render: StandalonePatternModal,
			icon: 'layout'
		});

		// Register for More Tools menu (if available)
		if (PluginMoreMenuItem) {
			registerPlugin('gutenblock-pro-pattern-browser', {
				render: PatternBrowserMenuItem,
				icon: 'layout'
			});
		}
	}

	// Global flag to prevent multiple button creations
	let buttonCreated = false;
	let buttonCheckInterval = null;

	// Add button next to inserter toggle (plus icon)
	function addToolbarButton() {
		// Check if button already exists (more thorough check)
		const existingButton = document.getElementById('gutenblock-pro-toolbar-modal-button');
		if (existingButton || buttonCreated) {
			if (buttonCheckInterval) {
				clearInterval(buttonCheckInterval);
				buttonCheckInterval = null;
			}
			return;
		}

		// Find the plus icon button (Block-Inserter)
		const inserterToggle = document.querySelector(
			'.editor-document-tools__inserter-toggle, ' +
			'button[aria-label="Block-Inserter"], ' +
			'button[data-toolbar-item="true"][aria-label*="Inserter"]'
		);
		
		if (!inserterToggle) {
			// Retry if not found yet
			if (!buttonCheckInterval) {
				buttonCheckInterval = setInterval(addToolbarButton, 500);
				setTimeout(() => {
					if (buttonCheckInterval) {
						clearInterval(buttonCheckInterval);
						buttonCheckInterval = null;
					}
				}, 10000);
			}
			return;
		}

		// Stop interval if running
		if (buttonCheckInterval) {
			clearInterval(buttonCheckInterval);
			buttonCheckInterval = null;
		}

		// Mark as created
		buttonCreated = true;

		// Create button (icon only, matching inserter style)
		const button = document.createElement('button');
		button.id = 'gutenblock-pro-toolbar-modal-button';
		button.className = 'components-button components-toolbar-button gutenblock-pro-toolbar-modal-button is-primary is-compact has-icon';
		button.type = 'button';
		button.setAttribute('aria-label', 'GutenBlock Patterns öffnen');
		button.setAttribute('data-toolbar-item', 'true');
		
		// Use SVG icon matching WordPress style
		button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M4 4h7v2H6v5H4V4zm16 0v7h-2V6h-5V4h7zM4 20v-7h2v5h5v2H4zm16 0h-7v-2h5v-5h2v7z"></path></svg>';
		
		button.onclick = (e) => {
			e.preventDefault();
			e.stopPropagation();
			
			// Open modal via global manager (primary method)
			if (globalModalManager.setIsOpen) {
				globalModalManager.open();
			} else if (window.gutenblockProOpenModal) {
				// Fallback to window function
				window.gutenblockProOpenModal();
			} else {
				// Last resort: dispatch event
				document.dispatchEvent(new CustomEvent('gutenblock-pro-open-modal'));
			}
		};

		// Insert right after inserter toggle button
		if (inserterToggle.parentNode) {
			inserterToggle.parentNode.insertBefore(button, inserterToggle.nextSibling);
		}
	}

	// Initialize toolbar button when editor is ready
	function initToolbarButton() {
		// Wait for React components to initialize first (modal must be ready)
		setTimeout(() => {
			addToolbarButton();
		}, 2000);

		// Also try after DOM is ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				setTimeout(addToolbarButton, 2500);
			});
		}

		// Retry after editor state changes (but limit retries)
		if (wp && wp.data && wp.data.subscribe) {
			let retryCount = 0;
			const maxRetries = 3;
			const unsubscribe = wp.data.subscribe(() => {
				if (retryCount < maxRetries && !buttonCreated) {
					retryCount++;
					setTimeout(addToolbarButton, 1500);
				} else if (retryCount >= maxRetries) {
					unsubscribe();
				}
			});
		}
	}

	// Start initialization after a delay to ensure React components are mounted
	setTimeout(initToolbarButton, 500);

})(window.wp);

