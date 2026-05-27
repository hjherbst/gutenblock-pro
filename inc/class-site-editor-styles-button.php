<?php
/**
 * Site Editor Styles Sidebar
 *
 * Re-introduces the right-hand "Styles" panel that WordPress 6.x mounted
 * unconditionally in the Site Editor and WordPress 7.0 gated behind
 * `(postType === "wp_template" || renderingMode === "template-locked")`,
 * which removed it from page-edit routes along with the half-filled-
 * circle toolbar toggle.
 *
 * The JS bundle registered here re-creates the trigger and the panel
 * via a public `<PluginSidebar>` that internally mounts the WP-native
 * `GlobalStylesUIWrapper` component pulled from `wp.editor.privateApis`.
 * Skipped on WP < 7.0 where the native panel is still rendered by core.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Site_Editor_Styles_Button {

	const HANDLE = 'gutenblock-pro-site-editor-styles-button';

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the styles-sidebar JS on the Site Editor screen, but only
	 * on WP ≥ 7.0 where the native trigger has been removed.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( 'site-editor.php' !== $hook_suffix ) {
			return;
		}
		if ( ! version_compare( get_bloginfo( 'version' ), '7.0', '>=' ) ) {
			return;
		}

		$rel  = 'assets/js/site-editor-styles-button.js';
		$path = GUTENBLOCK_PRO_PATH . $rel;
		$url  = GUTENBLOCK_PRO_URL . $rel;
		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			$url,
			array(
				'wp-plugins',
				'wp-editor',
				'wp-data',
				'wp-element',
				'wp-i18n',
				'wp-components',
				'wp-primitives',
				'wp-private-apis',
			),
			filemtime( $path ),
			true
		);
		wp_set_script_translations( self::HANDLE, 'gutenblock-pro' );
	}
}
