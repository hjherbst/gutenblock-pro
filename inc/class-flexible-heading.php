<?php
/**
 * Flexible Heading Block – Registrierung und Frontend-Stylesheet.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GutenBlock_Pro_Flexible_Heading
 */
class GutenBlock_Pro_Flexible_Heading {

	/**
	 * Hook into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
	}

	/**
	 * Register block types.
	 * - Frontend-Style via wp_enqueue_block_style (frontend + editor iframe).
	 * - Editor-Canvas-Style via editor_style_handles (nur Editor-Canvas/iframe).
	 */
	public function register_blocks() {
		// Register editor-canvas style (injected into editor iframe).
		$editor_css_path = GUTENBLOCK_PRO_PATH . 'assets/css/flexible-heading-editor.css';
		if ( file_exists( $editor_css_path ) ) {
			wp_register_style(
				'gbp-flexible-heading-editor',
				GUTENBLOCK_PRO_URL . 'assets/css/flexible-heading-editor.css',
				array(),
				filemtime( $editor_css_path )
			);
		}

		// Register frontend style.
		$frontend_css_path = GUTENBLOCK_PRO_PATH . 'assets/css/flexible-heading.css';
		if ( file_exists( $frontend_css_path ) ) {
			wp_register_style(
				'gbp-flexible-heading',
				GUTENBLOCK_PRO_URL . 'assets/css/flexible-heading.css',
				array(),
				filemtime( $frontend_css_path )
			);
		}

		// Parent block: editor_style_handles injects CSS into the editor canvas (iframe).
		register_block_type(
			'gutenblock-pro/flexible-heading',
			array(
				'style_handles'        => array( 'gbp-flexible-heading' ),
				'editor_style_handles' => array( 'gbp-flexible-heading-editor' ),
			)
		);

		// Child block: no separate styles needed (styles come from WP block supports).
		register_block_type( 'gutenblock-pro/heading-part' );
	}
}
