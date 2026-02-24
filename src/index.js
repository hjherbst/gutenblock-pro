/**
 * GutenBlock Pro - Editor Scripts
 *
 * This file is compiled by @wordpress/scripts
 */

import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter as addBlocksFilter } from '@wordpress/hooks';
import { Fragment, useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { InspectorControls, BlockControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, Button, Spinner, TextareaControl, Notice, ComboboxControl, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useBlockProps } from '@wordpress/block-editor';

// Import editor styles
import './editor.scss';

// Material Icon Block (Feature: material-icons)
import './blocks/material-icon';

/**
 * Set/remove generating animation on a block
 */
function setGeneratingAnimation(clientId, isGenerating) {
	const blockElement = document.querySelector(`[data-block="${clientId}"]`);
	
	if (blockElement) {
		if (isGenerating) {
			blockElement.classList.add('gutenblock-ai-generating');
		} else {
			blockElement.classList.remove('gutenblock-ai-generating');
		}
	}
}

/**
 * Token Usage Display Component
 */
const TokenUsage = ({ usage }) => {
	if (!usage) return null;

	if (usage.is_pro) {
		return (
			<div className="gb-ai-tokens gb-ai-tokens-pro">
				<span className="dashicons dashicons-awards"></span>
				{__('Unbegrenzte Tokens (Pro)', 'gutenblock-pro')}
			</div>
		);
	}

	const percentage = Math.min(100, (usage.used / usage.limit) * 100);
	const barClass = percentage > 95 ? 'critical' : percentage > 80 ? 'warning' : '';

	return (
		<div className="gb-ai-tokens">
			<div className="gb-ai-tokens-bar">
				<div 
					className={`gb-ai-tokens-progress ${barClass}`} 
					style={{ width: `${percentage}%` }}
				></div>
			</div>
			<div className="gb-ai-tokens-text">
				{usage.used.toLocaleString()} / {usage.limit.toLocaleString()} Tokens
			</div>
		</div>
	);
};

/**
 * Recursively get all blocks with content fields from a group/wrapper block
 * IMPORTANT: Only processes blocks that are passed in - does NOT search the entire page
 */
function getAllContentFields(blocks, prompts) {
	const contentFields = [];
	const seenClientIds = new Set(); // Track already processed blocks by clientId
	
	function traverse(blockList) {
		if (!blockList || !Array.isArray(blockList)) return;
		
		blockList.forEach(block => {
			// Skip if we've already processed this block by clientId
			if (!block || !block.clientId || seenClientIds.has(block.clientId)) {
				return;
			}
			
			seenClientIds.add(block.clientId);
			
			const metadataName = block.attributes?.metadata?.name;
			if (metadataName && prompts[metadataName]) {
				// Add ALL blocks with content fields, even if they have the same fieldName
				// (because the same fieldName can appear multiple times in a group)
				const blockText = getBlockText(block.name, block.attributes);
				contentFields.push({
					clientId: block.clientId,
					blockName: block.name,
					fieldName: metadataName,
					prompt: prompts[metadataName].prompt || '',
					currentText: blockText,
					attributes: block.attributes,
				});
			}
			
			// Recursively check inner blocks
			if (block.innerBlocks && block.innerBlocks.length > 0) {
				traverse(block.innerBlocks);
			}
		});
	}
	
	traverse(blocks);
	return contentFields;
}

/**
 * AI Panel Component for Block Inspector
 */
const AIPanel = ({ clientId, blockName, attributes }) => {
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState('');
	const [tempPrompt, setTempPrompt] = useState('');
	const [usage, setUsage] = useState(null);
	const [prompts, setPrompts] = useState({});
	const [searchTerm, setSearchTerm] = useState('');

	const { updateBlockAttributes } = useDispatch('core/block-editor');
	const { getBlocks } = useSelect((select) => ({
		getBlocks: select('core/block-editor').getBlocks,
	}));

	// Get block metadata name (if set)
	const metadataName = attributes?.metadata?.name || '';
	
	// Check if this is a group/wrapper block
	const isGroupBlock = ['core/group', 'core/columns', 'core/cover', 'core/column'].includes(blockName);

	// Load prompts and usage on mount
	useEffect(() => {
		// Fetch prompts
		apiFetch({ path: '/gutenblock-pro/v1/prompts' })
			.then(data => {
				setPrompts(data || {});
				// Set initial prompt if we have one for this block
				if (metadataName && data[metadataName]) {
					setTempPrompt(data[metadataName].prompt || '');
				}
			})
			.catch(err => console.error('Error loading prompts:', err));

		// Fetch usage
		apiFetch({ path: '/gutenblock-pro/v1/ai/usage' })
			.then(data => setUsage(data))
			.catch(err => console.error('Error loading usage:', err));
	}, []);

	// Update prompt when metadataName changes
	useEffect(() => {
		if (metadataName && prompts[metadataName]) {
			setTempPrompt(prompts[metadataName].prompt || '');
		}
	}, [metadataName, prompts]);

	/**
	 * Build options for ComboboxControl
 */
	const contentFieldOptions = useMemo(() => {
		const options = Object.entries(prompts).map(([fieldId]) => ({
			value: fieldId,
			label: fieldId,
		}));
		
		// Sort alphabetically
		options.sort((a, b) => a.label.localeCompare(b.label));
		
		return options;
	}, [prompts]);

	/**
	 * Handle content field selection
	 */
	const handleFieldSelect = (fieldId) => {
		if (!fieldId) {
			// Clear selection
			updateBlockAttributes(clientId, {
				metadata: {
					...attributes.metadata,
					name: undefined,
				},
			});
			setTempPrompt('');
			return;
		}

		// Update block metadata.name
		updateBlockAttributes(clientId, {
			metadata: {
				...attributes.metadata,
				name: fieldId,
			},
		});

		// Load the prompt for this field
		if (prompts[fieldId]) {
			setTempPrompt(prompts[fieldId].prompt || '');
		}
	};

	/**
	 * Generate text for this block
	 */
	const generateText = async (feedback = null) => {
		setIsLoading(true);
		setError('');
		setGeneratingAnimation(clientId, true);

		try {
			const currentText = getBlockText(blockName, attributes);
			
			const response = await apiFetch({
				path: '/gutenblock-pro/v1/ai/generate',
				method: 'POST',
				data: {
					prompt: tempPrompt,
					blockName: metadataName || blockName,
					currentText: currentText,
					feedback: feedback,
				},
			});

			if (response.success && response.text) {
				updateBlockContent(clientId, blockName, response.text, updateBlockAttributes);
				
				if (response.usage) {
					setUsage(response.usage);
				}
			}
		} catch (err) {
			setError(err.message || __('Fehler bei der Generierung', 'gutenblock-pro'));
		} finally {
			setIsLoading(false);
			setGeneratingAnimation(clientId, false);
		}
	};

	/**
	 * Translate current block text to a specific language.
	 */
	const translateToLanguage = async (promptLang) => {
		setIsLoading(true);
		setError('');
		setGeneratingAnimation(clientId, true);

		try {
			const currentText = getBlockText(blockName, attributes);
			
			if (!currentText || currentText.length < 3) {
				setError(__('Mindestens 3 Zeichen Text erforderlich', 'gutenblock-pro'));
				setGeneratingAnimation(clientId, false);
				setIsLoading(false);
				return;
			}

			const response = await apiFetch({
				path: '/gutenblock-pro/v1/ai/generate',
				method: 'POST',
				data: {
					prompt: `Übersetze den folgenden Text ${promptLang}. Antworte nur mit der Übersetzung:\n\n${currentText}`,
					blockName: 'translation',
				},
			});

			if (response.success && response.text) {
				updateBlockContent(clientId, blockName, response.text, updateBlockAttributes);
				
				if (response.usage) {
					setUsage(response.usage);
				}
			}
		} catch (err) {
			setError(err.message || __('Fehler bei der Übersetzung', 'gutenblock-pro'));
		} finally {
			setIsLoading(false);
			setGeneratingAnimation(clientId, false);
		}
	};

	// Check if this block type is supported
	const supportedBlocks = ['core/paragraph', 'core/heading', 'core/button', 'core/list-item'];
	const supportedGroupBlocks = ['core/group', 'core/columns', 'core/cover', 'core/column'];
	
	// If it's a group block, show group AI panel (always show, even if no fields found)
	if (isGroupBlock) {
		return <GroupAIPanel clientId={clientId} blockName={blockName} attributes={attributes} />;
	}
	
	// Otherwise, show single block AI panel only for supported blocks
	if (!supportedBlocks.includes(blockName)) {
		return null;
	}

	const hasValidPrompt = tempPrompt && tempPrompt.trim().length >= 3;

	return (
		<>
		<PanelBody title={__('GutenBlock AI', 'gutenblock-pro')} initialOpen={true}>
			{/* Token Usage */}
			<PanelRow>
				<TokenUsage usage={usage} />
			</PanelRow>

			{/* Content Field Selector */}
			<PanelRow>
				<div className="gb-ai-field-selector">
					<ComboboxControl
						label={__('Content-Feld', 'gutenblock-pro')}
						value={metadataName}
						onChange={handleFieldSelect}
						options={contentFieldOptions}
						onFilterValueChange={(inputValue) => setSearchTerm(inputValue)}
						__next40pxDefaultSize={true}
						__nextHasNoMarginBottom={true}
					/>
					{metadataName && (
						<div className="gb-ai-field-badge">
							<span className="dashicons dashicons-yes-alt"></span>
							{metadataName}
						</div>
					)}
				</div>
			</PanelRow>

			{/* Prompt Display/Edit */}
			<PanelRow>
				<TextareaControl
					label={metadataName ? __('Prompt (aus Content-Feld)', 'gutenblock-pro') : __('Eigener Prompt', 'gutenblock-pro')}
					value={tempPrompt}
					onChange={setTempPrompt}
					placeholder={__('Wähle ein Content-Feld oder schreibe einen eigenen Prompt...', 'gutenblock-pro')}
					rows={3}
					help={metadataName ? __('Der Prompt wird aus dem Content-Feld geladen. Du kannst ihn hier temporär anpassen.', 'gutenblock-pro') : null}
					__nextHasNoMarginBottom={true}
				/>
			</PanelRow>

			{/* Generate Buttons */}
			<PanelRow>
				<div className="gb-ai-buttons">
					<Button
						isPrimary
						onClick={() => generateText()}
						disabled={isLoading || !hasValidPrompt}
						className="gb-ai-btn-generate"
					>
						{isLoading ? <Spinner /> : __('Generieren', 'gutenblock-pro')}
					</Button>

					<div className="gb-ai-btn-row">
						<Button
							isSecondary
							onClick={() => generateText('Leicht ändern, andere Wortwahl.')}
							disabled={isLoading || !hasValidPrompt}
							size="small"
						>
							{__('Variante', 'gutenblock-pro')}
						</Button>
						<Button
							isSecondary
							onClick={() => generateText('Komplett anderer Ansatz.')}
							disabled={isLoading || !hasValidPrompt}
							size="small"
						>
							{__('Neu', 'gutenblock-pro')}
						</Button>
					</div>

					<div className="gb-ai-btn-row">
						<Button
							isSecondary
							onClick={() => generateText('Verlängere den Text um 10%.')}
							disabled={isLoading || !hasValidPrompt}
							size="small"
						>
							{__('+10%', 'gutenblock-pro')}
						</Button>
						<Button
							isSecondary
							onClick={() => generateText('Kürze den Text um 10%.')}
							disabled={isLoading || !hasValidPrompt}
							size="small"
						>
							{__('-10%', 'gutenblock-pro')}
						</Button>
					</div>
				</div>
			</PanelRow>

			{/* Error Message */}
			{error && (
				<PanelRow>
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				</PanelRow>
			)}
		</PanelBody>
		<TranslatePanel
			clientId={clientId}
			blockName={blockName}
			attributes={attributes}
			translateToLanguage={translateToLanguage}
			isLoading={isLoading}
		/>
		</>
	);
};

/**
 * TranslatePanel – Grouped section with per-language buttons and Translate All.
 */
const TranslatePanel = ({ clientId, blockName, attributes, translateToLanguage, isLoading }) => {
	const languages = window.gutenblockProConfig?.translateLanguages || [];
	const aiSettingsUrl = window.gutenblockProConfig?.aiSettingsUrl || '/wp-admin/admin.php?page=gutenblock-pro-ai';
	const isPro = window.gutenblockProConfig?.isPro || false;
	const [showWarning, setShowWarning] = useState(false);
	const [selectedLang, setSelectedLang] = useState(null);
	const [batchRunning, setBatchRunning] = useState(false);
	const [batchProgress, setBatchProgress] = useState('');
	const [batchError, setBatchError] = useState('');

	const { updateBlockAttributes } = useDispatch('core/block-editor');
	const allBlocks = useSelect((select) => select('core/block-editor').getBlocks(), []);

	if (!languages.length) return null;

	const collectTextBlocks = (blocks) => {
		const result = [];
		const supported = ['core/paragraph', 'core/heading', 'core/button', 'core/list-item'];
		const traverse = (list) => {
			if (!list) return;
			list.forEach((block) => {
				if (supported.includes(block.name)) {
					const text = getBlockText(block.name, block.attributes);
					if (text && text.length > 2) {
						result.push({ clientId: block.clientId, blockName: block.name, text });
					}
				}
				if (block.innerBlocks?.length) traverse(block.innerBlocks);
			});
		};
		traverse(blocks);
		return result;
	};

	const runTranslateAll = async (lang) => {
		const textBlocks = collectTextBlocks(allBlocks);
		if (!textBlocks.length) {
			setBatchError(__('Keine übersetzbaren Textblöcke gefunden.', 'gutenblock-pro'));
			return;
		}
		setBatchRunning(true);
		setBatchError('');
		let done = 0;
		for (const block of textBlocks) {
			setBatchProgress(`${done + 1}/${textBlocks.length}`);
			setGeneratingAnimation(block.clientId, true);
			try {
				const response = await apiFetch({
					path: '/gutenblock-pro/v1/ai/generate',
					method: 'POST',
					data: {
						prompt: `Übersetze den folgenden Text ${lang.promptLang}. Antworte nur mit der Übersetzung:\n\n${block.text}`,
						blockName: 'translation',
					},
				});
				if (response.success && response.text) {
					updateBlockContent(block.clientId, block.blockName, response.text, updateBlockAttributes);
				}
			} catch (err) {
				if (err?.code === 'token_limit_reached' || err?.data?.status === 403) {
					setGeneratingAnimation(block.clientId, false);
					setBatchError(`Token-Limit erreicht. ${done} von ${textBlocks.length} Blöcken übersetzt.`);
					setBatchRunning(false);
					setBatchProgress('');
					return;
				}
			} finally {
				setGeneratingAnimation(block.clientId, false);
			}
			done++;
		}
		setBatchProgress('');
		setBatchRunning(false);
	};

	return (
		<PanelBody title={__('Übersetzen', 'gutenblock-pro')} initialOpen={false}>
			<PanelRow>
				<div className="gb-ai-buttons gb-translate-grid">
					{languages.map((lang) => (
						<div key={lang.code} className="gb-translate-lang-row">
							<Button
								isSecondary
								size="small"
								className="gb-translate-lang-btn"
								disabled={isLoading || batchRunning}
								onClick={() => translateToLanguage(lang.promptLang)}
							>
								{`→ ${lang.label}`}
							</Button>
							<Button
								isSecondary
								size="small"
								className="gb-translate-all-btn"
								disabled={batchRunning}
								onClick={() => { setSelectedLang(lang); setShowWarning(true); }}
							>
								{lang.translateAll}
							</Button>
						</div>
					))}

					{batchRunning && batchProgress && (
						<div className="gb-translate-progress">
							<Spinner /> {batchProgress} {__('übersetzt…', 'gutenblock-pro')}
						</div>
					)}
					{batchError && (
						<Notice status="warning" isDismissible={false} className="gb-translate-notice">
							{batchError}
						</Notice>
					)}
				</div>
			</PanelRow>
			{showWarning && selectedLang && (
				<Modal
					title={__('Ganze Seite übersetzen', 'gutenblock-pro')}
					onRequestClose={() => setShowWarning(false)}
					isDismissible={true}
				>
					{isPro ? (
						<p>{__('Alle Textblöcke auf dieser Seite werden übersetzt. Das kann einen Moment dauern.', 'gutenblock-pro')}</p>
					) : (
						<p>{__('Die Übersetzung aller Textblöcke auf dieser Seite kann viele Tokens verbrauchen.', 'gutenblock-pro')}</p>
					)}
					<div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 16 }}>
						{!isPro && (
							<Button
								isSecondary
								onClick={() => { setShowWarning(false); window.open(aiSettingsUrl, '_blank'); }}
							>
								{__('Token-Limit erhöhen', 'gutenblock-pro')}
							</Button>
						)}
						<Button
							isPrimary
							onClick={() => { setShowWarning(false); runTranslateAll(selectedLang); }}
						>
							{isPro ? __('Übersetzen', 'gutenblock-pro') : __('Trotzdem übersetzen', 'gutenblock-pro')}
						</Button>
						<Button
							isTertiary
							onClick={() => setShowWarning(false)}
						>
							{__('Abbrechen', 'gutenblock-pro')}
						</Button>
					</div>
				</Modal>
			)}
		</PanelBody>
	);
};

