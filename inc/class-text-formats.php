<?php
/**
 * Text Formats – RichText-Format-Types (Einkreisen, Unterstreichen, Marker)
 *
 * Enqueues frontend and editor CSS for the format styles.
 * Format types are registered in JS (src/blocks/text-formats/).
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Text_Formats {

	/**
	 * Initialize: enqueue CSS when feature is enabled.
	 */
	public function init() {
		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'text-formats' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
	}

	/**
	 * Enqueue frontend CSS for text format styles.
	 */
	public function enqueue_frontend() {
		$path = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : plugin_dir_path( dirname( __FILE__ ) );
		$url  = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : plugin_dir_url( dirname( __FILE__ ) );
		$file = 'assets/css/text-formats.css';
		if ( ! file_exists( $path . $file ) ) {
			return;
		}
		wp_enqueue_style(
			'gutenblock-pro-text-formats',
			$url . $file,
			array(),
			defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : (string) filemtime( $path . $file )
		);
	}

	/**
	 * Enqueue same CSS in block editor for format preview + editor-only overrides.
	 */
	public function enqueue_editor() {
		$path = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : plugin_dir_path( dirname( __FILE__ ) );
		$url  = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : plugin_dir_url( dirname( __FILE__ ) );
		$file = 'assets/css/text-formats.css';
		if ( file_exists( $path . $file ) ) {
			wp_enqueue_style(
				'gutenblock-pro-text-formats-editor',
				$url . $file,
				array(),
				defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : (string) filemtime( $path . $file )
			);
		}
		$file_editor = 'assets/css/text-formats-editor.css';
		if ( file_exists( $path . $file_editor ) ) {
			wp_enqueue_style(
				'gutenblock-pro-text-formats-editor-overrides',
				$url . $file_editor,
				array( 'gutenblock-pro-text-formats-editor' ),
				defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : (string) filemtime( $path . $file_editor )
			);
		}
	}
}
