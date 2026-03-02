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
		// Only load for allowed users
		if ( ! $this->is_allowed_user() ) {
			return;
		}

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_ajax_gutenblock_pro_create_pattern', array( $this, 'ajax_create_pattern' ) );
		add_action( 'wp_ajax_gutenblock_pro_check_pattern', array( $this, 'ajax_check_pattern' ) );
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

		$pexels_api_key = get_option( 'gutenblock_pro_pexels_api_key', '' );
		$unsplash_api_key = get_option( 'gutenblock_pro_unsplash_api_key', '' );
		$image_api_provider = get_option( 'gutenblock_pro_image_api_provider', 'pexels' );
		$current_user = wp_get_current_user();
		$is_allowed_user = ( $current_user->user_login === 'hjherbst' );
		
		wp_localize_script( 'gutenblock-pro-pattern-creator', 'gutenblockProCreator', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gutenblock_pro_create_pattern' ),
			'adminNonce' => wp_create_nonce( 'gutenblock_pro_admin' ),
			'pexelsApiKey' => $pexels_api_key,
			'unsplashApiKey' => $unsplash_api_key,
			'imageApiProvider' => $image_api_provider,
			'isAllowedUser' => $is_allowed_user,
			'currentLocale' => get_locale(),
			'groups'  => $groups,
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
				'languageLabel'    => __( 'Sprache', 'gutenblock-pro' ),
				'languageHelp'     => __( 'Für welche Sprache ist dieser Content?', 'gutenblock-pro' ),
				'languageDefault'  => __( 'Default (Fallback)', 'gutenblock-pro' ),
				'typeLabel'        => __( 'Typ', 'gutenblock-pro' ),
				'typePattern'      => __( 'Section', 'gutenblock-pro' ),
				'typePage'         => __( 'Seite', 'gutenblock-pro' ),
				'groupLabel'       => __( 'Gruppe', 'gutenblock-pro' ),
				'groupNone'        => __( '— Keine Gruppe —', 'gutenblock-pro' ),
				'replaceImagesLabel' => __( 'Lokale Bilder ersetzen', 'gutenblock-pro' ),
				'replaceImagesHelp'  => __( 'Ersetzt Bilder aus dem Uploads-Ordner durch Placeholder (picsum.photos)', 'gutenblock-pro' ),
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
			),
			'languages' => array(
				array( 'value' => 'default', 'label' => __( 'Default (Fallback)', 'gutenblock-pro' ) ),
				array( 'value' => 'de_DE', 'label' => 'Deutsch (DE)' ),
				array( 'value' => 'en_US', 'label' => 'English (US)' ),
				array( 'value' => 'en_GB', 'label' => 'English (UK)' ),
				array( 'value' => 'fr_FR', 'label' => 'Français' ),
				array( 'value' => 'es_ES', 'label' => 'Español' ),
				array( 'value' => 'it_IT', 'label' => 'Italiano' ),
				array( 'value' => 'nl_NL', 'label' => 'Nederlands' ),
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

		$name = sanitize_text_field( $_POST['name'] );
		$slug = sanitize_title( $_POST['slug'] );
		$description = sanitize_text_field( $_POST['description'] );
		$keywords = sanitize_text_field( $_POST['keywords'] );
		$language = sanitize_text_field( $_POST['language'] );
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'pattern';
		$group = isset( $_POST['group'] ) ? sanitize_key( $_POST['group'] ) : '';
		$premium = isset( $_POST['premium'] ) && $_POST['premium'] === 'true';
		$content = wp_unslash( $_POST['content'] );

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

		// Replace local images with placeholders
		$replace_images = isset( $_POST['replace_images'] ) && $_POST['replace_images'] === 'true';
		if ( $replace_images ) {
			$content = $this->replace_local_images( $content, $slug );
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

		// Determine content filename based on language
		$content_filename = 'content.html';
		if ( ! empty( $language ) && $language !== 'default' ) {
			$content_filename = 'content-' . $language . '.html';
		}

		if ( $is_new_pattern ) {
			// Create pattern.php and asset files for NEW patterns
			$pattern_php = $this->generate_pattern_php( $name, $description, $keywords, $type, $group, $premium );
			file_put_contents( $pattern_dir . '/pattern.php', $pattern_php );

			$style_css = $this->generate_style_css( $name, $slug );
			file_put_contents( $pattern_dir . '/style.css', $style_css );

			$editor_css = $this->generate_editor_css( $name, $slug );
			file_put_contents( $pattern_dir . '/editor.css', $editor_css );

			$script_js = $this->generate_script_js( $name, $slug );
			file_put_contents( $pattern_dir . '/script.js', $script_js );
		} else {
			// Update group in existing pattern.php if provided
			$pattern_file = $pattern_dir . '/pattern.php';
			if ( file_exists( $pattern_file ) ) {
				$this->update_pattern_php_field( $pattern_file, 'group', $group );
			}
		}

		// Create/update content file (language-specific or default)
		// CSS and JS are preserved for existing patterns
		file_put_contents( $pattern_dir . '/' . $content_filename, $content );

		// Determine success message
		if ( $is_new_pattern ) {
			$message = 'Pattern created successfully';
		} elseif ( $is_update_mode ) {
			$message = 'Pattern content updated. CSS/JS preserved.';
		} else {
			$message = 'Language version added: ' . ( $language === 'default' ? 'Default' : $language );
		}

		wp_send_json_success( array(
			'message'    => $message,
			'slug'       => $slug,
			'path'       => $pattern_dir,
			'language'   => $language,
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
			$pattern_file = $pattern_dir . '/pattern.php';
			if ( file_exists( $pattern_file ) ) {
				$pattern_data = require $pattern_file;
				$pattern_info['title'] = isset( $pattern_data['title'] ) ? $pattern_data['title'] : $slug;
				$pattern_info['premium'] = isset( $pattern_data['premium'] ) ? $pattern_data['premium'] : false;
				$pattern_info['group'] = isset( $pattern_data['group'] ) ? $pattern_data['group'] : '';
			}
			$pattern_info['has_style'] = file_exists( $pattern_dir . '/style.css' );
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
	private function generate_pattern_php( $name, $description, $keywords, $type = 'pattern', $group = '', $premium = false ) {
		$keywords_array = array_map( 'trim', explode( ',', $keywords ) );
		$keywords_php = "array( '" . implode( "', '", array_filter( $keywords_array ) ) . "' )";
		
		if ( empty( array_filter( $keywords_array ) ) ) {
			$keywords_php = 'array()';
		}

		$group_line = $group ? "\n\t'group'       => '{$group}'," : "\n\t'group'       => '',";
		$premium_line = $premium ? "\n\t'premium'     => true, // true = benötigt Pro Plus Lizenz für Bearbeitung" : "\n\t'premium'     => false, // true = benötigt Pro Plus Lizenz für Bearbeitung";

		return "<?php
/**
 * Pattern: {$name}
 */

return array(
	'title'       => __( '{$name}', 'gutenblock-pro' ),
	'description' => __( '{$description}', 'gutenblock-pro' ),
	'type'        => '{$type}', // 'pattern' or 'page'{$group_line}
	'categories'  => array( 'gutenblock-pro' ),
	'keywords'    => {$keywords_php},
	'content'     => '', // Loaded from content.html{$premium_line}
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

	/**
	 * Replace local images with placeholder images
	 *
	 * @param string $content Block content
	 * @param string $slug    Pattern slug for consistent placeholders
	 * @return string Modified content
	 */
	private function replace_local_images( $content, $slug ) {
		$site_url   = site_url();
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		$url_map       = array();
		$image_counter = 0;
		$self          = $this;

		$get_placeholder = function( $original_url ) use ( $site_url, $upload_url, $slug, $self, &$url_map, &$image_counter ) {
			if ( strpos( $original_url, $site_url ) === false && strpos( $original_url, $upload_url ) === false ) {
				return null;
			}
			if ( isset( $url_map[ $original_url ] ) ) {
				return $url_map[ $original_url ];
			}
			$dimensions  = $self->extract_image_dimensions( $original_url );
			$width       = $dimensions['width'] ?: 800;
			$height      = $dimensions['height'] ?: 600;
			$seed        = $slug . '-' . $image_counter;
			$placeholder = "https://picsum.photos/seed/{$seed}/{$width}/{$height}";
			$image_counter++;
			$url_map[ $original_url ] = $placeholder;
			return $placeholder;
		};

		// Replace img src attributes
		$content = preg_replace_callback(
			'/(<img[^>]+src=["\'])([^"\']+)(["\'][^>]*>)/i',
			function( $matches ) use ( $get_placeholder ) {
				$replacement = $get_placeholder( $matches[2] );
				return $replacement ? $matches[1] . $replacement . $matches[3] : $matches[0];
			},
			$content
		);

		// Replace "url" in block JSON attributes (same URL gets same placeholder)
		$content = preg_replace_callback(
			'/"url":"([^"]+)"/',
			function( $matches ) use ( $get_placeholder ) {
				$replacement = $get_placeholder( $matches[1] );
				return $replacement ? '"url":"' . $replacement . '"' : $matches[0];
			},
			$content
		);

		// wp-image-{n} Klasse aus <img> entfernen (wird zum führenden Leerzeichen → bereinigen)
		$content = preg_replace( '/\bwp-image-\d+\b/', '', $content );
		// Verbleibende Leerzeichen in class-Attributen normalisieren
		$content = preg_replace( '/class="\s+/', 'class="', $content );
		// size-full Klasse entfernen (nur gültig bei lokal referenzierten Anhängen)
		$content = preg_replace( '/\bsize-full\b\s*/', '', $content );
		$content = preg_replace( '/class="\s*"/', '', $content );

		// "id":{n} aus Block-JSON (z.B. core/image)
		$content = preg_replace( '/"id":\d+,?/', '', $content );
		// "mediaId":{n} aus core/media-text Block-JSON – löst Block-Validierungsfehler aus
		$content = preg_replace( '/"mediaId":\d+,?/', '', $content );
		// "mediaLink":"..." – enthält lokale Upload-/Attachment-URLs
		$content = preg_replace( '/"mediaLink":"[^"]*",?/', '', $content );

		return $content;
	}

	/**
	 * Extract image dimensions from URL or filename
	 *
	 * @param string $url Image URL
	 * @return array ['width' => int, 'height' => int]
	 */
	public function extract_image_dimensions( $url ) {
		$width = 0;
		$height = 0;

		// Try to extract from WordPress-style filename (image-800x600.jpg)
		if ( preg_match( '/-(\d+)x(\d+)\.[a-z]+$/i', $url, $matches ) ) {
			$width = (int) $matches[1];
			$height = (int) $matches[2];
		}

		// If no dimensions found, try to get from attachment metadata
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( $metadata && isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
				$width = $metadata['width'];
				$height = $metadata['height'];
			}
		}

		// Limit dimensions for reasonable placeholder sizes
		if ( $width > 1920 ) {
			$ratio = $height / $width;
			$width = 1920;
			$height = round( $width * $ratio );
		}

		return array(
			'width'  => $width,
			'height' => $height,
		);
	}
}