/**
 * Get text content from a block
 */
function getBlockText(blockName, attributes) {
	switch (blockName) {
		case 'core/paragraph':
		case 'core/heading':
		case 'core/list-item':
			return attributes?.content || '';
		case 'core/button':
			return attributes?.text || '';
		default:
			return '';
	}
}

/**
 * Group AI Panel Component - for generating multiple content fields at once
 */
const GroupAIPanel = ({ clientId, blockName, attributes }) => {
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState('');
	const [groupPrompt, setGroupPrompt] = useState('');
	const [prompts, setPrompts] = useState({});
	const [usage, setUsage] = useState(null);
	
	const { updateBlockAttributes } = useDispatch('core/block-editor');
	
	// Get the block directly from the store with its innerBlocks
	const block = useSelect((select) => {
		return select('core/block-editor').getBlock(clientId);
	}, [clientId]);
	
	// Load prompts and usage on mount
	useEffect(() => {
		apiFetch({ path: '/gutenblock-pro/v1/prompts' })
			.then(data => setPrompts(data || {}))
			.catch(err => console.error('Error loading prompts:', err));

		apiFetch({ path: '/gutenblock-pro/v1/ai/usage' })
			.then(data => setUsage(data))
			.catch(err => console.error('Error loading usage:', err));
	}, []);
	
	// Calculate content fields using useMemo to avoid recalculation and duplicates
	const contentFields = useMemo(() => {
		if (!block || !block.innerBlocks || Object.keys(prompts).length === 0) {
			return [];
		}
		
		// Use Map to ensure each clientId appears only once
		const fieldsMap = new Map();
		
		// Recursively collect all fields from innerBlocks
		function collectFields(blocks) {
			if (!blocks || !Array.isArray(blocks)) return;
			
			blocks.forEach(innerBlock => {
				if (!innerBlock || !innerBlock.clientId) return;
				
				// Skip if already processed
				if (fieldsMap.has(innerBlock.clientId)) return;
				
				const metadataName = innerBlock.attributes?.metadata?.name;
				if (metadataName && prompts[metadataName]) {
					const blockText = getBlockText(innerBlock.name, innerBlock.attributes);
					fieldsMap.set(innerBlock.clientId, {
						clientId: innerBlock.clientId,
						blockName: innerBlock.name,
						fieldName: metadataName,
						prompt: prompts[metadataName].prompt || '',
						currentText: blockText,
						attributes: innerBlock.attributes,
					});
				}
				
				// Recursively check inner blocks
				if (innerBlock.innerBlocks && innerBlock.innerBlocks.length > 0) {
					collectFields(innerBlock.innerBlocks);
				}
			});
		}
		
		collectFields(block.innerBlocks);
		return Array.from(fieldsMap.values());
	}, [block, prompts]);
	
	// Update group prompt when fields are first found
	useEffect(() => {
		if (contentFields.length > 0 && !groupPrompt) {
			const fieldNames = [...new Set(contentFields.map(f => f.fieldName))].join(', ');
			setGroupPrompt(`Generiere passende Inhalte für alle folgenden Content-Felder: ${fieldNames}`);
		}
	}, [contentFields, groupPrompt]);
	
	/**
	 * Generate content for all fields in the group
	 */
	const generateGroupContent = async (feedback = null) => {
		if (contentFields.length === 0) {
			setError(__('Keine Content-Felder in dieser Gruppe gefunden.', 'gutenblock-pro'));
			return;
		}
		
		setIsLoading(true);
		setError('');
		
		// Mark all blocks as generating
		contentFields.forEach(field => {
			setGeneratingAnimation(field.clientId, true);
		});
		
		try {
			// Build request with all fields
			const fieldsData = contentFields.map(field => ({
				clientId: field.clientId,
				blockName: field.blockName,
				fieldName: field.fieldName,
				prompt: field.prompt,
				currentText: field.currentText,
			}));
			
			const response = await apiFetch({
				path: '/gutenblock-pro/v1/ai/generate-group',
				method: 'POST',
				data: {
					groupPrompt: groupPrompt || '',
					fields: fieldsData,
					feedback: feedback,
				},
			});
			
			if (response.success && response.fields) {
				// Update all blocks with their generated content
				response.fields.forEach((fieldData) => {
					const field = contentFields.find(f => f.clientId === fieldData.clientId);
					if (field) {
						updateBlockContent(field.clientId, field.blockName, fieldData.text, updateBlockAttributes);
					}
				});
				
				if (response.usage) {
					// Usage is handled globally
				}
			} else {
				setError(response.message || __('Fehler bei der Generierung', 'gutenblock-pro'));
			}
		} catch (err) {
			setError(err.message || __('Fehler bei der Generierung', 'gutenblock-pro'));
		} finally {
			setIsLoading(false);
			contentFields.forEach(field => {
				setGeneratingAnimation(field.clientId, false);
			});
		}
	};
	
	return (
		<PanelBody title={__('GutenBlock AI - Gruppe', 'gutenblock-pro')} initialOpen={true}>
			{/* Token Usage */}
			<PanelRow>
				<TokenUsage usage={usage} />
			</PanelRow>
			
			{/* Content Fields Overview */}
			<PanelRow>
				<div className="gb-ai-group-fields">
					<strong>{__('Gefundene Content-Felder:', 'gutenblock-pro')}</strong>
					{contentFields.length === 0 ? (
						<p style={{ color: '#757575', fontSize: '12px', marginTop: '8px' }}>
							{__('Keine Content-Felder mit metadata.name in dieser Gruppe gefunden.', 'gutenblock-pro')}
						</p>
					) : (
						<ul style={{ marginTop: '8px', marginBottom: '16px', paddingLeft: '20px' }}>
							{contentFields.map((field, index) => (
								<li key={index} style={{ marginBottom: '4px', fontSize: '12px' }}>
									<strong>{field.fieldName}</strong> ({field.blockName})
								</li>
							))}
						</ul>
					)}
				</div>
			</PanelRow>
			
			{/* Group Prompt */}
			<PanelRow>
				<TextareaControl
					label={__('Gruppen-Prompt', 'gutenblock-pro')}
					value={groupPrompt}
					onChange={setGroupPrompt}
					placeholder={__('Beschreibe, was für alle Content-Felder generiert werden soll...', 'gutenblock-pro')}
					rows={3}
					help={__('Dieser Prompt wird für alle Content-Felder in der Gruppe verwendet.', 'gutenblock-pro')}
					__nextHasNoMarginBottom={true}
				/>
			</PanelRow>
			
			{/* Generate Button */}
			<PanelRow>
				<Button
					isPrimary
					onClick={() => generateGroupContent()}
					disabled={isLoading || contentFields.length === 0 || !groupPrompt.trim()}
					className="gb-ai-btn-generate"
				>
					{isLoading ? <Spinner /> : __('Alle Felder generieren', 'gutenblock-pro')}
				</Button>
				{contentFields.length === 0 && (
					<p style={{ color: '#757575', fontSize: '12px', marginTop: '8px' }}>
						{__('Hinweis: Füge Blöcke mit metadata.name in diese Gruppe ein, um sie hier zu sehen.', 'gutenblock-pro')}
					</p>
				)}
			</PanelRow>
			
			{/* Error Message */}
			{error && (
				<PanelRow>
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				</PanelRow>
			)}
		</PanelBody>
	);
};

