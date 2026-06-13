<?php
/**
 * GutenTheme functions.
 *
 * This is a thin block theme. All real site-builder features come from the
 * GutenBlock Pro plugin, so this file deliberately stays minimal:
 *
 *  1. Standard block-theme setup (theme support).
 *  2. Frontend + editor stylesheets/scripts.
 *
 * The headless-CMS bridge for the GutenBlock SaaS (the `gbp_content` custom
 * post type, its SEO meta panel, the revalidation webhook, and the
 * `gutenblock/v1/cms/*` REST endpoints) used to live here. It moved to the
 * dedicated, backup-excluded "Gutenblock Headless CMS" plugin so that this
 * theme stays a clean deliverable: when a customer site imports a GutenBlock
 * template, none of the CMS-only code ships with it. That plugin is activated
 * only on the CMS instance (patterns.gutenblock.com / local dev).
 *
 * The plugin gutenblock-pro must work on ANY block theme, so anything that is
 * required for pattern functionality lives in the plugin — not here.
 *
 * @package GutenTheme
 */

/* -------------------------------------------------------------------------
 * 1. Block-theme setup
 * --------------------------------------------------------------------- */

add_action( 'after_setup_theme', function() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'post-thumbnails' );
	add_editor_style( 'css/gutenblock-custom-styles.css' );
} );

/* -------------------------------------------------------------------------
 * 2. Stylesheets
 * --------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function() {
	$version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'gutentheme-style',
		get_stylesheet_directory_uri() . '/style.css',
		array(),
		$version
	);

	// Scroll-reveal animations (opt-in per block via the editor "Motion" panel).
	wp_enqueue_style(
		'gutentheme-motion',
		get_stylesheet_directory_uri() . '/css/motion.css',
		array(),
		$version
	);

	// Sticky header on back-scroll: toggles scroll-up/scroll-down on <body>.
	wp_enqueue_script(
		'gutentheme-scroll',
		get_stylesheet_directory_uri() . '/js/scroll.js',
		array(),
		$version,
		true
	);

	// Reveal-on-scroll runtime for blocks carrying data-motion attributes.
	wp_enqueue_script(
		'gutentheme-motion',
		get_stylesheet_directory_uri() . '/js/motion.js',
		array(),
		$version,
		true
	);
} );

// Editor-only helpers (visited-link colors, header/footer link reset,
// grid container fix for media-text). Frontend styling for the patterns
// comes from the plugin's per-pattern CSS.
add_action( 'enqueue_block_editor_assets', function() {
	$version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'gutentheme-editor-fixes',
		get_stylesheet_directory_uri() . '/css/gutenblock-custom-styles.css',
		array(),
		$version
	);

	// "Motion" inspector panel for group/columns/column/image blocks.
	wp_enqueue_script(
		'gutentheme-motion-sidebar',
		get_stylesheet_directory_uri() . '/js/motion-sidebar.js',
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-compose' ),
		$version,
		true
	);
} );
