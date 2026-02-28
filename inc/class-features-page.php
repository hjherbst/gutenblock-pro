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

	const OPTION_NAME = 'gutenblock_pro_features';

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
		$saved = get_option( self::OPTION_NAME, array() );
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
			.gbp-feature-card .gbp-feature-toggle { margin-top: auto; }
			.gbp-features-form .submit { margin-top: 1.5rem; }
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
		$features_state = self::get_feature_states();
		$features_list  = $this->get_features();
		?>
		<div class="wrap gbp-features-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Aktiviere oder deaktiviere optionale Funktionen von GutenBlock Pro.', 'gutenblock-pro' ); ?></p>

			<form method="post" action="options.php" class="gbp-features-form">
				<?php settings_fields( 'gutenblock_pro_features' ); ?>
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
				<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
			</form>
		</div>
		<?php
	}
}
