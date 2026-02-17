<?php
/**
 * Admin Bar Replacement - Optional feature (toggleable via Features page)
 *
 * Replaces the WordPress admin bar with a floating icon and context-aware edit links.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Admin_Bar {

	/**
	 * Initialize: hook into wp_enqueue_scripts
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
	}

	/**
	 * Load Admin Bar Replacement assets when feature is enabled and no collision with gutenblock
	 */
	public function enqueue_admin_bar_assets() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'admin-bar' ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		$css_path = GUTENBLOCK_PRO_PATH . 'assets/css/admin-bar.css';
		$js_path  = GUTENBLOCK_PRO_PATH . 'assets/js/admin-bar.js';

		wp_enqueue_style(
			'gutenblock-pro-admin-bar-css',
			GUTENBLOCK_PRO_URL . 'assets/css/admin-bar.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : GUTENBLOCK_PRO_VERSION
		);

		wp_enqueue_script(
			'gutenblock-pro-admin-bar-js',
			GUTENBLOCK_PRO_URL . 'assets/js/admin-bar.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_localize_script( 'gutenblock-pro-admin-bar-js', 'gbp_admin_bar_params', $this->get_params() );
	}

	/**
	 * Build localized params for the admin bar script
	 *
	 * @return array
	 */
	private function get_params() {
		$term_id = '';
		$term_taxonomy = '';
		if ( is_category() || is_tag() ) {
			$queried = get_queried_object();
			if ( $queried && isset( $queried->term_id ) ) {
				$term_id       = $queried->term_id;
				$term_taxonomy = $queried->taxonomy;
			}
		}

		$theme_name = get_template();
		if ( is_child_theme() ) {
			$theme_name = get_stylesheet();
		}

		return array(
			'admin_url'      => admin_url(),
			'post_id'        => get_the_ID(),
			'post_type'      => get_post_type(),
			'is_front_page'  => is_front_page(),
			'is_home_page'   => is_home(),
			'front_page_id'  => get_option( 'page_on_front' ),
			'home_page_id'   => get_option( 'page_for_posts' ),
			'site_editor_url' => function_exists( 'wp_admin_bar_site_editor_url' ) ? wp_admin_bar_site_editor_url() : '',
			'is_archive'     => is_archive(),
			'is_category'    => is_category(),
			'is_tag'         => is_tag(),
			'is_author'      => is_author(),
			'is_date'        => is_date(),
			'is_search'      => is_search(),
			'is_404'         => is_404(),
			'term_id'        => $term_id,
			'term_taxonomy'  => $term_taxonomy,
			'author_id'      => is_author() ? get_queried_object_id() : '',
			'theme_name'     => $theme_name,
		);
	}
}
