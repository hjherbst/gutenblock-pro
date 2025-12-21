/**
 * GutenBlock Pro - Pattern Modal Browser
 * Creates a modal interface for browsing patterns and pages (similar to Elementor)
 */

(function (wp) {
	'use strict';

	const { createElement: el, Fragment, useState, useEffect } = wp.element;
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
	function PatternModal({ isOpen, onClose, category = null }) {
		const [patterns, setPatterns] = useState([]);
		const [loading, setLoading] = useState(true);
		const [searchTerm, setSearchTerm] = useState('');
		const [selectedCategory, setSelectedCategory] = useState(category || 'sections');
		const { insertBlocks: insertBlocksAction } = useDispatch('core/block-editor');

		// Get all registered patterns
		useEffect(() => {
			if (!isOpen) return;

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
					
					// Separate sections and pages
					// Note: Premium patterns without access are still shown but locked
					const sections = allPatterns.filter(p => 
						p.type !== 'page'
					);
					const pages = allPatterns.filter(p => 
						p.type === 'page'
					);

					setPatterns({
						sections: sections,
						pages: pages,
						all: allPatterns
					});
				}
				setLoading(false);
			})
			.catch(error => {
				console.error('Error loading patterns:', error);
				setLoading(false);
			});
		}, [isOpen]);

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
			// Block inserting premium patterns without access
			const isPremium = pattern.premium === true;
			const hasAccess = pattern.hasAccess !== false;
			
			if (isPremium && !hasAccess) {
				// Show upgrade notice
				const upgradeUrl = gutenblockProModal.upgradeUrl || 'https://app.gutenblock.com/licenses';
				if (window.confirm('Dieses Pattern benötigt GutenBlock Pro Plus.\n\nMöchtest du jetzt upgraden?')) {
					window.open(upgradeUrl, '_blank');
				}
				return; // Don't insert
			}
			
			// Allow inserting non-premium or premium with access
			if (!pattern.content) return;

			try {
				const blocks = wp.blocks.parse(pattern.content);
				insertBlocksAction(blocks);
				// Only close modal for pages, keep it open for sections
				if (pattern.type === 'page') {
					onClose();
				}
			} catch (error) {
				console.error('Error inserting pattern:', error);
			}
		};

		const renderPatternPreview = (pattern) => {
			// Use slug directly from pattern data
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

			// Create preview iframe
			const previewUrl = gutenblockProModal.ajaxUrl + 
				'?action=gutenblock_pro_preview_pattern&pattern=' + 
				encodeURIComponent(patternSlug);

			return el('div', {
				className: 'gutenblock-pro-modal-pattern-preview'
			}, el('iframe', {
				src: previewUrl,
				sandbox: 'allow-same-origin allow-scripts',
				loading: 'lazy',
				tabIndex: -1
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
						const upgradeUrl = gutenblockProModal.upgradeUrl || 'https://app.gutenblock.com/licenses';
						if (window.confirm('Dieses Pattern benötigt GutenBlock Pro Plus.\n\nMöchtest du jetzt upgraden?')) {
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
						}, 'Pro Plus')
					]),
					pattern.description && el('p', {
						className: 'gutenblock-pro-modal-pattern-description'
					}, pattern.description)
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
				}, [
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
				])
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
			}, 'GutenBlock Pro'),
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
		button.setAttribute('aria-label', 'GutenBlock Pro Patterns öffnen');
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

