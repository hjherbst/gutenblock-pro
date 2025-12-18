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
	function PatternCreatorModal({ isOpen, onClose, selectedBlocks }) {
		const [name, setName] = useState('');
		const [slug, setSlug] = useState('');
		const [description, setDescription] = useState('');
		const [keywords, setKeywords] = useState('');
		const [language, setLanguage] = useState('default');
		const [patternType, setPatternType] = useState('pattern');
		const [group, setGroup] = useState('');
		const [replaceImages, setReplaceImages] = useState(true);
		const [isPremium, setIsPremium] = useState(false);
		const [isCreating, setIsCreating] = useState(false);
		const [notice, setNotice] = useState(null);
		const [slugManuallyEdited, setSlugManuallyEdited] = useState(false);
		const [patternExists, setPatternExists] = useState(false);
		const [existingPatternInfo, setExistingPatternInfo] = useState(null);
		const [isChecking, setIsChecking] = useState(false);

		// Load premium status from existing pattern if it exists
		useEffect(() => {
			if (existingPatternInfo && existingPatternInfo.premium !== undefined) {
				setIsPremium(existingPatternInfo.premium);
			}
		}, [existingPatternInfo]);

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
			setLanguage('default');
			setPatternType('pattern');
			setGroup('');
			setReplaceImages(true);
			setIsPremium(false);
			setNotice(null);
			setSlugManuallyEdited(false);
			setPatternExists(false);
			setExistingPatternInfo(null);
		}

		function handleClose() {
			resetForm();
			onClose();
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
			formData.append('language', language);
			formData.append('type', patternType);
			formData.append('group', group);
			formData.append('replace_images', replaceImages ? 'true' : 'false');
			formData.append('premium', isPremium ? 'true' : 'false');
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
				// Only show these fields for new patterns
				!isUpdateMode && el(TextareaControl, {
					label: strings.descLabel,
					value: description,
					onChange: setDescription,
					placeholder: strings.descPlaceholder,
					rows: 2,
				}),
				!isUpdateMode && el(TextControl, {
					label: strings.keywordsLabel,
					value: keywords,
					onChange: setKeywords,
					placeholder: strings.keywordsPlaceholder,
				}),
				el(SelectControl, {
					label: strings.languageLabel,
					value: language,
					onChange: setLanguage,
					options: gutenblockProCreator.languages || [
						{ value: 'default', label: 'Default' }
					],
					help: strings.languageHelp,
				}),
				!isUpdateMode && el(SelectControl, {
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
				!isUpdateMode && el(SelectControl, {
					label: strings.groupLabel || 'Gruppe',
					value: group,
					onChange: setGroup,
					options: gutenblockProCreator.groups || [
						{ value: '', label: strings.groupNone || '— Keine Gruppe —' }
					],
				}),
				el(CheckboxControl, {
					label: strings.replaceImagesLabel,
					checked: replaceImages,
					onChange: setReplaceImages,
					help: strings.replaceImagesHelp,
				}),
				!isUpdateMode && el(CheckboxControl, {
					label: 'Premium Pattern (Pro Plus erforderlich)',
					checked: isPremium,
					onChange: setIsPremium,
					help: 'Wenn aktiviert, benötigt dieses Pattern eine Pro Plus Lizenz für die Bearbeitung. Kann aber als Vorschau eingefügt werden.',
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
	 * Global Modal Container (rendered once)
	 */
	function GlobalModalContainer() {
		const [isOpen, setIsOpen] = useState(false);
		const [blocks, setBlocks] = useState([]);

		// Register the callback so toolbar buttons can open the modal
		openModalCallback = (selectedBlocks) => {
			setBlocks(selectedBlocks);
			setIsOpen(true);
		};

		return el(PatternCreatorModal, {
			isOpen: isOpen,
			onClose: () => setIsOpen(false),
			selectedBlocks: blocks,
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
