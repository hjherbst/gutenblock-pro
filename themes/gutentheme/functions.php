<?php
/**
 * GutenTheme functions.
 *
 * This is a thin block theme shipped with the patterns.gutenblock.com
 * WordPress instance. All real site-builder features come from the
 * GutenBlock Pro plugin, so this file deliberately stays minimal:
 *
 *  1. Standard block-theme setup (theme support).
 *  2. Frontend + editor stylesheets.
 *  3. Headless CMS bridge for the GutenBlock SaaS: a custom post type
 *     `gbp_content`, exposed via the REST API at `content-pages`.
 *
 * The plugin gutenblock-pro must work on ANY block theme, so anything
 * that is required for pattern functionality lives in the plugin —
 * not here. This file is intentionally instance-specific.
 *
 * @package GutenTheme
 */

/* -------------------------------------------------------------------------
 * 1. Block-theme setup
 * --------------------------------------------------------------------- */

add_action( 'after_setup_theme', function() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'post-thumbnails' );
	add_editor_style( 'css/gutenblock-custom-styles.css' );
} );

/* -------------------------------------------------------------------------
 * 2. Stylesheets
 * --------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'gutentheme-style',
		get_stylesheet_directory_uri() . '/style.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
} );

// Editor-only helpers (visited-link colors, header/footer link reset,
// grid container fix for media-text). Frontend styling for the patterns
// comes from the plugin's per-pattern CSS.
add_action( 'enqueue_block_editor_assets', function() {
	wp_enqueue_style(
		'gutentheme-editor-fixes',
		get_stylesheet_directory_uri() . '/css/gutenblock-custom-styles.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
} );

/* -------------------------------------------------------------------------
 * 3. Headless CMS bridge for the GutenBlock SaaS
 *
 * Custom post type `gbp_content` (REST base: `content-pages`) authored in
 * WordPress and consumed by the Next.js SaaS as marketing content
 * (About, Impressum, Datenschutz, Blog, …).
 *
 * REST examples:
 *   GET /wp-json/wp/v2/content-pages?slug=about&_embed=wp:featuredmedia
 *   GET /wp-json/wp/v2/content-pages?per_page=100&_fields=slug,modified_gmt
 *
 * Fields consumed by the SaaS:
 *   - title.rendered            → <h1> / og:title fallback
 *   - content.rendered          → main HTML body (Gutenberg blocks)
 *   - excerpt.rendered          → meta description / og:description
 *   - featured_media (_embed)   → og:image
 *   - slug                      → URL slug on the SaaS
 *   - modified_gmt              → ISR cache key / sitemap lastmod
 * --------------------------------------------------------------------- */

add_action( 'init', function() {
	register_post_type(
		'gbp_content',
		array(
			'labels'              => array(
				'name'                  => 'Content Pages',
				'singular_name'         => 'Content Page',
				'menu_name'             => 'Content Pages',
				'name_admin_bar'        => 'Content Page',
				'add_new'               => 'Neue Seite',
				'add_new_item'          => 'Neue Content-Seite anlegen',
				'new_item'              => 'Neue Content-Seite',
				'edit_item'             => 'Content-Seite bearbeiten',
				'view_item'             => 'Content-Seite ansehen',
				'all_items'             => 'Content-Seiten',
				'search_items'          => 'Content-Seiten durchsuchen',
				'not_found'             => 'Keine Content-Seiten gefunden.',
				'not_found_in_trash'    => 'Keine Content-Seiten im Papierkorb.',
				'featured_image'        => 'OG-Bild',
				'set_featured_image'    => 'OG-Bild festlegen',
				'remove_featured_image' => 'OG-Bild entfernen',
				'use_featured_image'    => 'Als OG-Bild verwenden',
				'item_published'        => 'Content-Seite veröffentlicht.',
				'item_updated'          => 'Content-Seite aktualisiert.',
			),
			'description'         => 'Inhaltsseiten (Über, Impressum, Blog, …) für die GutenBlock-SaaS-Site. Werden über die REST-API headless ausgespielt.',
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'rest_base'           => 'content-pages',
			'menu_icon'           => 'dashicons-media-document',
			'menu_position'       => 22,
			'hierarchical'        => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'supports'            => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
				'author',
				'custom-fields',
			),
			// The local "View" URL is only used as an editor-side preview;
			// the canonical public URL lives on the SaaS (see permalink filter).
			'rewrite'             => array(
				'slug'       => 'content',
				'with_front' => false,
			),
			'template'            => array(
				array( 'core/heading', array( 'level' => 1, 'placeholder' => 'Seitentitel als H1' ) ),
				array( 'core/paragraph', array( 'placeholder' => 'Einleitungstext …' ) ),
			),
		)
	);
} );

/**
 * Public SaaS path for a `gbp_content` post (path only, no host).
 * Docs hub children live under `/docs/{slug}`; everything else is `/{slug}`.
 */
