/**
 * Editor-side tools: insert patterns / blocks into the open document.
 * Uses core/block-editor so native Undo and post revisions apply.
 */

import { createBlock, parse } from '@wordpress/blocks';
import { dispatch, select } from '@wordpress/data';
import { fillPattern } from './api';
import { completePatternTexts } from './pattern-texts';

function editorStore() {
	if (select('core/block-editor')) return 'core/block-editor';
	return 'core/editor';
}

function parseIndex(position, count) {
	if (position === 'start') return 0;
	if (position === 'end' || position == null || position === '') return count;
	const n = parseInt(position, 10);
	if (Number.isFinite(n)) return Math.max(0, Math.min(count, n));
	return count;
}

function insertMarkup(markup, position) {
	const blocks = parse(markup || '');
	const valid = (blocks || []).filter((b) => b && b.name);
	if (!valid.length) {
		throw new Error('No valid blocks to insert.');
	}
	const store = editorStore();
	const editor = select(store);
	if (!editor || typeof editor.getBlocks !== 'function') {
		throw new Error('Block editor is not ready. Open a page or template in the canvas.');
	}
	const current = editor.getBlocks() || [];
	const index = parseIndex(position, current.length);
	const d = dispatch(store);
	if (typeof d.insertBlocks !== 'function') {
		throw new Error('Cannot insert blocks in this editor view.');
	}
	d.insertBlocks(valid, index);
	const insertedRoots = (select(store).getBlocks() || [])
		.slice(index, index + valid.length)
		.map((b) => b.clientId);
	return {
		count: valid.length,
		names: valid.map((b) => b.name),
		rootClientIds: insertedRoots,
		insertIndex: index,
	};
}

async function resolvePatternTexts({ slug, texts, context }) {
	let finalTexts = texts && typeof texts === 'object' ? { ...texts } : {};
	if (context?.catalog?.length && context?.chatExtras) {
		finalTexts = await completePatternTexts({
			slug,
			texts: finalTexts,
			catalog: context.catalog,
			chatExtras: context.chatExtras,
			onCompletingTexts: context.onCompletingTexts,
		});
	}
	return finalTexts;
}

async function fillPatternMarkup({ slug, texts, context }) {
	let finalTexts = await resolvePatternTexts({ slug, texts, context });
	let result = await fillPattern({ slug, texts: finalTexts });

	if (
		result?.markup &&
		Array.isArray(result.missing) &&
		result.missing.length &&
		context?.catalog?.length &&
		context?.chatExtras
	) {
		finalTexts = await completePatternTexts({
			slug,
			texts: finalTexts,
			catalog: context.catalog,
			chatExtras: context.chatExtras,
			onCompletingTexts: context.onCompletingTexts,
		});
		const retry = await fillPattern({ slug, texts: finalTexts });
		if (retry?.markup) {
			result = retry;
		}
	}

	if (!result?.markup) throw new Error('Pattern markup is empty.');
	return result;
}

export async function runInsertPattern({ slug, texts, position }, context) {
	if (!slug) throw new Error('Missing pattern slug.');
	const result = await fillPatternMarkup({ slug, texts, context });
	const inserted = insertMarkup(result.markup, position);
	return {
		ok: true,
		slug,
		replaced: result.replaced || 0,
		inserted: inserted.count,
		missing: result.missing || [],
	};
}

export async function runBuildPage({ title, sections }, context) {
	const list = Array.isArray(sections) ? sections : [];
	if (!list.length) throw new Error('No sections provided.');

	const markups = [];
	for (const section of list) {
		const slug = section?.slug;
		if (!slug) continue;
		const result = await fillPatternMarkup({
			slug,
			texts: section.texts || {},
			context,
		});
		const parsed = parse(result.markup || '').filter((b) => b && b.name);
		markups.push({ slug, markup: result.markup, blockCount: parsed.length });
	}
	if (!markups.length) throw new Error('Could not load any pattern.');

	const allBlocks = [];
	for (const item of markups) {
		const parsed = parse(item.markup || '').filter((b) => b && b.name);
		allBlocks.push(...parsed);
	}
	if (!allBlocks.length) throw new Error('No valid blocks in assembled page.');

	const store = editorStore();
	const current = select(store).getBlocks() || [];
	if (current.length) {
		dispatch(store).removeBlocks(current.map((b) => b.clientId));
	}
	dispatch(store).insertBlocks(allBlocks, 0);

	if (title && typeof title === 'string' && title.trim()) {
		try {
			dispatch('core/editor').editPost({ title: title.trim() });
		} catch (e) {
			// Site editor templates have no post title.
		}
	}

	return {
		ok: true,
		sections: markups.map((m) => m.slug),
		blocks: allBlocks.length,
		title: title || null,
	};
}

export async function runInsertBlocks({ markup, position }) {
	if (!markup || typeof markup !== 'string') {
		throw new Error('Missing block markup.');
	}
	const inserted = insertMarkup(markup, position || 'end');
	return { ok: true, inserted: inserted.count, names: inserted.names };
}

