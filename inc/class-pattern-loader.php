<?php
/**
 * Pattern Loader - Auto-discovers and registers patterns from /patterns/ directory
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Pattern_Loader {

	/**
	 * Discovered patterns cache
	 *
	 * @var array
	 */
	private $patterns = array();

	/**
	 * Available pattern groups (sorted by typical page structure)
	 *
	 * @var array
	 */
	public static $groups = array(
		'header'      => 'Header',
		'hero'        => 'Hero',
		'benefits'    => 'Benefits',
		'about'       => 'About',
		'offer'       => 'Offer',
		'teaser'      => 'Teaser',
		'process'     => 'Process',
		'team'        => 'Team',
		'testimonial' => 'Testimonial',
		'quote'       => 'Quote',
		'faq'         => 'FAQ',
		'cta'         => 'CTA',
		'contact'     => 'Contact',
		'footer'      => 'Footer',
	);

	/**
	 * Initialize the pattern loader
	 */
	public function init() {
		add_action( 'init', array( $this, 'discover_patterns' ), 8 );
		add_action( 'init', array( $this, 'register_pattern_categories' ), 8 );
		add_action( 'init', array( $this, 'register_patterns' ), 9 );
	}

	/**
	 * Register pattern categories (only those with content)
	 */
	public function register_pattern_categories() {
		// First discover patterns to know which groups are used
		if ( empty( $this->patterns ) ) {
			$this->discover_patterns();
		}

		// Find which groups have patterns
		$used_groups = array();
		$disabled_patterns = get_option( 'gutenblock_pro_disabled_patterns', array() );
		
		foreach ( $this->patterns as $slug => $pattern ) {
			if ( in_array( $slug, $disabled_patterns ) ) {
				continue;
			}
			if ( ! empty( $pattern['group'] ) && isset( self::$groups[ $pattern['group'] ] ) ) {
				$used_groups[ $pattern['group'] ] = true;
			}
		}

		// Register only used groups as categories
		foreach ( self::$groups as $group_slug => $group_label ) {
			if ( isset( $used_groups[ $group_slug ] ) ) {
				register_block_pattern_category( 'gutenblock-pro-' . $group_slug, array(
					'label' => 'GB Pro: ' . $group_label,
				) );
			}
		}

		// Always register main category
		register_block_pattern_category( 'gutenblock-pro', array(
			'label' => 'GutenBlock Pro',
		) );
	}

	/**
	 * Auto-discover all patterns in /patterns/ directory
	 */
	public function discover_patterns() {
		$patterns_dir = GUTENBLOCK_PRO_PATTERNS_PATH;

		if ( ! is_dir( $patterns_dir ) ) {
			return;
		}

		$pattern_folders = glob( $patterns_dir . '*', GLOB_ONLYDIR );

		foreach ( $pattern_folders as $folder ) {
			$pattern_file = $folder . '/pattern.php';

			if ( file_exists( $pattern_file ) ) {
				$pattern_data = $this->load_pattern_data( $pattern_file, $folder );
				
				if ( $pattern_data ) {
					$this->patterns[ basename( $folder ) ] = $pattern_data;
				}
			}
		}

		// Allow filtering of patterns
		$this->patterns = apply_filters( 'gutenblock_pro_patterns', $this->patterns );
	}

	/**
	 * Load pattern data from pattern.php file
	 *
	 * @param string $file   Path to pattern.php
	 * @param string $folder Pattern folder path
	 * @return array|false Pattern data or false on failure
	 */
	private function load_pattern_data( $file, $folder ) {
		$pattern_data = require $file;

		if ( ! is_array( $pattern_data ) || empty( $pattern_data['title'] ) ) {
			return false;
		}

		$slug = basename( $folder );

		// Default pattern structure
		$defaults = array(
			'title'       => '',
			'description' => '',
			'content'     => '',
			'categories'  => array( 'gutenblock-pro' ),
			'keywords'    => array(),
			'blockTypes'  => array(),
			'inserter'    => true,
			'group'       => '', // Group for categorization
			'type'        => 'pattern', // pattern or page
			// Custom fields for assets
			'has_style'   => file_exists( $folder . '/style.css' ),
			'has_editor'  => file_exists( $folder . '/editor.css' ),
			'has_script'  => file_exists( $folder . '/script.js' ),
			'folder'      => $folder,
			'slug'        => $slug,
		);

		$parsed = wp_parse_args( $pattern_data, $defaults );

		// Build categories based on group
		if ( ! empty( $parsed['group'] ) && isset( self::$groups[ $parsed['group'] ] ) ) {
			$parsed['categories'] = array( 'gutenblock-pro-' . $parsed['group'], 'gutenblock-pro' );
		}

		return $parsed;
	}

	/**
	 * Register all discovered patterns
	 */
	public function register_patterns() {
		$disabled_patterns = get_option( 'gutenblock_pro_disabled_patterns', array() );

		foreach ( $this->patterns as $slug => $pattern ) {
			// Skip disabled patterns
			if ( in_array( $slug, $disabled_patterns ) ) {
				continue;
			}
			$this->register_single_pattern( $slug, $pattern );
		}
	}

	/**
	 * Register a single pattern
	 *
	 * @param string $slug    Pattern slug
	 * @param array  $pattern Pattern data
	 */
	private function register_single_pattern( $slug, $pattern ) {
		// Load content from separate file if not inline
		$content = $pattern['content'];
		
		if ( empty( $content ) ) {
			$content = $this->load_localized_content( $pattern['folder'] );
		}

		if ( empty( $content ) ) {
			return;
		}

		// CSS class marker for asset detection
		$css_class = 'gb-pattern-' . $slug;
		
		// Only wrap if marker class is not already present in content
		if ( strpos( $content, $css_class ) === false ) {
			// Find first wp:group and add class to it
			$content = preg_replace(
				'/<!-- wp:group \{/',
				'<!-- wp:group {"className":"' . $css_class . '",',
				$content,
				1
			);
			// Also add to the div
			$content = preg_replace(
				'/<div class="wp-block-group/',
				'<div class="wp-block-group ' . $css_class,
				$content,
				1
			);
		}

		$pattern_args = array(
			'title'       => $pattern['title'],
			'description' => $pattern['description'],
			'content'     => $content,
			'categories'  => $pattern['categories'],
			'keywords'    => $pattern['keywords'],
			'blockTypes'  => $pattern['blockTypes'],
			'inserter'    => $pattern['inserter'],
		);

		register_block_pattern( 'gutenblock-pro/' . $slug, $pattern_args );
	}

	/**
	 * Load localized content file
	 * Tries: content-{locale}.html -> content-{lang}.html -> content.html
	 *
	 * @param string $folder Pattern folder path
	 * @return string Content or empty string
	 */
	private function load_localized_content( $folder ) {
		$locale = get_locale(); // e.g. de_DE
		$lang = substr( $locale, 0, 2 ); // e.g. de

		// Try files in order of specificity
		$files_to_try = array(
			$folder . '/content-' . $locale . '.html',  // content-de_DE.html
			$folder . '/content-' . $lang . '.html',    // content-de.html
			$folder . '/content.html',                   // content.html (fallback)
		);

		foreach ( $files_to_try as $file ) {
			if ( file_exists( $file ) ) {
				return file_get_contents( $file );
			}
		}

		return '';
	}

	/**
	 * Get all discovered patterns
	 *
	 * @return array
	 */
	public function get_patterns() {
		return $this->patterns;
	}

	/**
	 * Get a single pattern by slug
	 *
	 * @param string $slug Pattern slug
	 * @return array|null
	 */
	public function get_pattern( $slug ) {
		return isset( $this->patterns[ $slug ] ) ? $this->patterns[ $slug ] : null;
	}
}

