<?php
/**
 * Pattern Creator - Create patterns from selected blocks (Dev Tool)
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Pattern_Creator {

	/**
	 * Allowed usernames who can create patterns
	 */
	const ALLOWED_USERS = array( 'hjherbst' );

	/**
	 * Initialize the pattern creator
	 */
	public function init() {
		// REST endpoint for plugin images is available to all editors (not only allowed users)
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Only load for allowed users
		if ( ! $this->is_allowed_user() ) {
			return;
		}

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_ajax_gutenblock_pro_create_pattern', array( $this, 'ajax_create_pattern' ) );
		add_action( 'wp_ajax_gutenblock_pro_check_pattern', array( $this, 'ajax_check_pattern' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			'gutenblock-pro/v1',
			'/plugin-images',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_plugin_images' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * REST: Return list of images from assets/images/
	 *
	 * @return WP_REST_Response
	 */
	public function rest_plugin_images() {
		$dir        = GUTENBLOCK_PRO_PATH . 'assets/images/';
		$url_base   = GUTENBLOCK_PRO_URL  . 'assets/images/';
		$extensions = array( 'jpg', 'jpeg', 'png', 'webp', 'svg' );
		$images     = array();

		if ( ! is_dir( $dir ) ) {
			return rest_ensure_response( $images );
		}

		foreach ( glob( $dir . '*' ) as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $extensions, true ) ) {
				continue;
			}

			$basename = basename( $file );
			$name     = pathinfo( $file, PATHINFO_FILENAME );
			$width    = 0;
			$height   = 0;

			// Read dimensions from filename convention: name-WIDTHxHEIGHT.ext
			if ( preg_match( '/-(\d+)x(\d+)$/', $name, $m ) ) {
				$width  = (int) $m[1];
				$height = (int) $m[2];
			}

			$images[] = array(
				'url'    => $url_base . $basename,
				'name'   => $name,
				'width'  => $width,
				'height' => $height,
			);
		}

		// Sort alphabetically by name
		usort( $images, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

		return rest_ensure_response( $images );
	}

	/**
	 * Check if current user is allowed to create patterns
	 *
	 * @return bool
	 */
	private function is_allowed_user() {
		$current_user = wp_get_current_user();
		
		if ( ! $current_user->exists() ) {
			return false;
		}

		return in_array( $current_user->user_login, self::ALLOWED_USERS, true );
	}

	/**
	 * Enqueue editor assets
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'gutenblock-pro-pattern-creator',
			GUTENBLOCK_PRO_URL . 'assets/js/pattern-creator.js',
			array(
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-block-editor',
				'wp-blocks',
				'wp-compose',
				'wp-hooks',
			),
			GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_enqueue_style(
			'gutenblock-pro-pattern-creator',
			GUTENBLOCK_PRO_URL . 'assets/css/pattern-creator.css',
			array(),
			GUTENBLOCK_PRO_VERSION
		);

		// Get groups for dropdown
		$groups = array(
			array( 'value' => '', 'label' => __( '— Keine Gruppe —', 'gutenblock-pro' ) ),
		);
		foreach ( GutenBlock_Pro_Pattern_Loader::$groups as $slug => $label ) {
			$groups[] = array( 'value' => $slug, 'label' => $label );
		}

		// Page-Types: Ziel-Unterseite, der eine Page-Vorlage zugeordnet wird.
		// Werte korrespondieren mit dem `page_type`-Feld in pattern.php und
		// werden vom SaaS in /api/canvas/page/create gefiltert.
		$page_types = array(
			array( 'value' => '',         'label' => __( '— Keine Zuordnung (Standalone) —', 'gutenblock-pro' ) ),
			array( 'value' => 'services', 'label' => __( 'Services Page', 'gutenblock-pro' ) ),
			array( 'value' => 'about',    'label' => __( 'About Page', 'gutenblock-pro' ) ),
			array( 'value' => 'blog',     'label' => __( 'Blog Post', 'gutenblock-pro' ) ),
			array( 'value' => 'legal',    'label' => __( 'Legal / Impressum', 'gutenblock-pro' ) ),
		);

		$current_user = wp_get_current_user();
		$is_allowed_user = ( $current_user->user_login === 'hjherbst' );

		wp_localize_script( 'gutenblock-pro-pattern-creator', 'gutenblockProCreator', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'restUrl' => rest_url( 'gutenblock-pro/v1/' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'nonce'   => wp_create_nonce( 'gutenblock_pro_create_pattern' ),
			'adminNonce' => wp_create_nonce( 'gutenblock_pro_admin' ),
			'isAllowedUser' => $is_allowed_user,
			'currentLocale' => get_locale(),
			'groups'  => $groups,
			'pageTypes' => $page_types,
			'strings' => array(
				'menuLabel'        => __( 'Als GB Pro Pattern speichern', 'gutenblock-pro' ),
				'modalTitle'       => __( 'GutenBlock Pro Pattern erstellen', 'gutenblock-pro' ),
				'nameLabel'        => __( 'Pattern Name', 'gutenblock-pro' ),
				'namePlaceholder'  => __( 'Mein neues Pattern', 'gutenblock-pro' ),
				'slugLabel'        => __( 'Slug', 'gutenblock-pro' ),
				'slugHelp'         => __( 'Wird automatisch generiert', 'gutenblock-pro' ),
				'descLabel'        => __( 'Beschreibung', 'gutenblock-pro' ),
				'descPlaceholder'  => __( 'Kurze Beschreibung des Patterns', 'gutenblock-pro' ),
				'keywordsLabel'    => __( 'Keywords', 'gutenblock-pro' ),
				'keywordsPlaceholder' => __( 'hero, cta, button (kommagetrennt)', 'gutenblock-pro' ),
				'typeLabel'        => __( 'Typ', 'gutenblock-pro' ),
				'typePattern'      => __( 'Section', 'gutenblock-pro' ),
				'typePage'         => __( 'Seite', 'gutenblock-pro' ),
				'groupLabel'       => __( 'Gruppe', 'gutenblock-pro' ),
				'groupNone'        => __( '— Keine Gruppe —', 'gutenblock-pro' ),
				'pageTypeLabel'    => __( 'Ziel-Unterseite', 'gutenblock-pro' ),
				'pageTypeHelp'     => __( 'Ordnet diese Seitenvorlage einer SaaS-Unterseite zu (z. B. „Services Page“). Nur sichtbar bei Typ = Seite.', 'gutenblock-pro' ),
				'createButton'     => __( 'Pattern erstellen', 'gutenblock-pro' ),
				'updateButton'     => __( 'Content aktualisieren', 'gutenblock-pro' ),
				'updateMode'       => __( 'Bestehendes Pattern aktualisieren', 'gutenblock-pro' ),
				'updateModeHelp'   => __( 'Nur content.html wird überschrieben. CSS und JS bleiben erhalten.', 'gutenblock-pro' ),
				'patternExists'    => __( 'Pattern existiert bereits. Content wird aktualisiert.', 'gutenblock-pro' ),
				'cancelButton'     => __( 'Abbrechen', 'gutenblock-pro' ),
				'creating'         => __( 'Erstelle Pattern...', 'gutenblock-pro' ),
				'success'          => __( 'Pattern erfolgreich erstellt!', 'gutenblock-pro' ),
				'error'            => __( 'Fehler beim Erstellen des Patterns', 'gutenblock-pro' ),
				'noBlocks'         => __( 'Bitte wähle mindestens einen Block aus.', 'gutenblock-pro' ),
				'nameRequired'     => __( 'Bitte gib einen Namen ein.', 'gutenblock-pro' ),
				'aiHintLabel'      => __( 'AI Hint', 'gutenblock-pro' ),
				'aiHintPlaceholder' => __( 'Strukturelle Analyse (Layout, Hintergrundtyp, CTA-Variante, Medien)', 'gutenblock-pro' ),
				'aiSuggestButton'  => __( 'Beschreibung & AI Hint mit KI generieren (EN)', 'gutenblock-pro' ),
				'aiSuggesting'     => __( 'KI generiert…', 'gutenblock-pro' ),
				'aiSuggestError'   => __( 'KI-Vorschlag fehlgeschlagen.', 'gutenblock-pro' ),
				'aiNoBlocks'       => __( 'Bitte zuerst Blöcke auswählen.', 'gutenblock-pro' ),
				'enableTonesLabel' => __( 'Tonalitäts-Varianten anbieten (Dark + Soft)', 'gutenblock-pro' ),
				'enableTonesHelp'  => __( 'Erzeugt Dark- und Soft-Varianten dieses Patterns für FSE und SaaS.', 'gutenblock-pro' ),
				'tonesUnsupported' => __( 'Nicht möglich: Top-Block hat Bild/Gradient als Hintergrund.', 'gutenblock-pro' ),
			),
		) );
	}

	/**
	 * AJAX: Create pattern from blocks
	 */
	public function ajax_create_pattern() {
		check_ajax_referer( 'gutenblock_pro_create_pattern', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$name        = sanitize_text_field( $_POST['name'] );
		$slug        = sanitize_title( $_POST['slug'] );
		$description = sanitize_textarea_field( wp_unslash( isset( $_POST['description'] ) ? $_POST['description'] : '' ) );
		$keywords    = sanitize_text_field( isset( $_POST['keywords'] ) ? $_POST['keywords'] : '' );
		$ai_hint     = sanitize_textarea_field( wp_unslash( isset( $_POST['ai_hint'] ) ? $_POST['ai_hint'] : '' ) );
		$type        = isset( $_POST['type'] ) && in_array( $_POST['type'], array( 'pattern', 'page' ), true ) ? $_POST['type'] : 'pattern';
		$group       = isset( $_POST['group'] ) ? sanitize_key( $_POST['group'] ) : '';
		$page_type   = isset( $_POST['page_type'] ) ? sanitize_key( $_POST['page_type'] ) : '';
		$valid_page_types = array( '', 'services', 'about', 'blog', 'legal' );
		if ( ! in_array( $page_type, $valid_page_types, true ) ) {
			$page_type = '';
		}
		// page_type ergibt nur Sinn für Pages
		if ( $type !== 'page' ) {
			$page_type = '';
		}
		$premium     = isset( $_POST['premium'] ) && $_POST['premium'] === 'true';
		$enable_tones = isset( $_POST['enable_tones'] ) && $_POST['enable_tones'] === 'true';
		$content     = wp_unslash( $_POST['content'] );

		if ( empty( $name ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Name and slug are required' ) );
		}

		// Create pattern directory
		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $slug;
		$is_new_pattern = ! is_dir( $pattern_dir );
		$is_update_mode = isset( $_POST['update_mode'] ) && $_POST['update_mode'] === 'true';

		// Check if this is a new pattern or updating existing
		if ( $is_new_pattern ) {
			if ( ! wp_mkdir_p( $pattern_dir ) ) {
				wp_send_json_error( array( 'message' => 'Could not create pattern directory' ) );
			}
		}

		// Remove invalid empty-array style values that break block validation
		$content = preg_replace( '/,"color":\[\]/', '', $content );
		$content = preg_replace( '/"color":\[\],?/', '', $content );

		// Immer: mediaLink mit Site-URL entfernen (enthält sonst localhost-/Staging-URLs)
		$escaped_site = preg_quote( site_url(), '/' );
		$content = preg_replace( '/"mediaLink":"' . $escaped_site . '[^"]*",?/', '', $content );

		// Add pattern marker class to content
		$css_class = 'gb-pattern-' . $slug;
		$content = $this->add_pattern_class( $content, $css_class );

		// Normalize core/image blocks so they pass validation when pattern is inserted
		$content = GutenBlock_Pro_Pattern_Loader::normalize_core_image_blocks( $content );

		$content_filename = 'content.html';

		// Auto-Detect: bei Cover/BG-Image/Gradient kann der User keine Varianten erzwingen
		$tone_capability = GutenBlock_Pro_Tone_Injector::detect_tone_capability( $content );
		$tones_for_pattern = ( $enable_tones && $tone_capability['supported'] )
			? array( 'neutral', 'dark', 'soft' )
			: array( 'neutral' );

		if ( $is_new_pattern ) {
			// Create pattern.php and asset files for NEW patterns
			$pattern_php = $this->generate_pattern_php( $name, $description, $keywords, $type, $group, $premium, $ai_hint, $tones_for_pattern, $page_type );
			file_put_contents( $pattern_dir . '/pattern.php', $pattern_php );

			$style_css = $this->generate_style_css( $name, $slug );
			file_put_contents( $pattern_dir . '/style.css', $style_css );

			$editor_css = $this->generate_editor_css( $name, $slug );
			file_put_contents( $pattern_dir . '/editor.css', $editor_css );

			$script_js = $this->generate_script_js( $name, $slug );
			file_put_contents( $pattern_dir . '/script.js', $script_js );
		} else {
			// Update all meta fields in existing pattern.php
			$pattern_file = $pattern_dir . '/pattern.php';
			if ( file_exists( $pattern_file ) ) {
				$keywords_arr = array_values( array_filter( array_map( 'trim', explode( ',', $keywords ) ) ) );
				$this->update_all_pattern_php_fields( $pattern_file, array(
					'title'       => $name,
					'description' => $description,
					'ai_hint'     => $ai_hint,
					'type'        => $type,
					'group'       => $group,
					'page_type'   => $page_type,
					'keywords'    => $keywords_arr,
					'premium'     => $premium,
					'tones'       => $tones_for_pattern,
				) );
			}
		}

		// Create/update content.html — CSS und JS bleiben bei bestehenden Patterns erhalten
		file_put_contents( $pattern_dir . '/' . $content_filename, $content );

		// Determine success message
		if ( $is_new_pattern ) {
			$message = 'Pattern created successfully';
		} elseif ( $is_update_mode ) {
			$message = 'Pattern content updated. CSS/JS preserved.';
		} else {
			$message = 'Pattern saved';
		}

		wp_send_json_success( array(
			'message'    => $message,
			'slug'       => $slug,
			'path'       => $pattern_dir,
			'file'       => $content_filename,
			'is_update'  => ! $is_new_pattern,
		) );
	}

	/**
	 * AJAX: Check if pattern exists
	 */
	public function ajax_check_pattern() {
		check_ajax_referer( 'gutenblock_pro_create_pattern', 'nonce' );

		if ( ! $this->is_allowed_user() ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$slug = sanitize_title( $_POST['slug'] );

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'No slug provided' ) );
		}

		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $slug;
		$exists = is_dir( $pattern_dir );

		$pattern_info = array(
			'exists' => $exists,
			'slug'   => $slug,
		);

		if ( $exists ) {
			$pattern_file = function_exists( 'gutenblock_pro_resolve_pattern_php_path' )
				? gutenblock_pro_resolve_pattern_php_path( $slug )
				: $pattern_dir . '/pattern.php';
			if ( file_exists( $pattern_file ) ) {
				$pattern_data = require $pattern_file;
				$kw = isset( $pattern_data['keywords'] ) && is_array( $pattern_data['keywords'] )
					? implode( ', ', $pattern_data['keywords'] )
					: '';
				$pattern_info['title']       = isset( $pattern_data['title'] ) ? $pattern_data['title'] : $slug;
				$pattern_info['description'] = isset( $pattern_data['description'] ) ? $pattern_data['description'] : '';
				$pattern_info['ai_hint']     = isset( $pattern_data['ai_hint'] ) ? $pattern_data['ai_hint'] : '';
				$pattern_info['type']        = isset( $pattern_data['type'] ) ? $pattern_data['type'] : 'pattern';
				$pattern_info['group']       = isset( $pattern_data['group'] ) ? $pattern_data['group'] : '';
				$pattern_info['page_type']   = isset( $pattern_data['page_type'] ) ? $pattern_data['page_type'] : '';
				$pattern_info['keywords']    = $kw;
				$pattern_info['premium']     = isset( $pattern_data['premium'] ) ? (bool) $pattern_data['premium'] : false;
				$pattern_info['tones']       = isset( $pattern_data['tones'] ) && is_array( $pattern_data['tones'] ) ? $pattern_data['tones'] : array( 'neutral' );
			}
			$pattern_info['has_style']  = file_exists( $pattern_dir . '/style.css' );
			$pattern_info['has_script'] = file_exists( $pattern_dir . '/script.js' );
		}

		wp_send_json_success( $pattern_info );
	}

	/**
	 * Add pattern marker class to content
	 *
	 * @param string $content   Block content
	 * @param string $css_class CSS class to add
	 * @return string Modified content
	 */
	private function add_pattern_class( $content, $css_class ) {
		// Check if class already exists
		if ( strpos( $content, $css_class ) !== false ) {
			return $content;
		}

		// Try to add to first wp:group block
		if ( preg_match( '/<!-- wp:group \{/', $content ) ) {
			// Add to JSON attributes
			$content = preg_replace(
				'/<!-- wp:group \{/',
				'<!-- wp:group {"className":"' . $css_class . '",',
				$content,
				1
			);
			// Add to div class
			$content = preg_replace(
				'/<div class="wp-block-group/',
				'<div class="wp-block-group ' . $css_class,
				$content,
				1
			);
		} else {
			// Wrap in group block with class
			$content = '<!-- wp:group {"className":"' . $css_class . '"} -->' . "\n" .
			           '<div class="wp-block-group ' . $css_class . '">' . "\n" .
			           $content . "\n" .
			           '</div>' . "\n" .
			           '<!-- /wp:group -->';
		}

		return $content;
	}

	/**
	 * Generate pattern.php content
	 */
	/**
	 * Liest vorhandene pattern.php, merged neue Felder hinein und schreibt sie zurück.
	 *
	 * @param string $file   Pfad zur pattern.php.
	 * @param array  $fields Felder, die überschrieben werden sollen.
	 * @return bool
	 */
	private function update_all_pattern_php_fields( $file, array $fields ) {
		$data = require $file;
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data = array_merge( $data, $fields );

		$title_safe = str_replace( array( "\r", "\n", '*' ), '', (string) ( isset( $data['title'] ) ? $data['title'] : basename( dirname( $file ) ) ) );
		$export     = var_export( $data, true );
		$php        = "<?php\n/**\n * Pattern: {$title_safe}\n */\n\nreturn {$export};\n";

		return file_put_contents( $file, $php ) !== false;
	}

	private function generate_pattern_php( $name, $description, $keywords, $type = 'pattern', $group = '', $premium = false, $ai_hint = '', $tones = array( 'neutral' ), $page_type = '' ) {
		$keywords_array = array_map( 'trim', explode( ',', $keywords ) );
		$keywords_array = array_values( array_filter( $keywords_array ) );
		$keywords_safe  = array_map( 'addslashes', $keywords_array );
		$keywords_php   = "array( '" . implode( "', '", $keywords_safe ) . "' )";

		if ( empty( $keywords_array ) ) {
			$keywords_php = 'array()';
		}

		$tones_safe = array_map( 'addslashes', (array) $tones );
		$tones_php  = "array( '" . implode( "', '", $tones_safe ) . "' )";

		$name_esc    = addslashes( $name );
		$desc_esc    = addslashes( $description );
		$ai_hint_esc = addslashes( $ai_hint );
		$group_line = $group ? "\n\t'group'          => '{$group}'," : "\n\t'group'          => '',";
		$premium_line = $premium ? "\n\t'premium'        => true," : "\n\t'premium'        => false,";

		// Nur für Pages (type=page) sinnvoll. Bei type=pattern wird das
		// Feld weggelassen, damit pattern.php-Dateien sauber bleiben.
		$page_type_line = ( $type === 'page' )
			? "\n\t'page_type'      => '" . addslashes( (string) $page_type ) . "',"
			: '';

		return "<?php
/**
 * Pattern: {$name_esc}
 */

return array(
	'title'          => '{$name_esc}',
	'description'    => '{$desc_esc}',
	'type'           => '{$type}',{$page_type_line}{$group_line}
	'categories'     => array( 'gutenblock-pro' ),
	'keywords'       => {$keywords_php},
	'content'        => '',
	'ai_hint'        => '{$ai_hint_esc}',
	'tones'          => {$tones_php},
	'content_fields' => array(),{$premium_line}
);
";
	}

	/**
	 * Update a single field inside an existing pattern.php
	 */
	private function update_pattern_php_field( $file, $field, $value ) {
		$contents = file_get_contents( $file );
		if ( $contents === false ) {
			return;
		}
		$escaped = addslashes( $value );
		$pattern = "/'" . preg_quote( $field, '/' ) . "'\s*=>\s*'[^']*'/";
		if ( preg_match( $pattern, $contents ) ) {
			$contents = preg_replace( $pattern, "'{$field}' => '{$escaped}'", $contents );
		} else {
			$contents = preg_replace(
				"/('type'\s*=>.*,)/",
				"$1\n\t'group'       => '{$escaped}',",
				$contents
			);
		}
		file_put_contents( $file, $contents );
	}

	/**
	 * Generate style.css content
	 */
	private function generate_style_css( $name, $slug ) {
		return "/**
 * {$name} - Frontend Styles
 */

.gb-pattern-{$slug} {
	/* Add your styles here */
}
";
	}

	/**
	 * Generate editor.css content
	 */
	private function generate_editor_css( $name, $slug ) {
		return "/**
 * {$name} - Editor Styles
 */

.editor-styles-wrapper .gb-pattern-{$slug} {
	/* Add your editor-specific styles here */
}
";
	}

	/**
	 * Generate script.js content
	 */
	private function generate_script_js( $name, $slug ) {
		return "/**
 * {$name} - Frontend Script
 */

(function () {
	'use strict';

	function init{$this->slugToCamelCase( $slug )}() {
		const elements = document.querySelectorAll('.gb-pattern-{$slug}');
		
		if (!elements.length) return;

		elements.forEach(function (element) {
			// Add your JavaScript here
		});
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init{$this->slugToCamelCase( $slug )});
	} else {
		init{$this->slugToCamelCase( $slug )}();
	}
})();
";
	}

	/**
	 * Convert slug to CamelCase
	 */
	private function slugToCamelCase( $slug ) {
		return str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) );
	}

}