function escapeHtml(text) {
	return String(text || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;');
}

function insertBlockObjects(blocks, position) {
	const store = editorStore();
	const editor = select(store);
	if (!editor || typeof editor.getBlocks !== 'function') {
		throw new Error('Block editor is not ready. Open a page or template in the canvas.');
	}
	const current = editor.getBlocks() || [];
	const index = parseIndex(position, current.length);
	dispatch(store).insertBlocks(blocks, index);
	return index;
}

/** True when the post title was empty and got set to the given value. */
function maybeSetPostTitle(title) {
	try {
		const editor = select('core/editor');
		if (!editor || typeof editor.getEditedPostAttribute !== 'function') return false;
		const existing = (editor.getEditedPostAttribute('title') || '').trim();
		if (existing) return false;
		dispatch('core/editor').editPost({ title });
		return true;
	} catch (e) {
		return false;
	}
}

/**
 * Render a structured article (title + sections) as core blocks.
 * Structured args replace the old markup-string approach: type-safe, no regex billing.
 */
export async function runWriteArticle({ title, sections }) {
	const list = Array.isArray(sections) ? sections : [];
	if (!list.length) throw new Error('No article sections provided.');

	const cleanTitle = typeof title === 'string' ? title.trim() : '';
	const titleSetOnPost = cleanTitle ? maybeSetPostTitle(cleanTitle) : false;

	const blocks = [];
	// Only add an h1 block when the post title could not carry the headline
	// (e.g. site-editor templates without a title field).
	if (cleanTitle && !titleSetOnPost) {
		blocks.push(createBlock('core/heading', { level: 1, content: escapeHtml(cleanTitle) }));
	}

	let paragraphCount = 0;
	for (const section of list) {
		if (!section || typeof section !== 'object') continue;
		const heading = typeof section.heading === 'string' ? section.heading.trim() : '';
		if (heading) {
			const level = section.level === 3 ? 3 : 2;
			blocks.push(createBlock('core/heading', { level, content: escapeHtml(heading) }));
		}
		const paragraphs = Array.isArray(section.paragraphs) ? section.paragraphs : [];
		for (const p of paragraphs) {
			const text = typeof p === 'string' ? p.trim() : '';
			if (!text) continue;
			blocks.push(createBlock('core/paragraph', { content: escapeHtml(text) }));
			paragraphCount += 1;
		}
		const listItems = Array.isArray(section.list) ? section.list.filter((i) => typeof i === 'string' && i.trim()) : [];
		if (listItems.length) {
			blocks.push(
				createBlock(
					'core/list',
					{},
					listItems.map((item) => createBlock('core/list-item', { content: escapeHtml(item.trim()) }))
				)
			);
		}
	}

	if (!blocks.length) throw new Error('Article has no content.');
	insertBlockObjects(blocks, 'end');

	return {
		ok: true,
		title: cleanTitle || null,
		sections: list.length,
		paragraphs: paragraphCount,
		blocks: blocks.length,
	};
}

function pluginBaseUrl() {
	const rest = window?.gutenblockProConfig?.pluginUrl || '';
	if (rest) return rest.replace(/\/+$/, '');
	return '/wp-content/plugins/gutenblock-pro';
}

const PLUGIN_IMAGE_ASSETS = [
	'media-1',
	'media-2',
	'media-3',
	'media-big-1',
	'media-about-female',
	'media-about-female-2',
	'media-about-female-3',
];

/** Insert one bundled plugin image as a core/image block. */
export async function runInsertImage({ asset, position, alt }) {
	const slug = typeof asset === 'string' ? asset.trim().replace(/\.webp$/i, '') : '';
	if (!PLUGIN_IMAGE_ASSETS.includes(slug)) {
		throw new Error(`Unknown image asset: ${asset || '(empty)'}`);
	}
	const url = `${pluginBaseUrl()}/assets/images/${slug}.webp`;
	const block = createBlock('core/image', {
		url,
		alt: typeof alt === 'string' ? alt : '',
		sizeSlug: 'large',
	});
	const index = insertBlockObjects([block], position || 'end');
	return { ok: true, asset: slug, url, index };
}

export function argsFromToolCall(call) {
	const raw =
		call?.function?.arguments ??
		call?.arguments ??
		call?.input ??
		{};
	if (raw && typeof raw === 'object' && !Array.isArray(raw)) return raw;
	try {
		return JSON.parse(raw || '{}');
	} catch {
		return {};
	}
}

export async function executeToolCall(name, args, context) {
	switch (name) {
		case 'insert_pattern':
			return runInsertPattern(args, context);
		case 'build_page':
			return runBuildPage(args, context);
		case 'insert_blocks':
			return runInsertBlocks(args);
		case 'write_article':
			return runWriteArticle(args);
		case 'insert_image':
			return runInsertImage(args);
		default:
			throw new Error(`Unknown tool: ${name}`);
	}
}
