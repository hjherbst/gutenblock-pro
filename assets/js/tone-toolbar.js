/**
 * GutenBlock Pro – Tone-Toolbar
 *
 * Zeigt einen Tone-Picker in der Block-Toolbar, wenn ein als gb-pattern-*
 * markierter Block selektiert ist. Schaltet die Tonalität (neutral/dark/soft)
 * via REST-Endpoint und ersetzt den selektierten Block-Tree durch die neue
 * Variante.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.element || !wp.blockEditor || !wp.compose) return;

	const { createElement: el, Fragment } = wp.element;
	const { createHigherOrderComponent } = wp.compose;
	const { addFilter } = wp.hooks;
	const { BlockControls } = wp.blockEditor;
	const { ToolbarGroup, ToolbarDropdownMenu } = wp.components;
	const { useDispatch, useSelect } = wp.data;

	const cfg = window.gutenblockProToneToolbar || {};
	const TONE_LABELS = { neutral: 'Neutral', dark: 'Dark', soft: 'Soft' };

	/**
	 * Liest aus dem className-Attribut des Blocks den Pattern-Slug "gb-pattern-{slug}".
	 */
	function detectPatternSlug(props) {
		if (!props.attributes) return null;
		const className = props.attributes.className || '';
		const m = className.match(/gb-pattern-([a-z0-9-]+)/);
		return m ? m[1] : null;
	}

	function detectActiveTone(props) {
		const className = props.attributes && props.attributes.className || '';
		const m = className.match(/gb-tone-(neutral|dark|soft)/);
		if (m) return m[1];
		return 'neutral';
	}

	const withToneToolbar = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			const slug = detectPatternSlug(props);
			const allowedBlocks = ['core/group', 'core/cover', 'core/columns'];
			if (!slug || !allowedBlocks.includes(props.name)) {
				return el(BlockEdit, props);
			}

			const patternMeta = (cfg.patterns || {})[slug];
			if (!patternMeta || !Array.isArray(patternMeta.tones) || patternMeta.tones.length <= 1) {
				return el(BlockEdit, props);
			}

			const activeTone = detectActiveTone(props);
			const { replaceBlock } = useDispatch('core/block-editor');
			const blockClientId = props.clientId;

			const swapTone = (newTone) => {
				if (!cfg.ajaxUrl || !cfg.nonce) return;
				const body = new URLSearchParams({
					action: 'gutenblock_pro_get_pattern_tone_content',
					nonce: cfg.nonce,
					pattern: slug,
					tone: newTone,
				});
				fetch(cfg.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body,
				})
					.then((r) => r.json())
					.then((data) => {
						if (!data || !data.success || !data.data || !data.data.content) {
							console.error('[Tone-Toolbar] swap failed', data);
							return;
						}
						try {
							const newBlocks = wp.blocks.parse(data.data.content);
							if (newBlocks && newBlocks.length > 0) {
								replaceBlock(blockClientId, newBlocks[0]);
							}
						} catch (e) {
							console.error('[Tone-Toolbar] parse error', e);
						}
					})
					.catch((err) => console.error('[Tone-Toolbar] fetch error', err));
			};

			const controls = patternMeta.tones.map((t) => ({
				title: TONE_LABELS[t] || t,
				icon: t === 'dark' ? 'admin-appearance' : (t === 'soft' ? 'art' : 'admin-customizer'),
				isActive: activeTone === t,
				onClick: () => swapTone(t),
			}));

			return el(
				Fragment,
				{},
				el(BlockEdit, props),
				el(
					BlockControls,
					{ group: 'other' },
					el(ToolbarGroup, {},
						el(ToolbarDropdownMenu, {
							icon: 'admin-customizer',
							label: cfg.toolbarLabel || 'Tonalität',
							controls: controls,
						})
					)
				)
			);
		};
	}, 'withGutenBlockProToneToolbar');

	addFilter(
		'editor.BlockEdit',
		'gutenblock-pro/tone-toolbar',
		withToneToolbar
	);
})(window.wp);
