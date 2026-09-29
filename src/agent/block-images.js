/**
 * Shared helpers for image blocks in the block editor.
 */

import { dispatch } from '@wordpress/data';
import { generateAgentImage, uploadAgentImage } from './api';
import { clearBlockPending, markBlockPending } from './image-pending';

export function getEditorDocument() {
	const iframe =
		document.querySelector('iframe[name="editor-canvas"]') ||
		document.querySelector('iframe.editor-canvas__iframe');
	if (iframe?.contentDocument?.body) {
		return iframe.contentDocument;
	}
	return document;
}

export function guessFluxAspectRatioFromDimensions(dimensions) {
	const w = Number(dimensions?.width);
	const h = Number(dimensions?.height);
	if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) {
		return '1:1';
	}
	const ratio = w / h;
	const candidates = [
		{ v: '1:1', r: 1 },
		{ v: '4:3', r: 4 / 3 },
		{ v: '3:4', r: 3 / 4 },
		{ v: '16:9', r: 16 / 9 },
		{ v: '9:16', r: 9 / 16 },
	];
	let best = candidates[0].v;
	let bestDiff = Math.abs(ratio - candidates[0].r);
	for (const c of candidates.slice(1)) {
		const d = Math.abs(ratio - c.r);
		if (d < bestDiff) {
			bestDiff = d;
			best = c.v;
		}
	}
	return best;
}

export function applyUrlToBlock(clientId, blockName, url, altText) {
	try {
		if (blockName === 'core/cover') {
			dispatch('core/block-editor').updateBlockAttributes(clientId, {
				url,
				backgroundImageUrl: url,
				backgroundType: 'image',
			});
		} else {
			dispatch('core/block-editor').updateBlockAttributes(clientId, {
				url,
				alt: altText || '',
			});
		}
	} catch (e) {
		// Ignore editor update errors.
	}
}

/**
 * Generate a single image for a selected block (manual image mode).
 */
export async function generateImageForBlock({
	clientId,
	blockName,
	aspectRatio,
	prompt,
	onBilling,
}) {
	markBlockPending(clientId);
	try {
		const response = await generateAgentImage({ prompt, aspectRatio });
		if (response?.billing && onBilling) {
			onBilling(response.billing);
		}
		if (!response?.url) {
			throw new Error(response?.error || 'Image generation failed.');
		}

		applyUrlToBlock(clientId, blockName, response.url, prompt);

		try {
			const upload = await uploadAgentImage({
				url: response.url,
				prompt,
				alt: prompt,
				slug: response.slug || '',
			});
			if (upload?.url) {
				applyUrlToBlock(clientId, blockName, upload.url, upload.alt || prompt);
			}
			return { url: upload?.url || response.url, slug: response.slug };
		} catch (e) {
			return { url: response.url, slug: response.slug };
		}
	} finally {
		clearBlockPending(clientId);
	}
}
