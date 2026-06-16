<?php
/**
 * Viewport Visibility – hide group/button blocks per viewport (display:none).
 *
 * Adds an inspector panel to core/group and core/button with toggles to hide
 * the block on Desktop, Tablet and/or Mobile on the frontend. In the editor the
 * block always stays visible but receives an indicator badge so the user knows a
 * viewport rule is applied (similar to Nick Diego's Block Visibility plugin).
 *
 * Breakpoints:
 *   - Mobile:  max-width 600px
 *   - Tablet:  601px – 781px
 *   - Desktop: min-width 782px
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Viewport_Visibility {

	const ATTR_DESKTOP  = 'gbpHideDesktop';
	const ATTR_TABLET   = 'gbpHideTablet';
	const ATTR_MOBILE   = 'gbpHideMobile';
	const CLASS_DESKTOP = 'gbp-hide-desktop';
	const CLASS_TABLET  = 'gbp-hide-tablet';
	const CLASS_MOBILE  = 'gbp-hide-mobile';
	const SUPPORTED_BLOCKS = array( 'core/group', 'core/button', 'core/buttons' );

	public function init() {
		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'viewport-visibility' ) ) {
			return;
		}
		add_filter( 'register_block_type_args', array( $this, 'register_attributes' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'apply_class' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_css' ) );
		// Inject the indicator CSS into the iframed editor canvas (WP 6.3+),
		// where wp_add_inline_style( 'wp-edit-blocks' ) does not reach.
		add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_canvas_styles' ) );
	}

	/**
	 * Register hide attributes on supported blocks.
	 */
	public function register_attributes( $args, $block_name ) {
		if ( ! in_array( $block_name, self::SUPPORTED_BLOCKS, true ) ) {
			return $args;
		}
		$args['attributes'] = array_merge(
			$args['attributes'] ?? array(),
			array(
				self::ATTR_DESKTOP => array(
					'type'    => 'boolean',
					'default' => false,
				),
				self::ATTR_TABLET => array(
					'type'    => 'boolean',
					'default' => false,
				),
				self::ATTR_MOBILE => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		return $args;
	}

	/**
	 * Add hide classes to the rendered block HTML on the frontend.
	 */
	public function apply_class( $content, $block ) {
		if ( ! in_array( $block['blockName'], self::SUPPORTED_BLOCKS, true ) ) {
			return $content;
		}
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
		$add   = array();
		if ( ! empty( $attrs[ self::ATTR_DESKTOP ] ) ) {
			$add[] = self::CLASS_DESKTOP;
		}
		if ( ! empty( $attrs[ self::ATTR_TABLET ] ) ) {
			$add[] = self::CLASS_TABLET;
		}
		if ( ! empty( $attrs[ self::ATTR_MOBILE ] ) ) {
			$add[] = self::CLASS_MOBILE;
		}
		if ( empty( $add ) ) {
			return $content;
		}

		// Add the hide classes to the outermost block element. WP_HTML_Tag_Processor
		// guarantees we target the first tag (the block wrapper) even when it
		// already has a class attribute or none at all — works in theme parts,
		// synced patterns and inside navigation menus alike.
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );
			if ( $processor->next_tag() ) {
				foreach ( $add as $cls ) {
					$processor->add_class( $cls );
				}
				return $processor->get_updated_html();
			}
		}

		// Fallback for environments without the HTML API.
		$prefix = implode( ' ', $add ) . ' ';
		if ( preg_match( '/\bclass="/', $content ) ) {
			return preg_replace( '/\bclass="/', 'class="' . $prefix, $content, 1 );
		}
		return preg_replace( '/^(\s*)(<[a-z][a-z0-9]*)/i', '$1$2 class="' . trim( $prefix ) . '"', $content, 1 );
	}

	public function enqueue_css() {
		wp_register_style( 'gbp-viewport-visibility', false, array(), GUTENBLOCK_PRO_VERSION );
		wp_enqueue_style( 'gbp-viewport-visibility' );
		wp_add_inline_style( 'gbp-viewport-visibility', $this->get_css() );
	}

	public function enqueue_editor_css() {
		wp_add_inline_style( 'wp-edit-blocks', $this->get_editor_css() );
	}

	/**
	 * Push the indicator CSS into the editor canvas so it also applies inside
	 * the iframed block canvas used since WP 6.3.
	 *
	 * @param array $settings Block editor settings.
	 * @return array
	 */
	public function inject_editor_canvas_styles( $settings ) {
		if ( ! isset( $settings['styles'] ) || ! is_array( $settings['styles'] ) ) {
			$settings['styles'] = array();
		}
		$settings['styles'][] = array( 'css' => $this->get_editor_css() );
		return $settings;
	}

	public function enqueue_editor_assets() {
		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/viewport-visibility-editor.js';
		wp_enqueue_script(
			'gbp-viewport-visibility-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/viewport-visibility-editor.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
	}

	/**
	 * Frontend CSS: hide per viewport via media queries.
	 */
	private function get_css() {
		return '
		@media (min-width: 782px) {
			.' . self::CLASS_DESKTOP . ' { display: none !important; }
		}
		@media (min-width: 601px) and (max-width: 781px) {
			.' . self::CLASS_TABLET . ' { display: none !important; }
		}
		@media (max-width: 600px) {
			.' . self::CLASS_MOBILE . ' { display: none !important; }
		}
		';
	}

	/**
	 * Editor CSS: block stays visible but shows an indicator badge so the user
	 * knows a viewport rule is applied. Never actually hides the block.
	 */
	private function get_editor_css() {
		return '
		.gbp-vv-has-rule {
			position: relative;
			outline: 1px dashed rgba(34, 113, 177, 0.6) !important;
			outline-offset: 1px;
		}
		.gbp-vv-has-rule::after {
			content: "\1F441\FE0F " attr(data-gbp-vv);
			position: absolute;
			top: 0;
			right: 0;
			z-index: 21;
			pointer-events: none;
			font-size: 10px;
			line-height: 1.2;
			font-weight: 600;
			letter-spacing: 0.02em;
			padding: 3px 6px;
			border-radius: 0 0 0 3px;
			background: #2271b1;
			color: #fff;
			white-space: nowrap;
			box-shadow: 0 1px 2px rgba(0,0,0,0.25);
		}
		';
	}
}