function gutenblock_gbp_content_saas_path( $post ) {
	if ( ! $post || 'gbp_content' !== $post->post_type ) {
		return '';
	}
	$slug = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
	if ( ! $slug ) {
		return '';
	}
	$docs_hub = get_page_by_path( 'docs', OBJECT, 'gbp_content' );
	if ( $docs_hub && (int) $post->post_parent === (int) $docs_hub->ID ) {
		return '/docs/' . $slug;
	}
	return '/' . $slug;
}

/**
 * Make the "View"/"Vorschau" link in the editor point at the SaaS URL
 * instead of the local /content/{slug}.
 * Base URL is overridable via the `GUTENBLOCK_SAAS_PUBLIC_URL` constant in
 * wp-config.php (e.g. to point a staging WP at a preview SaaS deployment).
 */
add_filter( 'post_type_link', function( $url, $post ) {
	if ( ! $post || 'gbp_content' !== $post->post_type ) {
		return $url;
	}
	$path = gutenblock_gbp_content_saas_path( $post );
	if ( ! $path ) {
		return $url;
	}
	$base = defined( 'GUTENBLOCK_SAAS_PUBLIC_URL' )
		? rtrim( (string) GUTENBLOCK_SAAS_PUBLIC_URL, '/' )
		: 'https://gutenblock.com';
	return $base . $path;
}, 10, 2 );

/**
 * Show the slug in the content-pages list view — the slug is the public URL
 * on the SaaS and the primary lookup key for the REST API.
 */
add_filter( 'manage_gbp_content_posts_columns', function( $columns ) {
	$reordered = array();
	foreach ( $columns as $key => $label ) {
		$reordered[ $key ] = $label;
		if ( 'title' === $key ) {
			$reordered['gbp_slug'] = 'Slug';
		}
	}
	return $reordered;
} );

add_action( 'manage_gbp_content_posts_custom_column', function( $column, $post_id ) {
	if ( 'gbp_slug' !== $column ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$path = gutenblock_gbp_content_saas_path( $post );
	if ( ! $path ) {
		echo '—';
		return;
	}
	echo '<code>' . esc_html( $path ) . '</code>';
}, 10, 2 );

/**
 * SEO meta fields (title + description) for the `gbp_content` CPT.
 *
 * Exposed via REST so the SaaS can read them in `generateMetadata` and
 * fall back to the post title / excerpt when the editor leaves them
 * blank. Edited through a small Gutenberg sidebar panel registered
 * below — no separate SEO plugin needed for the handful of marketing
 * pages we author headlessly.
 */
add_action( 'init', function() {
	$auth_callback = function() {
		return current_user_can( 'edit_posts' );
	};
	$shared_args = array(
		'object_subtype'    => 'gbp_content',
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => $auth_callback,
	);
	register_post_meta( 'gbp_content', '_meta_title', $shared_args );
	register_post_meta( 'gbp_content', '_meta_description', array_merge( $shared_args, array(
		'sanitize_callback' => function( $v ) {
			return sanitize_textarea_field( (string) $v );
		},
	) ) );
} );

/**
 * Enqueue the Gutenberg sidebar panel that surfaces the two SEO meta
 * fields when editing a `gbp_content` post. Pure block-editor JS so we
 * don't pull in a classic-editor meta-box.
 */
add_action( 'enqueue_block_editor_assets', function() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'gbp_content' !== $screen->post_type ) {
		return;
	}
	$rel  = '/js/cms-seo-panel.js';
	$path = get_stylesheet_directory() . $rel;
	if ( ! file_exists( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'gutentheme-cms-seo-panel',
		get_stylesheet_directory_uri() . $rel,
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-core-data' ),
		(string) filemtime( $path ),
		true
	);
	wp_set_script_translations( 'gutentheme-cms-seo-panel', 'gutentheme' );
} );

/* -------------------------------------------------------------------------
 * 4. On-demand revalidation webhook to the GutenBlock SaaS
 *
 * Whenever a `gbp_content` post is saved, trashed, or untrashed, we
 * notify the Next.js SaaS so it can drop its ISR cache for that slug
 * and the sitemap (otherwise editors would wait up to 5 minutes for
 * their changes to appear).
 *
 * Configuration (wp-config.php constants, optional):
 *   - GUTENBLOCK_SAAS_PUBLIC_URL  — defaults to https://gutenblock.com
 *   - GUTENBLOCK_CMS_WEBHOOK_SECRET — shared secret, must match the
 *                                     CMS_REVALIDATE_SECRET env on the SaaS
 *
 * The webhook is non-blocking (`blocking => false`); editors never wait
 * for the HTTP roundtrip. Failures are silent — the next ISR cycle
 * (5 minutes) is the safety net.
 * --------------------------------------------------------------------- */

