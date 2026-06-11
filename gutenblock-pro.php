<?php
/**
 * Plugin Name: GutenBlock Plugin
 * Plugin URI: https://github.com/hjherbst/gutenblock-pro
 * Description: Block patterns and Full Site Editor building blocks for WordPress — also acts as the import bridge for the GutenBlock SaaS website builder. Activate a GutenBlock Pro license to unlock premium sections and the higher AI token quota.
 * Version: 1.32.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Hans-Jürgen Herbst
 * Author URI: https://gutenblock.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gutenblock-pro
 * Domain Path: /languages
 *
 * Display name is "GutenBlock Plugin"; the technical slug, text-domain and
 * folder stay `gutenblock-pro` so existing installations keep getting updates.
 * "GutenBlock Pro" now refers exclusively to the paid license tier.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'GUTENBLOCK_PRO_VERSION', '1.32.0' );
define( 'GUTENBLOCK_PRO_FILE', __FILE__ );
define( 'GUTENBLOCK_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'GUTENBLOCK_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'GUTENBLOCK_PRO_PATTERNS_PATH', GUTENBLOCK_PRO_PATH . 'patterns/' );
define( 'GUTENBLOCK_PRO_BLOCKS_PATH', GUTENBLOCK_PRO_PATH . 'blocks/' );

if ( ! defined( 'GUTENBLOCK_PRO_DEV' ) ) {
	define( 'GUTENBLOCK_PRO_DEV', false );
}

/**
 * Get the uploads-based custom path/URL for a block variant file.
 * Custom files live in wp-content/uploads/gutenblock-pro/blocks/{slug}/
 * and survive plugin updates.
 *
 * @param string $slug Block variant slug.
 * @param string $file Filename (e.g. 'custom.css').
 * @return array { 'path' => string, 'url' => string, 'dir' => string }
 */
function gutenblock_pro_custom_block_file( $slug, $file = 'custom.css' ) {
	$upload_dir = wp_upload_dir();
	$base       = $upload_dir['basedir'] . '/gutenblock-pro/blocks/' . $slug . '/';
	$base_url   = $upload_dir['baseurl'] . '/gutenblock-pro/blocks/' . $slug . '/';
	return array(
		'path' => $base . $file,
		'url'  => $base_url . $file,
		'dir'  => $base,
	);
}

/**
 * Get the uploads-based custom path/URL for a pattern file.
 *
 * @param string $slug Pattern slug.
 * @param string $file Filename (e.g. 'style.css', 'script.js', 'content.html').
 * @return array { 'path' => string, 'url' => string, 'dir' => string }
 */
function gutenblock_pro_custom_pattern_file( $slug, $file = 'style.css' ) {
	$upload_dir = wp_upload_dir();
	$base       = $upload_dir['basedir'] . '/gutenblock-pro/patterns/' . $slug . '/';
	$base_url   = $upload_dir['baseurl'] . '/gutenblock-pro/patterns/' . $slug . '/';
	return array(
		'path' => $base . $file,
		'url'  => $base_url . $file,
		'dir'  => $base,
	);
}

/**
 * Pfad zu pattern.php: Uploads-Override hat Vorrang (überlebt Plugin-Updates).
 *
 * @param string $slug Pattern-Slug.
 * @return string Absoluter Pfad zu pattern.php.
 */
function gutenblock_pro_resolve_pattern_php_path( $slug ) {
	$slug = sanitize_key( $slug );
	$custom = gutenblock_pro_custom_pattern_file( $slug, 'pattern.php' );
	if ( file_exists( $custom['path'] ) ) {
		return $custom['path'];
	}
	return GUTENBLOCK_PRO_PATTERNS_PATH . $slug . '/pattern.php';
}

// Load classes
require_once GUTENBLOCK_PRO_PATH . 'inc/class-i18n-fallback.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-site-editor-styles-button.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-tone-injector.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-pattern-loader.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-asset-loader.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-admin-page.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-pattern-creator.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-license.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-ai-generator.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-ai-settings.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-features-page.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-admin-bar.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-container-forms.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-horizontal-scroll.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-media-text-stack.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-material-icons.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-translation-settings.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-provisioning-wizard.php';
/** REST: Pattern-Builder (assemble-page, Section-Ops) — MU-Bridge lädt diese Datei nicht mehr doppelt. */
require_once GUTENBLOCK_PRO_PATH . 'includes/bridge/includes/gutenblock-pattern-builder.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-block-registry.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-grid-responsive.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-sticky-feature.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-flexible-heading.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-mobile-align.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-faq-style.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-text-formats.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-heading-text-image.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-contact-form-presets.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-contact-form-mailer.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-contact-form-settings.php';
require_once GUTENBLOCK_PRO_PATH . 'inc/class-contact-form.php';

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
 * Entfernt veraltete GutenBlock-MU-Bridge-Dateien, die bis 1.20.x von
 * Canvas-/Bridge-Installationen genutzt wurden. Seit 1.22 lädt GutenBlock Pro
 * die Pattern-Builder-REST-Routen selbst; alte MU-Dateien können sonst doppelte
 * Routen, veraltete Preview-Scripts oder Konflikte verursachen.
 */
