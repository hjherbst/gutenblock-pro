<?php
/**
 * Provisioning-Wizard: Site aus SaaS-Manifest auf dieser WP-Instanz aufbauen.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI + Import-Logik (Token oder Manifest-URL).
 */
class GutenBlock_Pro_Provisioning_Wizard {

	const OPTION_SAAS_BASE = 'gutenblock_pro_saas_base_url';

	/**
	 * Singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		static $inst = null;
		if ( null === $inst ) {
			$inst = new self();
		}
		return $inst;
	}

	/**
	 * Hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_submit' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_customizer_css' ), 20 );
	}

	/**
	 * Lädt die generierte user-customizer.css aus dem Upload-Ordner.
	 */
	public function enqueue_customizer_css(): void {
		$url = get_option( 'gutenblock_pro_customizer_css_url', '' );
		if ( ! $url || ! is_string( $url ) ) {
			return;
		}
		wp_enqueue_style(
			'gutenblock-provision-customizer',
			esc_url( $url ),
			array(),
			(string) get_option( 'gutenblock_pro_customizer_css_ver', GUTENBLOCK_PRO_VERSION )
		);
	}

	/**
	 * Default SaaS-Base (ohne trailing slash).
	 */
	public static function default_saas_base(): string {
		if ( defined( 'GUTENBLOCK_SAAS_API_URL' ) && GUTENBLOCK_SAAS_API_URL ) {
			return rtrim( (string) GUTENBLOCK_SAAS_API_URL, '/' );
		}
		return 'https://app.gutenblock.com';
	}

