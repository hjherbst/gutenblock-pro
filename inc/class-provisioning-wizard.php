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

	const OPTION_SAAS_BASE        = 'gutenblock_pro_saas_base_url';
	const OPTION_IMPORT_STYLES    = 'gutenblock_pro_import_styles';
	const OPTION_CUSTOMIZER_FONTS = 'gutenblock_pro_customizer_fonts_url';

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
		// Späte Priorität, damit das Top-Level-Menü `gutenblock-pro` (aus
		// class-admin-page.php) bereits registriert ist und wir als Submenu
		// einhängen können.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_init', array( $this, 'handle_submit' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_customizer_css' ), 20 );
	}

	/**
	 * Lädt die generierte user-customizer.css + (optional) Google-Fonts.
	 */
	public function enqueue_customizer_css(): void {
		$fonts_url = get_option( self::OPTION_CUSTOMIZER_FONTS, '' );
		if ( $fonts_url && is_string( $fonts_url ) ) {
			wp_enqueue_style(
				'gutenblock-provision-fonts',
				esc_url( $fonts_url ),
				array(),
				null
			);
		}

		$url = get_option( 'gutenblock_pro_customizer_css_url', '' );
		if ( ! $url || ! is_string( $url ) ) {
			return;
		}
		wp_enqueue_style(
			'gutenblock-provision-customizer',
			esc_url( $url ),
			array( 'gutenblock-provision-fonts' ),
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
	 * Submenu „Import" unter dem Plugin-Top-Level-Menü `gutenblock-pro`.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'gutenblock-pro',
			__( 'Import', 'gutenblock-pro' ),
			__( 'Import', 'gutenblock-pro' ),
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
	 * Import-Dashboard mit Karten.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base               = (string) get_option( self::OPTION_SAAS_BASE, self::default_saas_base() );
		$last               = (string) get_option( 'gutenblock_pro_last_manifest_sync', '' );
		$last_pages         = (int) get_option( 'gutenblock_pro_last_manifest_pages_count', 0 );
		$styles_active      = (bool) get_option( 'gutenblock_pro_customizer_css_url', '' );
		$can_rebuild_bundle = $this->current_user_can_rebuild_pattern_bundle();

		$this->render_admin_styles();

		echo '<div class="wrap gbp-import-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Import aus GutenBlock-SaaS', 'gutenblock-pro' ) . '</h1>';
		echo '<p class="description gbp-lead">' . esc_html__( 'Übernimm deine im SaaS-Editor gestaltete Site in diese WordPress-Installation: Seiten, Header/Footer, Menü und Medien.', 'gutenblock-pro' ) . '</p>';

		// ── Karte: Status ────────────────────────────────────────────────
		echo '<div class="gbp-card gbp-card-status">';
		echo '<div class="gbp-card-head"><h2>' . esc_html__( 'Status', 'gutenblock-pro' ) . '</h2></div>';
		echo '<div class="gbp-status-grid">';
		echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Letzter Import', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $last ? esc_html( $last ) : '<em>—</em>' ) . '</span></div>';
		echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Seiten zuletzt', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $last_pages ? (int) $last_pages : '<em>—</em>' ) . '</span></div>';
		echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'SaaS-Styles aktiv', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $styles_active ? '<span class="gbp-pill gbp-pill-on">' . esc_html__( 'Aktiv', 'gutenblock-pro' ) . '</span>' : '<span class="gbp-pill gbp-pill-off">' . esc_html__( 'Aus', 'gutenblock-pro' ) . '</span>' ) . '</span></div>';
		echo '</div>';
		echo '</div>';

		// ── Karte: Import-Formular ───────────────────────────────────────
		echo '<div class="gbp-card">';
		echo '<div class="gbp-card-head"><h2>' . esc_html__( 'Import starten', 'gutenblock-pro' ) . '</h2></div>';
		echo '<p class="gbp-card-intro">' . esc_html__( 'Trage deinen Provisioning-Token aus dem GutenBlock-Dashboard (Site → Ausliefern) ein.', 'gutenblock-pro' ) . '</p>';

		echo '<form method="post" action="" class="gbp-form">';
		wp_nonce_field( 'gutenblock_provision', 'gutenblock_provision_nonce' );

		echo '<div class="gbp-field">';
		echo '<label for="gutenblock_token">' . esc_html__( 'Provisioning-Token', 'gutenblock-pro' ) . '</label>';
		echo '<input type="text" class="large-text code" id="gutenblock_token" name="gutenblock_token" value="" autocomplete="off" placeholder="' . esc_attr__( '64-stelliger Hex-Token aus dem Dashboard', 'gutenblock-pro' ) . '" />';
		echo '</div>';

		echo '<div class="gbp-field">';
		echo '<label for="gutenblock_saas_base">' . esc_html__( 'SaaS-Basis-URL', 'gutenblock-pro' ) . '</label>';
		echo '<input type="url" class="regular-text" id="gutenblock_saas_base" name="gutenblock_saas_base" value="' . esc_attr( $base ) . '" placeholder="https://app.gutenblock.com" />';
		echo '<p class="gbp-help">' . esc_html__( 'Normalerweise nicht ändern.', 'gutenblock-pro' ) . '</p>';
		echo '</div>';

		// Optionaler Styles-Override
		echo '<div class="gbp-option gbp-styles-toggle">';
		echo '<label class="gbp-checkbox">';
		echo '<input type="checkbox" name="gutenblock_import_styles" value="1" />';
		echo '<span class="gbp-checkbox-text">' . esc_html__( 'Styles aus dem SaaS übernehmen', 'gutenblock-pro' ) . '</span>';
		echo '</label>';
		echo '<div class="gbp-warn">';
		echo '<strong>' . esc_html__( 'Hinweis:', 'gutenblock-pro' ) . '</strong> ';
		echo esc_html__( 'Aktiviert ersetzt diese Option Farben, Schriften (inkl. Heading-Weight) und semantische Schriftgrößen (H1–H4, Absatz) deiner Site durch die im SaaS festgelegten Werte. Diese werden als zusätzliches Stylesheet eingehängt und überschreiben Theme-Defaults via `!important`. Bestehende Block-individuelle Overrides bleiben erhalten.', 'gutenblock-pro' );
		echo '</div>';
		echo '</div>';

		// Opt-in: aktuelle Startseite durch SaaS-Home ersetzen (sonst beibehalten).
		echo '<div class="gbp-option gbp-replace-home-toggle">';
		echo '<label class="gbp-checkbox">';
		echo '<input type="checkbox" name="gutenblock_replace_home" value="1" />';
		echo '<span class="gbp-checkbox-text">' . esc_html__( 'Startseite ersetzen', 'gutenblock-pro' ) . '</span>';
		echo '</label>';
		echo '<div class="gbp-warn">';
		echo '<strong>' . esc_html__( 'Hinweis:', 'gutenblock-pro' ) . '</strong> ';
		echo esc_html__( 'Aktiviert ersetzt diese Option deine aktuelle WordPress-Startseite durch die im SaaS gestaltete Startseite. Lasse die Option deaktiviert, wenn deine bestehende Startseite erhalten bleiben soll – alle weiteren Seiten werden in beiden Fällen importiert.', 'gutenblock-pro' );
		echo '</div>';
		echo '</div>';

		echo '<div class="gbp-actions">';
		submit_button( __( 'Import starten', 'gutenblock-pro' ), 'primary', 'gutenblock_provision_submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';

		// ── Karte: Pattern-Bundle (Admin only) ───────────────────────────
		if ( $can_rebuild_bundle ) {
			$bundle    = get_option( 'gutenblock_bridge_pattern_bundle' );
			$built_at  = is_array( $bundle ) && ! empty( $bundle['builtAt'] ) ? (string) $bundle['builtAt'] : '';
			$count     = is_array( $bundle ) && ! empty( $bundle['patterns'] ) && is_array( $bundle['patterns'] ) ? count( $bundle['patterns'] ) : 0;

			echo '<div class="gbp-card gbp-card-internal">';
			echo '<div class="gbp-card-head"><h2>' . esc_html__( 'Pattern-Bundle (intern)', 'gutenblock-pro' ) . '</h2><span class="gbp-pill gbp-pill-admin">' . esc_html__( 'Admin', 'gutenblock-pro' ) . '</span></div>';
			echo '<p class="gbp-card-intro">' . esc_html__( 'Statisches Pattern-Bundle für den SaaS-Canvas neu bauen.', 'gutenblock-pro' ) . '</p>';
			echo '<div class="gbp-status-grid">';
			echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Letzter Build', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $built_at ? esc_html( $built_at ) : '<em>—</em>' ) . '</span></div>';
			echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Patterns', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . (int) $count . '</span></div>';
			echo '</div>';
			echo '<form method="post" action="" class="gbp-actions">';
			wp_nonce_field( 'gutenblock_rebuild_pattern_bundle', 'gutenblock_rebuild_pattern_bundle_nonce' );
			submit_button( __( 'Pattern-Bundle neu bauen', 'gutenblock-pro' ), 'secondary', 'gutenblock_rebuild_pattern_bundle_submit', false );
			echo '</form>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Inline-CSS für das Import-Dashboard. Klein gehalten, scoped auf
	 * `.gbp-import-wrap`, damit es im Admin nichts anderes beeinflusst.
	 */
	private function render_admin_styles(): void {
		echo '<style>'
			. '.gbp-import-wrap{max-width:920px;}'
			. '.gbp-import-wrap .gbp-lead{font-size:13px;color:#50575e;margin:6px 0 18px;}'
			. '.gbp-card{background:#fff;border:1px solid #e3e5e8;border-radius:8px;padding:18px 20px;margin:0 0 16px;box-shadow:0 1px 0 rgba(0,0,0,.02);}' 
			. '.gbp-card-head{display:flex;align-items:center;gap:10px;margin:0 0 10px;}'
			. '.gbp-card-head h2{font-size:14px;margin:0;color:#1d2327;font-weight:600;}'
			. '.gbp-card-intro{margin:0 0 14px;color:#50575e;font-size:13px;}'
			. '.gbp-form .gbp-field{margin:0 0 14px;}'
			. '.gbp-form label{display:block;font-size:12px;font-weight:600;color:#1d2327;margin-bottom:4px;}'
			. '.gbp-form input[type=text].large-text,.gbp-form input[type=url].regular-text{width:100%;max-width:680px;}'
			. '.gbp-help{margin:4px 0 0;font-size:12px;color:#646970;}'
			. '.gbp-status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;}'
			. '.gbp-stat{display:flex;flex-direction:column;gap:2px;padding:10px 12px;background:#f6f7f7;border:1px solid #ebedee;border-radius:6px;}'
			. '.gbp-stat-label{font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.04em;}'
			. '.gbp-stat-value{font-size:13px;color:#1d2327;font-weight:600;}'
			. '.gbp-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;}'
			. '.gbp-pill-on{background:#dff4e3;color:#0a5d2a;}'
			. '.gbp-pill-off{background:#f0f0f1;color:#646970;}'
			. '.gbp-pill-admin{background:#fff3cd;color:#7a5b00;margin-left:auto;}'
			. '.gbp-option{padding:12px 14px;border:1px dashed #d4d7da;border-radius:6px;background:#fafbfc;margin:8px 0 16px;}'
			. '.gbp-checkbox{display:flex;align-items:center;gap:8px;cursor:pointer;}'
			. '.gbp-checkbox-text{font-weight:600;color:#1d2327;}'
			. '.gbp-warn{margin-top:8px;font-size:12px;color:#50575e;line-height:1.55;}'
			. '.gbp-warn strong{color:#7a5b00;}'
			. '.gbp-actions{margin-top:4px;}'
			. '.gbp-card-internal{border-color:#f0d99c;background:#fffdf5;}'
			. '.gbp-card-internal .gbp-card-head h2{color:#7a5b00;}'
			. '</style>';
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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Bitte gültigen Token einfügen.', 'gutenblock-pro' ) . '</p></div>';
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
					echo '<div class="notice notice-error"><p>' . esc_html( $response->get_error_message() ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
					$msg = sprintf(
						/* translators: 1: HTTP status code, 2: response body */
						esc_html__( 'Manifest HTTP %1$d: %2$s', 'gutenblock-pro' ),
						(int) $code,
						esc_html( wp_strip_all_tags( $body ) )
					);
					echo '<div class="notice notice-error"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
			return;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['pages'] ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Ungültiges Manifest.', 'gutenblock-pro' ) . '</p></div>';
				}
			);
			return;
		}

		$import_styles = ! empty( $_POST['gutenblock_import_styles'] );
		$replace_home  = ! empty( $_POST['gutenblock_replace_home'] );
		update_option( self::OPTION_IMPORT_STYLES, $import_styles ? 1 : 0 );

		$url_map = $this->import_assets( $data );

		$this->apply_pages( $data, $url_map, $replace_home );
		$this->apply_menu( $data );
		if ( $import_styles ) {
			$this->apply_customizer_css( $data );
		} else {
			$this->clear_customizer_css();
		}
		$this->apply_header_footer_options( $data );

		$pages_count = isset( $data['pages'] ) && is_array( $data['pages'] ) ? count( $data['pages'] ) : 0;
		update_option( 'gutenblock_pro_last_manifest_sync', current_time( 'mysql' ) );
		update_option( 'gutenblock_pro_last_manifest_pages_count', $pages_count );

		add_action(
			'admin_notices',
			function () use ( $pages_count, $import_styles, $replace_home ) {
				$msg = sprintf(
					/* translators: 1: pages count, 2: styles state, 3: home state */
					esc_html__( 'Import erfolgreich. Seiten: %1$d · Styles: %2$s · Startseite: %3$s', 'gutenblock-pro' ),
					(int) $pages_count,
					$import_styles ? esc_html__( 'aus SaaS übernommen', 'gutenblock-pro' ) : esc_html__( 'unverändert', 'gutenblock-pro' ),
					$replace_home ? esc_html__( 'ersetzt', 'gutenblock-pro' ) : esc_html__( 'beibehalten', 'gutenblock-pro' )
				);
				echo '<div class="notice notice-success"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		);
	}

	/**
	 * Entfernt die generierte Customizer-CSS-Verknüpfung, damit der Theme-
	 * Default wieder greift. Die Datei selbst wird nicht gelöscht (idempotent),
	 * lediglich nicht mehr enqueued.
	 */
	private function clear_customizer_css(): void {
		delete_option( 'gutenblock_pro_customizer_css_url' );
		delete_option( 'gutenblock_pro_customizer_css_ver' );
		delete_option( self::OPTION_CUSTOMIZER_FONTS );
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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Pattern-Bundle-Builder ist nicht verfügbar.', 'gutenblock-pro' ) . '</p></div>';
				}
			);
			return;
		}

		$bundle = gutenblock_bridge_build_patterns_bundle();
		if ( is_wp_error( $bundle ) ) {
			add_action(
				'admin_notices',
				function () use ( $bundle ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $bundle->get_error_message() ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
			return;
		}

		update_option( 'gutenblock_bridge_pattern_bundle', $bundle, false );
		add_action(
			'admin_notices',
			function () use ( $bundle ) {
				$count = isset( $bundle['patterns'] ) && is_array( $bundle['patterns'] ) ? count( $bundle['patterns'] ) : 0;
				$msg   = sprintf(
					/* translators: %d: number of patterns built */
					esc_html__( 'Pattern-Bundle neu gebaut. Patterns: %d', 'gutenblock-pro' ),
					(int) $count
				);
				echo '<div class="notice notice-success"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 * @param array                $manifest      Manifest.
	 * @param array<string,string> $url_map       Ersetzungen.
	 * @param bool                 $replace_home  Wenn true, wird `show_on_front`/`page_on_front`
	 *                                            auf die im Manifest markierte Home-Page gesetzt.
	 *                                            Andernfalls bleibt die bestehende Startseite der
	 *                                            Zielinstanz unverändert (Seite wird trotzdem als
	 *                                            normale Page importiert).
	 */
	private function apply_pages( array $manifest, array $url_map, bool $replace_home = false ): void {
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

		if ( ! $replace_home ) {
			return;
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
	 * Customizer als CSS-Datei in uploads (Farben, Schriften, Gewicht,
	 * semantische Schriftgrößen). Google-Fonts-URL wird separat als Option
	 * gespeichert und beim Frontend-Enqueue zuerst geladen.
	 *
	 * @param array $manifest Manifest.
	 */
	private function apply_customizer_css( array $manifest ): void {
		if ( empty( $manifest['customizer'] ) || ! is_array( $manifest['customizer'] ) ) {
			return;
		}
		$c        = $manifest['customizer'];
		$colors   = isset( $c['colors'] ) && is_array( $c['colors'] ) ? $c['colors'] : array();
		$fonts    = isset( $c['fonts'] ) && is_array( $c['fonts'] ) ? $c['fonts'] : array();
		$semantic = isset( $c['semanticFontSizes'] ) && is_array( $c['semanticFontSizes'] ) ? $c['semanticFontSizes'] : array();

		$lines = array();
		$lines[] = '/* GutenBlock SaaS Customizer – generated ' . gmdate( 'c' ) . ' */';

		// Farben
		$mapc = array(
			'base'     => '--wp--preset--color--base',
			'contrast' => '--wp--preset--color--contrast',
			'primary'  => '--wp--preset--color--primary',
			'tertiary' => '--wp--preset--color--tertiary',
		);
		$color_rules = array();
		foreach ( $mapc as $k => $var ) {
			if ( ! empty( $colors[ $k ] ) ) {
				$color_rules[] = '  ' . $var . ': ' . esc_attr( (string) $colors[ $k ] ) . ';';
			}
		}
		if ( $color_rules ) {
			$lines[] = ':root {';
			$lines   = array_merge( $lines, $color_rules );
			$lines[] = '}';
		}

		// Schriften
		$heading_family = isset( $fonts['heading'] ) ? trim( (string) $fonts['heading'] ) : '';
		$body_family    = isset( $fonts['body'] ) ? trim( (string) $fonts['body'] ) : '';
		$heading_weight = isset( $fonts['headingWeight'] ) ? (int) $fonts['headingWeight'] : 0;

		$heading_sel = 'h1, h2, h3, h4, h5, h6, .wp-block-heading, .wp-block-post-title';
		$body_sel    = 'body, p, li, blockquote, .wp-block-paragraph, .wp-block-list';

		if ( $body_family ) {
			$lines[] = $body_sel . ' { font-family: ' . $this->safe_font_family( $body_family ) . ' !important; }';
		}
		if ( $heading_family ) {
			$lines[] = $heading_sel . ' { font-family: ' . $this->safe_font_family( $heading_family ) . ' !important; }';
		}
		if ( $heading_weight >= 100 && $heading_weight <= 900 ) {
			$lines[] = $heading_sel . ' { font-weight: ' . $heading_weight . ' !important; }';
		}

		// Semantische Schriftgrößen (CSS-Werte, üblicherweise `var(--wp--preset--font-size--*)` oder `clamp(...)`)
		$size_map = array(
			'h1' => 'h1, h1.wp-block-heading',
			'h2' => 'h2, h2.wp-block-heading',
			'h3' => 'h3, h3.wp-block-heading',
			'h4' => 'h4, h4.wp-block-heading',
			'p'  => 'p, .wp-block-paragraph',
		);
		foreach ( $size_map as $key => $sel ) {
			if ( ! empty( $semantic[ $key ] ) ) {
				$value = (string) $semantic[ $key ];
				// Nur ungefährliche Zeichen erlauben.
				if ( preg_match( '/^[A-Za-z0-9\\.\\(\\)\\-\\,\\s%#_\\*\\/]+$/', $value ) ) {
					$lines[] = $sel . ' { font-size: ' . $value . ' !important; }';
				}
			}
		}

		$css = implode( "\n", $lines ) . "\n";

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

		// Google-Fonts separat enqueuen (besser als @import).
		$gf_url = isset( $fonts['googleFontsUrl'] ) ? trim( (string) $fonts['googleFontsUrl'] ) : '';
		if ( $gf_url && preg_match( '#^https?://fonts\\.googleapis\\.com/#i', $gf_url ) ) {
			update_option( self::OPTION_CUSTOMIZER_FONTS, $gf_url );
		} else {
			delete_option( self::OPTION_CUSTOMIZER_FONTS );
		}
	}

	/**
	 * Sanitisiert einen `font-family`-Wert für CSS-Output.
	 *
	 * @param string $family Familie ggf. mit Fallback (Comma-Liste).
	 * @return string
	 */
	private function safe_font_family( string $family ): string {
		$clean = preg_replace( '/[<>{};]+/', '', $family );
		return is_string( $clean ) ? $clean : '';
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
