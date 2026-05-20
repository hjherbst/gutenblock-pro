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
 * Admin UI + Import-Logik (Provisioning-Token).
 */
class GutenBlock_Pro_Provisioning_Wizard {

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
		return 'https://gutenblock.com';
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

		$last               = (string) get_option( 'gutenblock_pro_last_manifest_sync', '' );
		$last_pages         = (int) get_option( 'gutenblock_pro_last_manifest_pages_count', 0 );
		$styles_active      = (bool) get_option( 'gutenblock_pro_customizer_css_url', '' );
		$can_rebuild_bundle = $this->current_user_can_rebuild_pattern_bundle();

		$this->render_admin_styles();

		echo '<div class="wrap gbp-import-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Import von gutenblock.com', 'gutenblock-pro' ) . '</h1>';
		echo '<p class="description gbp-lead">' . esc_html__( 'Hol deine bei gutenblock.com gestaltete Site in diese WordPress-Installation: Seiten, Header/Footer, Menü und Medien werden übernommen.', 'gutenblock-pro' ) . '</p>';

		// ── Karte: Status ────────────────────────────────────────────────
		echo '<div class="gbp-card gbp-card-status">';
		echo '<div class="gbp-card-head"><h2>' . esc_html__( 'Status', 'gutenblock-pro' ) . '</h2></div>';
		echo '<div class="gbp-status-grid">';
		echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Letzter Import', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $last ? esc_html( $last ) : '<em>—</em>' ) . '</span></div>';
		echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Seiten zuletzt', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $last_pages ? (int) $last_pages : '<em>—</em>' ) . '</span></div>';
		echo '<div class="gbp-stat"><span class="gbp-stat-label">' . esc_html__( 'Importierte Styles aktiv', 'gutenblock-pro' ) . '</span><span class="gbp-stat-value">' . ( $styles_active ? '<span class="gbp-pill gbp-pill-on">' . esc_html__( 'Aktiv', 'gutenblock-pro' ) . '</span>' : '<span class="gbp-pill gbp-pill-off">' . esc_html__( 'Aus', 'gutenblock-pro' ) . '</span>' ) . '</span></div>';
		echo '</div>';
		echo '</div>';

		// ── Karte: Import-Formular ───────────────────────────────────────
		echo '<div class="gbp-card">';
		echo '<div class="gbp-card-head"><h2>' . esc_html__( 'Import starten', 'gutenblock-pro' ) . '</h2></div>';
		echo '<p class="gbp-card-intro">' . esc_html__( 'Trage den Provisioning-Token aus deinem gutenblock.com-Projekt (Site → WordPress verbinden) ein.', 'gutenblock-pro' ) . '</p>';
		echo '<p class="gbp-card-note">' . esc_html__( 'Hinweis: Existieren bereits Seiten mit denselben Slugs, sichert WordPress die bisherige Version als Revision. Du kannst sie jederzeit über „Seite bearbeiten → Revisionen" wiederherstellen.', 'gutenblock-pro' ) . '</p>';

		echo '<form method="post" action="" class="gbp-form">';
		wp_nonce_field( 'gutenblock_provision', 'gutenblock_provision_nonce' );

		echo '<div class="gbp-field">';
		echo '<label for="gutenblock_token">' . esc_html__( 'Provisioning-Token', 'gutenblock-pro' ) . '</label>';
		echo '<input type="text" class="large-text code" id="gutenblock_token" name="gutenblock_token" value="" autocomplete="off" placeholder="' . esc_attr__( '64-stelliger Hex-Token aus deinem gutenblock.com-Projekt', 'gutenblock-pro' ) . '" />';
		echo '</div>';

		// Optionaler Styles-Override
		echo '<div class="gbp-option gbp-styles-toggle">';
		echo '<label class="gbp-checkbox">';
		echo '<input type="checkbox" name="gutenblock_import_styles" value="1" />';
		echo '<span class="gbp-checkbox-text">' . esc_html__( 'Styles von gutenblock.com übernehmen', 'gutenblock-pro' ) . '</span>';
		echo '</label>';
		echo '<div class="gbp-warn">';
		echo '<strong>' . esc_html__( 'Hinweis:', 'gutenblock-pro' ) . '</strong> ';
		echo esc_html__( 'Aktiviert ersetzt diese Option Farben, Schriften (inkl. Heading-Weight) und semantische Schriftgrößen (H1–H4, Absatz) deiner Site durch die in deinem gutenblock.com-Projekt festgelegten Werte. Diese werden als zusätzliches Stylesheet eingehängt und überschreiben Theme-Defaults via `!important`. Bestehende Block-individuelle Overrides bleiben erhalten.', 'gutenblock-pro' );
		echo '</div>';
		echo '</div>';

		// Opt-in: replace current WordPress home page with the gutenblock.com home (otherwise keep it).
		echo '<div class="gbp-option gbp-replace-home-toggle">';
		echo '<label class="gbp-checkbox">';
		echo '<input type="checkbox" name="gutenblock_replace_home" value="1" />';
		echo '<span class="gbp-checkbox-text">' . esc_html__( 'Startseite ersetzen', 'gutenblock-pro' ) . '</span>';
		echo '</label>';
		echo '<div class="gbp-warn">';
		echo '<strong>' . esc_html__( 'Hinweis:', 'gutenblock-pro' ) . '</strong> ';
		echo esc_html__( 'Aktiviert ersetzt diese Option deine aktuelle WordPress-Startseite durch die bei gutenblock.com gestaltete Startseite. Lasse die Option deaktiviert, wenn deine bestehende Startseite erhalten bleiben soll – alle weiteren Seiten werden in beiden Fällen importiert.', 'gutenblock-pro' );
		echo '</div>';
		echo '</div>';

		// Opt-in: Header als Template-Part anlegen/aktivieren.
		echo '<div class="gbp-option gbp-import-header-toggle">';
		echo '<label class="gbp-checkbox">';
		echo '<input type="checkbox" name="gutenblock_import_header" value="1" />';
		echo '<span class="gbp-checkbox-text">' . esc_html__( 'Header von gutenblock.com importieren', 'gutenblock-pro' ) . '</span>';
		echo '</label>';
		echo '<div class="gbp-warn">';
		echo esc_html__( 'Aktiviert erstellt einen neuen Header-Template-Part neben den bestehenden. Vorherige Importe von gutenblock.com werden ersetzt; eigene oder andere Header bleiben erhalten.', 'gutenblock-pro' );
		echo '</div>';
		echo '</div>';

		// Opt-in: Footer als Template-Part anlegen/aktivieren.
		echo '<div class="gbp-option gbp-import-footer-toggle">';
		echo '<label class="gbp-checkbox">';
		echo '<input type="checkbox" name="gutenblock_import_footer" value="1" />';
		echo '<span class="gbp-checkbox-text">' . esc_html__( 'Footer von gutenblock.com importieren', 'gutenblock-pro' ) . '</span>';
		echo '</label>';
		echo '<div class="gbp-warn">';
		echo esc_html__( 'Aktiviert erstellt einen neuen Footer-Template-Part neben den bestehenden. Vorherige Importe von gutenblock.com werden ersetzt; eigene oder andere Footer bleiben erhalten.', 'gutenblock-pro' );
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
			echo '<p class="gbp-card-intro">' . esc_html__( 'Statisches Pattern-Bundle für gutenblock.com neu bauen.', 'gutenblock-pro' ) . '</p>';
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
			. '.gbp-card-note{margin:-8px 0 14px;padding:8px 12px;border-left:3px solid #b9c0c7;background:#f6f7f7;color:#3c434a;font-size:12px;border-radius:4px;}'
			. '.gbp-form .gbp-field{margin:0 0 14px;}'
			. '.gbp-form label{display:block;font-size:12px;font-weight:600;color:#1d2327;margin-bottom:4px;}'
			. '.gbp-form input[type=text].large-text{width:100%;max-width:680px;}'
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

		if ( strlen( $token ) < 16 ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Bitte gültigen Token einfügen.', 'gutenblock-pro' ) . '</p></div>';
				}
			);
			return;
		}

		$base = self::default_saas_base();

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
		$import_header = ! empty( $_POST['gutenblock_import_header'] );
		$import_footer = ! empty( $_POST['gutenblock_import_footer'] );
		update_option( self::OPTION_IMPORT_STYLES, $import_styles ? 1 : 0 );

		$url_map = $this->import_assets( $data );

		$this->apply_pages( $data, $url_map, $replace_home );
		$this->apply_menu( $data );
		if ( $import_styles ) {
			$this->apply_customizer_css( $data );
		} else {
			$this->clear_customizer_css();
		}
		// Legacy: Options-basierte Speicherung beibehalten (für ältere Themes,
		// die `gutenblock_pro_*_pattern_markup` direkt lesen).
		$this->apply_header_footer_options( $data, $url_map );
		// Neu: optionaler Import als echte `wp_template_part`-Posts.
		$this->apply_header_footer_template_parts(
			$data,
			$url_map,
			array(
				'header' => $import_header,
				'footer' => $import_footer,
			)
		);

		$pages_count = isset( $data['pages'] ) && is_array( $data['pages'] ) ? count( $data['pages'] ) : 0;
		update_option( 'gutenblock_pro_last_manifest_sync', current_time( 'mysql' ) );
		update_option( 'gutenblock_pro_last_manifest_pages_count', $pages_count );

		add_action(
			'admin_notices',
			function () use ( $pages_count, $import_styles, $replace_home, $import_header, $import_footer ) {
				$chrome_parts = array();
				if ( $import_header ) {
					$chrome_parts[] = esc_html__( 'Header', 'gutenblock-pro' );
				}
				if ( $import_footer ) {
					$chrome_parts[] = esc_html__( 'Footer', 'gutenblock-pro' );
				}
				$chrome_label = $chrome_parts
					? implode( ' + ', $chrome_parts )
					: esc_html__( '–', 'gutenblock-pro' );
				$msg = sprintf(
					/* translators: 1: pages count, 2: styles state, 3: home state, 4: imported chrome parts */
					esc_html__( 'Import erfolgreich. Seiten: %1$d · Styles: %2$s · Startseite: %3$s · Template-Parts: %4$s', 'gutenblock-pro' ),
					(int) $pages_count,
					$import_styles ? esc_html__( 'übernommen', 'gutenblock-pro' ) : esc_html__( 'unverändert', 'gutenblock-pro' ),
					$replace_home ? esc_html__( 'ersetzt', 'gutenblock-pro' ) : esc_html__( 'beibehalten', 'gutenblock-pro' ),
					$chrome_label
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

		$this->debug_log( sprintf( 'import_assets: %d candidates', count( $assets ) ) );

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
					$this->debug_log( sprintf( 'import_assets: sideloaded %s → %s (att=%d, role=%s)', $url, $new_url, $att_id, isset( $row['role'] ) ? $row['role'] : '' ) );
				}
			} else {
				$this->debug_log( sprintf( 'import_assets: sideload FAILED for %s (role=%s)', $url, isset( $row['role'] ) ? $row['role'] : '' ) );
			}
		}

		return $map;
	}

	/**
	 * Lightweight debug logger. Writes only when WP_DEBUG is enabled so
	 * production sites stay silent. Each line is prefixed for easy `grep`.
	 *
	 * @param string $message Log message.
	 */
	private function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[gutenblock-pro/provisioning] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
				// Force-capture the current state as a revision so the user can
				// roll back via Editor → Revisions if they had hand-edited the
				// page before this import. `wp_update_post()` would normally
				// create a revision too, but only when WP_POST_REVISIONS and the
				// post type's `revisions` support are both enabled — making the
				// snapshot explicit closes that gap and is idempotent (WP
				// dedupes identical revisions).
				if ( post_type_supports( 'page', 'revisions' ) ) {
					wp_save_post_revision( (int) $existing->ID );
				}
				$postarr['ID'] = (int) $existing->ID;
				wp_update_post( $postarr );
				update_post_meta( (int) $existing->ID, '_gutenblock_pro_last_import', current_time( 'mysql' ) );
			} else {
				$new_id = wp_insert_post( $postarr );
				if ( $new_id && ! is_wp_error( $new_id ) ) {
					update_post_meta( (int) $new_id, '_gutenblock_pro_last_import', current_time( 'mysql' ) );
				}
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
			$markup = $this->apply_text_fields_to_markup( $markup, $fields );
			// Apply user-supplied per-section image overrides (AI generations,
			// uploads, library picks) BEFORE the global URL map kicks in. The
			// remote URL is then replaced again by the local Media Library URL
			// during `apply_pages()` via the sideload `$url_map`.
			if ( ! empty( $section['imageOverrides'] ) && is_array( $section['imageOverrides'] ) ) {
				$markup = $this->apply_image_overrides_to_markup( $markup, $section['imageOverrides'] );
			}
			$out .= $markup . "\n\n";
		}
		return $out;
	}

	/**
	 * Replaces the n-th `<img>` inside a section's block markup with a
	 * per-section override URL coming from the manifest. Mirrors the SaaS DOM
	 * renderer's image indexing (document order of `<img>` inside the
	 * section). Also patches matching `"url":"OLD"` attributes inside Gutenberg
	 * block comments so subsequent editor saves keep the new image. The new
	 * remote URL is mapped to a local attachment URL later via `$url_map`.
	 *
	 * @param string $markup    Block markup for one section.
	 * @param array  $overrides Array of `{ imgIndex: int, url: string }`.
	 * @return string
	 */
	private function apply_image_overrides_to_markup( string $markup, array $overrides ): string {
		if ( '' === $markup || empty( $overrides ) ) {
			return $markup;
		}
		// Build imgIndex => url lookup; ignore malformed entries.
		$by_index = array();
		foreach ( $overrides as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$idx = isset( $entry['imgIndex'] ) ? (int) $entry['imgIndex'] : -1;
			$url = isset( $entry['url'] ) ? (string) $entry['url'] : '';
			if ( $idx < 0 || '' === $url ) {
				continue;
			}
			$by_index[ $idx ] = $url;
		}
		if ( empty( $by_index ) ) {
			return $markup;
		}
		$this->debug_log( sprintf( 'apply_image_overrides: %d overrides, markup length=%d', count( $by_index ), strlen( $markup ) ) );

		// 1) Patch the rendered <img> tags using WP_HTML_Tag_Processor (WP 6.2+)
		//    so the public frontend immediately shows the new image even before
		//    the URL map rewrites the URL to its local equivalent.
		$old_urls = array();
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $markup );
			$cursor    = 0;
			while ( $processor->next_tag( 'img' ) ) {
				if ( isset( $by_index[ $cursor ] ) ) {
					$current = (string) $processor->get_attribute( 'src' );
					if ( '' !== $current ) {
						$old_urls[ $cursor ] = $current;
					}
					$processor->set_attribute( 'src', $by_index[ $cursor ] );
					// Drop responsive sources, otherwise the browser may pick a
					// stale Unsplash/stock URL from `srcset` and ignore `src`.
					$processor->remove_attribute( 'srcset' );
					$processor->remove_attribute( 'sizes' );
					$processor->remove_attribute( 'data-gb-image-original' );
					$this->debug_log( sprintf( 'apply_image_overrides: img[%d] %s → %s', $cursor, $current, $by_index[ $cursor ] ) );
				}
				$cursor++;
			}
			$this->debug_log( sprintf( 'apply_image_overrides: scanned %d <img> tags via WP_HTML_Tag_Processor', $cursor ) );
			$markup = $processor->get_updated_html();
		} else {
			// Fallback for very old WordPress versions: rewrite via regex.
			$cursor_ref = 0;
			$markup     = preg_replace_callback(
				'/<img\b[^>]*>/i',
				static function ( $m ) use ( &$cursor_ref, $by_index, &$old_urls ) {
					$tag = $m[0];
					if ( isset( $by_index[ $cursor_ref ] ) ) {
						if ( preg_match( '/\bsrc="([^"]*)"/i', $tag, $sm ) ) {
							$old_urls[ $cursor_ref ] = $sm[1];
						}
						$tag = preg_replace( '/\bsrc="[^"]*"/i', 'src="' . esc_attr( $by_index[ $cursor_ref ] ) . '"', $tag );
						$tag = preg_replace( '/\bsrcset="[^"]*"/i', '', $tag );
						$tag = preg_replace( '/\bsizes="[^"]*"/i', '', $tag );
					}
					$cursor_ref++;
					return $tag;
				},
				$markup
			);
		}

		// 2) Block comments carry their own `"url":"..."` attribute for
		//    `core/image`, `core/cover` etc. Replace each captured old URL
		//    inside the markup so a later editor "Update" keeps the override
		//    instead of reverting to the pattern's stock URL. Scoped to this
		//    section's markup only, so we don't touch unrelated occurrences.
		foreach ( $old_urls as $idx => $old ) {
			if ( ! isset( $by_index[ $idx ] ) || '' === $old ) {
				continue;
			}
			$new = $by_index[ $idx ];
			if ( $old === $new ) {
				continue;
			}
			$markup = str_replace( $old, $new, $markup );
		}

		return $markup;
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
		$lines[] = '/* GutenBlock Customizer – generated ' . gmdate( 'c' ) . ' */';

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
	 * Legacy-Pfad: Markup als Option speichern. URL-Map wird angewendet, damit
	 * lokal sideloadete Assets greifen.
	 *
	 * @param array                $manifest Manifest.
	 * @param array<string,string> $url_map  Optional: remoteUrl → lokale URL.
	 */
	private function apply_header_footer_options( array $manifest, array $url_map = array() ): void {
		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( empty( $manifest[ $part ] ) || ! is_array( $manifest[ $part ] ) ) {
				continue;
			}
			$markup = $this->build_chrome_markup( $manifest[ $part ], $url_map );
			if ( '' !== $markup ) {
				update_option( 'gutenblock_pro_' . $part . '_pattern_markup', $markup );
			}
		}
	}

	/**
	 * Optionaler Import als echte FSE-Template-Parts. Pro Part (Header/Footer)
	 * wird beim Aktiv-Setzen ein neuer `wp_template_part`-Post angelegt und
	 * mit den Metas `_gutenblock_saas_import = 1` und
	 * `_gutenblock_saas_import_version` markiert. Frühere SaaS-Importe werden
	 * vor dem Insert in den Papierkorb verschoben — sodass nur der neue
	 * Import aktiv ist. Nicht-SaaS Header/Footer (Theme-Defaults oder eigene
	 * Anpassungen) bleiben unangetastet.
	 *
	 * @param array                $manifest Manifest.
	 * @param array<string,string> $url_map  remoteUrl → lokale URL.
	 * @param array{header:bool,footer:bool} $enabled Welche Teile importieren.
	 */
	private function apply_header_footer_template_parts( array $manifest, array $url_map, array $enabled ): void {
		$theme_slug = (string) get_stylesheet();

		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( empty( $enabled[ $part ] ) ) {
				continue;
			}
			if ( empty( $manifest[ $part ] ) || ! is_array( $manifest[ $part ] ) ) {
				continue;
			}
			$markup = $this->build_chrome_markup( $manifest[ $part ], $url_map );
			if ( '' === $markup ) {
				continue;
			}

			// Frühere SaaS-Importe in derselben Area in den Papierkorb verschieben.
			$previous = $this->find_saas_template_parts( $part );
			foreach ( $previous as $prev_id ) {
				wp_trash_post( $prev_id );
			}

			$timestamp = current_time( 'mysql' );
			$slug      = 'gbp-saas-' . $part . '-' . gmdate( 'YmdHis' );
			$title     = ucfirst( $part ) . ' (GutenBlock Import)';

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'wp_template_part',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_content' => $markup,
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			// Area-Taxonomie setzen (Header/Footer) — vom Block-Editor erwartet.
			wp_set_object_terms( $post_id, $part, 'wp_template_part_area', false );
			// Theme-Zuordnung — Template-Parts werden je Theme verwaltet.
			if ( $theme_slug ) {
				wp_set_object_terms( $post_id, $theme_slug, 'wp_theme', false );
			}
			// Markierung als SaaS-Import (für künftiges Auto-Replace).
			update_post_meta( $post_id, '_gutenblock_saas_import', 1 );
			update_post_meta( $post_id, '_gutenblock_saas_import_version', $timestamp );
			update_post_meta( $post_id, '_gutenblock_saas_import_area', $part );
		}
	}

	/**
	 * Findet bestehende Template-Part-Posts in einer Area, die aus einem
	 * früheren SaaS-Import stammen.
	 *
	 * @param string $area 'header'|'footer'.
	 * @return int[] Post-IDs.
	 */
	private function find_saas_template_parts( string $area ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_gutenblock_saas_import',
						'value' => '1',
					),
					array(
						'key'   => '_gutenblock_saas_import_area',
						'value' => $area,
					),
				),
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
				'suppress_filters' => false,
			)
		);
		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Erzeugt das finale Block-Markup für einen Chrome-Slot. Bevorzugt
	 * `blockMarkup` aus dem Manifest, fällt sonst auf das lokale Pattern
	 * (`patterns/{slug}/content.html`) zurück. URL-Rewriting wird zuletzt
	 * angewendet — der SaaS-Bildhost wird so durch lokale Attachment-URLs
	 * ersetzt.
	 *
	 * @param array                $chrome  Manifest-Eintrag (header/footer).
	 * @param array<string,string> $url_map remoteUrl → lokale URL.
	 * @return string
	 */
	private function build_chrome_markup( array $chrome, array $url_map ): string {
		$markup = '';
		if ( ! empty( $chrome['blockMarkup'] ) ) {
			$markup = (string) $chrome['blockMarkup'];
		} elseif ( ! empty( $chrome['patternSlug'] ) ) {
			$markup = $this->pattern_file_markup( (string) $chrome['patternSlug'] );
		}
		if ( '' === $markup ) {
			return '';
		}
		// Apply SaaS-side image overrides before the URL map rewrites the
		// remote URLs to local Media Library URLs (same order as
		// `assemble_page_from_sections`).
		if ( ! empty( $chrome['imageOverrides'] ) && is_array( $chrome['imageOverrides'] ) ) {
			$markup = $this->apply_image_overrides_to_markup( $markup, $chrome['imageOverrides'] );
		}
		if ( ! empty( $url_map ) ) {
			$markup = strtr( $markup, $url_map );
		}
		return $markup;
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
