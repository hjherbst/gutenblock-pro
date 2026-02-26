<?php
/**
 * Material Icons Block – SVG icon block with search and style options.
 *
 * Registers the gutenblock-pro/material-icon block and provides
 * AJAX endpoint for icon path data from @material-symbols-svg/metadata.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Material_Icons {

	/**
	 * Path to metadata package (node_modules) for path JSON files.
	 *
	 * @var string
	 */
	public function init() {
		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'material-icons' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'init', array( $this, 'enqueue_block_style' ), 20 );
		add_action( 'wp_ajax_gutenblock_pro_icon_paths', array( $this, 'ajax_icon_paths' ) );
		add_action( 'wp_ajax_nopriv_gutenblock_pro_icon_paths', array( $this, 'ajax_icon_paths' ) );
		add_action( 'wp_ajax_gutenblock_pro_svg_markup', array( $this, 'ajax_svg_markup' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_mime_type' ), 10, 5 );
	}

	/**
	 * Allow SVG upload for the block (Mediabibliothek).
	 *
	 * @param array $mimes Existing mimes.
	 * @return array
	 */
	public function allow_svg_upload( $mimes ) {
		if ( ! isset( $mimes['svg'] ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		if ( ! isset( $mimes['svgz'] ) ) {
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Fix SVG MIME type detection – WordPress core rejects SVGs
	 * because fileinfo cannot identify them as image/svg+xml.
	 */
	public function fix_svg_mime_type( $data, $file, $filename, $mimes, $real_mime = '' ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( strtolower( $ext ) === 'svg' ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}

	/**
	 * Register render_callback for the Material Icon block (block is registered in JS).
	 */
	public function register_block() {
		register_block_type(
			'gutenblock-pro/material-icon',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Enqueue frontend style so block wrapper has no margin/padding (linksbündig).
	 */
	public function enqueue_block_style() {
		$path = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : plugin_dir_path( dirname( __FILE__ ) );
		$url  = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : plugin_dir_url( dirname( __FILE__ ) );
		$file = 'assets/css/material-icon-block.css';
		if ( ! file_exists( $path . $file ) ) {
			return;
		}
		wp_enqueue_block_style(
			'gutenblock-pro/material-icon',
			array(
				'handle' => 'gutenblock-pro-material-icon-block',
				'src'    => $url . $file,
				'path'   => $path . $file,
				'ver'    => filemtime( $path . $file ),
			)
		);
	}

	/**
	 * Render the Material Icon block (inline SVG).
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_block( $attributes ) {
		$icon_source = isset( $attributes['iconSource'] ) ? $attributes['iconSource'] : 'material';
		$size       = isset( $attributes['size'] ) ? absint( $attributes['size'] ) : 48;
		$color      = isset( $attributes['color'] ) ? $attributes['color'] : '#000000';
		$color_slug = isset( $attributes['colorSlug'] ) ? $attributes['colorSlug'] : '';

		$fill = $color_slug
			? 'var(--wp--preset--color--' . esc_attr( $color_slug ) . ')'
			: esc_attr( $color );

		$size_attr = $size . 'px';

		if ( $icon_source === 'custom' ) {
			$markup = isset( $attributes['customSvgMarkup'] ) ? $attributes['customSvgMarkup'] : '';
			if ( $markup !== '' ) {
				$markup = $this->sanitize_svg_markup( $markup );
				$markup = $this->apply_svg_size_and_fill( $markup, $size_attr, $fill );
				return '<span class="wp-block-gutenblock-pro-material-icon" style="display:inline-block; width:' . esc_attr( $size_attr ) . '; height:' . esc_attr( $size_attr ) . ';">' . $markup . '</span>';
			}
			return '';
		}

		$icon = isset( $attributes['icon'] ) ? $attributes['icon'] : '';
		$path = isset( $attributes['svgPath'] ) ? $attributes['svgPath'] : '';

		if ( empty( $path ) && ! empty( $icon ) ) {
			$path = $this->get_path_for_icon( $icon );
		}

		if ( empty( $path ) ) {
			return '';
		}

		$aria = $icon ? ' aria-hidden="false" role="img" aria-label="' . esc_attr( str_replace( '_', ' ', $icon ) ) . '"' : ' aria-hidden="true"';

		return sprintf(
			'<span class="wp-block-gutenblock-pro-material-icon" style="display:inline-block; width:%1$s; height:%1$s;">' .
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="%1$s" height="%1$s" fill="%2$s"%3$s><path d="%4$s"/></svg>' .
			'</span>',
			esc_attr( $size_attr ),
			$fill,
			$aria,
			esc_attr( $path )
		);
	}

	/**
	 * Sanitize SVG markup (strip scripts, events, external refs).
	 *
	 * @param string $markup Raw SVG string.
	 * @return string Sanitized SVG.
	 */
	private function sanitize_svg_markup( $markup ) {
		$markup = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $markup );
		$markup = preg_replace( '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $markup );
		$markup = preg_replace( '/\s+on\w+\s*=\s*[^\s>]+/i', '', $markup );
		$markup = preg_replace( '/<(\w+)[^>]*\s(href|xlink:href)\s*=\s*["\']?\s*javascript:/i', '<$1 ', $markup );
		return trim( $markup );
	}

	/**
	 * Set width, height and fill on root SVG element.
	 *
	 * @param string $markup   SVG markup.
	 * @param string $size_css e.g. "48px".
	 * @param string $fill    Color value.
	 * @return string Modified SVG.
	 */
	private function apply_svg_size_and_fill( $markup, $size_css, $fill ) {
		$markup = preg_replace( '/<svg\s/i', '<svg xmlns="http://www.w3.org/2000/svg" width="' . esc_attr( $size_css ) . '" height="' . esc_attr( $size_css ) . '" fill="' . esc_attr( $fill ) . '" aria-hidden="true" ', $markup, 1 );
		return $markup;
	}

	/**
	 * AJAX: Return sanitized SVG markup for an attachment ID.
	 */
	public function ajax_svg_markup() {
		$id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Missing attachment_id' ) );
		}
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			wp_send_json_error( array( 'message' => 'File not found' ) );
		}
		$mime = get_post_mime_type( $id );
		$is_svg = ( $mime === 'image/svg+xml' ) || ( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'svg' );
		if ( ! $is_svg ) {
			wp_send_json_error( array( 'message' => 'Not an SVG attachment' ) );
		}
		$markup = file_get_contents( $file );
		if ( $markup === false ) {
			wp_send_json_error( array( 'message' => 'Could not read file' ) );
		}
		$markup = $this->sanitize_svg_markup( $markup );
		wp_send_json_success( array( 'markup' => $markup ) );
	}

	/**
	 * Lazily load the bundled icon-paths.json (outlined/w400 only).
	 */
	private static $bundle_cache = null;

	private static function get_bundle() {
		if ( self::$bundle_cache === null ) {
			$plugin_dir = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : plugin_dir_path( dirname( __FILE__ ) );
			$bundle     = $plugin_dir . 'assets/data/icon-paths.json';
			if ( file_exists( $bundle ) ) {
				self::$bundle_cache = json_decode( file_get_contents( $bundle ), true );
			}
			if ( ! is_array( self::$bundle_cache ) ) {
				self::$bundle_cache = array();
			}
		}
		return self::$bundle_cache;
	}

	/**
	 * Get SVG path string for an icon from the bundled JSON.
	 *
	 * @param string $icon_name Icon name (e.g. home).
	 * @return string Path d or empty.
	 */
	private function get_path_for_icon( $icon_name ) {
		$icon_name = sanitize_file_name( $icon_name );
		if ( '' === $icon_name ) {
			return '';
		}
		$bundle = self::get_bundle();
		return isset( $bundle[ $icon_name ] ) ? $bundle[ $icon_name ] : '';
	}

	/**
	 * AJAX: Return path data for an icon (all styles/weights for editor).
	 * Falls back to bundled outlined/w400 path when individual files are unavailable.
	 */
	public function ajax_icon_paths() {
		$icon_name = isset( $_GET['icon'] ) ? sanitize_file_name( wp_unslash( $_GET['icon'] ) ) : '';
		if ( '' === $icon_name ) {
			wp_send_json_error( array( 'message' => 'Missing icon' ) );
		}

		$bundle = self::get_bundle();
		if ( isset( $bundle[ $icon_name ] ) ) {
			wp_send_json_success( array(
				'outlined' => array(
					'outline' => array( 'w400' => $bundle[ $icon_name ] ),
				),
			) );
		}

		wp_send_json_error( array( 'message' => 'Icon not found' ) );
	}
}
