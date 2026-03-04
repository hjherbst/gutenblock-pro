<?php
/**
 * Heading Text Image – applies background-clip:text styles server-side.
 *
 * PHP render_block filter is used instead of inline styles in the saved HTML,
 * because wp_kses_post strips background-image from inline styles.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Heading_Text_Image {

	/**
	 * Block names that support the text-image feature.
	 * Mirror TARGET_BLOCKS in src/blocks/heading-text-image/index.js.
	 */
	private static $target_blocks = [
		'core/heading',
		// 'gutenblock-pro/flexible-heading', // add when registered
	];

	public function init() {
		add_filter( 'render_block', array( $this, 'render_block' ), 10, 2 );
	}

	/**
	 * Inject background-clip:text inline style into the rendered heading.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block data array.
	 * @return string Modified HTML.
	 */
	public function render_block( $block_content, $block ) {
		if ( empty( $block['blockName'] ) || ! in_array( $block['blockName'], self::$target_blocks, true ) ) {
			return $block_content;
		}

		$url = isset( $block['attrs']['gbTextImageUrl'] ) ? esc_url_raw( $block['attrs']['gbTextImageUrl'] ) : '';
		if ( ! $url ) {
			return $block_content;
		}

		$size     = isset( $block['attrs']['gbTextImageSize'] ) ? sanitize_text_field( $block['attrs']['gbTextImageSize'] ) : 'cover';
		$position = isset( $block['attrs']['gbTextImagePosition'] ) ? sanitize_text_field( $block['attrs']['gbTextImagePosition'] ) : 'center center';

		$image_style = sprintf(
			'background-image: url(%s); background-clip: text; -webkit-background-clip: text; color: transparent; -webkit-text-fill-color: transparent; background-size: %s; background-position: %s; background-repeat: no-repeat;',
			esc_url( $url ),
			$size,
			$position
		);

		// Use WP_HTML_Tag_Processor (WP 6.2+) for reliable attribute injection
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
			$level = max( 1, min( 6, $level ) );

			$processor = new WP_HTML_Tag_Processor( $block_content );
			if ( $processor->next_tag( array( 'tag_name' => 'H' . $level ) ) ) {
				$existing = $processor->get_attribute( 'style' );
				$merged   = $existing ? rtrim( $existing, '; ' ) . '; ' . $image_style : $image_style;
				$processor->set_attribute( 'style', $merged );
				return $processor->get_updated_html();
			}
		}

		// Fallback regex for older WP versions
		if ( preg_match( '/<h[1-6][^>]* style="/i', $block_content ) ) {
			$block_content = preg_replace(
				'/(<h[1-6][^>]* style=")([^"]*)(")/i',
				'$1$2 ' . $image_style . '$3',
				$block_content,
				1
			);
		} else {
			$block_content = preg_replace(
				'/(<h[1-6])(\s|>)/i',
				'$1 style="' . $image_style . '"$2',
				$block_content,
				1
			);
		}

		return $block_content;
	}
}
