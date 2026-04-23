<?php
/**
 * Ausrichtung – Mobile links & Raster-Zentrierung für Group, Row, Stack.
 *
 * Fügt core/group, core/row und core/stack Steuerungen hinzu:
 * - Auf Mobilgeräten (≤ 781 px) Flex- und Textausrichtung nach links erzwingen.
 * - Bei Raster-Layout: Kindelemente in der Zelle horizontal und vertikal zentrieren.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Mobile_Align {

	const ATTR                 = 'mobileAlignLeft';
	const CLASS_NAME           = 'gbp-mobile-left';
	const ATTR_GRID_CENTER     = 'gridItemsCenter';
	const CLASS_GRID_CENTER    = 'gbp-grid-items-center';
	const SUPPORTED_BLOCKS     = array( 'core/group', 'core/row', 'core/stack' );

	public function init() {
		add_filter( 'register_block_type_args', array( $this, 'register_attribute' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'apply_class' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_css' ) );
	}

	/**
	 * Attribute an den unterstützten Blocks registrieren.
	 */
	public function register_attribute( $args, $block_name ) {
		if ( ! in_array( $block_name, self::SUPPORTED_BLOCKS, true ) ) {
			return $args;
		}
		$args['attributes'] = array_merge(
			$args['attributes'] ?? array(),
			array(
				self::ATTR => array(
					'type'    => 'boolean',
					'default' => false,
				),
				self::ATTR_GRID_CENTER => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		return $args;
	}

	/**
	 * CSS-Klassen am gerenderten Block-HTML setzen.
	 */
	public function apply_class( $content, $block ) {
		if ( ! in_array( $block['blockName'], self::SUPPORTED_BLOCKS, true ) ) {
			return $content;
		}
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
		$add   = array();
		if ( ! empty( $attrs[ self::ATTR ] ) ) {
			$add[] = self::CLASS_NAME;
		}
		if ( ! empty( $attrs[ self::ATTR_GRID_CENTER ] ) ) {
			$add[] = self::CLASS_GRID_CENTER;
		}
		if ( empty( $add ) ) {
			return $content;
		}
		$prefix = implode( ' ', $add ) . ' ';
		return preg_replace( '/\bclass="/', 'class="' . $prefix, $content, 1 );
	}

	public function enqueue_css() {
		wp_register_style( 'gbp-mobile-align', false, array(), GUTENBLOCK_PRO_VERSION );
		wp_enqueue_style( 'gbp-mobile-align' );
		wp_add_inline_style( 'gbp-mobile-align', $this->get_css() );
	}

	public function enqueue_editor_css() {
		wp_add_inline_style( 'wp-edit-blocks', $this->get_css() );
	}

	public function enqueue_editor_assets() {
		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/mobile-align-editor.js';
		wp_enqueue_script(
			'gbp-mobile-align-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/mobile-align-editor.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
	}

	private function get_css() {
		return '
		@media (max-width: 781px) {
			/* Flex-Ausrichtung des Containers auf links */
			.gbp-mobile-left.is-layout-flex {
				justify-content: flex-start !important;
				align-items: flex-start !important;
			}
			/* Text-Ausrichtung innerhalb: zentriert/rechts → links */
			.gbp-mobile-left .has-text-align-center,
			.gbp-mobile-left .has-text-align-right {
				text-align: left !important;
			}
		}
		/* Raster: direkte Kindelemente in der Zelle zentrieren */
		.gbp-grid-items-center.is-layout-grid {
			justify-items: center !important;
			align-items: center !important;
		}
		';
	}
}
