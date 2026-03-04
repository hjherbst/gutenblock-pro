<?php
/**
 * Grid Responsive – responsive column counts for core/group blocks with grid layout.
 *
 * Adds Sidebar controls for Tablet and Mobile column counts.
 * Injects a scoped <style> block per block instance at render time.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Grid_Responsive {

	public function init() {
		add_filter( 'register_block_type_args', array( $this, 'register_attributes' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'render' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register custom attributes on core/group.
	 */
	public function register_attributes( $args, $name ) {
		if ( 'core/group' !== $name ) {
			return $args;
		}
		if ( ! isset( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes']['gbpGridColsTablet'] = array(
			'type'    => 'integer',
			'default' => 0,
		);
		$args['attributes']['gbpGridColsMobile'] = array(
			'type'    => 'integer',
			'default' => 0,
		);
		return $args;
	}

	/**
	 * Inject responsive <style> block when grid layout + custom cols are set.
	 */
	public function render( $content, $block ) {
		if ( 'core/group' !== $block['blockName'] ) {
			return $content;
		}

		$attrs  = isset( $block['attrs'] ) ? $block['attrs'] : array();
		$layout = isset( $attrs['layout'] ) ? $attrs['layout'] : array();

		if ( ( $layout['type'] ?? '' ) !== 'grid' ) {
			return $content;
		}

		$cols_tablet = isset( $attrs['gbpGridColsTablet'] ) ? (int) $attrs['gbpGridColsTablet'] : 0;
		$cols_mobile = isset( $attrs['gbpGridColsMobile'] ) ? (int) $attrs['gbpGridColsMobile'] : 0;

		if ( 0 === $cols_tablet && 0 === $cols_mobile ) {
			return $content;
		}

		$uid   = 'gbp-rg-' . substr( md5( serialize( $attrs ) . uniqid() ), 0, 8 );
		$rules = '';
		// Höhere Spezifität als Theme-Regel .wp-block-group.alignwide.is-layout-grid:has(...)
		// (6 Spalten auf Tablet), damit unsere Spaltenzahl greift.
		$sel = ".wp-block-group.alignwide.is-layout-grid.{$uid}";

		// Tablet bis 1024px (gleicher Bereich wie Theme-Regel 6 Spalten), damit unsere Einstellung gewinnt
		if ( $cols_tablet > 0 ) {
			$rules .= "@media (max-width: 1024px) { {$sel} { grid-template-columns: repeat({$cols_tablet}, minmax(0, 1fr)) !important; } }";
		}
		if ( $cols_mobile > 0 ) {
			$rules .= "@media (max-width: 600px) { {$sel} { grid-template-columns: repeat({$cols_mobile}, minmax(0, 1fr)) !important; } }";
		}

		// Add unique class to first tag of content
		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag() ) {
			$processor->add_class( $uid );
		}
		$content = $processor->get_updated_html();

		return "<style>{$rules}</style>" . $content;
	}

	/**
	 * Enqueue editor sidebar controls.
	 */
	public function enqueue_editor_assets() {
		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/grid-responsive-editor.js';
		if ( ! file_exists( $js_path ) ) {
			return;
		}
		wp_enqueue_script(
			'gutenblock-pro-grid-responsive-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/grid-responsive-editor.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			(string) filemtime( $js_path ),
			true
		);
	}
}
