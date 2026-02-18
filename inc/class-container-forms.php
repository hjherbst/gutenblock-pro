<?php
/**
 * Container Forms – decorative shapes on core/group blocks.
 *
 * Adds a "Container-Form" dropdown to the group block inspector.
 * Each option applies a shape above and/or below the container
 * via CSS pseudo-elements.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Container_Forms {

	const ATTR         = 'containerForm';
	const CLASS_PREFIX = 'has-container-form-';

	private static $presets = array( 'wave', 'diagonal', 'curve', 'arrow', 'zigzag', 'asymmetric', 'layered' );

	private static $allowed = array(
		'wave-top', 'wave-bottom', 'wave-both',
		'diagonal-top', 'diagonal-bottom', 'diagonal-both',
		'curve-top', 'curve-bottom', 'curve-both',
		'arrow-top', 'arrow-bottom', 'arrow-both',
		'zigzag-top', 'zigzag-bottom', 'zigzag-both',
		'asymmetric-bottom',
		'layered-bottom',
	);

	public static function get_presets() {
		return array(
			'wave'       => __( 'Welle', 'gutenblock-pro' ),
			'diagonal'   => __( 'Diagonale', 'gutenblock-pro' ),
			'curve'      => __( 'Bogen', 'gutenblock-pro' ),
			'arrow'      => __( 'Spitze', 'gutenblock-pro' ),
			'zigzag'     => __( 'Zickzack', 'gutenblock-pro' ),
			'asymmetric' => __( 'Asymmetric', 'gutenblock-pro' ),
			'layered'    => __( 'Layered Wave', 'gutenblock-pro' ),
		);
	}

	public function init() {
		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'container-forms' ) ) {
			return;
		}
		add_filter( 'register_block_type_args', array( $this, 'register_attribute' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'add_frontend_class' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_css_editor' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css_frontend' ) );
	}

	public function register_attribute( $args, $name ) {
		if ( 'core/group' !== $name ) {
			return $args;
		}
		if ( ! isset( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes'][ self::ATTR ] = array(
			'type'    => 'string',
			'default' => '',
		);
		return $args;
	}

	public function add_frontend_class( $content, $block ) {
		if ( 'core/group' !== $block['blockName'] ) {
			return $content;
		}
		$form = isset( $block['attrs'][ self::ATTR ] ) ? $block['attrs'][ self::ATTR ] : '';
		if ( '' === $form || ! in_array( $form, self::$allowed, true ) ) {
			return $content;
		}
		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag() ) {
			$processor->add_class( self::CLASS_PREFIX . $form );
		}
		return $processor->get_updated_html();
	}

	public function enqueue_editor_assets() {
		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/container-forms-editor.js';
		wp_enqueue_script(
			'gutenblock-pro-container-forms-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/container-forms-editor.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
	}

	/* ------------------------------------------------------------------
	 * CSS
	 * ----------------------------------------------------------------*/

	public function enqueue_css_editor() {
		$css = $this->get_all_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'wp-edit-blocks', $css );
		}
	}

	public function enqueue_css_frontend() {
		$css = $this->get_all_css();
		if ( '' === $css ) {
			return;
		}
		$handle = 'gutenblock-pro-container-forms';
		wp_register_style( $handle, false, array() );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	private function get_all_css() {
		$positions = array( 'top', 'bottom', 'both' );
		$height    = '2.5rem';
		$parts     = array();

		foreach ( self::$presets as $preset ) {
			$method = 'get_css_' . $preset;
			if ( ! is_callable( array( $this, $method ) ) ) {
				continue;
			}
			foreach ( $positions as $pos ) {
				$key = $preset . '-' . $pos;
				if ( ! in_array( $key, self::$allowed, true ) ) {
					continue;
				}
				$sel = '.has-container-form-' . $key;
				if ( 'both' === $pos ) {
					$parts[] = $this->$method( $sel, 'top', $height, false );
					$parts[] = $this->$method( $sel, 'bottom', $height, true );
				} else {
					$parts[] = $this->$method( $sel, $pos, $height, false );
				}
			}
		}
		return implode( "\n", $parts );
	}

	/* ------------------------------------------------------------------
	 * Shared CSS builder
	 * ----------------------------------------------------------------*/

	private function get_docked_form_css( $selector, $position, $height, $clip_path, $pseudo = 'before', $svg_mask_url = null ) {
		$is_bottom  = ( 'bottom' === $position );
		$pseudo_sel = $selector . '::' . $pseudo;
		$pos_css    = $is_bottom
			? 'top: 100%%; left: 0; right: 0; width: auto; height: %s; display: block;'
			: 'bottom: 100%%; left: 0; right: 0; width: auto; height: %s; display: block;';
		$pos_css    = sprintf( $pos_css, $height );

		if ( null !== $svg_mask_url ) {
			$mask_value = sprintf( 'url("%s")', str_replace( '"', '\\22', $svg_mask_url ) );
			return sprintf(
				"%s { position: relative; overflow: visible; }\n%s { content: ''; position: absolute; display: block; %s background-color: inherit; -webkit-mask-image: %s; mask-image: %s; -webkit-mask-size: 100%% 100%%; mask-size: 100%% 100%%; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; }",
				$selector,
				$pseudo_sel,
				$pos_css,
				$mask_value,
				$mask_value
			);
		}
		return sprintf(
			"%s { position: relative; overflow: visible; }\n%s { content: ''; position: absolute; display: block; %s background-color: inherit; clip-path: %s; }",
			$selector,
			$pseudo_sel,
			$pos_css,
			$clip_path
		);
	}

	/**
	 * Build an SVG data URI with optional vertical/horizontal flip.
	 */
	private function build_svg_data_uri( $viewbox_w, $viewbox_h, $path_d, $flip_v, $flip_h ) {
		$transforms = array();
		if ( $flip_h ) {
			$transforms[] = 'scale(-1,1) translate(-' . $viewbox_w . ',0)';
		}
		if ( $flip_v ) {
			$transforms[] = 'scale(1,-1) translate(0,-' . $viewbox_h . ')';
		}
		$inner = ! empty( $transforms )
			? '<g transform="' . implode( ' ', $transforms ) . '"><path d="' . $path_d . '" fill="white"/></g>'
			: '<path d="' . $path_d . '" fill="white"/>';
		$svg = '<svg viewBox="0 0 ' . $viewbox_w . ' ' . $viewbox_h . '" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' . $inner . '</svg>';
		return 'data:image/svg+xml,' . rawurlencode( $svg );
	}

	/* ------------------------------------------------------------------
	 * 1. Wave  (SVG mask)
	 *    Original: wavy edge at y=0, flat at y=82.
	 *    → top (::before): no flip needed (flat bottom = container edge)
	 *    → bottom (::after): flip vertical (flat moves to top = container edge)
	 * ----------------------------------------------------------------*/

	private function get_css_wave( $selector, $position, $height, $mirror_h = false ) {
		$is_bottom = ( 'bottom' === $position );
		$pseudo    = $is_bottom ? 'after' : 'before';
		$svg_url   = $this->build_svg_data_uri(
			1768, 82,
			'M932.374 0C745.519 0.000150678 0 77.6833 0 77.6833V82H1768V0C1768 0 1653.23 35.0827 1537.99 36.5863C1422.75 38.0898 1119.23 -0.000150677 932.374 0Z',
			$is_bottom,
			$mirror_h
		);
		return $this->get_docked_form_css( $selector, $position, $height, '', $pseudo, $svg_url );
	}

	/* ------------------------------------------------------------------
	 * 2. Diagonal  (clip-path)
	 *    Top is always horizontally mirrored so top and bottom
	 *    diagonals go in opposite directions.
	 * ----------------------------------------------------------------*/

	private function get_css_diagonal( $selector, $position, $height, $mirror_h = false ) {
		$is_bottom = ( 'bottom' === $position );
		$pseudo    = $is_bottom ? 'after' : 'before';
		if ( $is_bottom ) {
			$poly = $mirror_h
				? 'polygon(0 0, 100% 0, 0 100%)'
				: 'polygon(0 0, 100% 0, 100% 100%)';
		} else {
			$poly = 'polygon(0 100%, 100% 100%, 100% 0)';
		}
		return $this->get_docked_form_css( $selector, $position, $height, $poly, $pseudo );
	}

	/* ------------------------------------------------------------------
	 * 3. Curve / Arc  (SVG mask)
	 *    Original: flat at y=0, dome arcs downward → solid at top.
	 *    → bottom (::after): NO flip (flat top = container edge)
	 *    → top (::before): flip vertical (flat moves to bottom = container edge)
	 * ----------------------------------------------------------------*/

	private function get_css_curve( $selector, $position, $height, $mirror_h = false ) {
		$is_bottom = ( 'bottom' === $position );
		$pseudo    = $is_bottom ? 'after' : 'before';
		$svg_url   = $this->build_svg_data_uri(
			1200, 120,
			'M0 0 H1200 Q600 240 0 0 Z',
			! $is_bottom,
			$mirror_h
		);
		return $this->get_docked_form_css( $selector, $position, $height, '', $pseudo, $svg_url );
	}

	/* ------------------------------------------------------------------
	 * 4. Arrow / Spitze  (clip-path)
	 *    Centered triangle pointing away from the container.
	 * ----------------------------------------------------------------*/

	private function get_css_arrow( $selector, $position, $height, $mirror_h = false ) {
		$is_bottom = ( 'bottom' === $position );
		$pseudo    = $is_bottom ? 'after' : 'before';
		$poly      = $is_bottom
			? 'polygon(0 0, 100% 0, 50% 100%)'
			: 'polygon(0 100%, 100% 100%, 50% 0)';
		return $this->get_docked_form_css( $selector, $position, $height, $poly, $pseudo );
	}

	/* ------------------------------------------------------------------
	 * 5. Zigzag / Zickzack  (SVG mask)
	 *    Flat at y=0, sawtooth teeth pointing down.
	 *    → bottom: no flip (flat top = container edge)
	 *    → top: flip vertical (flat bottom = container edge)
	 * ----------------------------------------------------------------*/

	private function get_css_zigzag( $selector, $position, $height, $mirror_h = false ) {
		$is_bottom = ( 'bottom' === $position );
		$pseudo    = $is_bottom ? 'after' : 'before';
		$svg_url   = $this->build_svg_data_uri(
			1200, 100,
			'M0 0 H1200 L1100 100 L1000 0 L900 100 L800 0 L700 100 L600 0 L500 100 L400 0 L300 100 L200 0 L100 100 L0 0 Z',
			! $is_bottom,
			$mirror_h
		);
		return $this->get_docked_form_css( $selector, $position, $height, '', $pseudo, $svg_url );
	}

	/* ------------------------------------------------------------------
	 * 6. Asymmetric  (SVG mask, bottom only)
	 *    Asymmetric curve: low on the left, rising to the right.
	 * ----------------------------------------------------------------*/

	private function get_css_asymmetric( $selector, $position, $height, $mirror_h = false ) {
		$svg_url = $this->build_svg_data_uri(
			1000, 100,
			'M0 0v100-79.4a1892 1892 0 0 1 500 0l500 66.7V0H0Z',
			false,
			$mirror_h
		);
		return $this->get_docked_form_css( $selector, 'bottom', $height, '', 'after', $svg_url );
	}

	/* ------------------------------------------------------------------
	 * 7. Layered Wave  (SVG mask, bottom only)
	 *    Three overlapping wave paths with decreasing opacity
	 *    creating a stepped fade from container color to transparent.
	 * ----------------------------------------------------------------*/

	private function get_css_layered( $selector, $position, $height, $mirror_h = false ) {
		$inner = '<g fill="white">'
			. '<path d="M1000 100C500 100 500 64 0 64V0h1000v100Z" opacity=".5"/>'
			. '<path d="M1000 100C500 100 500 34 0 34V0h1000v100Z" opacity=".5"/>'
			. '<path d="M1000 100C500 100 500 4 0 4V0h1000v100Z"/>'
			. '</g>';
		if ( $mirror_h ) {
			$inner = '<g transform="scale(-1,1) translate(-1000,0)">' . $inner . '</g>';
		}
		$svg     = '<svg viewBox="0 0 1000 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' . $inner . '</svg>';
		$svg_url = 'data:image/svg+xml,' . rawurlencode( $svg );
		return $this->get_docked_form_css( $selector, 'bottom', $height, '', 'after', $svg_url );
	}
}
