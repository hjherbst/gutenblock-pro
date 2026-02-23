<?php
/**
 * Asset Loader - Conditional CSS/JS loading based on pattern usage
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Asset_Loader {

	/**
	 * Patterns used on current page
	 *
	 * @var array
	 */
	private $used_patterns = array();

	/**
	 * Initialize the asset loader
	 */
	public function init() {
		// Frontend: Detect and load assets conditionally
		add_action( 'wp', array( $this, 'detect_used_patterns' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Editor: Load all pattern styles for preview
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		// FSE: Register block styles for editor iframe
		add_action( 'init', array( $this, 'register_block_styles' ) );
	}

	/**
	 * Detect which patterns are used on the current page
	 */
	public function detect_used_patterns() {
		if ( is_admin() ) {
			return;
		}

		$content = $this->get_page_content();
		$patterns_dir = GUTENBLOCK_PRO_PATTERNS_PATH;

		if ( ! is_dir( $patterns_dir ) ) {
			return;
		}

		$pattern_folders = glob( $patterns_dir . '*', GLOB_ONLYDIR );

		foreach ( $pattern_folders as $folder ) {
			$slug = basename( $folder );
			$css_class = 'gb-pattern-' . $slug;

			// Check if pattern marker class exists in content
			if ( strpos( $content, $css_class ) !== false ) {
				$this->used_patterns[] = $slug;
			}
		}

		$this->used_patterns = array_unique( $this->used_patterns );
	}

	/**
	 * Get all content from current page (including template parts)
	 *
	 * @return string
	 */
	private function get_page_content() {
		global $post, $_wp_current_template_content;

		$content = '';

		// Regular post/page content
		if ( $post ) {
			$content .= $post->post_content;
		}

		// FSE template content
		if ( ! empty( $_wp_current_template_content ) ) {
			$content .= $_wp_current_template_content;
		}

		// Check template parts (header, footer, etc.)
		$content .= $this->get_template_parts_content();

		return $content;
	}

	/**
	 * Get content from active template parts
	 *
	 * @return string
	 */
	private function get_template_parts_content() {
		$content = '';

		// Get active template parts from database
		$template_parts = get_posts( array(
			'post_type'      => 'wp_template_part',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $template_parts as $part ) {
			$content .= $part->post_content;
		}

		return $content;
	}

	/**
	 * Enqueue frontend assets for used patterns only
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin() ) {
			return;
		}

		foreach ( $this->used_patterns as $slug ) {
			$this->enqueue_pattern_assets( $slug, 'frontend' );
		}
	}

	/**
	 * Enqueue editor assets (all patterns for preview)
	 */
	public function enqueue_editor_assets() {
		$patterns_dir = GUTENBLOCK_PRO_PATTERNS_PATH;

		if ( ! is_dir( $patterns_dir ) ) {
			return;
		}

		$pattern_folders = glob( $patterns_dir . '*', GLOB_ONLYDIR );

		foreach ( $pattern_folders as $folder ) {
			$slug = basename( $folder );
			$this->enqueue_pattern_assets( $slug, 'editor' );
		}
	}

	/**
	 * Register block styles for FSE iframe support
	 */
	public function register_block_styles() {
		$patterns_dir = GUTENBLOCK_PRO_PATTERNS_PATH;

		if ( ! is_dir( $patterns_dir ) ) {
			return;
		}

		$pattern_folders = glob( $patterns_dir . '*', GLOB_ONLYDIR );

		foreach ( $pattern_folders as $folder ) {
			$slug = basename( $folder );
			$style_file = $folder . '/style.css';
			$editor_file = $folder . '/editor.css';

			// Register styles to be loaded in editor iframe via wp_enqueue_block_style
			if ( file_exists( $style_file ) ) {
				wp_enqueue_block_style(
					'core/group',
					array(
						'handle' => 'gutenblock-pro-' . $slug,
						'src'    => GUTENBLOCK_PRO_URL . 'patterns/' . $slug . '/style.css',
						'ver'    => filemtime( $style_file ),
						'path'   => $style_file,
					)
				);

				$custom_style = gutenblock_pro_custom_pattern_file( $slug, 'style.css' );
				if ( file_exists( $custom_style['path'] ) ) {
					wp_enqueue_block_style(
						'core/group',
						array(
							'handle' => 'gutenblock-pro-' . $slug . '-custom',
							'src'    => $custom_style['url'],
							'ver'    => filemtime( $custom_style['path'] ),
							'path'   => $custom_style['path'],
						)
					);
				}
			}

			// Register editor-specific styles
			if ( file_exists( $editor_file ) ) {
				wp_enqueue_block_style(
					'core/group',
					array(
						'handle' => 'gutenblock-pro-' . $slug . '-editor',
						'src'    => GUTENBLOCK_PRO_URL . 'patterns/' . $slug . '/editor.css',
						'ver'    => filemtime( $editor_file ),
						'path'   => $editor_file,
					)
				);

				$custom_editor = gutenblock_pro_custom_pattern_file( $slug, 'editor.css' );
				if ( file_exists( $custom_editor['path'] ) ) {
					wp_enqueue_block_style(
						'core/group',
						array(
							'handle' => 'gutenblock-pro-' . $slug . '-editor-custom',
							'src'    => $custom_editor['url'],
							'ver'    => filemtime( $custom_editor['path'] ),
							'path'   => $custom_editor['path'],
						)
					);
				}
			}
		}
	}

	/**
	 * Enqueue assets for a single pattern
	 *
	 * @param string $slug    Pattern slug
	 * @param string $context 'frontend' or 'editor'
	 */
	private function enqueue_pattern_assets( $slug, $context = 'frontend' ) {
		$folder = GUTENBLOCK_PRO_PATTERNS_PATH . $slug;
		$url_base = GUTENBLOCK_PRO_URL . 'patterns/' . $slug;
		$handle = 'gutenblock-pro-' . $slug;

		// Style CSS (both frontend and editor)
		$style_file = $folder . '/style.css';
		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				$handle,
				$url_base . '/style.css',
				array(),
				filemtime( $style_file )
			);

			$custom_style = gutenblock_pro_custom_pattern_file( $slug, 'style.css' );
			if ( file_exists( $custom_style['path'] ) ) {
				wp_enqueue_style(
					$handle . '-custom',
					$custom_style['url'],
					array( $handle ),
					filemtime( $custom_style['path'] )
				);
			}
		}

		// Editor CSS (editor only)
		if ( $context === 'editor' ) {
			$editor_file = $folder . '/editor.css';
			if ( file_exists( $editor_file ) ) {
				wp_enqueue_style(
					$handle . '-editor',
					$url_base . '/editor.css',
					array( $handle ),
					filemtime( $editor_file )
				);

				$custom_editor = gutenblock_pro_custom_pattern_file( $slug, 'editor.css' );
				if ( file_exists( $custom_editor['path'] ) ) {
					wp_enqueue_style(
						$handle . '-editor-custom',
						$custom_editor['url'],
						array( $handle . '-editor' ),
						filemtime( $custom_editor['path'] )
					);
				}
			}
		}

		// Script JS (frontend only)
		if ( $context === 'frontend' ) {
			$script_file = $folder . '/script.js';
			if ( file_exists( $script_file ) ) {
				wp_enqueue_script(
					$handle,
					$url_base . '/script.js',
					array(),
					filemtime( $script_file ),
					true
				);

				$custom_script = gutenblock_pro_custom_pattern_file( $slug, 'script.js' );
				if ( file_exists( $custom_script['path'] ) ) {
					wp_enqueue_script(
						$handle . '-custom',
						$custom_script['url'],
						array( $handle ),
						filemtime( $custom_script['path'] ),
						true
					);
				}
			}
		}
	}

	/**
	 * Get list of used patterns on current page
	 *
	 * @return array
	 */
	public function get_used_patterns() {
		return $this->used_patterns;
	}
}