if ( ! function_exists( 'gutenblock_notify_saas_revalidate' ) ) {
	function gutenblock_notify_saas_revalidate( $slug, $event, $previous_slug = null ) {
		if ( ! defined( 'GUTENBLOCK_CMS_WEBHOOK_SECRET' ) || ! GUTENBLOCK_CMS_WEBHOOK_SECRET ) {
			return;
		}
		$base = defined( 'GUTENBLOCK_SAAS_PUBLIC_URL' )
			? rtrim( (string) GUTENBLOCK_SAAS_PUBLIC_URL, '/' )
			: 'https://gutenblock.com';
		$url  = $base . '/api/cms/revalidate';

		$body = array(
			'slug'         => $slug ? (string) $slug : null,
			'previousSlug' => $previous_slug ? (string) $previous_slug : null,
			'event'        => $event,
		);

		wp_remote_post( $url, array(
			'timeout'  => 2,
			'blocking' => false,
			'headers'  => array(
				'Content-Type'              => 'application/json',
				'X-CMS-Revalidate-Secret'   => GUTENBLOCK_CMS_WEBHOOK_SECRET,
			),
			'body'     => wp_json_encode( $body ),
		) );
	}
}

/**
 * Capture the slug *before* it is rewritten on save, so we can invalidate
 * the previous URL when an editor renames a post.
 */
add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
	if ( empty( $postarr['ID'] ) || empty( $data['post_type'] ) || 'gbp_content' !== $data['post_type'] ) {
		return $data;
	}
	$existing = get_post( (int) $postarr['ID'] );
	if ( $existing && $existing->post_name ) {
		$GLOBALS['gutenblock_previous_slug_' . $postarr['ID']] = $existing->post_name;
	}
	return $data;
}, 10, 2 );

/**
 * Fire revalidation on save (after WP has assigned the final slug).
 * Skips autosaves, revisions, and unpublished states.
 */
add_action( 'save_post_gbp_content', function( $post_id, $post, $update ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		// Draft -> draft transitions are ignored. Publish-to-trash is handled
		// by the `trashed_post` hook below.
		return;
	}
	$previous = $GLOBALS[ 'gutenblock_previous_slug_' . $post_id ] ?? null;
	unset( $GLOBALS[ 'gutenblock_previous_slug_' . $post_id ] );
	gutenblock_notify_saas_revalidate( $post->post_name, $update ? 'saved' : 'saved', $previous );
}, 10, 3 );

add_action( 'trashed_post', function( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'gbp_content' !== $post->post_type ) {
		return;
	}
	gutenblock_notify_saas_revalidate( $post->post_name, 'trashed' );
} );

add_action( 'untrashed_post', function( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'gbp_content' !== $post->post_type ) {
		return;
	}
	gutenblock_notify_saas_revalidate( $post->post_name, 'saved' );
} );

add_action( 'before_delete_post', function( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'gbp_content' !== $post->post_type ) {
		return;
	}
	gutenblock_notify_saas_revalidate( $post->post_name, 'deleted' );
} );

/* -------------------------------------------------------------------------
 * 5. Global stylesheet bridge for the headless SaaS
 *
 * Exposes the WordPress core block-library CSS and the theme.json-generated
 * global stylesheet (`wp_get_global_stylesheet()`) at one REST endpoint,
 * so the Next.js side can inline them when rendering CMS pages. Without
 * these, blocks like `wp-block-columns is-layout-flex`, `alignwide`,
 * `has-tertiary-background-color` and the `--wp--preset--*` CSS variables
 * have no styling and break visually.
 *
 * Cache: the Next.js client caches the response for 5 minutes (matches
 * the page ISR), and our save-post webhook (function 4 above) revalidates
 * the cms:all tag — which also drops the cached stylesheet.
 *
 * GET /wp-json/gutenblock/v1/cms/global-styles
 * → { "globalCss": "...", "blockLibraryCss": "...", "version": <int> }
 * --------------------------------------------------------------------- */

