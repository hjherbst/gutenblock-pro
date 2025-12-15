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
	const { useSelect } = wp.data;
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
		const [isCreating, setIsCreating] = useState(false);
		const [notice, setNotice] = useState(null);
		const [slugManuallyEdited, setSlugManuallyEdited] = useState(false);
		const [patternExists, setPatternExists] = useState(false);
		const [existingPatternInfo, setExistingPatternInfo] = useState(null);
		const [isChecking, setIsChecking] = useState(false);

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
	 * Add GB Pro button to block toolbar via filter
	 */
	const withGBProToolbarButton = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			const { isSelected, clientId } = props;

			const selectedBlocks = useSelect((select) => {
				const { getSelectedBlockClientIds, getBlocksByClientId } = select('core/block-editor');
				const clientIds = getSelectedBlockClientIds();
				return getBlocksByClientId(clientIds).filter(Boolean);
			}, []);

			// Update current blocks for modal
			if (selectedBlocks.length > 0) {
				currentSelectedBlocks = selectedBlocks;
			}

			const handleClick = () => {
				if (openModalCallback && selectedBlocks.length > 0) {
					openModalCallback(selectedBlocks);
				}
			};

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				isSelected && el(
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