function gutenblock_pro_cleanup_legacy_mu_bridge() {
	$done_version = (string) get_option( 'gutenblock_pro_mu_bridge_cleanup_version', '' );
	if ( version_compare( $done_version, GUTENBLOCK_PRO_VERSION, '>=' ) ) {
		return;
	}

	if ( ! defined( 'WPMU_PLUGIN_DIR' ) || ! is_dir( WPMU_PLUGIN_DIR ) ) {
		update_option( 'gutenblock_pro_mu_bridge_cleanup_version', GUTENBLOCK_PRO_VERSION, false );
		return;
	}

	$legacy_files = array(
		'gutenblock-bridge.php',
		'gutenblock-pattern-builder.php',
		'gutenblock-pattern-builder.php.bak',
		'gutenblock-opcache-reset.php',
	);

	foreach ( $legacy_files as $file ) {
		$path = trailingslashit( WPMU_PLUGIN_DIR ) . $file;
		if ( is_file( $path ) && is_writable( $path ) ) {
			@unlink( $path );
		}
	}

	update_option( 'gutenblock_pro_mu_bridge_cleanup_version', GUTENBLOCK_PRO_VERSION, false );
}
add_action( 'plugins_loaded', 'gutenblock_pro_cleanup_legacy_mu_bridge', 20 );

/**
 * Initialize the plugin
 */
