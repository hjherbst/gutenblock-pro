/**
 * Ensure LLM tool payloads include every catalog content_field before fill-pattern runs.
 */

import { chatAgent } from './api';

function sanitizeFieldKey(key) {
	return String(key || '')
		.toLowerCase()
		.replace(/[^a-z0-9_-]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

export function findPattern(catalog, slug) {
	if (!slug || !Array.isArray(catalog)) return null;
	return catalog.find((p) => p.slug === slug) || null;
}

/** Field ids from the catalog that are missing or empty in texts. */
export function missingFieldIds(pattern, texts) {
	const required = Array.isArray(pattern?.content_fields) ? pattern.content_fields : [];
	if (!required.length) return [];

	const map = texts && typeof texts === 'object' && !Array.isArray(texts) ? texts : {};
	const byKey = {};
	for (const [rawKey, value] of Object.entries(map)) {
		byKey[sanitizeFieldKey(rawKey)] = value;
	}

	return required.filter((id) => {
		const key = sanitizeFieldKey(id);
		const value = byKey[key];
		return typeof value !== 'string' || value.trim() === '';
	});
}

function parseJsonObject(raw) {
	if (!raw || typeof raw !== 'string') return null;
	const trimmed = raw.trim();
	try {
		const parsed = JSON.parse(trimmed);
		if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
	} catch {
		// fall through — try to extract a JSON object from prose
	}
	const start = trimmed.indexOf('{');
	const end = trimmed.lastIndexOf('}');
	if (start >= 0 && end > start) {
		try {
			const parsed = JSON.parse(trimmed.slice(start, end + 1));
			if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
		} catch {
			return null;
		}
	}
	return null;
}

/** Merge LLM texts onto catalog keys (case-insensitive key match). */
export function normalizeTextsForPattern(pattern, texts) {
	const required = Array.isArray(pattern?.content_fields) ? pattern.content_fields : [];
	const map = texts && typeof texts === 'object' && !Array.isArray(texts) ? texts : {};
	const byKey = {};
	for (const [rawKey, value] of Object.entries(map)) {
		if (typeof value === 'string' && value.trim()) {
			byKey[sanitizeFieldKey(rawKey)] = value.trim();
		}
	}
	const out = {};
	for (const id of required) {
		const key = sanitizeFieldKey(id);
		if (byKey[key]) out[id] = byKey[key];
	}
	return out;
}

async function fetchMissingTexts({ slug, pattern, missingFields, existingTexts, chatExtras, onCompletingTexts }) {
	if (typeof onCompletingTexts === 'function') {
		onCompletingTexts();
	}
	const response = await chatAgent({
		messages: [
			{
				role: 'user',
				content: JSON.stringify({
					slug,
					missingFields,
					existingTexts: existingTexts || {},
					pattern: pattern
						? {
								slug: pattern.slug,
								title: pattern.title,
								group: pattern.group,
								description: pattern.description,
								ai_hint: pattern.ai_hint,
								content_fields: pattern.content_fields,
							}
						: { slug },
				}),
			},
		],
		patterns: pattern ? [pattern] : [],
		mode: 'fill_missing_fields',
		...chatExtras,
	});
	const parsed = parseJsonObject(response?.message?.content || '');
	if (!parsed) {
		throw new Error('Could not parse missing field texts from the model.');
	}
	const out = {};
	for (const id of missingFields) {
		const key = sanitizeFieldKey(id);
		for (const [rawKey, value] of Object.entries(parsed)) {
			if (sanitizeFieldKey(rawKey) === key && typeof value === 'string' && value.trim()) {
				out[id] = value.trim();
				break;
			}
		}
	}
	return out;
}

/**
 * Complete texts for one pattern slug using a targeted LLM follow-up when keys are missing.
 */
export async function completePatternTexts({ slug, texts, catalog, chatExtras, onCompletingTexts }) {
	const pattern = findPattern(catalog, slug);
	if (!pattern?.content_fields?.length) {
		return texts && typeof texts === 'object' ? { ...texts } : {};
	}

	let merged = { ...(texts && typeof texts === 'object' ? texts : {}), ...normalizeTextsForPattern(pattern, texts) };
	let missing = missingFieldIds(pattern, merged);

	if (!missing.length || !chatExtras) {
		return normalizeTextsForPattern(pattern, merged);
	}

	for (let attempt = 0; attempt < 2 && missing.length; attempt += 1) {
		const fetched = await fetchMissingTexts({
			slug,
			pattern,
			missingFields: missing,
			existingTexts: merged,
			chatExtras,
			onCompletingTexts,
		});
		merged = { ...merged, ...fetched };
		missing = missingFieldIds(pattern, merged);
	}

	return normalizeTextsForPattern(pattern, merged);
}

/** Complete texts for insert_pattern or each build_page section. */
export async function completeToolTexts(name, args, { catalog, chatExtras, onCompletingTexts }) {
	if (!catalog?.length || !chatExtras) return args;

	if (name === 'insert_pattern' && args?.slug) {
		const texts = await completePatternTexts({
			slug: args.slug,
			texts: args.texts,
			catalog,
			chatExtras,
			onCompletingTexts,
		});
		return { ...args, texts };
	}

	if (name === 'build_page' && Array.isArray(args?.sections)) {
		const sections = [];
		for (const section of args.sections) {
			if (!section?.slug) {
				sections.push(section);
				continue;
			}
			const texts = await completePatternTexts({
				slug: section.slug,
				texts: section.texts,
				catalog,
				chatExtras,
				onCompletingTexts,
			});
			sections.push({ ...section, texts });
		}
		return { ...args, sections };
	}

	return args;
}
