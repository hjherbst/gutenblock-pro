(function (wp) {
	'use strict';

	if (
		!wp ||
		!wp.hooks ||
		!wp.compose ||
		!wp.element ||
		!wp.blockEditor ||
		!wp.components
	) {
		return;
	}

	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl, RangeControl, SelectControl } = wp.components;

	const SUPPORTED_BLOCKS = [
		'core/group',
		'core/columns',
		'core/column',
		'core/image'
	];

	const EFFECTS = [
		{ label: 'Fade In', value: 'fadeIn', directional: false },
		{ label: 'Fade In Up', value: 'fadeInUp', directional: true }
	];

	const DEFAULTS = {
		gtMotion: false,
		gtMotionEffect: 'fadeIn',
		gtDelay: 0,
		gtMotionDistance: 20
	};

	const LIMITS = {
		delay: { min: 0, max: 0.6, step: 0.1 },
		distance: { min: 20, max: 60, step: 10 }
	};

	const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

	const sanitizeNumber = (value, fallback, min, max) => {
		const parsed = typeof value === 'number' ? value : parseFloat(value);
		if (Number.isNaN(parsed)) {
			return fallback;
		}
		return clamp(parsed, min, max);
	};

	const isSupportedBlock = (name) => SUPPORTED_BLOCKS.includes(name);

	const getEffect = (value) => {
		const match = EFFECTS.find((effect) => effect.value === value);
		return match ? match.value : DEFAULTS.gtMotionEffect;
	};

	const isDirectionalEffect = (value) => {
		const match = EFFECTS.find((effect) => effect.value === value);
		return !!match?.directional;
	};

	function addMotionAttributes(settings, name) {
		if (!isSupportedBlock(name)) {
			return settings;
		}

		settings.attributes = {
			...settings.attributes,
			gtMotion: { type: 'boolean', default: DEFAULTS.gtMotion },
			gtMotionEffect: { type: 'string', default: DEFAULTS.gtMotionEffect },
			gtDelay: { type: 'number', default: DEFAULTS.gtDelay },
			gtMotionDistance: { type: 'number', default: DEFAULTS.gtMotionDistance }
		};

		return settings;
	}

	addFilter(
		'blocks.registerBlockType',
		'gutentheme/motion/attributes',
		addMotionAttributes
	);

	const withMotionControls = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			if (!isSupportedBlock(props.name)) {
				return el(BlockEdit, props);
			}

			const { attributes, setAttributes } = props;

			const gtMotion = !!attributes.gtMotion;
			const gtMotionEffect = getEffect(attributes.gtMotionEffect);

			const gtDelay = sanitizeNumber(
				attributes.gtDelay,
				DEFAULTS.gtDelay,
				LIMITS.delay.min,
				LIMITS.delay.max
			);

			const gtMotionDistance = sanitizeNumber(
				attributes.gtMotionDistance,
				DEFAULTS.gtMotionDistance,
				LIMITS.distance.min,
				LIMITS.distance.max
			);

			const showDistance = isDirectionalEffect(gtMotionEffect);

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Motion', initialOpen: false },

						el(ToggleControl, {
							label: 'Enable motion',
							checked: gtMotion,
							onChange: (value) => {
								if (!value) {
									setAttributes({ ...DEFAULTS });
									return;
								}
								setAttributes({ gtMotion: true });
							}
						}),

						gtMotion && el(SelectControl, {
							label: 'Effect',
							value: gtMotionEffect,
							options: EFFECTS.map(({ label, value }) => ({ label, value })),
							onChange: (value) => {
								const nextEffect = getEffect(value);
								const nextAttributes = {
									gtMotionEffect: nextEffect
								};

								if (!isDirectionalEffect(nextEffect)) {
									nextAttributes.gtMotionDistance = DEFAULTS.gtMotionDistance;
								}

								setAttributes(nextAttributes);
							}
						}),

						gtMotion && showDistance && el(RangeControl, {
							label: 'Distance (px)',
							value: gtMotionDistance,
							onChange: (value) => setAttributes({
								gtMotionDistance: sanitizeNumber(
									value,
									DEFAULTS.gtMotionDistance,
									LIMITS.distance.min,
									LIMITS.distance.max
								)
							}),
							min: LIMITS.distance.min,
							max: LIMITS.distance.max,
							step: LIMITS.distance.step
						}),

						gtMotion && el(RangeControl, {
							label: 'Delay (seconds)',
							value: gtDelay,
							onChange: (value) => setAttributes({
								gtDelay: sanitizeNumber(
									value,
									DEFAULTS.gtDelay,
									LIMITS.delay.min,
									LIMITS.delay.max
								)
							}),
							min: LIMITS.delay.min,
							max: LIMITS.delay.max,
							step: LIMITS.delay.step
						})
					)
				)
			);
		};
	}, 'withMotionControls');

	addFilter(
		'editor.BlockEdit',
		'gutentheme/motion/controls',
		withMotionControls
	);

	function applyExtraProps(extraProps, blockType, attributes) {
		if (!isSupportedBlock(blockType.name) || !attributes?.gtMotion) {
			return extraProps;
		}

		const effect = getEffect(attributes.gtMotionEffect);

		extraProps['data-motion'] = effect;
		extraProps['data-delay'] = attributes.gtDelay;

		if (isDirectionalEffect(effect)) {
			extraProps['data-distance'] = attributes.gtMotionDistance;
		}

		return extraProps;
	}

	addFilter(
		'blocks.getSaveContent.extraProps',
		'gutentheme/motion/save-props',
		applyExtraProps
	);
})(window.wp || {});
