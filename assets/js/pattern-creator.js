/**
 * GutenBlock Pro - Pattern Creator
 * Adds "Als GB Pro Pattern speichern" button to block toolbar
 */

(function (wp) {
	'use strict';

	const { createElement: el, Fragment, useState, useEffect } = wp.element;
	const { createHigherOrderComponent } = wp.compose;
	const { addFilter } = wp.hooks;
	const { 
		Modal, 
		Button, 
		TextControl, 
		TextareaControl, 
		Spinner, 
		Notice,
		ToolbarGroup,
		ToolbarButton,
		SelectControl,
		CheckboxControl
	} = wp.components;
	const { BlockControls } = wp.blockEditor;
	const { useSelect, useDispatch } = wp.data;
	const { serialize } = wp.blocks;
	const strings = gutenblockProCreator.strings;

	// Store for modal state (shared across components)
	let openModalCallback = null;
	let currentSelectedBlocks = [];

	/**
	 * Erkennt, ob der Top-Block keine sinnvollen Tone-Varianten erlaubt.
	 * Liefert einen Grund-String oder null wenn ok.
	 */
	function detectToneIncompatibility(block) {
		if (!block || !block.name) return null;
		if (block.name === 'core/cover') return 'cover';
		const a = block.attributes || {};
		if (a.url || a.backgroundImage) return 'background-image';
		if (a.gradient) return 'gradient';
		if (a.style && a.style.background && a.style.background.backgroundImage) return 'background-image';
		if (a.style && a.style.color && a.style.color.gradient) return 'gradient';
		return null;
	}

	/**
	 * Generate slug from name
	 */
	function generateSlug(name) {
		return name
			.toLowerCase()
			.replace(/[äöüß]/g, function (match) {
				const map = { ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' };
				return map[match];
			})
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	/**
	 * Check if pattern exists
	 */
	function checkPatternExists(slug) {
		return new Promise((resolve) => {
			if (!slug) {
				resolve({ exists: false });
				return;
			}

			const formData = new FormData();
			formData.append('action', 'gutenblock_pro_check_pattern');
			formData.append('nonce', gutenblockProCreator.nonce);
			formData.append('slug', slug);

			fetch(gutenblockProCreator.ajaxUrl, {
				method: 'POST',
				body: formData,
			})
				.then((response) => response.json())
				.then((response) => {
					if (response.success) {
						resolve(response.data);
					} else {
						resolve({ exists: false });
					}
				})
				.catch(() => {
					resolve({ exists: false });
				});
		});
	}

	/**
	 * Pattern Creator Modal Component
	 */
	function PatternCreatorModal({ isOpen, onClose, selectedBlocks, initialName }) {
		const [name, setName] = useState('');
		const [slug, setSlug] = useState('');
		const [description, setDescription] = useState('');
		const [keywords, setKeywords] = useState('');
		const [aiHint, setAiHint] = useState('');
		const [patternType, setPatternType] = useState('pattern');
		const [group, setGroup] = useState('');
		const [pageType, setPageType] = useState('');
		const [isPremium, setIsPremium] = useState(false);
		const [enableTones, setEnableTones] = useState(false);
		const [toneCapability, setToneCapability] = useState({ supported: true, reason: '' });
		const [isCreating, setIsCreating] = useState(false);
		const [isSuggesting, setIsSuggesting] = useState(false);
		const [notice, setNotice] = useState(null);
		const [slugManuallyEdited, setSlugManuallyEdited] = useState(false);
		const [patternExists, setPatternExists] = useState(false);
		const [existingPatternInfo, setExistingPatternInfo] = useState(null);
		const [isChecking, setIsChecking] = useState(false);

		// Pre-fill name from block metadata when modal opens
		useEffect(() => {
			if (isOpen && initialName && !name) {
				setName(initialName);
				if (!slugManuallyEdited) {
					setSlug(generateSlug(initialName));
				}
			}
		}, [isOpen, initialName]);

		// Pre-fill ALL fields when an existing pattern is found.
		useEffect(() => {
			if (existingPatternInfo) {
				if (existingPatternInfo.description !== undefined) setDescription(existingPatternInfo.description || '');
				if (existingPatternInfo.ai_hint !== undefined) setAiHint(existingPatternInfo.ai_hint || '');
				if (existingPatternInfo.type !== undefined) setPatternType(existingPatternInfo.type || 'pattern');
				if (existingPatternInfo.keywords !== undefined) setKeywords(existingPatternInfo.keywords || '');
				if (existingPatternInfo.group !== undefined) setGroup(existingPatternInfo.group || '');
				if (existingPatternInfo.page_type !== undefined) setPageType(existingPatternInfo.page_type || '');
				if (existingPatternInfo.premium !== undefined) setIsPremium(!!existingPatternInfo.premium);
				if (Array.isArray(existingPatternInfo.tones)) {
					setEnableTones(existingPatternInfo.tones.length > 1);
				}
			}
		}, [existingPatternInfo]);

		// Auto-Detect: ist der Top-Block tone-fähig?
		useEffect(() => {
			if (!isOpen || !selectedBlocks || selectedBlocks.length === 0) {
				return;
			}
			const top = selectedBlocks[0];
			if (!top || !top.name) return;
			const reason = detectToneIncompatibility(top);
			setToneCapability({ supported: !reason, reason: reason || '' });
			if (reason) {
				// Bei Cover/BG-Image die Checkbox automatisch deaktivieren
				setEnableTones(false);
			}
		}, [isOpen, selectedBlocks]);

		// Check if pattern exists when slug changes
		useEffect(() => {
			if (!slug) {
				setPatternExists(false);
				setExistingPatternInfo(null);
				return;
			}

			const timeoutId = setTimeout(async () => {
				setIsChecking(true);
				const result = await checkPatternExists(slug);
				setPatternExists(result.exists);
				setExistingPatternInfo(result.exists ? result : null);
				setIsChecking(false);
			}, 300);

			return () => clearTimeout(timeoutId);
		}, [slug]);

		function handleNameChange(newName) {
			setName(newName);
			if (!slugManuallyEdited) {
				setSlug(generateSlug(newName));
			}
		}

		function handleSlugChange(newSlug) {
			setSlug(generateSlug(newSlug));
			setSlugManuallyEdited(true);
		}

		function resetForm() {
			setName('');
			setSlug('');
			setDescription('');
			setKeywords('');
			setAiHint('');
			setPatternType('pattern');
			setGroup('');
			setPageType('');
			setIsPremium(false);
			setEnableTones(false);
			setToneCapability({ supported: true, reason: '' });
			setNotice(null);
			setSlugManuallyEdited(false);
			setPatternExists(false);
			setExistingPatternInfo(null);
		}

		function handleClose() {
			resetForm();
			onClose();
		}

		function handleAiSuggest() {
			if (!selectedBlocks || selectedBlocks.length === 0) {
				setNotice({ type: 'error', message: strings.aiNoBlocks || strings.noBlocks });
				return;
			}
			setIsSuggesting(true);
			setNotice(null);

			const content = serialize(selectedBlocks);
			const restUrl = (gutenblockProCreator.restUrl || '').replace(/\/$/, '') + '/ai/pattern-meta';

			fetch(restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': gutenblockProCreator.restNonce || '',
				},
				body: JSON.stringify({
					name: name.trim(),
					slug: slug || generateSlug(name),
					type: patternType,
					content: content,
				}),
			})
				.then((r) => r.json().then((data) => ({ ok: r.ok, data })))
				.then(({ ok, data }) => {
					setIsSuggesting(false);
					if (!ok) {
						setNotice({
							type: 'error',
							message: (data && (data.message || data.error)) || strings.aiSuggestError,
						});
						return;
					}
					if (data && (data.description || data.ai_hint)) {
						if (data.description) setDescription(data.description);
						if (data.ai_hint) setAiHint(data.ai_hint);
					} else {
						setNotice({ type: 'warning', message: strings.aiSuggestError });
					}
				})
				.catch(() => {
					setIsSuggesting(false);
					setNotice({ type: 'error', message: strings.aiSuggestError });
				});
		}

		function handleCreate() {
			if (!name.trim()) {
				setNotice({ type: 'error', message: strings.nameRequired });
				return;
			}

			if (!selectedBlocks || selectedBlocks.length === 0) {
				setNotice({ type: 'error', message: strings.noBlocks });
				return;
			}

			setIsCreating(true);
			setNotice(null);

			const content = serialize(selectedBlocks);

			const formData = new FormData();
			formData.append('action', 'gutenblock_pro_create_pattern');
			formData.append('nonce', gutenblockProCreator.nonce);
			formData.append('name', name.trim());
			formData.append('slug', slug || generateSlug(name));
			formData.append('description', description);
			formData.append('keywords', keywords);
			formData.append('ai_hint', aiHint);
			formData.append('type', patternType);
			formData.append('group', group);
			formData.append('page_type', patternType === 'page' ? pageType : '');
			formData.append('premium', isPremium ? 'true' : 'false');
			formData.append('enable_tones', enableTones && toneCapability.supported ? 'true' : 'false');
			formData.append('update_mode', patternExists ? 'true' : 'false');
			formData.append('content', content);

			fetch(gutenblockProCreator.ajaxUrl, {
				method: 'POST',
				body: formData,
			})
				.then((response) => response.json())
				.then((response) => {
					setIsCreating(false);
					if (response.success) {
						const message = response.data.is_update 
							? (strings.patternExists || 'Pattern updated. CSS/JS preserved.')
							: strings.success;
						setNotice({ type: 'success', message: message });
						setTimeout(() => {
							handleClose();
						}, 1500);
					} else {
						setNotice({
							type: 'error',
							message: response.data?.message || strings.error,
						});
					}
				})
				.catch((error) => {
					setIsCreating(false);
					setNotice({ type: 'error', message: strings.error });
					console.error('Pattern creation error:', error);
				});
		}

		if (!isOpen) return null;

		const isUpdateMode = patternExists;
		const buttonLabel = isUpdateMode ? strings.updateButton : strings.createButton;

		return el(
			Modal,
			{
				title: el(
					Fragment,
					null,
					el('span', { 
						className: 'dashicons dashicons-layout', 
						style: { marginRight: '8px', color: '#2271b1' } 
					}),
					strings.modalTitle
				),
				onRequestClose: handleClose,
				className: 'gutenblock-pro-pattern-creator-modal',
			},
			notice &&
				el(
					Notice,
					{
						status: notice.type,
						isDismissible: true,
						onRemove: () => setNotice(null),
					},
					notice.message
				),
			// Show update mode notice
			isUpdateMode && !notice &&
				el(
					Notice,
					{
						status: 'warning',
						isDismissible: false,
						className: 'gutenblock-pro-update-notice',
					},
					el('strong', null, existingPatternInfo?.title || slug),
					' - ',
					strings.updateModeHelp || 'Pattern exists. Only content.html will be updated. CSS/JS preserved.'
				),
			el(
				'div',
				{ className: 'gutenblock-pro-pattern-creator-form' },
				el(TextControl, {
					label: strings.nameLabel,
					value: name,
					onChange: handleNameChange,
					placeholder: strings.namePlaceholder,
					autoFocus: true,
				}),
				el(TextControl, {
					label: strings.slugLabel,
					value: slug,
					onChange: handleSlugChange,
					help: isChecking ? 'Prüfe...' : (isUpdateMode ? '✓ Pattern existiert' : strings.slugHelp),
					className: isUpdateMode ? 'slug-exists' : '',
				}),
			el(TextareaControl, {
				label: strings.descLabel,
				value: description,
				onChange: setDescription,
				placeholder: strings.descPlaceholder,
				rows: 2,
			}),
			el(TextareaControl, {
				label: strings.aiHintLabel || 'AI Hint',
				value: aiHint,
				onChange: setAiHint,
				placeholder: strings.aiHintPlaceholder || 'Aufbau, Einsatzbereich, Stil, Zielgruppe',
				rows: 3,
			}),
			el('div', { className: 'gutenblock-pro-ai-suggest-row', style: { margin: '4px 0 12px' } },
				el(Button, {
					variant: 'secondary',
					icon: 'admin-generic',
					onClick: handleAiSuggest,
					disabled: isSuggesting || isCreating || !selectedBlocks || selectedBlocks.length === 0,
					isBusy: isSuggesting,
				}, isSuggesting
					? el(Fragment, null, el(Spinner), ' ', strings.aiSuggesting || 'KI generiert…')
					: (strings.aiSuggestButton || 'Beschreibung & AI Hint mit KI generieren (EN)'))
			),
			el(TextControl, {
				label: strings.keywordsLabel,
				value: keywords,
				onChange: setKeywords,
				placeholder: strings.keywordsPlaceholder,
			}),
			el(SelectControl, {
				label: strings.typeLabel || 'Typ',
				value: patternType,
				onChange: setPatternType,
				options: [
					{ value: 'pattern', label: strings.typePattern || 'Pattern' },
					{ value: 'page', label: strings.typePage || 'Seite' },
				],
				help: patternType === 'page'
					? 'Alle markierten Blöcke werden als eine zusammenhängende Seite gespeichert.'
					: 'Einzelnes wiederverwendbares Pattern.',
			}),
			patternType === 'page' && el(SelectControl, {
				label: strings.pageTypeLabel || 'Ziel-Unterseite',
				value: pageType,
				onChange: setPageType,
				options: gutenblockProCreator.pageTypes || [
					{ value: '', label: '— Keine Zuordnung —' }
				],
				help: strings.pageTypeHelp || 'Ordnet diese Seitenvorlage einer SaaS-Unterseite zu (z. B. „Services Page“).',
			}),
			patternType === 'pattern' && el(SelectControl, {
				label: strings.groupLabel || 'Gruppe',
				value: group,
				onChange: setGroup,
				options: gutenblockProCreator.groups || [
					{ value: '', label: strings.groupNone || '— Keine Gruppe —' }
				],
			}),
			el(CheckboxControl, {
				label: 'Paid Feature',
				checked: isPremium,
				onChange: setIsPremium,
				help: 'Wenn aktiviert, benötigt dieses Pattern eine Pro Plus Lizenz.',
			}),
			el(CheckboxControl, {
				label: strings.enableTonesLabel || 'Tonalitäts-Varianten anbieten (Dark + Soft)',
				checked: enableTones && toneCapability.supported,
				onChange: setEnableTones,
				disabled: !toneCapability.supported,
				help: !toneCapability.supported
					? (strings.tonesUnsupported || 'Nicht möglich: Top-Block hat Bild/Gradient als Hintergrund.')
					: (strings.enableTonesHelp || 'Erzeugt Dark- und Soft-Varianten dieses Patterns für FSE und SaaS.'),
			}),
				el(
					'div',
					{ className: 'gutenblock-pro-pattern-creator-actions' },
					el(
						Button,
						{
							variant: 'secondary',
							onClick: handleClose,
							disabled: isCreating,
						},
						strings.cancelButton
					),
					el(
						Button,
						{
							variant: 'primary',
							onClick: handleCreate,
							disabled: isCreating || !name.trim() || isChecking,
							isBusy: isCreating,
							className: isUpdateMode ? 'is-update-mode' : '',
						},
						isCreating
							? el(Fragment, null, el(Spinner), ' ', strings.creating)
							: buttonLabel
					)
				)
			)
		);
	}

	/**
	 * Block-Name (metadata.name) des ersten Blocks aus der Gutenberg-Block-Liste auslesen.
	 * Dies entspricht dem "Name"-Feld in den Listenansicht-Einstellungen.
	 */
	function detectBlockName(blocks) {
		if (!blocks || !blocks.length) return '';
		// metadata.name ist z. B. "Hero v3" wenn der Block in der Listenansicht umbenannt wurde
		const first = blocks[0];
		return (first && first.attributes && first.attributes.metadata && first.attributes.metadata.name)
			? first.attributes.metadata.name
			: '';
	}

	/**
	 * Global Modal Container (rendered once)
	 */
	function GlobalModalContainer() {
		const [isOpen, setIsOpen] = useState(false);
		const [blocks, setBlocks] = useState([]);
		const [initialName, setInitialName] = useState('');

		// Register the callback so toolbar buttons can open the modal
		openModalCallback = (selectedBlocks) => {
			setBlocks(selectedBlocks);
			setInitialName(detectBlockName(selectedBlocks));
			setIsOpen(true);
		};

		return el(PatternCreatorModal, {
			isOpen: isOpen,
			onClose: () => { setIsOpen(false); setInitialName(''); },
			selectedBlocks: blocks,
			initialName: initialName,
		});
	}

	/**
	 * Get selected blocks from editor or sidebar patterns
	 * For page type, all blocks are combined into one pattern
	 */
	function getSelectedBlocksOrPatterns() {
		if (!wp.data || !wp.data.select) {
			return [];
		}

		const blockEditor = wp.data.select('core/block-editor');
		if (!blockEditor) {
			return [];
		}

		// First try: Get selected blocks from editor
		const selectedIds = blockEditor.getSelectedBlockClientIds();
		if (selectedIds.length > 0) {
			const blocks = blockEditor.getBlocksByClientId(selectedIds).filter(Boolean);
			// Sort blocks by their order in the editor
			return blocks.sort((a, b) => {
				const aIndex = selectedIds.indexOf(a.clientId);
				const bIndex = selectedIds.indexOf(b.clientId);
				return aIndex - bIndex;
			});
		}

		// Second try: Check for selected patterns in sidebar
		const sidebar = document.querySelector('.edit-site-sidebar, .edit-post-sidebar, .interface-complementary-area');
		if (sidebar) {
			const selectedPatterns = sidebar.querySelectorAll(
				'.block-editor-block-patterns-list__item.is-selected, ' +
				'.block-editor-block-patterns-list__item[aria-selected="true"], ' +
				'.block-editor-block-patterns-list__item:focus-within, ' +
				'.block-editor-block-patterns-list__item[class*="selected"]'
			);

			if (selectedPatterns.length > 0) {
				const blocks = [];
				// Process patterns in order
				Array.from(selectedPatterns).forEach((patternEl) => {
					const patternPreview = patternEl.querySelector('.block-editor-block-preview__content, .block-editor-block-preview');
					if (patternPreview && wp.blocks && wp.blocks.parse) {
						// Try to get HTML content
						let patternHTML = '';
						
						// Method 1: Get from preview content
						if (patternPreview.innerHTML) {
							patternHTML = patternPreview.innerHTML;
						} else if (patternPreview.textContent) {
							patternHTML = patternPreview.textContent;
						}
						
						// Method 2: Try to get from data attribute
						if (!patternHTML) {
							const patternName = patternEl.getAttribute('data-pattern-name') || 
								patternEl.querySelector('[data-pattern-name]')?.getAttribute('data-pattern-name');
							if (patternName) {
								// Try to get pattern content from WordPress registry
								if (wp.data && wp.data.select('core/block-editor')) {
									const patterns = wp.data.select('core/block-editor').getPatterns();
									const pattern = patterns?.find(p => p.name === patternName);
									if (pattern && pattern.content) {
										patternHTML = pattern.content;
									}
								}
							}
						}
						
						if (patternHTML) {
							try {
								const parsed = wp.blocks.parse(patternHTML);
								if (parsed && parsed.length > 0) {
									// Add all blocks from this pattern
									blocks.push(...parsed);
								}
							} catch (e) {
								console.warn('Could not parse pattern content:', e);
							}
						}
					}
				});
				
				if (blocks.length > 0) {
					return blocks;
				}
			}
		}

		return [];
	}

	/**
	 * Add GB Pro button to block toolbar via filter
	 */
		const withGBProToolbarButton = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			const { isSelected, clientId } = props;

			// Get selected blocks from editor - this includes ALL selected blocks (multi-select)
			const selectedBlocks = useSelect((select) => {
				const { getSelectedBlockClientIds, getBlocksByClientId } = select('core/block-editor');
				const clientIds = getSelectedBlockClientIds();
				return getBlocksByClientId(clientIds).filter(Boolean);
			}, []);

			// Check if current block is part of selection (for multi-select)
			const isInSelection = useSelect((select) => {
				const { getSelectedBlockClientIds } = select('core/block-editor');
				const selectedIds = getSelectedBlockClientIds();
				return selectedIds.includes(clientId);
			}, [clientId]);

			// Also check for patterns in sidebar
			const [hasSidebarSelection, setHasSidebarSelection] = useState(false);
			
			useEffect(() => {
				const checkSidebar = () => {
					const sidebar = document.querySelector('.edit-site-sidebar, .edit-post-sidebar, .interface-complementary-area');
					if (sidebar) {
						const selectedPatterns = sidebar.querySelectorAll(
							'.block-editor-block-patterns-list__item.is-selected, ' +
							'.block-editor-block-patterns-list__item[aria-selected="true"], ' +
							'.block-editor-block-patterns-list__item:focus-within'
						);
						setHasSidebarSelection(selectedPatterns.length > 0);
					} else {
						setHasSidebarSelection(false);
					}
				};

				// Check initially
				checkSidebar();

				// Listen for changes
				const observer = new MutationObserver(checkSidebar);
				observer.observe(document.body, {
					childList: true,
					subtree: true,
					attributes: true,
					attributeFilter: ['class', 'aria-selected']
				});

				// Also check periodically
				const interval = setInterval(checkSidebar, 500);

				return () => {
					observer.disconnect();
					clearInterval(interval);
				};
			}, []);

			// Update current blocks for modal
			const allSelectedBlocks = selectedBlocks.length > 0 ? selectedBlocks : getSelectedBlocksOrPatterns();
			if (allSelectedBlocks.length > 0) {
				currentSelectedBlocks = allSelectedBlocks;
			}

			const handleClick = () => {
				const blocks = selectedBlocks.length > 0 ? selectedBlocks : getSelectedBlocksOrPatterns();
				if (openModalCallback && blocks.length > 0) {
					openModalCallback(blocks);
				}
			};

			// Show button if:
			// 1. Current block is selected (single or multi-select)
			// 2. OR patterns are selected in sidebar (show on any block when patterns are selected)
			// Note: isInSelection includes isSelected, so we can use isInSelection
			const shouldShowButton = isInSelection || hasSidebarSelection;

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				shouldShowButton && el(
					BlockControls,
					{ group: 'other' },
					el(
						ToolbarGroup,
						null,
						el(ToolbarButton, {
							icon: 'layout',
							label: strings.menuLabel,
							onClick: handleClick,
							className: 'gutenblock-pro-toolbar-button',
						})
					)
				)
			);
		};
	}, 'withGBProToolbarButton');

	// Add filter to inject toolbar button
	addFilter(
		'editor.BlockEdit',
		'gutenblock-pro/toolbar-button',
		withGBProToolbarButton
	);

	/**
	 * Plugin Image Picker — toolbar button on core/image blocks.
	 * Opens a modal with all images from the plugin's assets/images/ folder.
	 */
	const withPluginImagePicker = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			const { name, attributes, clientId } = props;
			const { updateBlockAttributes } = useDispatch('core/block-editor');
			const [isOpen, setIsOpen] = useState(false);
			const [images, setImages] = useState(null); // null = not loaded yet
			const [loadError, setLoadError] = useState(false);

			const isCover = name === 'core/cover';
			const isImage = name === 'core/image';
			if (!isImage && !isCover) return el(BlockEdit, props);
			if (!gutenblockProCreator.isAllowedUser) return el(BlockEdit, props);

			const openPicker = () => {
				setIsOpen(true);
				if (images === null) {
					fetch(gutenblockProCreator.restUrl + 'plugin-images', {
						headers: { 'X-WP-Nonce': gutenblockProCreator.restNonce },
					})
						.then((r) => {
							if (!r.ok) throw new Error('HTTP ' + r.status);
							return r.json();
						})
						.then((data) => setImages(data))
						.catch(() => {
							setLoadError(true);
							setImages([]);
						});
				}
			};

			const selectImage = (img) => {
				if (isCover) {
					// core/cover uses url + id must be cleared, useFeaturedImage off
					updateBlockAttributes(clientId, {
						url: img.url,
						id: undefined,
						useFeaturedImage: false,
					});
				} else {
					// core/image
					const attrs = { url: img.url, id: undefined };
					if (img.width)  attrs.width  = img.width;
					if (img.height) attrs.height = img.height;
					updateBlockAttributes(clientId, attrs);
				}
				setIsOpen(false);
			};

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(
					BlockControls,
					{ group: 'other' },
					el(
						ToolbarGroup,
						null,
						el(ToolbarButton, {
							icon: 'format-image',
							label: 'Plugin-Bild einfügen',
							onClick: openPicker,
						})
					)
				),
				isOpen && el(
					Modal,
					{
						title: 'Plugin-Bilder',
						onRequestClose: () => setIsOpen(false),
						style: { maxWidth: '640px', width: '100%' },
					},
					images === null
						? el('div', { style: { textAlign: 'center', padding: '32px' } },
							el(Spinner),
							el('p', { style: { marginTop: 8, color: '#666' } }, 'Bilder werden geladen…')
						  )
						: loadError
						? el('p', { style: { color: '#c00', padding: 16 } }, 'Fehler beim Laden der Bilder.')
						: images.length === 0
						? el('div', { style: { textAlign: 'center', padding: '32px', color: '#666' } },
							el('p', null, 'Keine Bilder in assets/images/ gefunden.'),
							el('p', { style: { fontSize: 12, marginTop: 4 } }, 'Lege Bilder im Ordner gutenblock-pro/assets/images/ ab.')
						  )
						: el(
							'div',
							{
								style: {
									display: 'grid',
									gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))',
									gap: '10px',
									maxHeight: '60vh',
									overflowY: 'auto',
									paddingRight: 4,
								},
							},
							images.map((img) =>
								el(
									'button',
									{
										key: img.url,
										title: img.name,
										onClick: () => selectImage(img),
										style: {
											padding: 0,
											border: '2px solid transparent',
											borderRadius: 6,
											cursor: 'pointer',
											background: '#f0f0f0',
											overflow: 'hidden',
											display: 'flex',
											flexDirection: 'column',
											alignItems: 'stretch',
										},
										onMouseEnter: (e) => { e.currentTarget.style.borderColor = '#7c3aed'; },
										onMouseLeave: (e) => { e.currentTarget.style.borderColor = 'transparent'; },
									},
									el('img', {
										src: img.url,
										alt: img.name,
										style: { width: '100%', aspectRatio: '4/3', objectFit: 'cover', display: 'block' },
									}),
									el('span', {
										style: {
											fontSize: 11,
											padding: '4px 6px',
											color: '#444',
											overflow: 'hidden',
											textOverflow: 'ellipsis',
											whiteSpace: 'nowrap',
										},
									}, img.name)
								)
							)
						  )
				)
			);
		};
	}, 'withPluginImagePicker');

	addFilter(
		'editor.BlockEdit',
		'gutenblock-pro/plugin-image-picker',
		withPluginImagePicker,
		20
	);

	/**
	 * Add icon button to toolbar when patterns are selected in sidebar
	 * This appears as a small icon, not a large button
	 */
	function addPatternToolbarIcon() {
		// Wait for editor to be ready
		const checkEditor = setInterval(() => {
			const editorToolbar = document.querySelector('.block-editor-block-toolbar, .block-editor-block-toolbar__group');
			if (!editorToolbar) {
				return;
			}

			clearInterval(checkEditor);

			// Check if icon already exists
			if (document.getElementById('gutenblock-pro-pattern-icon')) {
				return;
			}

			// Create icon button
			const iconButton = document.createElement('button');
			iconButton.id = 'gutenblock-pro-pattern-icon';
			iconButton.className = 'components-button components-icon-button gutenblock-pro-toolbar-button';
			iconButton.innerHTML = '<span class="dashicons dashicons-layout"></span>';
			iconButton.setAttribute('aria-label', strings.menuLabel);
			iconButton.style.display = 'none';
			iconButton.onclick = (e) => {
				e.preventDefault();
				e.stopPropagation();
				const blocks = getSelectedBlocksOrPatterns();
				if (blocks.length > 0 && openModalCallback) {
					openModalCallback(blocks);
				}
			};

			// Insert into toolbar
			editorToolbar.appendChild(iconButton);

			// Function to update icon visibility
			function updateIconVisibility() {
				const sidebar = document.querySelector('.edit-site-sidebar, .edit-post-sidebar, .interface-complementary-area');
				let hasPatternSelection = false;
				
				if (sidebar) {
					const selectedPatterns = sidebar.querySelectorAll(
						'.block-editor-block-patterns-list__item.is-selected, ' +
						'.block-editor-block-patterns-list__item[aria-selected="true"], ' +
						'.block-editor-block-patterns-list__item:focus-within'
					);
					hasPatternSelection = selectedPatterns.length > 0;
				}

				// Only show if patterns are selected AND no blocks are selected
				// (blocks use the existing button in BlockControls)
				if (wp.data && wp.data.select) {
					const blockEditor = wp.data.select('core/block-editor');
					if (blockEditor) {
						const selectedIds = blockEditor.getSelectedBlockClientIds();
						const hasBlockSelection = selectedIds.length > 0;
						iconButton.style.display = (hasPatternSelection && !hasBlockSelection) ? 'inline-flex' : 'none';
					}
				}
			}

			// Listen for changes
			if (wp.data && wp.data.subscribe) {
				wp.data.subscribe(updateIconVisibility);
			}

			const observer = new MutationObserver(updateIconVisibility);
			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class', 'aria-selected']
			});

			updateIconVisibility();
			setInterval(updateIconVisibility, 500);
		}, 500);

		setTimeout(() => clearInterval(checkEditor), 10000);
	}

	// Initialize pattern toolbar icon
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', addPatternToolbarIcon);
	} else {
		addPatternToolbarIcon();
	}


	// Render global modal container
	const modalContainer = document.createElement('div');
	modalContainer.id = 'gutenblock-pro-modal-container';
	document.body.appendChild(modalContainer);

	// Use wp.element.render for older WP or createRoot for newer
	if (wp.element.createRoot) {
		const root = wp.element.createRoot(modalContainer);
		root.render(el(GlobalModalContainer));
	} else {
		wp.element.render(el(GlobalModalContainer), modalContainer);
	}

})(window.wp);
