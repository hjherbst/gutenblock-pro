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
		'header'         => 'Header',
		'hero'           => 'Hero',
		'benefits'       => 'Benefits/Pain Points',
		'about'          => 'About',
		'stats'          => 'Stats/Numbers',
		'services'       => 'Services',
		'pricing'        => 'Pricing',
		'teaser'         => 'Teaser',
		'teaser-grid'    => 'Teaser Grid',
		'text-columns'   => 'Text Columns',
		'post-loop'      => 'Post Loop',
		'carousel'       => 'Carousel',
		'gallery'        => 'Gallery',
		'process'        => 'Process',
		'team'           => 'Team',
		'testimonial'    => 'Testimonial',
		'quote'          => 'Quote',
		'partners'       => 'Partners/Logos',
		'events'         => 'Events',
		'business-hours' => 'Business Hours',
		'cta'            => 'CTA',
		'faq'            => 'FAQ',
		'map'            => 'Map/Location',
		'contact'        => 'Contact',
		'newsletter'     => 'Newsletter',
		'footer'         => 'Footer',
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
		add_action( 'wp_ajax_gutenblock_pro_get_pattern_tone_content', array( $this, 'ajax_get_pattern_tone_content' ) );
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
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'gutenblock_pro_modal' ),
			'clearCacheNonce' => wp_create_nonce( 'gbp_clear_preview_cache' ),
			'isAdmin'         => current_user_can( 'manage_options' ),
			'groups'          => self::$groups,
			'hasPremium'      => $license->has_premium_access(),
			'licenseInfo'     => $license_info,
			'upgradeUrl'      => 'https://gutenblock.com/licenses',
			'pluginVersion'   => defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0',
		) );

		// Tone-Toolbar: Picker in der Block-Toolbar zum Umschalten der Tonalität.
		wp_enqueue_script(
			'gutenblock-pro-tone-toolbar',
			GUTENBLOCK_PRO_URL . 'assets/js/tone-toolbar.js',
			array( 'wp-element', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-compose', 'wp-hooks' ),
			GUTENBLOCK_PRO_VERSION,
			true
		);

		// Pattern-Slug → unterstützte Tones (für Toolbar-Sichtbarkeit)
		if ( empty( $this->patterns ) ) {
			$this->discover_patterns();
		}
		$tone_map = array();
		foreach ( $this->patterns as $slug => $p ) {
			$tones = isset( $p['tones'] ) && is_array( $p['tones'] ) ? array_values( $p['tones'] ) : array( 'neutral' );
			if ( count( $tones ) > 1 ) {
				$tone_map[ $slug ] = array( 'tones' => $tones );
			}
		}

		wp_localize_script( 'gutenblock-pro-tone-toolbar', 'gutenblockProToneToolbar', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'gutenblock_pro_modal' ),
			'patterns'     => $tone_map,
			'toolbarLabel' => __( 'Tonalität', 'gutenblock-pro' ),
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
			$slug         = basename( $folder );
			$pattern_file = function_exists( 'gutenblock_pro_resolve_pattern_php_path' )
				? gutenblock_pro_resolve_pattern_php_path( $slug )
				: $folder . '/pattern.php';

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

		// AI metadata is maintained directly in each pattern.php:
		// description, ai_hint, content_fields.

		// Default pattern structure
		$defaults = array(
			'title'          => '',
			'description'    => '',
			'ai_hint'        => '',
			'content_fields' => array(),
			'content'        => '',
			'categories'     => array( 'gutenblock-pro' ),
			'keywords'       => array(),
			'blockTypes'     => array(),
			'inserter'       => true,
			'group'          => '',
			'type'           => 'pattern', // pattern or page
			// Nur für Pages relevant: Sub-Typ, anhand dessen die KI im SaaS
			// eine Vorlage auswählen kann. Mögliche Werte: '', 'services',
			// 'about', 'blog', 'legal' (bestehende Impressum-Vorlage).
			'page_type'      => '',
			// Optionale Patterns-Liste: Die Page setzt sich aus diesen
			// Section-Slugs zusammen (für die KI-getriebene Page-Erstellung).
			'page_patterns'  => array(),
			'premium'        => false,
			'tones'          => array( 'neutral' ),
			// Custom fields for assets
			'has_style'      => file_exists( $folder . '/style.css' ),
			'has_editor'     => file_exists( $folder . '/editor.css' ),
			'has_script'     => file_exists( $folder . '/script.js' ),
			'folder'         => $folder,
			'slug'           => $slug,
		);

		$parsed = wp_parse_args( $pattern_data, $defaults );

		// Build categories based on type and group
		if ( isset( $parsed['type'] ) && $parsed['type'] === 'page' ) {
			// Pages go to "GutenBlock Pages" category
			$parsed['categories'] = array( 'gutenblock-pro-pages', 'gutenblock-pro' );
			// Page-type patterns surface in WordPress' "Choose a pattern"
			// modal that appears when creating a new page (or any CPT that
			// supports post-content). Without `core/post-content` in
			// blockTypes the modal would offer no patterns at all for
			// custom post types like `gbp_content`.
			if ( ! in_array( 'core/post-content', $parsed['blockTypes'], true ) ) {
				$parsed['blockTypes'][] = 'core/post-content';
			}
		} else {
			// Sections go to "GutenBlock Sections" with optional subgroup
			$parsed['categories'] = array( 'gutenblock-pro-sections', 'gutenblock-pro' );
			if ( ! empty( $parsed['group'] ) && isset( self::$groups[ $parsed['group'] ] ) ) {
				$parsed['categories'][] = 'gutenblock-pro-sections-' . $parsed['group'];
			}

			// Header- und Footer-Patterns sind Template-Parts und sollen nur
			// im Site Editor (Templates / Template-Parts) eingefügt werden.
			// `blockTypes` mit `core/template-part/{area}` macht sie für den
			// nativen "Choose a header/footer"-Dialog auffindbar; gleichzeitig
			// `inserter: false`, damit sie nicht im normalen Beitrags-Inserter
			// auftauchen. Unser eigenes Modal filtert zusätzlich nach
			// Editor-Kontext.
			$group_lower = strtolower( (string) $parsed['group'] );
			if ( $group_lower === 'header' || $group_lower === 'footer' ) {
				$block_type = 'core/template-part/' . $group_lower;
				if ( ! in_array( $block_type, $parsed['blockTypes'], true ) ) {
					$parsed['blockTypes'][] = $block_type;
				}
				$parsed['inserter'] = false;
				$parsed['template_part_only'] = true;
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

			$tones = isset( $pattern['tones'] ) && is_array( $pattern['tones'] ) ? $pattern['tones'] : array( 'neutral' );

			foreach ( $tones as $tone ) {
				if ( ! GutenBlock_Pro_Tone_Injector::is_valid_tone( $tone ) ) {
					continue;
				}
				$tone_slug = GutenBlock_Pro_Tone_Injector::tone_slug( $slug, $tone );
				$this->register_single_pattern( $tone_slug, $pattern, $tone );
			}
		}
	}

	/**
	 * Register a single pattern, optionally with a tone variant.
	 *
	 * @param string $slug    Pattern slug (may include tone suffix, e.g. hero-v1--dark)
	 * @param array  $pattern Pattern data
	 * @param string $tone    Tone key ('neutral'|'dark'|'soft')
	 */
	private function register_single_pattern( $slug, $pattern, $tone = 'neutral' ) {
		// Load content from separate file if not inline
		$content = $pattern['content'];

		if ( empty( $content ) ) {
			$content = $this->load_localized_content( $pattern['folder'] );
		}

		if ( empty( $content ) ) {
			return;
		}

		$content = self::normalize_core_image_blocks( $content );

		// Tone-Attribute injizieren (für neutral: no-op)
		if ( $tone !== 'neutral' ) {
			$content = GutenBlock_Pro_Tone_Injector::inject( $content, $tone );
		}

		// Check if pattern is premium
		$is_premium = isset( $pattern['premium'] ) && $pattern['premium'] === true;

		// CSS class marker for asset detection
		// Tone variants keep the base slug class so their styles are loaded.
		$base_slug = $pattern['slug'];
		$css_class  = 'gb-pattern-' . $base_slug;
		if ( $tone !== 'neutral' ) {
			$css_class .= ' gb-tone-' . $tone;
		}

		$content = preg_replace(
			'/<(section|div|article|aside|header|footer)\s+class="([^"]*)"/',
			'<$1 class="$2 ' . esc_attr( $css_class ) . '"',
			$content,
			1
		);

		if ( $is_premium ) {
			error_log( '[GutenBlock Pro] Registered premium pattern: ' . $slug );
		}

		// Titel um Ton-Label ergänzen
		$title = $pattern['title'];
		if ( $tone !== 'neutral' ) {
			$labels = GutenBlock_Pro_Tone_Injector::tone_labels();
			$title  = $title . ' (' . $labels[ $tone ] . ')';
		}

		// Tone-Varianten sollen NICHT im nativen Inserter auftauchen –
		// sie werden nur via Custom-Modal / SaaS-API verwendet (inserter: false).
		// So bleibt im FSE pro Pattern nur eine Karte; die Varianten wählst du
		// dort über Swatches.
		$inserter_visible = $pattern['inserter'];
		if ( $tone !== 'neutral' ) {
			$inserter_visible = false;
		}

		$pattern_args = array(
			'title'       => $title,
			'description' => $pattern['description'],
			'content'     => $content,
			'categories'  => $pattern['categories'],
			'keywords'    => $pattern['keywords'],
			'blockTypes'  => $pattern['blockTypes'],
			'inserter'    => $inserter_visible,
		);

		register_block_pattern( 'gutenblock-pro/' . $slug, $pattern_args );
	}

	/**
	 * Re-hostet alle absoluten URLs auf Plugin-Assets (`/wp-content/plugins/gutenblock-pro/...`)
	 * auf die aktuelle Plugin-Installation.
	 *
	 * Hintergrund: `content.html`-Dateien werden häufig via Copy-Paste aus dem FSE einer
	 * konkreten WP-Instanz (z. B. `patterns.gutenblock.com`) ins Repo übernommen. Damit
	 * würde jeder User des Plugins die Demo-Bilder dauerhaft vom Ursprungsserver laden.
	 * Dieser Helper sorgt dafür, dass beim Pattern-Register / Bundle-Build der jeweilige
	 * Plugin-Pfad der lokalen WP-Installation eingesetzt wird – egal, welche Host-Domain
	 * im Quelldokument steht.
	 *
	 * Erkennt auch den Platzhalter `__PLUGIN_URL__` (für künftige host-agnostische
	 * Pattern-Files).
	 *
	 * Idempotent: Auf der Ursprungs-Instanz selbst ist der Replace ein No-op.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function normalize_plugin_asset_urls( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return $content;
		}

		// Prefer the precomputed plugin URL constant; fall back to `plugins_url()`
		// against `GUTENBLOCK_PRO_FILE` for safety. Both yield the same value but
		// the constant route works even on early hooks where `plugins_url()` may
		// not yet be available.
		if ( defined( 'GUTENBLOCK_PRO_URL' ) ) {
			$base = rtrim( GUTENBLOCK_PRO_URL, '/' );
		} elseif ( defined( 'GUTENBLOCK_PRO_FILE' ) && function_exists( 'plugins_url' ) ) {
			$base = rtrim( plugins_url( '', GUTENBLOCK_PRO_FILE ), '/' );
		} else {
			return $content;
		}

		if ( strpos( $content, '__PLUGIN_URL__' ) !== false ) {
			$content = str_replace( '__PLUGIN_URL__', $base, $content );
		}

		if ( strpos( $content, '/wp-content/plugins/gutenblock-pro/' ) === false ) {
			return $content;
		}

		return preg_replace_callback(
			'#https?://[^\s"\'<>]+?/wp-content/plugins/gutenblock-pro/([^\s"\'<>]+)#i',
			function ( $m ) use ( $base ) {
				return $base . '/' . $m[1];
			},
			$content
		);
	}

	/**
	 * Inverse of {@see self::normalize_plugin_asset_urls()}: rewrites concrete
	 * plugin-asset URLs back to the host-agnostic `__PLUGIN_URL__` placeholder.
	 *
	 * Use this BEFORE persisting pattern content to disk (e.g. in
	 * `class-pattern-creator.php::ajax_save_pattern()`), so that `content.html`
	 * files committed into the plugin do not contain instance-specific URLs
	 * such as `http://localhost:10038/wp-content/plugins/gutenblock-pro/...`.
	 *
	 * Handles both absolute and root-relative variants:
	 *   - `https?://<host>/wp-content/plugins/gutenblock-pro/assets/images/X`
	 *   - `/wp-content/plugins/gutenblock-pro/assets/images/X`
	 *
	 * Idempotent: existing `__PLUGIN_URL__` placeholders are left untouched.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function to_plugin_url_placeholder( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return $content;
		}

		// Absolute URL with scheme + host
		$content = preg_replace(
			'#https?://[^\s"\'<>]+?/wp-content/plugins/gutenblock-pro/(assets/images/[^\s"\'<>]+)#i',
			'__PLUGIN_URL__/$1',
			$content
		);

		// Root-relative path (boundary: must not be preceded by alnum/dot/slash so we
		// do not break already-absolute URLs or already-placeholdered occurrences).
		$content = preg_replace(
			'#(?<![A-Za-z0-9./_-])/wp-content/plugins/gutenblock-pro/(assets/images/[^\s"\'<>]+)#',
			'__PLUGIN_URL__/$1',
			$content
		);

		return $content;
	}

	/**
	 * Normalize core/image blocks so they pass block validation when inserted.
	 * Ensures: url/alt in block comment, figure has wp-block-image and size-{sizeSlug} class.
	 *
	 * @param string $content Pattern block content
	 * @return string Normalized content
	 */
	public static function normalize_core_image_blocks( $content ) {
		$content = self::normalize_plugin_asset_urls( $content );
		if ( strpos( $content, 'wp:image' ) === false ) {
			return $content;
		}

		return preg_replace_callback(
			'/<!-- wp:image (.*?) -->\s*(.*?)\s*<!-- \/wp:image -->/s',
			function ( $m ) {
				$attrs_str = trim( $m[1] );
				$inner    = $m[2];
				$attrs    = json_decode( $attrs_str, true );
				if ( ! is_array( $attrs ) ) {
					return $m[0];
				}

				// Ensure url and alt in block comment (from img if missing)
				if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $inner, $src_m ) ) {
					$img_url = $src_m[1];
					if ( isset( $attrs['url'] ) && $attrs['url'] === '' ) {
						unset( $attrs['url'] );
					}
					if ( ! isset( $attrs['url'] ) || $attrs['url'] === '' ) {
						$attrs['url'] = $img_url;
					}
				}
				if ( ! array_key_exists( 'alt', $attrs ) ) {
					$attrs['alt'] = '';
					if ( preg_match( '/<img[^>]+alt=["\']([^"\']*)["\']/', $inner, $alt_m ) ) {
						$attrs['alt'] = $alt_m[1];
					}
				}

				// Ensure figure has wp-block-image and size-{sizeSlug} when sizeSlug is set
				$size_slug = isset( $attrs['sizeSlug'] ) ? trim( $attrs['sizeSlug'] ) : '';
				if ( preg_match( '/<figure\s+class=["\']([^"\']*)["\']/', $inner, $fig_m ) ) {
					$classes   = array_filter( array_map( 'trim', explode( ' ', $fig_m[1] ) ) );
					$classes[] = 'wp-block-image';
					if ( $size_slug && ! in_array( 'size-' . $size_slug, $classes, true ) ) {
						$classes[] = 'size-' . $size_slug;
					}
					$classes   = array_unique( $classes );
					$new_class = implode( ' ', $classes );
					$inner     = preg_replace( '/<figure\s+class=["\'][^"\']*["\']/', '<figure class="' . esc_attr( $new_class ) . '"', $inner, 1 );
				}

				$new_attrs = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				return '<!-- wp:image ' . $new_attrs . ' -->' . "\n" . $inner . "\n" . '<!-- /wp:image -->';
			},
			$content
		);
	}

	/**
	 * Load localized content file
	 * Tries: content-{locale}.html -> content-{lang}.html -> content.html
	 *
	 * @param string $folder Pattern folder path
	 * @return string Content or empty string
	 */
	private function load_localized_content( $folder ) {
		$slug   = basename( $folder );
		$locale = get_locale(); // e.g. de_DE
		$lang   = substr( $locale, 0, 2 ); // e.g. de

		// Try custom files from uploads first, then plugin defaults
		$candidates = array(
			'content-' . $locale . '.html',  // content-de_DE.html
			'content-' . $lang . '.html',    // content-de.html
			'content.html',                   // content.html (fallback)
		);

		foreach ( $candidates as $filename ) {
			$custom = gutenblock_pro_custom_pattern_file( $slug, $filename );
			if ( file_exists( $custom['path'] ) ) {
				return self::normalize_core_image_blocks( file_get_contents( $custom['path'] ) );
			}
			$default = $folder . '/' . $filename;
			if ( file_exists( $default ) ) {
				return self::normalize_core_image_blocks( file_get_contents( $default ) );
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
				$content = self::normalize_core_image_blocks( $pattern['content'] );
			} else {
				$content = $this->load_localized_content( $pattern['folder'] );
			}

			$tones = isset( $pattern['tones'] ) && is_array( $pattern['tones'] ) ? $pattern['tones'] : array( 'neutral' );

			$patterns_for_modal[] = array(
				'name'              => 'gutenblock-pro/' . $slug,
				'title'             => $pattern['title'],
				'description'       => $pattern['description'],
				'content'           => $content,
				'type'              => isset( $pattern['type'] ) ? $pattern['type'] : 'pattern',
				'group'             => isset( $pattern['group'] ) ? $pattern['group'] : '',
				'keywords'          => isset( $pattern['keywords'] ) ? $pattern['keywords'] : array(),
				'slug'              => $slug,
				'premium'           => $is_premium,
				'hasAccess'         => $has_access,
				'tones'             => array_values( $tones ),
				// Markiert Patterns, die nur im Template-/Template-Part-Editor
				// auftauchen sollen (Header/Footer). Vom Modal-JS für die
				// Editor-Scope-Filterung genutzt.
				'templatePartOnly'  => ! empty( $pattern['template_part_only'] ),
			);
		}

		wp_send_json_success( array(
			'patterns' => $patterns_for_modal,
			'groups'   => self::$groups,
		) );
	}

	/**
	 * AJAX: Liefert Pattern-Content mit injizierter Tonalität für den Insert-Vorgang.
	 *
	 * Erwartet POST: pattern (slug), tone ('neutral'|'dark'|'soft'), nonce
	 */
	public function ajax_get_pattern_tone_content() {
		check_ajax_referer( 'gutenblock_pro_modal', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$slug = isset( $_POST['pattern'] ) ? sanitize_key( $_POST['pattern'] ) : '';
		$tone = isset( $_POST['tone'] ) ? sanitize_key( $_POST['tone'] ) : 'neutral';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'No pattern slug' ) );
		}
		if ( ! GutenBlock_Pro_Tone_Injector::is_valid_tone( $tone ) ) {
			$tone = 'neutral';
		}

		if ( empty( $this->patterns ) ) {
			$this->discover_patterns();
		}
		if ( ! isset( $this->patterns[ $slug ] ) ) {
			wp_send_json_error( array( 'message' => 'Unknown pattern' ) );
		}

		$pattern = $this->patterns[ $slug ];
		$content = ! empty( $pattern['content'] )
			? $pattern['content']
			: $this->load_localized_content( $pattern['folder'] );

		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => 'No content' ) );
		}

		$content = self::normalize_core_image_blocks( $content );
		$content = GutenBlock_Pro_Tone_Injector::inject( $content, $tone );

		// Marker-Klasse beibehalten (wie in register_single_pattern)
		$css_class = 'gb-pattern-' . $slug;
		if ( $tone !== 'neutral' ) {
			$css_class .= ' gb-tone-' . $tone;
		}
		$content = preg_replace(
			'/<(section|div|article|aside|header|footer)\s+class="([^"]*)"/',
			'<$1 class="$2 ' . esc_attr( $css_class ) . '"',
			$content,
			1
		);

		wp_send_json_success( array(
			'slug'    => $slug,
			'tone'    => $tone,
			'content' => $content,
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

