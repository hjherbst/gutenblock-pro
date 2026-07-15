<?php
/**
 * Reiter (Tabs) Block – Container-Block mit umschaltbaren Reitern.
 *
 * Besteht aus zwei Blöcken:
 * - gutenblock-pro/tabs : Container, rendert Tab-Navigation + Panels (dynamisch).
 * - gutenblock-pro/tab  : Einzelner Reiter (Panel) mit beliebigen InnerBlocks.
 *
 * Der Container wird serverseitig gerendert, damit die Tab-Beschriftungen immer
 * aus den Kind-Attributen stammen und ARIA-IDs sauber verknüpft werden.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Tabs {

	/**
	 * Base directory of the block sources.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Base URL of the block sources.
	 *
	 * @var string
	 */
	private $url;

	public function __construct() {
		$this->dir = GUTENBLOCK_PRO_PATH . 'blocks-tabs/';
		$this->url = GUTENBLOCK_PRO_URL . 'blocks-tabs/';
	}

	/**
	 * Hook into WordPress.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register scripts, styles and both block types.
	 */
	public function register() {
		$this->register_assets();

		if ( file_exists( $this->dir . 'tabs/block.json' ) ) {
			register_block_type(
				$this->dir . 'tabs',
				array( 'render_callback' => array( $this, 'render_tabs' ) )
			);
		}

		if ( file_exists( $this->dir . 'tab/block.json' ) ) {
			// The child produces no standalone markup; the container renders its
			// inner blocks. Returning '' avoids duplicate output.
			register_block_type(
				$this->dir . 'tab',
				array( 'render_callback' => '__return_empty_string' )
			);
		}
	}

	/**
	 * Register the shared handles referenced from block.json.
	 */
	private function register_assets() {
		$editor_js  = $this->dir . 'editor.js';
		$view_js    = $this->dir . 'frontend.js';
		$style_css  = $this->dir . 'style.css';
		$editor_css = $this->dir . 'editor.css';

		if ( file_exists( $editor_js ) ) {
			wp_register_script(
				'gutenblock-pro-tabs-editor',
				$this->url . 'editor.js',
				array(
					'wp-blocks',
					'wp-block-editor',
					'wp-element',
					'wp-components',
					'wp-data',
					'wp-i18n',
				),
				filemtime( $editor_js ),
				true
			);
			wp_set_script_translations( 'gutenblock-pro-tabs-editor', 'gutenblock-pro' );
		}

		if ( file_exists( $view_js ) ) {
			wp_register_script(
				'gutenblock-pro-tabs-view',
				$this->url . 'frontend.js',
				array(),
				filemtime( $view_js ),
				true
			);
		}

		if ( file_exists( $style_css ) ) {
			wp_register_style(
				'gutenblock-pro-tabs',
				$this->url . 'style.css',
				array(),
				filemtime( $style_css )
			);
		}

		if ( file_exists( $editor_css ) ) {
			wp_register_style(
				'gutenblock-pro-tabs-editor',
				$this->url . 'editor.css',
				array(),
				filemtime( $editor_css )
			);
		}
	}

	/**
	 * Server-side render for the tabs container.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Rendered inner blocks (unused – we build manually).
	 * @param WP_Block $block      Block instance with inner_blocks.
	 * @return string
	 */
	public function render_tabs( $attributes, $content, $block ) {
		if ( empty( $block->inner_blocks ) || 0 === count( $block->inner_blocks ) ) {
			return '';
		}

		$count = count( $block->inner_blocks );

		$active_index = isset( $attributes['activeTab'] ) ? (int) $attributes['activeTab'] : 0;
		if ( $active_index < 0 || $active_index >= $count ) {
			$active_index = 0;
		}

		// Collect titles up front so each panel can reference the next tab.
		$titles = array();
		$i      = 0;
		foreach ( $block->inner_blocks as $tab ) {
			if ( isset( $tab->attributes['title'] ) && '' !== trim( (string) $tab->attributes['title'] ) ) {
				$titles[ $i ] = $tab->attributes['title'];
			} else {
				/* translators: %d: tab number. */
				$titles[ $i ] = sprintf( __( 'Reiter %d', 'gutenblock-pro' ), $i + 1 );
			}
			$i++;
		}

		$uid       = wp_unique_id( 'gbp-tabs-' );
		$tabs_html = '';
		$panels    = '';
		$i         = 0;

		foreach ( $block->inner_blocks as $tab ) {
			$title     = $titles[ $i ];
			$is_active = ( $i === $active_index );
			$tab_id    = $uid . '-tab-' . $i;
			$panel_id  = $uid . '-panel-' . $i;

			$tabs_html .= sprintf(
				'<button type="button" class="gbp-tabs__tab%1$s" id="%2$s" role="tab" aria-controls="%3$s" aria-selected="%4$s" tabindex="%5$s">%6$s</button>',
				$is_active ? ' is-active' : '',
				esc_attr( $tab_id ),
				esc_attr( $panel_id ),
				$is_active ? 'true' : 'false',
				$is_active ? '0' : '-1',
				esc_html( $title )
			);

			$panel_content = '';
			if ( ! empty( $tab->inner_blocks ) ) {
				foreach ( $tab->inner_blocks as $inner ) {
					$panel_content .= $inner->render();
				}
			}

			// "Next tab" link at the bottom of every panel except the last.
			// Mainly a mobile affordance (hidden on desktop via CSS) so it is
			// obvious that more content is reachable via the tabs.
			if ( $i < $count - 1 ) {
				$next_index = $i + 1;
				$panel_content .= sprintf(
					'<button type="button" class="gbp-tabs__next" data-gbp-tab-target="%1$d"><span class="gbp-tabs__next-label">%2$s</span><span class="gbp-tabs__next-title">%3$s</span></button>',
					$next_index,
					esc_html__( 'Nächster Reiter', 'gutenblock-pro' ),
					esc_html( $titles[ $next_index ] )
				);
			}

			$panels .= sprintf(
				'<div class="gbp-tabs__panel%1$s" id="%2$s" role="tabpanel" aria-labelledby="%3$s" tabindex="0"%4$s>%5$s</div>',
				$is_active ? ' is-active' : '',
				esc_attr( $panel_id ),
				esc_attr( $tab_id ),
				$is_active ? '' : ' hidden',
				$panel_content
			);

			$i++;
		}

		$classes = 'gbp-tabs';
		if ( ! empty( $attributes['showNextOnDesktop'] ) ) {
			$classes .= ' has-next-desktop';
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => $classes ) );

		return sprintf(
			'<div %1$s><div class="gbp-tabs__nav" role="tablist" aria-label="%2$s">%3$s</div><div class="gbp-tabs__panels">%4$s</div></div>',
			$wrapper,
			esc_attr__( 'Reiter', 'gutenblock-pro' ),
			$tabs_html,
			$panels
		);
	}
}
