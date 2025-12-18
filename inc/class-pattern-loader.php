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
		
		// Filter to group pattern categories visually
		add_filter( 'block_pattern_categories', array( $this, 'filter_pattern_categories' ), 10, 1 );
		
		// Enqueue navigation script for nested structure
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_navigation_script' ) );
		
		// AJAX endpoint for modal
		add_action( 'wp_ajax_gutenblock_pro_get_patterns_for_modal', array( $this, 'ajax_get_patterns_for_modal' ) );
	}

	/**
	 * Enqueue navigation script and styles for nested pattern categories
	 */
	public function enqueue_navigation_script() {
		wp_enqueue_script(
			'gutenblock-pro-pattern-navigation',
			GUTENBLOCK_PRO_URL . 'assets/js/pattern-navigation.js',
			array(),
			GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_enqueue_style(
			'gutenblock-pro-pattern-navigation',
			GUTENBLOCK_PRO_URL . 'assets/css/pattern-navigation.css',
			array(),
			GUTENBLOCK_PRO_VERSION
		);

		// Enqueue pattern modal
		wp_enqueue_script(
			'gutenblock-pro-pattern-modal',
			GUTENBLOCK_PRO_URL . 'assets/js/pattern-modal.js',
			array(
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-blocks',
				'wp-plugins',
				'wp-edit-post',
			),
			GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_enqueue_style(
			'gutenblock-pro-pattern-modal',
			GUTENBLOCK_PRO_URL . 'assets/css/pattern-modal.css',
			array(),
			GUTENBLOCK_PRO_VERSION
		);

		// Get license info for modal
		$license = GutenBlock_Pro_License::get_instance();
		$license_info = $license->get_license_info();

		// Localize script with data
		wp_localize_script( 'gutenblock-pro-pattern-modal', 'gutenblockProModal', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'gutenblock_pro_modal' ),
			'groups'       => self::$groups,
			'hasPremium'   => $license->has_premium_access(),
			'licenseInfo'  => $license_info,
			'upgradeUrl'   => 'https://app.gutenblock.com/licenses', // Link zum Lizenzkauf
		) );
	}

	/**
	 * Register pattern categories (only those with content)
	 * Creates two main categories: "GutenBlock Sections" (with subgroups) and "GutenBlock Pages"
	 */
	public function register_pattern_categories() {
		// First discover patterns to know which groups are used
		if ( empty( $this->patterns ) ) {
			$this->discover_patterns();
		}

		$disabled_patterns = get_option( 'gutenblock_pro_disabled_patterns', array() );
		
		// Separate sections and pages
		$sections = array();
		$pages = array();
		$used_groups = array();
		
		foreach ( $this->patterns as $slug => $pattern ) {
			if ( in_array( $slug, $disabled_patterns ) ) {
				continue;
			}
			
			if ( isset( $pattern['type'] ) && $pattern['type'] === 'page' ) {
				$pages[ $slug ] = $pattern;
			} else {
				$sections[ $slug ] = $pattern;
				if ( ! empty( $pattern['group'] ) && isset( self::$groups[ $pattern['group'] ] ) ) {
					$used_groups[ $pattern['group'] ] = true;
				}
			}
		}

		// Register main category for Sections (with subgroups)
		if ( ! empty( $sections ) ) {
			register_block_pattern_category( 'gutenblock-pro-sections', array(
				'label' => 'GutenBlock Sections',
			) );
			
			// Register subgroup categories - use separator for visual nesting
			foreach ( self::$groups as $group_slug => $group_label ) {
				if ( isset( $used_groups[ $group_slug ] ) ) {
					register_block_pattern_category( 'gutenblock-pro-sections-' . $group_slug, array(
						'label' => 'GutenBlock Sections › ' . $group_label,
					) );
				}
			}
		}

		// Register main category for Pages
		if ( ! empty( $pages ) ) {
			register_block_pattern_category( 'gutenblock-pro-pages', array(
				'label' => 'GutenBlock Pages',
			) );
		}

		// Legacy main category (for backwards compatibility)
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
			'premium'     => false, // true = benötigt Pro Plus Lizenz
			// Custom fields for assets
			'has_style'   => file_exists( $folder . '/style.css' ),
			'has_editor'  => file_exists( $folder . '/editor.css' ),
			'has_script'  => file_exists( $folder . '/script.js' ),
			'folder'      => $folder,
			'slug'        => $slug,
		);

		$parsed = wp_parse_args( $pattern_data, $defaults );

		// Build categories based on type and group
		if ( isset( $parsed['type'] ) && $parsed['type'] === 'page' ) {
			// Pages go to "GutenBlock Pages" category
			$parsed['categories'] = array( 'gutenblock-pro-pages', 'gutenblock-pro' );
		} else {
			// Sections go to "GutenBlock Sections" with optional subgroup
			$parsed['categories'] = array( 'gutenblock-pro-sections', 'gutenblock-pro' );
			if ( ! empty( $parsed['group'] ) && isset( self::$groups[ $parsed['group'] ] ) ) {
				$parsed['categories'][] = 'gutenblock-pro-sections-' . $parsed['group'];
			}
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

			// Register ALL patterns (including premium) - editing will be blocked in editor
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

		// Check if pattern is premium
		$is_premium = isset( $pattern['premium'] ) && $pattern['premium'] === true;
		$license = GutenBlock_Pro_License::get_instance();
		$has_premium_access = $license->has_premium_access();

		// CSS class marker for asset detection AND premium locking
		$css_class = 'gb-pattern-' . $slug;
		if ( $is_premium ) {
			$css_class .= ' gb-pattern-premium';
		}
		
		// Simple, robust approach: Add class to first HTML element directly
		// This works regardless of block type (cover, group, etc.)
		$content = preg_replace(
			'/<(section|div|article|aside|header|footer)\s+class="([^"]*)"/',
			'<$1 class="$2 ' . esc_attr( $css_class ) . '"',
			$content,
			1
		);
		
		// Debug logging
		if ( $is_premium ) {
			error_log( '[GutenBlock Pro] Added premium class to pattern: ' . $slug );
		}
		
		// Original class handling for backward compatibility
		if ( false && preg_match( '/^<!-- wp:(\S+) (\{.*?\}) -->/s', $content, $matches ) ) {
			// No wp:group found - wrap content in group with class
			$content = '<!-- wp:group {"className":"' . esc_attr( $css_class ) . '"} -->' . "\n" .
			           '<div class="wp-block-group ' . esc_attr( $css_class ) . '">' . "\n" .
			           $content . "\n" .
			           '</div>' . "\n" .
			           '<!-- /wp:group -->';
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
	 * Filter pattern categories to create visual grouping
	 * Reorders categories so "GutenBlock Sections" appears first with subgroups after
	 *
	 * @param array $categories Array of pattern categories
	 * @return array Filtered categories
	 */
	public function filter_pattern_categories( $categories ) {
		// Separate our categories from others
		$gutenblock_sections = array();
		$gutenblock_sections_subs = array();
		$gutenblock_pages = array();
		$other_categories = array();

		foreach ( $categories as $key => $category ) {
			if ( strpos( $key, 'gutenblock-pro-sections-' ) === 0 ) {
				// Subcategory (Hero, Benefits, etc.)
				$gutenblock_sections_subs[ $key ] = $category;
			} elseif ( $key === 'gutenblock-pro-sections' ) {
				// Main Sections category
				$gutenblock_sections[ $key ] = $category;
			} elseif ( $key === 'gutenblock-pro-pages' ) {
				// Pages category
				$gutenblock_pages[ $key ] = $category;
			} else {
				// Other categories
				$other_categories[ $key ] = $category;
			}
		}

		// Reorder: Sections main, then subs, then Pages, then others
		$reordered = array();
		
		// Add main Sections category first
		if ( ! empty( $gutenblock_sections ) ) {
			$reordered = array_merge( $reordered, $gutenblock_sections );
		}
		
		// Add subsection categories (sorted by group order)
		if ( ! empty( $gutenblock_sections_subs ) ) {
			$sorted_subs = array();
			foreach ( self::$groups as $group_slug => $group_label ) {
				$sub_key = 'gutenblock-pro-sections-' . $group_slug;
				if ( isset( $gutenblock_sections_subs[ $sub_key ] ) ) {
					$sorted_subs[ $sub_key ] = $gutenblock_sections_subs[ $sub_key ];
				}
			}
			$reordered = array_merge( $reordered, $sorted_subs );
		}
		
		// Add Pages category
		if ( ! empty( $gutenblock_pages ) ) {
			$reordered = array_merge( $reordered, $gutenblock_pages );
		}
		
		// Add other categories
		$reordered = array_merge( $reordered, $other_categories );

		return $reordered;
	}

	/**
	 * AJAX: Get patterns for modal
	 */
	public function ajax_get_patterns_for_modal() {
		check_ajax_referer( 'gutenblock_pro_modal', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		// Ensure patterns are discovered
		if ( empty( $this->patterns ) ) {
			$this->discover_patterns();
		}

		$disabled_patterns = get_option( 'gutenblock_pro_disabled_patterns', array() );
		$patterns_for_modal = array();

		$license = GutenBlock_Pro_License::get_instance();
		$has_premium_access = $license->has_premium_access();

		foreach ( $this->patterns as $slug => $pattern ) {
			if ( in_array( $slug, $disabled_patterns ) ) {
				continue;
			}

			// Check if pattern is premium
			$is_premium = isset( $pattern['premium'] ) && $pattern['premium'] === true;
			$has_access = ! $is_premium || $has_premium_access;

			// Load content (ALWAYS load, even for premium patterns - they can be inserted but not edited)
			$content = '';
			if ( ! empty( $pattern['content'] ) ) {
				$content = $pattern['content'];
			} else {
				$content = $this->load_localized_content( $pattern['folder'] );
			}

			$patterns_for_modal[] = array(
				'name'        => 'gutenblock-pro/' . $slug,
				'title'       => $pattern['title'],
				'description' => $pattern['description'],
				'content'     => $content, // Always include content - editing will be blocked in editor
				'type'        => isset( $pattern['type'] ) ? $pattern['type'] : 'pattern',
				'group'       => isset( $pattern['group'] ) ? $pattern['group'] : '',
				'keywords'    => isset( $pattern['keywords'] ) ? $pattern['keywords'] : array(),
				'slug'        => $slug,
				'premium'     => $is_premium,
				'hasAccess'   => $has_access, // For display purposes (badge)
			);
		}

		wp_send_json_success( array(
			'patterns' => $patterns_for_modal,
			'groups'   => self::$groups,
		) );
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

