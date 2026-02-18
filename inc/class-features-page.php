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
			);
		}
		return $out;
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
	 * Default feature states
	 *
	 * @return array
	 */
	private function get_defaults() {
		$defaults = array();
		foreach ( array_keys( $this->get_feature_definitions() ) as $key ) {
			$defaults[ $key ] = false;
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
			.gbp-feature-row { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
			.gbp-feature-row .gbp-feature-toggle { flex-shrink: 0; }
			.gbp-feature-content h3 { margin: 0 0 0.25rem 0; }
			.gbp-feature-content p { margin: 0; color: #646970; }
			.gbp-feature-notice { margin-top: 0.5rem; padding: 0.5rem 0.75rem; background: #f0f0f1; border-left: 4px solid #dba617; font-size: 13px; }
			.gbp-features-form .submit { margin-top: 0; }
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
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $features_list as $key => $feature ) : ?>
						<?php $enabled = ! empty( $features_state[ $key ] ); ?>
						<tr>
							<td>
								<div class="gbp-feature-row">
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
									<div class="gbp-feature-content">
										<h3><?php echo esc_html( $feature['name'] ); ?></h3>
										<p><?php echo esc_html( $feature['description'] ); ?></p>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
			</form>
		</div>
		<?php
	}
}
