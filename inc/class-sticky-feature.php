<?php
/**
 * Sticky Feature Block – Section mit Sticky-Bild und scroll-synchronisierten Text-Items.
 *
 * Registriert den Block, lädt Block-Style-CSS und frontend JS bedingt.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Sticky_Feature {

	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'init', array( $this, 'enqueue_block_style' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register block type from build/blocks/sticky-feature (block.json).
	 */
	public function register_block() {
		register_block_type( GUTENBLOCK_PRO_PATH . 'build/blocks/sticky-feature' );
	}

	/**
	 * Enqueue block style (frontend + editor iframe).
	 */
	public function enqueue_block_style() {
		$path = GUTENBLOCK_PRO_PATH . 'assets/css/sticky-feature.css';
		if ( ! file_exists( $path ) ) {
			return;
		}
		wp_enqueue_block_style(
			'gutenblock-pro/sticky-feature',
			array(
				'handle' => 'gbp-sticky-feature',
				'src'    => GUTENBLOCK_PRO_URL . 'assets/css/sticky-feature.css',
				'path'   => $path,
				'ver'    => filemtime( $path ),
			)
		);
	}

	/**
	 * Enqueue frontend JS only when block is used on the page.
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! has_block( 'gutenblock-pro/sticky-feature', $post ) ) {
			return;
		}
		wp_enqueue_script(
			'gbp-sticky-feature',
			GUTENBLOCK_PRO_URL . 'assets/js/sticky-feature.js',
			array(),
			GUTENBLOCK_PRO_VERSION,
			true
		);
	}
}
