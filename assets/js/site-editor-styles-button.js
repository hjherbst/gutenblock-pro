/**
 * GutenBlock Pro — Site Editor "Styles" sidebar restoration
 *
 * Backstory: In WordPress 6.x the Site Editor always rendered the right-
 * hand <GlobalStylesSidebar /> when the active theme is a block theme
 * (see WP 6.9.4 edit-site.js around `isBlockBasedTheme && <GlobalStylesSidebar />`).
 * In WordPress 7.0 the component was moved out of edit-site into editor.js
 * and is now gated on `(postType === "wp_template" || renderingMode === "template-locked")`,
 * which removes the right-side panel from page-edit / styles routes and
 * with it the half-filled-circle toggle from the toolbar.
 *
 * Restoration strategy:
 *   1. Pull `GlobalStylesUIWrapper` out of `wp.editor.privateApis` via
 *      the (intentionally constrained) opt-in to private APIs.
 *   2. Wrap it in a public `<PluginSidebar>` from `@wordpress/editor`.
 *      WP renders a pinned-item button for that plugin sidebar in the
 *      header automatically — no DOM hacks, no SlotFill plumbing.
 *   3. Title + icon match the native panel (`__('Styles')` + the
 *      half-circle SVG from `@wordpress/icons/styles`).
 *
 * Plugin acknowledges this dips into private editor APIs and may need
 * touching on future WP major releases. Skipped on WP < 7.0 (PHP layer)
 * because the native panel is still there.
 */
( function () {
	if ( typeof window === 'undefined' || ! window.wp ) {
		return;
	}

	var wp = window.wp;
	var registerPlugin = wp.plugins && wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor && wp.editor.PluginSidebar;
	var createElement = wp.element && wp.element.createElement;
	var useState = wp.element && wp.element.useState;
	var __ = ( wp.i18n && wp.i18n.__ ) || function ( s ) { return s; };

	if ( ! createElement || ! registerPlugin || ! PluginSidebar ) {
		return;
	}

	// Half-filled-circle "styles" icon — identical SVG path to
	// `@wordpress/icons/styles` (kept inline because that package isn't
	// globally exposed under `wp.icons`).
	var SVG = ( wp.primitives && wp.primitives.SVG ) || 'svg';
	var Path = ( wp.primitives && wp.primitives.Path ) || 'path';
	var STYLES_ICON = createElement(
		SVG,
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24' },
		createElement( Path, {
			fillRule: 'evenodd',
			clipRule: 'evenodd',
			d: 'M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 0 1-6.5 6.5v-13a6.5 6.5 0 0 1 6.5 6.5Z',
		} )
	);

	// Resolve the private editor APIs. WP gates this behind a fixed
	// consent string + a hard-coded module whitelist; we acknowledge
	// the risk in the file header.
	function resolveGlobalStylesUIWrapper() {
		try {
			var optIn = wp.privateApis && wp.privateApis.__dangerousOptInToUnstableAPIsOnlyForCoreModules;
			if ( typeof optIn !== 'function' ) {
				return null;
			}
			var box = optIn(
				'I acknowledge private features are not for use in themes or plugins and doing so will break in the next version of WordPress.',
				'@wordpress/editor'
			);
			if ( ! box || typeof box.unlock !== 'function' ) {
				return null;
			}
			if ( ! wp.editor || ! wp.editor.privateApis ) {
				return null;
			}
			var unlocked = box.unlock( wp.editor.privateApis );
			return unlocked && unlocked.GlobalStylesUIWrapper ? unlocked.GlobalStylesUIWrapper : null;
		} catch ( e ) {
			return null;
		}
	}

	var GlobalStylesUIWrapper = resolveGlobalStylesUIWrapper();

	function StylesContent() {
		var state = useState ? useState( '/' ) : [ '/', function () {} ];
		var path = state[ 0 ];
		var setPath = state[ 1 ];

		if ( ! GlobalStylesUIWrapper ) {
			return createElement(
				'div',
				{ style: { padding: 16 } },
				__( 'Das Stile-Panel konnte nicht geladen werden. Bitte WordPress aktualisieren oder das gutenblock-pro Plugin aktualisieren.', 'gutenblock-pro' )
			);
		}

		return createElement( GlobalStylesUIWrapper, {
			path: path,
			onPathChange: setPath,
		} );
	}

	function StylesPluginSidebar() {
		return createElement(
			PluginSidebar,
			{
				name: 'gutenblock-pro-styles',
				title: __( 'Stile', 'gutenblock-pro' ),
				icon: STYLES_ICON,
				className: 'gutenblock-pro-styles-sidebar editor-global-styles-sidebar',
			},
			createElement( StylesContent, {} )
		);
	}

	// Only run inside the Site Editor — the Post Editor isn't where this
	// panel belongs and would render an out-of-context UI tree.
	function onSiteEditor() {
		return /[?&]page=gutenberg-edit-site|site-editor\.php/.test( window.location.href );
	}

	if ( ! onSiteEditor() ) {
		return;
	}

	try {
		registerPlugin( 'gutenblock-pro-styles-trigger', {
			render: StylesPluginSidebar,
		} );
	} catch ( e ) {
		// Silent — the plugin sidebar API may already be registered
		// (hot-reload or double-enqueue scenarios).
	}
} )();
