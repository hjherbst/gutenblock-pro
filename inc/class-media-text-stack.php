<?php
/**
 * Media Text Stack – Stapel-Optionen für core/media-text.
 *
 * Erweitert den Block "Text/Medien" um:
 * - Immer stapeln: Vertikales Layout auf allen Bildschirmgrößen
 * - Reverse stapeln: Wie "immer stapeln", aber Bild unten, Text oben
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Media_Text_Stack {

	const ATTR         = 'stackMode';
	const ATTR_LINKBOX = 'linkbox';

	public function init() {
		add_filter( 'register_block_type_args', array( $this, 'register_attribute' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'add_class' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'apply_linkbox' ), 11, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_css' ) );
	}

	public function register_attribute( $args, $name ) {
		if ( 'core/media-text' !== $name ) {
			return $args;
		}
		if ( ! isset( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes'][ self::ATTR ] = array(
			'type'    => 'string',
			'default' => '',
		);
		$args['attributes'][ self::ATTR_LINKBOX ] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		return $args;
	}

	public function add_class( $content, $block ) {
		if ( 'core/media-text' !== $block['blockName'] ) {
			return $content;
		}
		$mode = isset( $block['attrs'][ self::ATTR ] ) ? $block['attrs'][ self::ATTR ] : '';
		if ( '' === $mode || ! in_array( $mode, array( 'always', 'reverse' ), true ) ) {
			return $content;
		}
		$processor = new WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag( array( 'class_name' => 'wp-block-media-text' ) ) ) {
			$processor->add_class( 'gbp-stack-' . $mode );
		}
		return $processor->get_updated_html();
	}

	/**
	 * Linkbox: Ganzes Media-Text als Link, innere Links entfernen
	 */
	public function apply_linkbox( $content, $block ) {
		if ( 'core/media-text' !== $block['blockName'] ) {
			return $content;
		}
		if ( empty( $block['attrs'][ self::ATTR_LINKBOX ] ) ) {
			return $content;
		}
		// Erste Link-URL im Content finden
		$url = $this->extract_first_link_url( $content );
		if ( '' === $url ) {
			return $content;
		}
		// Innere <a> durch <span> ersetzen (nested links vermeiden)
		$inner = $this->strip_inner_links( $content );
		$url   = esc_url( $url );
		// Inline padding-left/right von has-global-padding entfernen, damit die CSS-Regel greift
		$inner = $this->restore_global_padding( $inner );
		// Link als Kind des media-text-Containers, nicht außen (Breiteneinstellungen erhalten)
		return preg_replace(
			'/^(\s*<div[^>]*class="[^"]*wp-block-media-text[^"]*"[^>]*>)([\s\S]+)(<\/div>\s*)$/',
			'$1<a href="' . $url . '" class="gbp-media-text-linkbox">$2</a>$3',
			$inner,
			1
		);
	}

	private function extract_first_link_url( $html ) {
		if ( preg_match( '/<a\s+[^>]*href=(["\'])([^"\']+)\1/', $html, $m ) ) {
			return $m[2];
		}
		return '';
	}

	/**
	 * Entfernt padding-left und padding-right aus inline-Styles von .has-global-padding,
	 * damit die CSS-Regel mit --wp--style--root--padding-* greift.
	 */
	private function restore_global_padding( $html ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( array( 'class_name' => 'has-global-padding' ) ) ) {
			$style = $processor->get_attribute( 'style' );
			if ( ! $style ) {
				continue;
			}
			$style = preg_replace( '/\s*padding-left\s*:[^;]*;?/i', '', $style );
			$style = preg_replace( '/\s*padding-right\s*:[^;]*;?/i', '', $style );
			$style = trim( $style, " ;" );
			if ( '' === $style ) {
				$processor->remove_attribute( 'style' );
			} else {
				$processor->set_attribute( 'style', $style );
			}
		}
		return $processor->get_updated_html();
	}

	private function strip_inner_links( $html ) {
		return preg_replace_callback(
			'/<a\s+([^>]*)>([\s\S]*?)<\/a>/i',
			function ( $m ) {
				$attrs   = $m[1];
				$classes = array( 'gbp-linkbox-inner' );
				// Button-Links: wp-block-button__link erhalten, damit Stilvarianten greifen
				if ( preg_match( '/class\s*=\s*["\']([^"\']+)["\']/', $attrs, $class_m ) ) {
					$orig_classes = explode( ' ', $class_m[1] );
					foreach ( array( 'wp-block-button__link', 'wp-element-button' ) as $keep ) {
						if ( in_array( $keep, $orig_classes, true ) ) {
							$classes[] = $keep;
						}
					}
				}
				return '<span class="' . implode( ' ', $classes ) . '">' . $m[2] . '</span>';
			},
			$html
		);
	}

	public function enqueue_editor_assets() {
		$js_path = GUTENBLOCK_PRO_PATH . 'assets/js/media-text-stack-editor.js';
		wp_enqueue_script(
			'gutenblock-pro-media-text-stack-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/media-text-stack-editor.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
	}

	public function enqueue_css() {
		$css = $this->get_css();
		if ( '' === $css ) {
			return;
		}
		wp_register_style( 'gutenblock-pro-media-text-stack', false, array() );
		wp_enqueue_style( 'gutenblock-pro-media-text-stack' );
		wp_add_inline_style( 'gutenblock-pro-media-text-stack', $css );
	}

	public function enqueue_editor_css() {
		$css = $this->get_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'wp-edit-blocks', $css );
		}
	}

	public static function get_styles() {
		$instance = new self();
		return $instance->get_css();
	}

	private function get_css() {
		$frontend = '
			/* Immer stapeln: vertikal auf allen Breakpoints */
			.wp-block-media-text.gbp-stack-always {
				grid-template-columns: 100% !important;
			}
			.wp-block-media-text.gbp-stack-always > .wp-block-media-text__media {
				grid-column: 1;
				grid-row: 1;
			}
			.wp-block-media-text.gbp-stack-always > .wp-block-media-text__content {
				grid-column: 1;
				grid-row: 2;
			}

			/* Reverse stapeln: Text oben, Bild unten, alle Breakpoints */
			.wp-block-media-text.gbp-stack-reverse {
				grid-template-columns: 100% !important;
			}
			.wp-block-media-text.gbp-stack-reverse > .wp-block-media-text__media {
				grid-column: 1;
				grid-row: 2;
			}
			.wp-block-media-text.gbp-stack-reverse > .wp-block-media-text__content {
				grid-column: 1;
				grid-row: 1;
			}
			/* Stapel + Linkbox: Spezifität sichern via !important, da has-media-on-the-right
			   gleich spezifisch ist und sonst gewinnt (später deklariert im editor-Block) */
			.wp-block-media-text.gbp-stack-always > .gbp-media-text-linkbox > .wp-block-media-text__media {
				grid-column: 1 !important;
				grid-row: 1 !important;
			}
			.wp-block-media-text.gbp-stack-always > .gbp-media-text-linkbox > .wp-block-media-text__content {
				grid-column: 1 !important;
				grid-row: 2 !important;
			}
			.wp-block-media-text.gbp-stack-reverse > .gbp-media-text-linkbox > .wp-block-media-text__media {
				grid-column: 1 !important;
				grid-row: 2 !important;
			}
			.wp-block-media-text.gbp-stack-reverse > .gbp-media-text-linkbox > .wp-block-media-text__content {
				grid-column: 1 !important;
				grid-row: 1 !important;
			}
			/* Mobile-Fix: is-image-fill-element in gestapelter Reihe – height:100% auf figure löst zu
			   0 auf (auto-Grid-Reihe ohne andere Content-Quelle), daher img position:absolute unsichtbar.
			   Gilt für Core is-stacked-on-mobile sowie für unsere Stack-Modi + Linkbox.
			   !important nötig, da has-media-on-the-right > .gbp-media-text-linkbox > __media { grid-column:2 }
			   (gleiche Spezifität, später deklariert) die figure sonst in die 0px-Spalte schiebt. */
			@media (max-width: 600px) {
				.wp-block-media-text.is-stacked-on-mobile.is-image-fill-element > .wp-block-media-text__media,
				.wp-block-media-text.is-stacked-on-mobile.is-image-fill-element > .gbp-media-text-linkbox > .wp-block-media-text__media {
					height: 250px;
					grid-column: 1 !important;
					grid-row: 1 !important;
				}
				.wp-block-media-text.is-stacked-on-mobile > .wp-block-media-text__content,
				.wp-block-media-text.is-stacked-on-mobile > .gbp-media-text-linkbox > .wp-block-media-text__content {
					grid-column: 1 !important;
					grid-row: 2 !important;
				}
			}
		';
		$editor = '
			/* Editor: Klasse sitzt am BlockListBlock-Wrapper, innere .wp-block-media-text ansprechen */
			.gbp-stack-always .wp-block-media-text,
			.gbp-stack-reverse .wp-block-media-text {
				grid-template-columns: 100% !important;
			}
			.gbp-stack-always .wp-block-media-text > .wp-block-media-text__media {
				grid-column: 1;
				grid-row: 1;
			}
			.gbp-stack-always .wp-block-media-text > .wp-block-media-text__content {
				grid-column: 1;
				grid-row: 2;
			}
			.gbp-stack-reverse .wp-block-media-text > .wp-block-media-text__media {
				grid-column: 1;
				grid-row: 2;
			}
			.gbp-stack-reverse .wp-block-media-text > .wp-block-media-text__content {
				grid-column: 1;
				grid-row: 1;
			}

			/* Linkbox: ganzer Container als Link (display:contents = Layout unverändert) */
			.gbp-media-text-linkbox {
				display: contents;
				text-decoration: none;
				color: inherit;
			}
			.gbp-media-text-linkbox:hover {
				text-decoration: none;
				color: inherit;
			}
			/* __content + __media: Core nutzt ">" (direct child), greift bei Linkbox nicht – Overrides */
			.wp-block-media-text > .gbp-media-text-linkbox > .wp-block-media-text__content {
				padding: 0 8%;
				grid-column: 2;
				grid-row: 1;
			}
			.wp-block-media-text.has-media-on-the-right > .gbp-media-text-linkbox > .wp-block-media-text__content {
				grid-column: 1;
				grid-row: 1;
			}
			.wp-block-media-text > .gbp-media-text-linkbox > .wp-block-media-text__media {
				margin: 0;
				grid-column: 1;
				grid-row: 1;
			}
			.wp-block-media-text.has-media-on-the-right > .gbp-media-text-linkbox > .wp-block-media-text__media {
				grid-column: 2;
				grid-row: 1;
			}
			.wp-block-media-text.is-image-fill-element > .gbp-media-text-linkbox > .wp-block-media-text__media {
				position: relative;
				height: 100%;
				min-height: 250px;
			}
			.wp-block-media-text.is-image-fill-element > .gbp-media-text-linkbox > .wp-block-media-text__media img {
				position: absolute;
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			.gbp-linkbox-inner {
				display: inline;
			}
		';
		return $frontend . $editor;
	}
}
