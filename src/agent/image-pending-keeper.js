/**
 * Paints loader overlays on image/cover blocks while AI generation is pending.
 * Mirrors the SaaS ImagePendingKeeper behaviour for the WP block editor canvas.
 */

import { useEffect } from '@wordpress/element';
import {
	listPendingBlocks,
	subscribePendingBlocks,
} from './image-pending';
import { getEditorDocument } from './block-images';

const CAPTION_CLASS = 'gb-pending-caption';
const CAPTION_TEXT = 'Generating image…';
const STYLE_ID = 'gb-agent-pending-styles';

const PENDING_CSS = `
img[data-gb-pending="1"] {
	opacity: 0.45;
	filter: blur(6px) saturate(0.7);
	transition: opacity 240ms ease, filter 240ms ease;
}
[data-gb-pending-host="1"] {
	position: relative;
	isolation: isolate;
}
[data-gb-pending-host="1"]::before {
	content: "";
	position: absolute;
	inset: 0;
	background: linear-gradient(110deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.55) 45%, rgba(255,255,255,0) 75%);
	background-size: 220% 100%;
	background-position: 100% 0;
	animation: gb-pending-shimmer 1.6s linear infinite;
	pointer-events: none;
	z-index: 4;
}
[data-gb-pending-host="1"]::after {
	content: "";
	position: absolute;
	top: 50%;
	left: 50%;
	width: 40px;
	height: 40px;
	margin: -20px 0 0 -20px;
	border-radius: 50%;
	border: 3px solid rgba(124, 58, 237, 0.22);
	border-top-color: rgb(124, 58, 237);
	animation: gb-pending-spin 0.9s linear infinite;
	pointer-events: none;
	z-index: 5;
	box-shadow: 0 0 0 6px rgba(255, 255, 255, 0.5);
}
[data-gb-pending-host="1"] .gb-pending-caption {
	position: absolute;
	left: 50%;
	top: calc(50% + 18px);
	transform: translateX(-50%);
	z-index: 6;
	pointer-events: none;
	white-space: nowrap;
	max-width: calc(100% - 16px);
	overflow: hidden;
	text-overflow: ellipsis;
	font-size: 12px;
	font-weight: 600;
	line-height: 1.2;
	color: rgb(76, 29, 149);
	background: rgba(255, 255, 255, 0.92);
	padding: 5px 11px;
	border-radius: 999px;
	box-shadow: 0 2px 10px rgba(15, 23, 42, 0.12);
}
@keyframes gb-pending-spin { to { transform: rotate(360deg); } }
@keyframes gb-pending-shimmer {
	0% { background-position: 100% 0; }
	100% { background-position: -120% 0; }
}
`;

function ensurePendingStyles(doc) {
	if (!doc?.head || doc.getElementById(STYLE_ID)) return;
	const style = doc.createElement('style');
	style.id = STYLE_ID;
	style.textContent = PENDING_CSS;
	doc.head.appendChild(style);
}

function findHostForImage(img) {
	const host = img.closest(
		'figure, .wp-block-cover, .wp-block-image, .wp-block-media-text__media, .wp-block-cover__inner-container'
	);
	if (host && host !== img) return host;
	let walker = img.parentElement;
	while (walker && walker.tagName?.toLowerCase() === 'picture') {
		walker = walker.parentElement;
	}
	return walker || img;
}

function ensureCaption(host) {
	if (host.querySelector(`.${CAPTION_CLASS}`)) return;
	const caption = host.ownerDocument.createElement('span');
	caption.className = CAPTION_CLASS;
	caption.textContent = CAPTION_TEXT;
	caption.setAttribute('aria-live', 'polite');
	host.appendChild(caption);
}

function clearAllMarkers(root) {
	root.querySelectorAll('[data-gb-pending="1"]').forEach((el) => {
		el.removeAttribute('data-gb-pending');
	});
	root.querySelectorAll('[data-gb-pending-host="1"]').forEach((el) => {
		el.removeAttribute('data-gb-pending-host');
	});
	root.querySelectorAll(`.${CAPTION_CLASS}`).forEach((el) => el.remove());
}

function applyPendingForBlock(doc, clientId) {
	const blockEl = doc.querySelector(`[data-block="${clientId}"]`);
	if (!blockEl) return false;

	const img =
		blockEl.querySelector('img.wp-block-cover__image-background') ||
		blockEl.querySelector('img');
	if (!img) return false;

	img.setAttribute('data-gb-pending', '1');
	const host = findHostForImage(img);
	host.setAttribute('data-gb-pending-host', '1');
	ensureCaption(host);
	return true;
}

export function ImagePendingKeeper() {
	useEffect(() => {
		let stopped = false;

		const reapply = () => {
			if (stopped) return;
			const doc = getEditorDocument();
			ensurePendingStyles(doc);
			const pending = listPendingBlocks();
			clearAllMarkers(doc);
			if (!pending.length) return;
			for (const clientId of pending) {
				applyPendingForBlock(doc, clientId);
			}
		};

		reapply();

		const unsub = subscribePendingBlocks(reapply);

		const observer = new MutationObserver((mutations) => {
			if (listPendingBlocks().length === 0) return;
			const onlyCaptionNoise = mutations.every((m) => {
				const nodes = [...Array.from(m.addedNodes), ...Array.from(m.removedNodes)];
				return (
					nodes.length > 0 &&
					nodes.every(
						(n) => n instanceof HTMLElement && n.classList.contains(CAPTION_CLASS)
					)
				);
			});
			if (onlyCaptionNoise) return;
			reapply();
		});

		const doc = getEditorDocument();
		observer.observe(doc.body || doc, { childList: true, subtree: true });

		const iframe =
			document.querySelector('iframe[name="editor-canvas"]') ||
			document.querySelector('iframe.editor-canvas__iframe');
		const onIframeLoad = () => reapply();
		iframe?.addEventListener('load', onIframeLoad);

		return () => {
			stopped = true;
			observer.disconnect();
			iframe?.removeEventListener('load', onIframeLoad);
			unsub();
			clearAllMarkers(getEditorDocument());
		};
	}, []);

	return null;
}