	/**
	 * Menü unter Werkzeuge.
	 */
	public function register_menu(): void {
		add_management_page(
			'GutenBlock einrichten',
			'GutenBlock einrichten',
			'manage_options',
			'gutenblock-provisioning',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Hinweis nach Plugin-Aktivierung (optional).
	 */
	public function maybe_notice(): void {
		if ( ! isset( $_GET['page'] ) || 'gutenblock-provisioning' !== $_GET['page'] ) {
			return;
		}
	}

	/**
	 * Formular & Status.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base = get_option( self::OPTION_SAAS_BASE, self::default_saas_base() );
		$last  = get_option( 'gutenblock_pro_last_manifest_sync', '' );
		$can_rebuild_bundle = $this->current_user_can_rebuild_pattern_bundle();

		echo '<div class="wrap"><h1>GutenBlock einrichten</h1>';
		echo '<p>Verbinde diese WordPress-Installation mit deiner Site im GutenBlock-SaaS. Du benötigst einen Provisioning-Token aus dem Dashboard (Site → Ausliefern).</p>';

		if ( $last ) {
			echo '<div class="notice notice-success"><p>Letzter Sync: ' . esc_html( $last ) . '</p></div>';
		}

		echo '<form method="post" action="">';
		wp_nonce_field( 'gutenblock_provision', 'gutenblock_provision_nonce' );
		echo '<table class="form-table"><tbody>';

		echo '<tr><th><label for="gutenblock_saas_base">SaaS-Basis-URL</label></th><td>';
		echo '<input type="url" class="regular-text" id="gutenblock_saas_base" name="gutenblock_saas_base" value="' . esc_attr( $base ) . '" placeholder="https://app.gutenblock.com" />';
		echo '<p class="description">Normalerweise nicht ändern.</p></td></tr>';

		echo '<tr><th><label for="gutenblock_token">Provisioning-Token</label></th><td>';
		echo '<input type="text" class="large-text code" id="gutenblock_token" name="gutenblock_token" value="" autocomplete="off" placeholder="64 Zeichen Hex-Token" />';
		echo '<p class="description">Aus dem GutenBlock-Dashboard kopieren (Endpoint erzeugt auch die Manifest-URL).</p></td></tr>';

		echo '</tbody></table>';
		submit_button( 'Site importieren / aktualisieren', 'primary', 'gutenblock_provision_submit' );
		echo '</form>';

		if ( $can_rebuild_bundle ) {
			$bundle = get_option( 'gutenblock_bridge_pattern_bundle' );
			$built_at = is_array( $bundle ) && ! empty( $bundle['builtAt'] ) ? (string) $bundle['builtAt'] : '';
			$count = is_array( $bundle ) && ! empty( $bundle['patterns'] ) && is_array( $bundle['patterns'] ) ? count( $bundle['patterns'] ) : 0;

			echo '<hr />';
			echo '<h2>Pattern-Bundle</h2>';
			echo '<p>Erzeugt das statische Pattern-Bundle für den SaaS-native Canvas. Nur für den internen Admin sichtbar.</p>';
			if ( $built_at ) {
				echo '<p><strong>Letzter Build:</strong> ' . esc_html( $built_at ) . ' · <strong>Patterns:</strong> ' . (int) $count . '</p>';
			} else {
				echo '<p><strong>Status:</strong> Noch kein Bundle gebaut.</p>';
			}
			echo '<form method="post" action="">';
			wp_nonce_field( 'gutenblock_rebuild_pattern_bundle', 'gutenblock_rebuild_pattern_bundle_nonce' );
			submit_button( 'Pattern-Bundle neu bauen', 'secondary', 'gutenblock_rebuild_pattern_bundle_submit' );
			echo '</form>';
		}
		echo '</div>';
	}

	/**
	 * POST: Manifest laden und anwenden.
	 */
	public function handle_submit(): void {
		if ( isset( $_POST['gutenblock_rebuild_pattern_bundle_submit'] ) ) {
			$this->handle_pattern_bundle_rebuild_submit();
			return;
		}

		if ( ! isset( $_POST['gutenblock_provision_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'gutenblock_provision', 'gutenblock_provision_nonce' );

		$token = isset( $_POST['gutenblock_token'] ) ? sanitize_text_field( wp_unslash( $_POST['gutenblock_token'] ) ) : '';
		$base  = isset( $_POST['gutenblock_saas_base'] ) ? esc_url_raw( wp_unslash( $_POST['gutenblock_saas_base'] ) ) : '';

		if ( strlen( $token ) < 16 ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>Bitte gültigen Token einfügen.</p></div>';
				}
			);
			return;
		}

		if ( $base ) {
			update_option( self::OPTION_SAAS_BASE, rtrim( $base, '/' ) );
		} else {
			$base = get_option( self::OPTION_SAAS_BASE, self::default_saas_base() );
		}

		$manifest_url = rtrim( $base, '/' ) . '/api/v1/sites/' . rawurlencode( $token ) . '/manifest';

		$response = wp_remote_get(
			$manifest_url,
			array(
				'timeout' => 90,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			add_action(
				'admin_notices',
				function () use ( $response ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $response->get_error_message() ) . '</p></div>';
				}
			);
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== (int) $code ) {
			add_action(
				'admin_notices',
				function () use ( $code, $body ) {
					echo '<div class="notice notice-error"><p>Manifest HTTP ' . (int) $code . ': ' . esc_html( wp_strip_all_tags( $body ) ) . '</p></div>';
				}
			);
			return;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['pages'] ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>Ungültiges Manifest.</p></div>';
				}
			);
			return;
		}

		$url_map = $this->import_assets( $data );

		$this->apply_pages( $data, $url_map );
		$this->apply_menu( $data );
		$this->apply_customizer_css( $data );
		$this->apply_header_footer_options( $data );

		update_option( 'gutenblock_pro_last_manifest_sync', current_time( 'mysql' ) );

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>Site erfolgreich aus dem SaaS übernommen.</p></div>';
			}
		);
	}

	/**
	 * Nur der interne Admin-User darf das öffentliche Plugin-Bundle manuell bauen.
	 */
	private function current_user_can_rebuild_pattern_bundle(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$user = wp_get_current_user();
		return $user && isset( $user->user_login ) && 'hjherbst' === (string) $user->user_login;
	}

	/**
	 * POST: Statisches Pattern-Bundle neu bauen.
	 */
	private function handle_pattern_bundle_rebuild_submit(): void {
		if ( ! $this->current_user_can_rebuild_pattern_bundle() ) {
			wp_die( esc_html__( 'Du bist nicht berechtigt, das Pattern-Bundle neu zu bauen.', 'gutenblock-pro' ) );
		}
		check_admin_referer( 'gutenblock_rebuild_pattern_bundle', 'gutenblock_rebuild_pattern_bundle_nonce' );

		if ( ! function_exists( 'gutenblock_bridge_build_patterns_bundle' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>Pattern-Bundle-Builder ist nicht verfügbar.</p></div>';
				}
			);
			return;
		}

		$bundle = gutenblock_bridge_build_patterns_bundle();
		if ( is_wp_error( $bundle ) ) {
			add_action(
				'admin_notices',
				function () use ( $bundle ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $bundle->get_error_message() ) . '</p></div>';
				}
			);
			return;
		}

		update_option( 'gutenblock_bridge_pattern_bundle', $bundle, false );
		add_action(
			'admin_notices',
			function () use ( $bundle ) {
				$count = isset( $bundle['patterns'] ) && is_array( $bundle['patterns'] ) ? count( $bundle['patterns'] ) : 0;
				echo '<div class="notice notice-success"><p>Pattern-Bundle neu gebaut. Patterns: ' . (int) $count . '</p></div>';
			}
		);
	}

	/**
	 * Medien sideloaden, URL-Map alt→neu.
	 *
	 * @param array $manifest Manifest.
	 * @return array<string,string>
	 */
	private function import_assets( array $manifest ): array {
		$map      = array();
		$assets   = isset( $manifest['assets'] ) && is_array( $manifest['assets'] ) ? $manifest['assets'] : array();
		$need_lib = false;

		foreach ( $assets as $row ) {
			$url = isset( $row['remoteUrl'] ) ? (string) $row['remoteUrl'] : '';
			if ( ! $url || isset( $map[ $url ] ) ) {
				continue;
			}
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$need_lib = true;
			}

			$att_id = $this->sideload_one( $url );
			if ( $att_id ) {
				$new_url = wp_get_attachment_url( $att_id );
				if ( $new_url ) {
					$map[ $url ] = $new_url;
				}
			}
		}

		return $map;
	}

	/**
	 * Ein Bild sideloaden.
	 *
	 * @param string $url Remote URL.
	 * @return int Attachment-ID oder 0.
	 */
	private function sideload_one( string $url ): int {
		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ?: 'image.bin' ),
			'tmp_name' => $tmp,
		);

		$att_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			return 0;
		}
		return (int) $att_id;
	}

	/**
	 * Pages anlegen/aktualisieren.
	 *
	 * @param array                $manifest Manifest.
	 * @param array<string,string> $url_map  Ersetzungen.
	 */
	private function apply_pages( array $manifest, array $url_map ): void {
		$fields = isset( $manifest['contentFields'] ) && is_array( $manifest['contentFields'] )
			? $manifest['contentFields']
			: array();
		foreach ( $manifest['pages'] as $page ) {
			if ( empty( $page['slug'] ) ) {
				continue;
			}
			$slug    = sanitize_title( (string) $page['slug'] );
			$title   = isset( $page['title'] ) ? (string) $page['title'] : $slug;
			if ( ! empty( $page['sections'] ) && is_array( $page['sections'] ) ) {
				$content = $this->assemble_page_from_sections( $page['sections'], $fields );
			} elseif ( isset( $page['blockMarkup'] ) ) {
				$content = $this->apply_text_fields_to_markup( (string) $page['blockMarkup'], $fields );
			} else {
				continue;
			}

			foreach ( $url_map as $from => $to ) {
				$content = str_replace( $from, $to, $content );
			}

			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			$postarr  = array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			);
			if ( $existing ) {
				$postarr['ID'] = (int) $existing->ID;
				wp_update_post( $postarr );
			} else {
				wp_insert_post( $postarr );
			}
		}

		$home = '';
		foreach ( $manifest['pages'] as $p ) {
			if ( isset( $p['kind'] ) && 'home' === $p['kind'] && ! empty( $p['slug'] ) ) {
				$home = sanitize_title( (string) $p['slug'] );
				break;
			}
		}
		if ( $home ) {
			$hp = get_page_by_path( $home, OBJECT, 'page' );
			if ( $hp ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', (int) $hp->ID );
			}
		}
	}

	/**
	 * Baut eine Seite aus Pattern-Slugs aus dem lokalen Plugin zusammen.
	 *
	 * @param array $sections Manifest-Sections.
	 * @param array $fields   Textfelder.
	 * @return string
	 */
	private function assemble_page_from_sections( array $sections, array $fields ): string {
		$out = '';
		foreach ( $sections as $section ) {
			if ( empty( $section['patternSlug'] ) ) {
				continue;
			}
			$slug = sanitize_file_name( (string) $section['patternSlug'] );
			$tone = isset( $section['tone'] ) ? (string) $section['tone'] : 'neutral';
			$markup = $this->pattern_file_markup( $slug );
			if ( '' === $markup ) {
				continue;
			}
			if ( 'neutral' !== $tone && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
				$markup = GutenBlock_Pro_Tone_Injector::inject( $markup, $tone );
			}
			$out .= $this->apply_text_fields_to_markup( $markup, $fields ) . "\n\n";
		}
		return $out;
	}

	/**
	 * Ersetzt Text-Slots in serialisiertem Block-Markup anhand metadata.name.
	 *
	 * @param string $markup Block-Markup.
	 * @param array  $fields Field-ID => Text.
	 * @return string
	 */
	private function apply_text_fields_to_markup( string $markup, array $fields ): string {
		if ( '' === trim( $markup ) || empty( $fields ) || ! function_exists( 'parse_blocks' ) ) {
			return $markup;
		}
		$blocks = parse_blocks( $markup );
		$this->replace_text_fields_in_blocks( $blocks, $fields );
		return serialize_blocks( $blocks );
	}

	/**
	 * @param array $blocks Blöcke per Referenz.
	 * @param array $fields Field-ID => Text.
	 */
	private function replace_text_fields_in_blocks( array &$blocks, array $fields ): void {
		$text_blocks = array( 'core/heading', 'core/paragraph', 'core/button', 'core/list-item' );
		foreach ( $blocks as &$block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = '';
			if ( isset( $block['attrs']['metadata']['name'] ) && is_string( $block['attrs']['metadata']['name'] ) ) {
				$name = (string) $block['attrs']['metadata']['name'];
			}
			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( $name && in_array( $block_name, $text_blocks, true ) && array_key_exists( $name, $fields ) ) {
				$value = (string) $fields[ $name ];
				if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
					$block['innerHTML'] = $this->replace_text_in_html_fragment( $block['innerHTML'], $value );
				}
				if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
					foreach ( $block['innerContent'] as &$chunk ) {
						if ( is_string( $chunk ) ) {
							$chunk = $this->replace_text_in_html_fragment( $chunk, $value );
						}
					}
					unset( $chunk );
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->replace_text_fields_in_blocks( $block['innerBlocks'], $fields );
			}
		}
		unset( $block );
	}

	/**
	 * Ersetzt den sichtbaren Text im ersten HTML-Element eines statischen Blocks.
	 *
	 * @param string $html  HTML-Fragment.
	 * @param string $text  Neuer Text.
	 * @return string
	 */
	private function replace_text_in_html_fragment( string $html, string $text ): string {
		$escaped = esc_html( $text );
		if ( false !== stripos( $html, '<a ' ) || false !== stripos( $html, '<a>' ) ) {
			$with_anchor = preg_replace(
				'/(<a\b[^>]*>)(.*?)(<\/a>)/s',
				'${1}' . $escaped . '${3}',
				$html,
				1
			);
			if ( is_string( $with_anchor ) ) {
				return $with_anchor;
			}
		}
		return preg_replace(
			'/(<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>)(.*?)(<\/\2>)/s',
			'${1}' . $escaped . '${4}',
			$html,
			1
		) ?: $html;
	}

	/**
	 * Navigationsmenü aus Manifest.
	 *
	 * @param array $manifest Manifest.
	 */
	private function apply_menu( array $manifest ): void {
		if ( empty( $manifest['menu'] ) || ! is_array( $manifest['menu'] ) ) {
			return;
		}

		$menu_name = 'GutenBlock Primary';
		$menus     = wp_get_nav_menus();
		$menu_id   = 0;
		foreach ( $menus as $m ) {
			if ( $m->name === $menu_name ) {
				$menu_id = (int) $m->term_id;
				break;
			}
		}
		if ( ! $menu_id ) {
			$menu_id = wp_create_nav_menu( $menu_name );
		}
		if ( is_wp_error( $menu_id ) || ! $menu_id ) {
			return;
		}

		$items = wp_get_nav_menu_items( $menu_id );
		if ( is_array( $items ) ) {
			foreach ( $items as $it ) {
				wp_delete_post( (int) $it->ID, true );
			}
		}

		$order = 0;
		foreach ( $manifest['menu'] as $row ) {
			$slug = isset( $row['slug'] ) ? sanitize_title( (string) $row['slug'] ) : '';
			$label = isset( $row['title'] ) ? (string) $row['title'] : $slug;
			if ( ! $slug ) {
				continue;
			}
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page ) {
				continue;
			}
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $label,
					'menu-item-object'    => 'page',
					'menu-item-object-id' => (int) $page->ID,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => ++$order,
				)
			);
		}

		$locations = get_theme_mod( 'nav_menu_locations', array() );
		if ( ! is_array( $locations ) ) {
			$locations = array();
		}
		// Häufige Theme-Locations.
		foreach ( array( 'primary', 'main', 'header', 'menu-1' ) as $loc ) {
			$locations[ $loc ] = $menu_id;
		}
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Customizer als CSS-Datei in uploads (einfache Variablen).
	 *
	 * @param array $manifest Manifest.
	 */
	private function apply_customizer_css( array $manifest ): void {
		if ( empty( $manifest['customizer'] ) || ! is_array( $manifest['customizer'] ) ) {
			return;
		}
		$c = $manifest['customizer'];
		$colors = isset( $c['colors'] ) && is_array( $c['colors'] ) ? $c['colors'] : array();

		$css  = "/* GutenBlock SaaS Customizer */\n:root {\n";
		$mapc = array(
			'base'     => '--wp--preset--color--base',
			'contrast' => '--wp--preset--color--contrast',
			'primary'  => '--wp--preset--color--primary',
			'tertiary' => '--wp--preset--color--tertiary',
		);
		foreach ( $mapc as $k => $var ) {
			if ( ! empty( $colors[ $k ] ) ) {
				$css .= '  ' . $var . ': ' . esc_attr( (string) $colors[ $k ] ) . ";\n";
			}
		}
		$css .= "}\n";

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'gutenblock-pro';
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		$file = $dir . '/user-customizer.css';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $css );

		$url = trailingslashit( $upload['baseurl'] ) . 'gutenblock-pro/user-customizer.css';
		update_option( 'gutenblock_pro_customizer_css_url', $url );
		update_option( 'gutenblock_pro_customizer_css_ver', (string) time() );
	}

	/**
	 * Header-/Footer-Pattern (Manifest: patternSlug und/oder blockMarkup).
	 *
	 * @param array $manifest Manifest.
	 */
	private function apply_header_footer_options( array $manifest ): void {
		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( empty( $manifest[ $part ] ) || ! is_array( $manifest[ $part ] ) ) {
				continue;
			}
			$h      = $manifest[ $part ];
			$markup = '';
			if ( ! empty( $h['blockMarkup'] ) ) {
				$markup = (string) $h['blockMarkup'];
			} elseif ( ! empty( $h['patternSlug'] ) ) {
				$markup = $this->pattern_file_markup( (string) $h['patternSlug'] );
			}
			if ( '' !== $markup ) {
				update_option( 'gutenblock_pro_' . $part . '_pattern_markup', $markup );
			}
		}
	}

	/**
	 * Roh-Inhalt aus patterns/{slug}/content.html im Plugin.
	 *
	 * @param string $slug Pattern-Slug.
	 * @return string
	 */
	private function pattern_file_markup( string $slug ): string {
		if ( ! defined( 'GUTENBLOCK_PRO_PATH' ) ) {
			return '';
		}
		$slug = sanitize_file_name( $slug );
		$path = trailingslashit( GUTENBLOCK_PRO_PATH ) . 'patterns/' . $slug . '/content.html';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return (string) file_get_contents( $path );
	}
}
