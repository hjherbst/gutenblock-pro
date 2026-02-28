<?php
/**
 * Horizontal Scroll – horizontal scrolling for core/columns blocks.
 *
 * Adds a Sidebar panel to the columns block with toggle and responsive
 * settings. When enabled, columns display in a horizontal scroll with
 * snap, optional dots and arrow navigation.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Horizontal_Scroll {

	public function init() {
		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'horizontal-scroll' ) ) {
			return;
		}
		add_filter( 'register_block_type_args', array( $this, 'register_attributes' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'render_horizontal_scroll' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_css' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register custom attributes for core/columns.
	 */
	public function register_attributes( $args, $name ) {
		if ( 'core/columns' !== $name ) {
			return $args;
		}
		if ( ! isset( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes']['horizontalScroll'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		$args['attributes']['hScrollDesktop'] = array(
			'type'    => 'integer',
			'default' => 3,
		);
		$args['attributes']['hScrollTablet'] = array(
			'type'    => 'integer',
			'default' => 2,
		);
		$args['attributes']['hScrollMobile'] = array(
			'type'    => 'integer',
			'default' => 1,
		);
		$args['attributes']['hScrollPeekDesktop'] = array(
			'type'    => 'integer',
			'default' => 40,
		);
		$args['attributes']['hScrollPeekTablet'] = array(
			'type'    => 'integer',
			'default' => 30,
		);
		$args['attributes']['hScrollPeekMobile'] = array(
			'type'    => 'integer',
			'default' => 0,
		);
		$args['attributes']['hScrollDots'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
		$args['attributes']['hScrollArrows'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
		$args['attributes']['hScrollInfinite'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		return $args;
	}

	/**
	 * Modify columns block output when horizontal scroll is enabled.
	 */
	public function render_horizontal_scroll( $content, $block ) {
		if ( 'core/columns' !== $block['blockName'] ) {
			return $content;
		}
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
		if ( empty( $attrs['horizontalScroll'] ) ) {
			return $content;
		}

		$desktop = isset( $attrs['hScrollDesktop'] ) ? (int) $attrs['hScrollDesktop'] : 3;
		$tablet  = isset( $attrs['hScrollTablet'] ) ? (int) $attrs['hScrollTablet'] : 2;
		$mobile  = isset( $attrs['hScrollMobile'] ) ? (int) $attrs['hScrollMobile'] : 1;
		$peek_d  = isset( $attrs['hScrollPeekDesktop'] ) ? (int) $attrs['hScrollPeekDesktop'] : 40;
		$peek_t  = isset( $attrs['hScrollPeekTablet'] ) ? (int) $attrs['hScrollPeekTablet'] : 30;
		$peek_m  = isset( $attrs['hScrollPeekMobile'] ) ? (int) $attrs['hScrollPeekMobile'] : 0;
		$dots     = ! isset( $attrs['hScrollDots'] ) || $attrs['hScrollDots'];
		$arrows   = ! isset( $attrs['hScrollArrows'] ) || $attrs['hScrollArrows'];
		$infinite = ! empty( $attrs['hScrollInfinite'] );

		$processor = new WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag() ) {
			return $content;
		}

		$processor->add_class( 'has-horizontal-scroll' );
		$processor->set_attribute( 'data-hscroll', 'true' );
		$processor->set_attribute( 'data-hscroll-desktop', (string) $desktop );
		$processor->set_attribute( 'data-hscroll-tablet', (string) $tablet );
		$processor->set_attribute( 'data-hscroll-mobile', (string) $mobile );
		$processor->set_attribute( 'data-hscroll-peek-desktop', (string) $peek_d );
		$processor->set_attribute( 'data-hscroll-peek-tablet', (string) $peek_t );
		$processor->set_attribute( 'data-hscroll-peek-mobile', (string) $peek_m );
		$processor->set_attribute( 'data-hscroll-dots', $dots ? 'true' : 'false' );
		$processor->set_attribute( 'data-hscroll-arrows', $arrows ? 'true' : 'false' );
		$processor->set_attribute( 'data-hscroll-infinite', $infinite ? 'true' : 'false' );
		$hscroll_style = $this->get_inline_style( $desktop, $tablet, $mobile, $peek_d, $peek_t, $peek_m );
		$existing_style = $processor->get_attribute( 'style' );
		$processor->set_attribute( 'style', $existing_style ? $hscroll_style . ' ' . $existing_style : $hscroll_style );

		$content = $processor->get_updated_html();

		$col_count  = isset( $block['innerBlocks'] ) ? count( $block['innerBlocks'] ) : 0;
		$dot_count  = $col_count > 0 ? (int) ceil( $col_count / $desktop ) : 1;
		$dot_count  = max( 1, $dot_count );

		$nav = '';
		if ( $dots || $arrows ) {
			$nav = '<div class="gb-hscroll-nav">';
			if ( $dots ) {
				$nav .= '<div class="gb-hscroll-dots" role="tablist" aria-label="' . esc_attr__( 'Seiten', 'gutenblock-pro' ) . '">';
				for ( $i = 0; $i < $dot_count; $i++ ) {
					$active = 0 === $i ? ' is-active' : '';
					$nav .= '<button type="button" class="' . $active . '" aria-label="' . esc_attr( sprintf( __( 'Seite %d', 'gutenblock-pro' ), $i + 1 ) ) . '" aria-current="' . ( 0 === $i ? 'true' : 'false' ) . '"></button>';
				}
				$nav .= '</div>';
			}
			if ( $arrows ) {
				$nav .= '<div class="gb-hscroll-arrows">';
				$nav .= '<button type="button" class="gb-hscroll-prev" aria-label="' . esc_attr__( 'Zurück', 'gutenblock-pro' ) . '">' . esc_html( '←' ) . '</button>';
				$nav .= '<button type="button" class="gb-hscroll-next" aria-label="' . esc_attr__( 'Weiter', 'gutenblock-pro' ) . '">' . esc_html( '→' ) . '</button>';
				$nav .= '</div>';
			}
			$nav .= '</div>';
		}

		$align = isset( $attrs['align'] ) ? $attrs['align'] : '';
		$wrapper_class = 'gb-hscroll-wrapper';
		if ( 'wide' === $align ) {
			$wrapper_class .= ' alignwide';
		} elseif ( 'full' === $align ) {
			$wrapper_class .= ' alignfull';
		}

		return '<div class="' . esc_attr( $wrapper_class ) . '">' . $content . $nav . '</div>';
	}

	/**
	 * Build inline style with CSS custom properties for column widths.
	 */
	private function get_inline_style( $desktop, $tablet, $mobile, $peek_d, $peek_t, $peek_m ) {
		return sprintf(
			'--hscroll-cols: %d; --hscroll-peek: %dpx; --hscroll-cols-tablet: %d; --hscroll-peek-tablet: %dpx; --hscroll-cols-mobile: %d; --hscroll-peek-mobile: %dpx;',
			$desktop, $peek_d, $tablet, $peek_t, $mobile, $peek_m
		);
	}

	/**
	 * Enqueue editor assets.
	 */
	public function enqueue_editor_assets() {
		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/horizontal-scroll-editor.js';
		wp_enqueue_script(
			'gutenblock-pro-horizontal-scroll-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/horizontal-scroll-editor.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
	}

	/**
	 * Enqueue editor CSS.
	 */
	public function enqueue_editor_css() {
		$css = $this->get_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'wp-edit-blocks', $css );
		}
	}

	/**
	 * Enqueue frontend CSS and JS.
	 */
	public function enqueue_frontend_assets() {
		$handle = 'gutenblock-pro-horizontal-scroll';
		wp_register_style( $handle, false, array() );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $this->get_css() );

		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/horizontal-scroll.js';
		wp_enqueue_script(
			$handle,
			GUTENBLOCK_PRO_URL . 'assets/js/horizontal-scroll.js',
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
	}

	/**
	 * Get CSS for horizontal scroll (public for pattern preview).
	 *
	 * @return string
	 */
	public static function get_styles() {
		return self::get_css_static();
	}

	/**
	 * Get CSS for horizontal scroll.
	 */
	private function get_css() {
		return self::get_css_static();
	}

	/**
	 * Static CSS string for horizontal scroll.
	 */
	private static function get_css_static() {
		return "
.gb-hscroll-wrapper { position: relative; }
/* Respect alignment: override constrained layout so alignwide/full take effect */
.is-layout-constrained > .gb-hscroll-wrapper.alignwide {
	max-width: var(--wp--style--global--wide-size, 1280px);
}
.is-layout-constrained > .gb-hscroll-wrapper.alignfull {
	max-width: none;
}
/* Editor canvas: ensure alignment width is applied */
.editor-styles-wrapper .gb-hscroll-wrapper.alignwide {
	max-width: var(--wp--style--global--wide-size, 1280px);
}
.editor-styles-wrapper .gb-hscroll-wrapper.alignfull {
	max-width: none;
}
.has-horizontal-scroll {
	display: flex !important;
	flex-wrap: nowrap !important;
	overflow-x: auto;
	scroll-snap-type: x mandatory;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: none;
}
.has-horizontal-scroll::-webkit-scrollbar { display: none; }
.has-horizontal-scroll > .wp-block-column {
	flex: 0 0 auto;
	scroll-snap-align: start;
	min-width: calc((100% - var(--hscroll-peek, 0px)) / var(--hscroll-cols, 3));
}
@media (max-width: 781px) {
	.has-horizontal-scroll > .wp-block-column {
		min-width: calc((100% - var(--hscroll-peek-tablet, 0px)) / var(--hscroll-cols-tablet, 2));
	}
}
@media (max-width: 600px) {
	.has-horizontal-scroll > .wp-block-column {
		min-width: calc((100% - var(--hscroll-peek-mobile, 0px)) / var(--hscroll-cols-mobile, 1));
	}
}
.gb-hscroll-nav {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 1rem;
	padding: 0 0.5rem;
}
.gb-hscroll-dots {
	display: flex;
	gap: 6px;
	align-items: center;
}
.gb-hscroll-dots button {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	border: 1px solid currentColor;
	background: transparent;
	cursor: pointer;
	padding: 0;
	opacity: 0.5;
	transition: opacity 0.2s, background 0.2s;
}
.gb-hscroll-dots button:hover { opacity: 0.8; }
.gb-hscroll-dots button.is-active {
	background: currentColor;
	opacity: 1;
}
.gb-hscroll-arrows {
	display: flex;
	gap: 4px;
}
.gb-hscroll-prev, .gb-hscroll-next {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	padding: 0;
	border: 1px solid currentColor;
	border-radius: 6px;
	background: transparent;
	cursor: pointer;
	font-size: 18px;
	line-height: 1;
	transition: background 0.2s, color 0.2s;
}
.gb-hscroll-prev:hover, .gb-hscroll-next:hover {
	background: rgba(0,0,0,0.05);
}
";
	}
}