/**
 * Update block content based on type
 */
function updateBlockContent(clientId, blockName, newText, updateBlockAttributes) {
	switch (blockName) {
		case 'core/paragraph':
		case 'core/heading':
		case 'core/list-item':
			updateBlockAttributes(clientId, { content: newText });
			break;
		case 'core/button':
			updateBlockAttributes(clientId, { text: newText });
			break;
	}
}

/**
 * Higher Order Component to add AI controls to blocks
 */
const withAIControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { clientId, name, attributes } = props;
		
		const hasPremium = window.gutenblockProConfig?.hasPremium || false;
		const premiumSlugs = window.gutenblockProConfig?.premiumPatterns || [];
		const className = attributes?.className || '';
		const isPremiumPattern = premiumSlugs.some((slug) => className.includes(`gb-pattern-${slug}`));

		if (isPremiumPattern && !hasPremium) {
			return <BlockEdit {...props} />;
		}

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<AIPanel 
						clientId={clientId} 
						blockName={name} 
						attributes={attributes} 
					/>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withAIControls');

// Add filter to inject AI controls (lower priority than premium lock)
addFilter(
	'editor.BlockEdit',
	'gutenblock-pro/ai-controls',
	withAIControls,
	10 // Lower priority - runs after premium lock
);

/**
 * Higher Order Component to block editing for Premium Patterns
 */
