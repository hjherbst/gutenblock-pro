<?php
/**
 * Plugin Name: GutenBlock Pro
 * Description: Professional block patterns with conditional CSS/JS loading for the Full Site Editor.
 * Version: 1.2.9
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Hans-Jürgen Herbst
 * Text Domain: gutenblock-pro
 * Domain Path: /languages
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'GUTENBLOCK_PRO_VERSION', '1.2.9' );
define( 'GUTENBLOCK_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'GUTENBLOCK_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'GUTENBLOCK_PRO_PATTERNS_PATH', GUTENBLOCK_PRO_PATH . 'patterns/' );

// Load classes
require_once GUTENBLOCK_PRO_PATH . 'inc/class-pattern-loader.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-asset-loader.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-admin-page.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-pattern-creator.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-license.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-ai-generator.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-ai-settings.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-bridge-installer.php';

// Plugin Update Checker - GitHub Releases (initialized in hook)
require_once GUTENBLOCK_PRO_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Initialize Plugin Update Checker
 */
function gutenblock_pro_init_update_checker() {
	$gutenblockProUpdateChecker = PucFactory::buildUpdateChecker(
		'https://github.com/hjherbst/gutenblock-pro/',
		GUTENBLOCK_PRO_PATH . 'gutenblock-pro.php',
		'gutenblock-pro'
	);

	// Set the branch that contains the stable release (default: master/main)
	$gutenblockProUpdateChecker->setBranch( 'main' );

	// Optional: Enable release assets (ZIP file from GitHub Release)
	$gutenblockProUpdateChecker->getVcsApi()->enableReleaseAssets();
}
add_action( 'plugins_loaded', 'gutenblock_pro_init_update_checker', 5 );

/**
 * Initialize the plugin
 */
function gutenblock_pro_init() {
	// Initialize Bridge Installer (mu-plugin)
	GutenBlock_Pro_Bridge_Installer::get_instance();

	// Initialize License System
	GutenBlock_Pro_License::get_instance();

	// Initialize AI Generator
	GutenBlock_Pro_AI_Generator::get_instance();

	// Initialize Pattern Loader
	$pattern_loader = new GutenBlock_Pro_Pattern_Loader();
	$pattern_loader->init();

	// Initialize Asset Loader
	$asset_loader = new GutenBlock_Pro_Asset_Loader();
	$asset_loader->init();

	// Initialize Admin Page
	if ( is_admin() ) {
		$admin_page = new GutenBlock_Pro_Admin_Page();
		$admin_page->init();

		// Initialize Pattern Creator (Dev Tool - only for allowed users)
		$pattern_creator = new GutenBlock_Pro_Pattern_Creator();
		$pattern_creator->init();

		// Initialize AI Settings Page
		$ai_settings = GutenBlock_Pro_AI_Settings::get_instance();
		$ai_settings->init();
	}
}
add_action( 'plugins_loaded', 'gutenblock_pro_init' );

/**
 * Register block pattern category
 */
function gutenblock_pro_register_category() {
	register_block_pattern_category(
		'gutenblock-pro',
		array(
			'label' => __( 'GutenBlock Pro', 'gutenblock-pro' ),
		)
	);
}
add_action( 'init', 'gutenblock_pro_register_category', 5 );

/**
 * Add data-content-field attribute to blocks with metadata.name
 * This enables content replacement via Bridge plugin and Migrator
 */
function gutenblock_pro_add_content_field_attribute( $block_content, $block ) {
	// Check if block has metadata.name set
	if ( empty( $block['attrs']['metadata']['name'] ) ) {
		return $block_content;
	}

	$field_id = $block['attrs']['metadata']['name'];

	// Only process text blocks
	$text_blocks = array( 'core/paragraph', 'core/heading', 'core/button', 'core/list-item' );
	if ( ! in_array( $block['blockName'], $text_blocks, true ) ) {
		return $block_content;
	}

	// Add data-content-field attribute to the first HTML tag
	// Pattern: <tagname ... > → <tagname data-content-field="fieldId" ... >
	// Note: Content may start with whitespace/newlines, so we use \s* instead of ^
	$block_content = preg_replace(
		'/^(\s*)(<[a-z][a-z0-9]*)/i',
		'$1$2 data-content-field="' . esc_attr( $field_id ) . '"',
		$block_content,
		1
	);

	return $block_content;
}
add_filter( 'render_block', 'gutenblock_pro_add_content_field_attribute', 10, 2 );

