<?php
/**
 * Features Page - Toggle optional GutenBlock Pro features
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Features_Page {

	const OPTION_NAME     = 'gutenblock_pro_features';
	const OPTION_VARIANTS = 'gutenblock_pro_block_variants';

	/**
	 * Feature definitions (raw strings; translated in get_features()).
	 *
	 * @return array
	 */
	private function get_feature_definitions() {
		return array(
			'admin-bar' => array(
				'name'        => 'Admin Bar ersetzen',
				'description' => 'Ersetzt die WordPress Admin-Bar durch ein kleines schwebendes Icon unten rechts mit kontextabhängigen Bearbeitungslinks.',
			),
			'container-forms' => array(
				'name'        => 'Container-Formen',
				'description' => 'Ermöglicht Abschlussformen (z. B. Welle, Diagonale) oben oder unten an Gruppen-Blöcken als Stilvarianten.',
			),
			'material-icons' => array(
				'name'        => 'Material Icons Block',
				'description' => 'Custom Block für Google Material Symbols mit Inline-SVG, Suche und Größen-/Farbsteuerung.',
			),
			'horizontal-scroll' => array(
				'name'        => 'Horizontal Scroll',
				'description' => 'Horizontales Scrollen für Spalten-Blöcke mit Snap, Dots und Pfeilen.',
			),
		);
	}

	/**
	 * Get feature definitions with translated strings
	 *
	 * @return array
	 */
	private function get_features() {
		$out = array();
		foreach ( $this->get_feature_definitions() as $key => $def ) {
			$out[ $key ] = array(
				'name'        => __( $def['name'], 'gutenblock-pro' ),
				'description' => __( $def['description'], 'gutenblock-pro' ),
				'icon'        => $this->get_feature_icon( $key ),
			);
		}
		return $out;
	}

	/**
	 * SVG-Icon für ein Feature
	 *
	 * @param string $key Feature-Key.
	 * @return string
	 */
	private function get_feature_icon( $key ) {
		$icons = array(
			'admin-bar'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>',
			'container-forms'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 16" fill="currentColor"><path d="M0 16c8-4 16-4 24 0s16-4 24 0V0H0v16z"/></svg>',
			'material-icons'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
			'horizontal-scroll' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor"><path d="M100-160q-24 0-42-18t-18-42v-520q0-24 18-42t42-18h120q24 0 42 18t18 42v520q0 24-18 42t-42 18H100Zm0-59h120v-521H100v521Zm320 59q-24 0-42-18t-18-42v-520q0-24 18-42t42-18h440q24 0 42 18t18 42v520q0 24-18 42t-42 18H420Zm0-59h440v-521H420v521Zm-200 0v-521 521Zm200 0v-521 521Z"/></svg>',
		);
		return isset( $icons[ $key ] ) ? $icons[ $key ] : '';
	}

	// -------------------------------------------------------------------------
	// Block Variant Definitions
	// -------------------------------------------------------------------------

	/**
	 * SVG-Icon für eine Stilvariante
	 *
	 * @param string $slug Block-Varianten-Slug.
	 * @return string
	 */
	private function get_variant_icon( $slug ) {
		$icons = array(
			'button-simple' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8-8-8z"/></svg>',
			'button-arrow-circle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>',
			'space-between' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h18v2H3zm0 16h18v2H3zm4-4h10v2H7zm0-6h10v2H7z"/></svg>',
			'step-circle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
			'vertical-center' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 11h8V3H9v6H5V3H3zm8 2H3v8h2v-6h4v6h2zm2-2h8V3h-2v6h-4V3h-2zm8 2h-8v8h2v-6h4v6h2z"/></svg>',
			'checkmark-list' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>',
		);
		return isset( $icons[ $slug ] ) ? $icons[ $slug ] : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>';
	}

	/**
	 * Liest alle Block-Varianten aus dem blocks/-Verzeichnis
	 *
	 * @return array slug => ['name', 'label', 'description', 'block']
	 */
	private function get_block_variant_definitions() {
		// Statische Basis-Definitionen – immer sichtbar, auch wenn das Filesystem
		// (z. B. nach einem Deploy ohne blocks/-Verzeichnis) leer ist.
		$base = array(
			'button-simple'      => array(
				'label'       => 'Simple',
				'description' => 'Transparenter Button ohne Hintergrund – nur Text mit Pfeil-Icon und Hover-Animation',
				'block'       => 'core/button',
			),
			'button-arrow-circle' => array(
				'label'       => 'Arrow Circle',
				'description' => 'Pill-Button mit animiertem Kreis-Pfeil-Icon rechts',
				'block'       => 'core/button',
			),
			'checkmark-list'     => array(
				'label'       => 'Checkmark',
				'description' => 'Zeigt Checkmarks (✓) statt Bullets für alle Listenelemente',
				'block'       => 'core/list',
			),
			'space-between'      => array(
				'label'       => 'Space Between',
				'description' => 'Verteilt Kinder-Elemente gleichmäßig (justify-content: space-between)',
				'block'       => 'core/group',
			),
			'step-circle'        => array(
				'label'       => 'Step Circle',
				'description' => 'Zeigt nummerierte Schritt-Kreise in einer Gruppe',
				'block'       => 'core/group',
			),
			'vertical-center'    => array(
				'label'       => 'Vertical Center',
				'description' => 'Zentriert Kinder-Elemente vertikal (align-items: center)',
				'block'       => 'core/group',
			),
		);

		// Filesystem-Scan: überschreibt/ergänzt die Basis-Definitionen mit aktuellen Werten.
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;
		if ( is_dir( $blocks_dir ) ) {
			foreach ( glob( $blocks_dir . '*', GLOB_ONLYDIR ) as $folder ) {
				$slug        = basename( $folder );
				$config_file = $folder . '/block.json';

				if ( ! file_exists( $config_file ) ) {
					continue;
				}

				$config = json_decode( file_get_contents( $config_file ), true );
				if ( ! $config || empty( $config['block'] ) ) {
					continue;
				}

				$base[ $slug ] = array(
					'label'       => $config['label'] ?? $slug,
					'description' => $config['description'] ?? '',
					'block'       => $config['block'],
				);
			}
		}

		return $base;
	}

	/**
	 * Default-States für Stilvarianten (alle aktiv)
	 *
	 * @return array
	 */
	private function get_variant_defaults() {
		$defaults = array();
		foreach ( array_keys( $this->get_block_variant_definitions() ) as $slug ) {
			$defaults[ $slug ] = true;
		}
		return $defaults;
	}

	/**
	 * Aktuelle Toggle-Zustände der Stilvarianten
	 *
	 * @return array
	 */
	public static function get_block_variant_states() {
		$saved    = get_option( self::OPTION_VARIANTS, array() );
		$instance = new self();
		$defaults = $instance->get_variant_defaults();
		return array_merge( $defaults, $saved );
	}

	/**
	 * Prüft ob eine Stilvariante aktiviert ist
	 *
	 * @param string $slug Stilvarianten-Slug (= Ordnername in blocks/).
	 * @return bool
	 */
	public static function is_block_variant_enabled( $slug ) {
		$states = self::get_block_variant_states();
		// Unbekannte Slugs (neu hinzugefügte Varianten) gelten als aktiv
		return ! isset( $states[ $slug ] ) || ! empty( $states[ $slug ] );
	}

	/**
	 * Sanitize für Stilvarianten-Option
	 *
	 * @param array|mixed $value Raw POST value.
	 * @return array
	 */
	public function sanitize_variants( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->get_variant_defaults();
		}
		$out = array();
		foreach ( array_keys( $this->get_block_variant_definitions() ) as $slug ) {
			$out[ $slug ] = ! empty( $value[ $slug ] );
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// WordPress Hooks
	// -------------------------------------------------------------------------

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add Features submenu page
	 */
	public function add_submenu() {
		add_submenu_page(
			'gutenblock-pro',
			__( 'Features', 'gutenblock-pro' ),
			__( 'Features', 'gutenblock-pro' ),
			'manage_options',
			'gutenblock-pro-features',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'gutenblock_pro_features',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_features' ),
			)
		);
		register_setting(
			'gutenblock_pro_features',
			self::OPTION_VARIANTS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_variants' ),
			)
		);
	}

	/**
	 * Default feature states (alle aktiv bei Neuinstallation)
	 *
	 * @return array
	 */
	private function get_defaults() {
		$defaults = array();
		foreach ( array_keys( $this->get_feature_definitions() ) as $key ) {
			$defaults[ $key ] = true;
		}
		return $defaults;
	}

	/**
	 * Sanitize features array: only allow known keys and boolean values
	 *
	 * @param array|mixed $value Raw POST value.
	 * @return array
	 */
	public function sanitize_features( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->get_defaults();
		}
		$out = array();
		foreach ( array_keys( $this->get_feature_definitions() ) as $key ) {
			$out[ $key ] = ! empty( $value[ $key ] );
		}
		return $out;
	}

	/**
	 * Get current feature states (saved toggles)
	 *
	 * @return array
	 */
	public static function get_feature_states() {
		$saved    = get_option( self::OPTION_NAME, array() );
		$instance = new self();
		$defaults = $instance->get_defaults();
		return array_merge( $defaults, $saved );
	}

	/**
	 * Check if a feature is enabled
	 *
	 * @param string $key Feature key (e.g. 'admin-bar').
	 * @return bool
	 */
	public static function is_feature_enabled( $key ) {
		$features = self::get_feature_states();
		return ! empty( $features[ $key ] );
	}

	/**
	 * Enqueue assets only on Features page
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'gutenblock-pro_page_gutenblock-pro-features' !== $hook ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', $this->get_toggle_css() );
	}

	/**
	 * CSS for toggle switches (no JS required)
	 *
	 * @return string
	 */
	private function get_toggle_css() {
		return '
			.gbp-features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; max-width: 1200px; margin-top: 1.5rem; }
			.gbp-feature-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: 0 1px 1px rgba(0,0,0,.04); transition: box-shadow .15s; }
			.gbp-feature-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
			.gbp-feature-icon { width: 64px; height: 64px; margin-bottom: 1rem; color: #2271b1; }
			.gbp-feature-icon svg { width: 100%; height: 100%; }
			.gbp-feature-card h3 { margin: 0 0 0.5rem 0; font-size: 1.1em; }
			.gbp-feature-card p { margin: 0 0 1rem 0; color: #646970; font-size: 13px; line-height: 1.5; flex-grow: 1; }
			.gbp-feature-card .gbp-feature-badge { font-size: 11px; color: #646970; background: #f0f0f1; border-radius: 3px; padding: 1px 6px; display: inline-block; margin-bottom: 0.5rem; }
			.gbp-feature-card .gbp-feature-toggle { margin-top: auto; }
			.gbp-features-form .submit { margin-top: 1.5rem; }
			.gbp-features-section-title { margin: 2.5rem 0 0.5rem; font-size: 1.3em; border-bottom: 1px solid #c3c4c7; padding-bottom: 0.5rem; max-width: 1200px; }
			.gbp-toggle { position: relative; display: inline-block; width: 44px; height: 24px; }
			.gbp-toggle input { opacity: 0; width: 0; height: 0; }
			.gbp-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #c3c4c7; border-radius: 24px; transition: 0.2s; }
			.gbp-toggle-slider::before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.2); }
			.gbp-toggle input:checked + .gbp-toggle-slider { background: #2271b1; }
			.gbp-toggle input:checked + .gbp-toggle-slider::before { transform: translateX(20px); }
			.gbp-toggle input:disabled + .gbp-toggle-slider { opacity: 0.6; cursor: not-allowed; }
		';
	}

	/**
	 * Render Features page
	 */
	public function render_page() {
		$features_state  = self::get_feature_states();
		$features_list   = $this->get_features();
		$variants_state  = self::get_block_variant_states();
		$variants_list   = $this->get_block_variant_definitions();
		?>
		<div class="wrap gbp-features-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Aktiviere oder deaktiviere optionale Funktionen von GutenBlock Pro.', 'gutenblock-pro' ); ?></p>

			<form method="post" action="options.php" class="gbp-features-form">
				<?php settings_fields( 'gutenblock_pro_features' ); ?>

				<!-- Features -->
				<div class="gbp-features-grid">
				<?php foreach ( $features_list as $key => $feature ) : ?>
					<?php $enabled = ! empty( $features_state[ $key ] ); ?>
					<div class="gbp-feature-card">
						<div class="gbp-feature-icon"><?php echo $feature['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from plugin. ?></div>
						<h3><?php echo esc_html( $feature['name'] ); ?></h3>
						<p><?php echo esc_html( $feature['description'] ); ?></p>
						<div class="gbp-feature-toggle">
							<label class="gbp-toggle">
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
									value="1"
									<?php checked( $enabled ); ?>
								/>
								<span class="gbp-toggle-slider"></span>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
				</div>

				<!-- Stilvarianten für Blöcke -->
				<?php if ( ! empty( $variants_list ) ) : ?>
				<h2 class="gbp-features-section-title"><?php esc_html_e( 'Stilvarianten für Blöcke', 'gutenblock-pro' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Aktiviere oder deaktiviere einzelne Block-Stilvarianten. Deaktivierte Varianten werden weder registriert noch geladen.', 'gutenblock-pro' ); ?></p>
				<div class="gbp-features-grid">
				<?php foreach ( $variants_list as $slug => $variant ) : ?>
					<?php $enabled = self::is_block_variant_enabled( $slug ); ?>
					<div class="gbp-feature-card">
						<div class="gbp-feature-icon"><?php echo $this->get_variant_icon( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from plugin. ?></div>
						<span class="gbp-feature-badge"><?php echo esc_html( $variant['block'] ); ?></span>
						<h3><?php echo esc_html( $variant['label'] ); ?></h3>
						<?php if ( ! empty( $variant['description'] ) ) : ?>
						<p><?php echo esc_html( $variant['description'] ); ?></p>
						<?php endif; ?>
						<div class="gbp-feature-toggle">
							<label class="gbp-toggle">
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION_VARIANTS . '[' . $slug . ']' ); ?>"
									value="1"
									<?php checked( $enabled ); ?>
								/>
								<span class="gbp-toggle-slider"></span>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
				</div>
				<?php endif; ?>

			<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
		</form>

		<!-- KI-Übersetzungssprachen -->
		<h2 class="gbp-features-section-title"><?php esc_html_e( 'KI-Übersetzungssprachen', 'gutenblock-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Aktiviere die Sprachen, in die im Editor übersetzt werden kann. Jede aktivierte Sprache erscheint als Button in der Sidebar.', 'gutenblock-pro' ); ?></p>

		<form method="post" action="options.php" class="gbp-features-form">
			<?php settings_fields( 'gutenblock_pro_translations' ); ?>
			<div class="gbp-features-grid gbp-languages-grid">
			<?php
			$available_languages = GutenBlock_Pro_Translation_Settings::get_available_languages();
			$saved_languages     = get_option( GutenBlock_Pro_Translation_Settings::OPTION_NAME, array() );
			foreach ( $available_languages as $code => $meta ) :
				$lang_enabled = ! empty( $saved_languages[ $code ] );
			?>
				<div class="gbp-feature-card">
					<div class="gbp-feature-icon" style="font-size:1.6rem;line-height:1;">
						<?php echo esc_html( strtoupper( $code ) ); ?>
					</div>
					<h3>
						<?php echo esc_html( $meta['label'] ); ?>
						<small style="color:#646970;font-weight:400;">(<?php echo esc_html( strtoupper( $code ) ); ?>)</small>
					</h3>
					<div class="gbp-feature-toggle">
						<label class="gbp-toggle">
							<input type="checkbox"
								name="<?php echo esc_attr( GutenBlock_Pro_Translation_Settings::OPTION_NAME . '[' . $code . ']' ); ?>"
								value="1"
								<?php checked( $lang_enabled ); ?>
							/>
							<span class="gbp-toggle-slider"></span>
						</label>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
			<?php submit_button( __( 'Sprachen speichern', 'gutenblock-pro' ) ); ?>
		</form>
	</div>
	<?php
}
}