// Filter to restrict block supports for premium patterns
addFilter(
	'blocks.registerBlockType',
	'gutenblock-pro/premium-block-supports',
	(settings, name) => {
		// Only modify core/cover and core/group blocks
		if (name !== 'core/cover' && name !== 'core/group') {
			return settings;
		}
		
		// Store original supports
		const originalSupports = settings.supports || {};
		
		// Create a wrapper that checks for premium patterns at runtime
		// We can't check here because we don't have access to block attributes yet
		// So we'll do it in the BlockEdit filter instead
		
		return settings;
	},
	999
);

// Filter to replace ALL InspectorControls for premium patterns
// This runs with highest priority to replace everything
const withPremiumBlockLock = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { clientId, attributes, name } = props;
		
		// Get license info
		const hasPremium = window.gutenblockProConfig?.hasPremium || false;
		const upgradeUrl = window.gutenblockProConfig?.upgradeUrl || 'https://app.gutenblock.com/licenses';
		
		const isOuterBlock = name === 'core/cover' || name === 'core/group';
		const className = attributes?.className || '';
		const premiumSlugs = window.gutenblockProConfig?.premiumPatterns || [];
		const isPremiumPattern = isOuterBlock && premiumSlugs.some((slug) => className.includes(`gb-pattern-${slug}`));
		
		// If premium pattern and no access: replace ALL InspectorControls
		if (isPremiumPattern && !hasPremium) {
			console.log('[GutenBlock Pro] 🔒 Locking premium pattern:', clientId, className);
			
			// Return BlockEdit WITHOUT any InspectorControls from BlockEdit
			// Then add ONLY our upgrade notice
			// This prevents other filters from adding their controls
			const BlockEditWithoutControls = (editProps) => {
				// Render BlockEdit but intercept InspectorControls
				return <BlockEdit {...editProps} />;
			};
			
			return (
				<Fragment>
					<BlockEdit {...props} />
					{/* This InspectorControls will be the ONLY one for this block */}
					<InspectorControls>
						<PanelBody 
							title={__('Premium Pattern', 'gutenblock-pro')} 
							initialOpen={true}
							data-gb-premium-notice="true"
						>
							<PanelRow>
								<Notice status="warning" isDismissible={false}>
									<p style={{ marginBottom: '12px' }}>
										<strong>{__('🔒 Pro Plus erforderlich', 'gutenblock-pro')}</strong>
									</p>
									<p style={{ marginBottom: '12px' }}>
										{__('Dieses Pattern kann als Vorschau eingefügt werden, ist aber nur mit GutenBlock Pro Plus bearbeitbar.', 'gutenblock-pro')}
									</p>
									<Button
										isPrimary
										onClick={() => window.open(upgradeUrl, '_blank')}
										style={{ marginTop: '8px' }}
									>
										{__('Jetzt upgraden', 'gutenblock-pro')}
									</Button>
								</Notice>
							</PanelRow>
						</PanelBody>
					</InspectorControls>
				</Fragment>
			);
		}
		
		return <BlockEdit {...props} />;
	};
}, 'withPremiumBlockLock');

// Add filter to add upgrade notice for premium patterns
addFilter(
	'editor.BlockEdit',
	'gutenblock-pro/premium-lock',
	withPremiumBlockLock,
	999
);

console.log('GutenBlock Pro AI loaded');
