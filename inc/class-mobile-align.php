<?php
/**
 * Mobile Ausrichtung – "Links ausrichten (Mobil)" für Group, Row, Stack.
 *
 * Fügt core/group, core/row und core/stack einen Toggle hinzu, der auf Mobilgeräten
 * (≤ 781 px) die Flex-Ausrichtung und Text-Ausrichtung nach links erzwingt.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Mobile_Align {

	const ATTR            = 'mobileAlignLeft';
	const CLASS_NAME      = 'gbp-mobile-left';
	const SUPPORTED_BLOCKS = array( 'core/group', 'core/row', 'core/stack' );

	public function init() {
		add_filter( 'register_block_type_args', array( $this, 'register_attribute' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'apply_class' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_css' ) );
	}

	/**
	 * Attribut mobileAlignLeft an den unterstützten Blocks registrieren.
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
			)
		);
		return $args;
	}

	/**
	 * CSS-Klasse gbp-mobile-left auf dem gerenderten Block-HTML setzen.
	 */
	public function apply_class( $content, $block ) {
		if ( ! in_array( $block['blockName'], self::SUPPORTED_BLOCKS, true ) ) {
			return $content;
		}
		if ( empty( $block['attrs'][ self::ATTR ] ) ) {
			return $content;
		}
		// Klasse zum ersten class="…" im HTML hinzufügen
		return preg_replace( '/\bclass="/', 'class="' . self::CLASS_NAME . ' ', $content, 1 );
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
		';
	}
}