function gutenblock_pro_init() {
	// Locale fallback BEFORE load_plugin_textdomain: msgids are German,
	// so a de_* site needs no catalog; every non-German site should fall
	// back to en_US instead of seeing the raw German msgid because there
	// is no catalog for its locale (fr_FR, es_ES, …).
	add_filter(
		'plugin_locale',
		function ( $locale, $domain ) {
			if ( 'gutenblock-pro' !== $domain ) {
				return $locale;
			}
			if ( is_string( $locale ) && 0 === strpos( $locale, 'de_' ) ) {
				return $locale;
			}
			return 'en_US';
		},
		10,
		2
	);

	// Load plugin text domain (with the filtered locale).
	load_plugin_textdomain( 'gutenblock-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Hard-coded EN fallback for any msgid the compiled .mo catalog does not
	// cover yet (admin pages were written in German, the .mo file lags behind
	// new strings). Active only on non-de_* locales — German sites keep the
	// untouched msgids.
	( new GutenBlock_Pro_I18n_Fallback() )->init();

	// Re-introduce the half-filled-circle "Styles" pinned-item in the
	// Site Editor (WP 7.0 removed the trigger + side panel from page-
	// edit routes; see CHANGELOG 1.30.2).
	( new GutenBlock_Pro_Site_Editor_Styles_Button() )->init();

	// Initialize License System
	GutenBlock_Pro_License::get_instance();

	// Initialize AI Generator
	GutenBlock_Pro_AI_Generator::get_instance();

	// Initialize Pattern Loader
	$pattern_loader = new GutenBlock_Pro_Pattern_Loader();
	$pattern_loader->init();

	// Provisioning-Wizard (Tools → GutenBlock einrichten)
	GutenBlock_Pro_Provisioning_Wizard::instance()->init();

	// Initialize Asset Loader
	$asset_loader = new GutenBlock_Pro_Asset_Loader();
	$asset_loader->init();

	// Initialize Block Registry
	$block_registry = new GutenBlock_Pro_Block_Registry();
	$block_registry->init();

	// Initialize Pattern Creator — must run outside is_admin() so the
	// REST endpoint (GET /gutenblock-pro/v1/plugin-images) is registered for /wp-json/ requests.
	$pattern_creator = new GutenBlock_Pro_Pattern_Creator();
	$pattern_creator->init();

	// Initialize Admin Page
	if ( is_admin() ) {
		$admin_page = new GutenBlock_Pro_Admin_Page();
		$admin_page->init();

		// Initialize AI Settings Page
		$ai_settings = GutenBlock_Pro_AI_Settings::get_instance();
		$ai_settings->init();

		// Initialize Features Page (toggle optional features)
		$features_page = new GutenBlock_Pro_Features_Page();
		$features_page->init();

		// Initialize Translation Settings Page
		$translation_settings = new GutenBlock_Pro_Translation_Settings();
		$translation_settings->init();

		// Initialize Contact Form Settings Page (recipient + SMTP + test mail)
		$contact_form_settings = new GutenBlock_Pro_Contact_Form_Settings();
		$contact_form_settings->init();
	}

	// Initialize Contact Form Mailer (phpmailer_init SMTP hook; needed on
	// both frontend submit and the admin test-mail action).
	$contact_form_mailer = new GutenBlock_Pro_Contact_Form_Mailer();
	$contact_form_mailer->init();

	// Initialize Contact Form Block (block + REST submit; feature-gated inside).
	$contact_form = new GutenBlock_Pro_Contact_Form();
	$contact_form->init();

	// Initialize Admin Bar Replacement (frontend; feature toggle checked inside class)
	$admin_bar = new GutenBlock_Pro_Admin_Bar();
	$admin_bar->init();

	// Initialize Container Forms (block styles + CSS when feature enabled)
	$container_forms = new GutenBlock_Pro_Container_Forms();
	$container_forms->init();

	// Initialize Horizontal Scroll (columns block when feature enabled)
	$horizontal_scroll = new GutenBlock_Pro_Horizontal_Scroll();
	$horizontal_scroll->init();

	// Initialize Media Text Stack (Text/Medien block: immer stapeln, reverse stapeln)
	$media_text_stack = new GutenBlock_Pro_Media_Text_Stack();
	$media_text_stack->init();

	// Initialize Material Icons Block (when feature enabled)
	$material_icons = new GutenBlock_Pro_Material_Icons();
	$material_icons->init();

	// Initialize Text Formats (RichText toolbar: circle, underline, marker – when feature enabled)
	$text_formats = new GutenBlock_Pro_Text_Formats();
	$text_formats->init();

	// Initialize Heading Text Image (background-clip:text fill for headings)
	$heading_text_image = new GutenBlock_Pro_Heading_Text_Image();
	$heading_text_image->init();

	// Initialize Grid Responsive (responsive column counts for grid-layout group blocks)
	$grid_responsive = new GutenBlock_Pro_Grid_Responsive();
	$grid_responsive->init();

	// Initialize Sticky Feature Block (sticky section with scroll-synced image)
	$sticky_feature = new GutenBlock_Pro_Sticky_Feature();
	$sticky_feature->init();

	// Initialize Flexible Heading Block (grouped H1/H2 with styled span parts)
	$flexible_heading = new GutenBlock_Pro_Flexible_Heading();
	$flexible_heading->init();

	// Initialize Ausrichtung (mobil links + optional Raster-Zentrierung)
	$mobile_align = new GutenBlock_Pro_Mobile_Align();
	$mobile_align->init();

	// Initialize FAQ Style (slide animation + FAQPage structured data)
	$faq_style = new GutenBlock_Pro_Faq_Style();
	$faq_style->init();
}
add_action( 'plugins_loaded', 'gutenblock_pro_init' );

/**
 * Register block pattern category
 */
function gutenblock_pro_register_category() {
	register_block_pattern_category(
		'gutenblock-pro',
		array(
			'label' => __( 'GutenBlock', 'gutenblock-pro' ),
		)
	);
}
add_action( 'init', 'gutenblock_pro_register_category', 5 );

/**
 * Add premium class to premium pattern blocks
 * This enables the premium lock JavaScript to identify and lock premium patterns
 */
function gutenblock_pro_add_premium_class( $block_content, $block ) {
	// Only in editor/admin
	if ( ! is_admin() ) {
		return $block_content;
	}
	
	// Check if block has className attribute
	if ( ! isset( $block['attrs']['className'] ) ) {
		return $block_content;
	}
	
	$class_name = $block['attrs']['className'];
	
	// List of premium pattern identifiers
	$premium_patterns = array( 'gb-section-hero-v2', 'gb-section-cta-v1' );
	$is_premium = false;
	
	foreach ( $premium_patterns as $pattern_class ) {
		if ( strpos( $class_name, $pattern_class ) !== false ) {
			$is_premium = true;
			break;
		}
	}
	
	if ( ! $is_premium ) {
		return $block_content;
	}
	
	// Add premium class to first HTML element
	$block_content = preg_replace(
		'/<(section|div|article|aside|header|footer)([^>]*class=")([^"]*)(")/i',
		'<$1$2$3 gb-pattern-premium$4',
		$block_content,
		1
	);
	
	return $block_content;
}
add_filter( 'render_block', 'gutenblock_pro_add_premium_class', 10, 2 );

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
add_filter( 'render_block', 'gutenblock_pro_add_content_field_attribute', 11, 2 );

