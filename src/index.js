/**
 * GutenBlock Pro - Editor Scripts
 *
 * This file is compiled by @wordpress/scripts
 */

import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, useState, useEffect, useMemo } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, Button, Spinner, TextareaControl, Notice, ComboboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

// Import editor styles
import './editor.scss';

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

	// Get block metadata name (if set)
	const metadataName = attributes?.metadata?.name || '';

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
	 * Translate current block text
	 */
	const translateText = async () => {
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
					prompt: `Übersetze den folgenden Text ins Englische. Antworte nur mit der Übersetzung:\n\n${currentText}`,
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
	if (!supportedBlocks.includes(blockName)) {
		return null;
	}

	const hasValidPrompt = tempPrompt && tempPrompt.trim().length >= 3;

	return (
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
						<Button
							isSecondary
							onClick={translateText}
							disabled={isLoading}
							size="small"
						>
							{__('→ EN', 'gutenblock-pro')}
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

// Add filter to inject AI controls
addFilter(
	'editor.BlockEdit',
	'gutenblock-pro/ai-controls',
	withAIControls
);

console.log('GutenBlock Pro AI loaded');