add_action( 'rest_api_init', function() {
	register_rest_route( 'gutenblock/v1', '/cms/global-styles', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function() {
			// Only ship CSS variables and preset utility classes from theme.json.
			// We DELIBERATELY skip the `styles` slice (global element selectors
			// like `a {}`, `h1 {}`) because it would leak into the SaaS chrome
			// (header, footer) when inlined on the Next.js side. The CMS
			// content area gets its typography from the SaaS' own globals.css.
			$global_css = '';
			if ( function_exists( 'wp_get_global_stylesheet' ) ) {
				$global_css = (string) wp_get_global_stylesheet( array( 'variables', 'presets' ) );
			}

			$block_library_path = ABSPATH . WPINC . '/css/dist/block-library/style.min.css';
			$block_css = file_exists( $block_library_path )
				? (string) file_get_contents( $block_library_path )
				: '';

			// Plugin-side block-variant CSS (e.g. is-style-space-between,
			// is-style-checkmark-list, is-style-step-circle). GutenBlock Pro
			// registers these via `wp_enqueue_block_style()` for the WP
			// frontend, but they never reach the headless SaaS because we
			// don't run `wp_head()` in the REST context. We collect them
			// from the filesystem here so any block variant used in a
			// content page renders identically on the SaaS.
			$plugin_css      = '';
			$plugin_css_size = 0;
			$plugin_blocks_dir = WP_PLUGIN_DIR . '/gutenblock-pro/blocks/';
			if ( is_dir( $plugin_blocks_dir ) ) {
				$variant_files = glob( $plugin_blocks_dir . '*/style.css' );
				foreach ( (array) $variant_files as $css_file ) {
					$plugin_css     .= "\n/* gutenblock-pro/blocks/" . basename( dirname( $css_file ) ) . " */\n";
					$plugin_css     .= (string) file_get_contents( $css_file );
					$plugin_css_size = max( $plugin_css_size, (int) filemtime( $css_file ) );
				}
			}

			$version = (int) ( file_exists( $block_library_path ) ? filemtime( $block_library_path ) : 0 );
			$version += crc32( $global_css );
			$version += $plugin_css_size;

			return new WP_REST_Response( array(
				'globalCss'        => $global_css,
				'blockLibraryCss'  => $block_css,
				'pluginCss'        => $plugin_css,
				'version'          => $version,
			) );
		},
	) );

	/**
	 * Render a single `gbp_content` post AND collect the per-page inline
	 * styles that WordPress' Style Engine generates while rendering blocks
	 * (e.g. `.wp-container-core-columns-is-layout-15e8c587 { flex-wrap: wrap; gap: 2em; }`).
	 *
	 * The default `wp/v2/content-pages` endpoint returns the rendered HTML
	 * but NOT these dynamic style rules — without them columns collapse,
	 * spacing is gone, and `is-layout-flex` containers look broken.
	 *
	 * GET /wp-json/gutenblock/v1/cms/page?slug=about
	 * → {
	 *     id, slug, title, contentHtml, excerptHtml, modifiedGmt,
	 *     featuredImage: { url, alt, width, height } | null,
	 *     inlineStyles: ".wp-container-... { ... } …"
	 *   }
	 */
	register_rest_route( 'gutenblock/v1', '/cms/page', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'slug' => array( 'required' => true, 'type' => 'string' ),
		),
		'callback'            => function( $request ) {
			$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
			if ( ! $slug ) {
				return new WP_Error( 'invalid_slug', 'Missing slug', array( 'status' => 400 ) );
			}

			$query = new WP_Query( array(
				'post_type'      => 'gbp_content',
				'name'           => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			) );

			if ( ! $query->have_posts() ) {
				return new WP_Error( 'not_found', 'Page not found', array( 'status' => 404 ) );
			}

			$query->the_post();
			$post = get_post();

			// Render content. This populates the Style Engine `block-supports`
			// store with all per-block layout rules.
			$content_html = (string) apply_filters( 'the_content', $post->post_content );

			$inline_styles = '';
			if ( function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
				$inline_styles = (string) wp_style_engine_get_stylesheet_from_context(
					'block-supports',
					array( 'optimize' => false, 'prettify' => false )
				);
			}

			$thumbnail_id = get_post_thumbnail_id( $post );
			$featured     = null;
			if ( $thumbnail_id ) {
				$src = wp_get_attachment_image_src( $thumbnail_id, 'full' );
				if ( $src ) {
					$featured = array(
						'url'    => $src[0],
						'width'  => isset( $src[1] ) ? (int) $src[1] : null,
						'height' => isset( $src[2] ) ? (int) $src[2] : null,
						'alt'    => (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
					);
				}
			}

			$response = array(
				'id'              => (int) $post->ID,
				'slug'            => (string) $post->post_name,
				'title'           => (string) wp_strip_all_tags( get_the_title( $post ) ),
				'contentHtml'     => $content_html,
				'excerptHtml'     => (string) apply_filters( 'the_excerpt', get_the_excerpt( $post ) ),
				'modifiedGmt'     => (string) $post->post_modified_gmt,
				'featuredImage'   => $featured,
				'inlineStyles'    => $inline_styles,
				// SEO meta authored via the Gutenberg sidebar panel
				// (see register_post_meta + js/cms-seo-panel.js above).
				// Empty strings are returned as-is so the SaaS can fall
				// back to title / excerpt deterministically.
				'metaTitle'       => (string) get_post_meta( $post->ID, '_meta_title', true ),
				'metaDescription' => (string) get_post_meta( $post->ID, '_meta_description', true ),
			);

			wp_reset_postdata();
			return new WP_REST_Response( $response );
		},
	) );
} );
