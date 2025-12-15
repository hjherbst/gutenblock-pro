<?php
/**
 * AI Settings Page for GutenBlock Pro
 * 
 * Admin settings for license management, system prompt, and token tracking.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_AI_Settings {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * License instance
	 */
	private $license;

	/**
	 * AI Generator instance
	 */
	private $ai_generator;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize
	 */
	public function init() {
		$this->license = GutenBlock_Pro_License::get_instance();
		$this->ai_generator = GutenBlock_Pro_AI_Generator::get_instance();

		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gutenblock_pro_activate_license', array( $this, 'ajax_activate_license' ) );
		add_action( 'wp_ajax_gutenblock_pro_deactivate_license', array( $this, 'ajax_deactivate_license' ) );
		add_action( 'wp_ajax_gutenblock_pro_refresh_prompts', array( $this, 'ajax_refresh_prompts' ) );
	}

	/**
	 * Add submenu page
	 */
	public function add_submenu() {
		add_submenu_page(
			'gutenblock-pro',
			__( 'KI-Einstellungen', 'gutenblock-pro' ),
			__( 'KI-Einstellungen', 'gutenblock-pro' ),
			'manage_options',
			'gutenblock-pro-ai',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'gutenblock_pro_ai_settings', 'gutenblock_pro_system_prompt', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		) );
	}

	/**
	 * Enqueue assets
	 */
	public function enqueue_assets( $hook ) {
		if ( 'gutenblock-pro_page_gutenblock-pro-ai' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'gutenblock-pro-ai-settings',
			GUTENBLOCK_PRO_URL . 'assets/css/ai-settings.css',
			array(),
			GUTENBLOCK_PRO_VERSION
		);

		wp_enqueue_script(
			'gutenblock-pro-ai-settings',
			GUTENBLOCK_PRO_URL . 'assets/js/ai-settings.js',
			array( 'jquery' ),
			GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_localize_script( 'gutenblock-pro-ai-settings', 'gutenblockProAI', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gutenblock_pro_ai' ),
			'strings' => array(
				'activating'   => __( 'Aktiviere...', 'gutenblock-pro' ),
				'deactivating' => __( 'Deaktiviere...', 'gutenblock-pro' ),
				'refreshing'   => __( 'Lade Prompts...', 'gutenblock-pro' ),
			),
		) );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$license_info = $this->license->get_license_info();
		$token_usage = $this->license->get_token_usage();
		$system_prompt = get_option( 'gutenblock_pro_system_prompt', '' );
		$prompts = $this->ai_generator->get_prompts();
		?>
		<div class="wrap gutenblock-pro-ai-settings">
			<h1>
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'GutenBlock Pro - KI-Einstellungen', 'gutenblock-pro' ); ?>
			</h1>

			<!-- License Section -->
			<div class="gb-settings-section">
				<h2><?php esc_html_e( 'Lizenz', 'gutenblock-pro' ); ?></h2>
				
				<div class="gb-license-box <?php echo $license_info['is_pro'] ? 'is-pro' : 'is-free'; ?>">
					<?php if ( $license_info['is_pro'] ) : ?>
						<div class="gb-license-status">
							<span class="gb-status-badge pro">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Pro-Lizenz aktiv', 'gutenblock-pro' ); ?>
							</span>
							<span class="gb-license-key"><?php echo esc_html( $this->license->get_masked_license_key() ); ?></span>
						</div>
						<button type="button" class="button" id="gb-deactivate-license">
							<?php esc_html_e( 'Lizenz deaktivieren', 'gutenblock-pro' ); ?>
						</button>
					<?php else : ?>
						<div class="gb-license-status">
							<span class="gb-status-badge free">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Kostenlose Version', 'gutenblock-pro' ); ?>
							</span>
						</div>
						<div class="gb-license-form">
							<input type="text" 
							       id="gb-license-key" 
							       placeholder="GBPRO-XXXX-XXXX-XXXX" 
							       pattern="GBPRO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}"
							       class="regular-text">
							<button type="button" class="button button-primary" id="gb-activate-license">
								<?php esc_html_e( 'Lizenz aktivieren', 'gutenblock-pro' ); ?>
							</button>
						</div>
						<p class="description">
							<?php 
							printf(
								/* translators: %s: link to gutenblock.com */
								esc_html__( 'Noch keine Lizenz? %s', 'gutenblock-pro' ),
								'<a href="https://gutenblock.com/pro" target="_blank">' . esc_html__( 'Jetzt kaufen', 'gutenblock-pro' ) . '</a>'
							); 
							?>
						</p>
					<?php endif; ?>
					<div id="gb-license-message" class="gb-message hidden"></div>
				</div>
			</div>

			<!-- Token Usage Section -->
			<div class="gb-settings-section">
				<h2><?php esc_html_e( 'Token-Verbrauch', 'gutenblock-pro' ); ?></h2>
				
				<div class="gb-token-box">
					<?php if ( $token_usage['is_pro'] ) : ?>
						<div class="gb-token-unlimited">
							<span class="dashicons dashicons-awards"></span>
							<span><?php esc_html_e( 'Unbegrenzte Tokens (Pro)', 'gutenblock-pro' ); ?></span>
						</div>
					<?php else : ?>
						<div class="gb-token-meter">
							<div class="gb-token-bar">
								<?php 
								$percentage = min( 100, ( $token_usage['used'] / $token_usage['limit'] ) * 100 );
								$bar_class = $percentage > 80 ? 'warning' : ( $percentage > 95 ? 'critical' : '' );
								?>
								<div class="gb-token-progress <?php echo esc_attr( $bar_class ); ?>" 
								     style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
							</div>
							<div class="gb-token-numbers">
								<span class="gb-token-used">
									<?php echo esc_html( number_format_i18n( $token_usage['used'] ) ); ?>
								</span>
								<span class="gb-token-separator">/</span>
								<span class="gb-token-limit">
									<?php echo esc_html( number_format_i18n( $token_usage['limit'] ) ); ?>
								</span>
								<span class="gb-token-label"><?php esc_html_e( 'Tokens', 'gutenblock-pro' ); ?></span>
							</div>
						</div>
						<p class="description">
							<?php 
							printf(
								/* translators: %s: reset date */
								esc_html__( 'Verbleibend: %1$s Tokens. Reset: %2$s', 'gutenblock-pro' ),
								'<strong>' . number_format_i18n( $token_usage['remaining'] ) . '</strong>',
								'<strong>' . esc_html( date_i18n( 'j. F Y', strtotime( 'first day of next month' ) ) ) . '</strong>'
							); 
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- System Prompt Section -->
			<div class="gb-settings-section">
				<h2><?php esc_html_e( 'System-Prompt', 'gutenblock-pro' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Definiere den Grundton und Stil für alle KI-generierten Texte. Dieser Prompt wird bei jeder Generierung als Kontext verwendet.', 'gutenblock-pro' ); ?>
				</p>
				
				<form method="post" action="options.php">
					<?php settings_fields( 'gutenblock_pro_ai_settings' ); ?>
					
					<textarea name="gutenblock_pro_system_prompt" 
					          id="gutenblock_pro_system_prompt" 
					          rows="6" 
					          class="large-text code"
					          placeholder="<?php esc_attr_e( 'Du bist ein professioneller Copywriter für Webseiten...', 'gutenblock-pro' ); ?>"
					><?php echo esc_textarea( $system_prompt ); ?></textarea>
					
					<p class="description">
						<?php esc_html_e( 'Tipp: Beschreibe dein Unternehmen, deine Zielgruppe und den gewünschten Schreibstil.', 'gutenblock-pro' ); ?>
					</p>
					
					<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
				</form>
			</div>

			<!-- Block Prompts Section (read-only from API) -->
			<div class="gb-settings-section">
				<h2>
					<?php esc_html_e( 'Block-Prompts', 'gutenblock-pro' ); ?>
					<button type="button" class="button button-secondary button-small" id="gb-refresh-prompts">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Aktualisieren', 'gutenblock-pro' ); ?>
					</button>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Diese Prompts werden automatisch von gutenblock.com geladen und definieren, welcher Text für welches Block-Element generiert wird.', 'gutenblock-pro' ); ?>
				</p>

				<?php if ( ! empty( $prompts ) ) : ?>
					<table class="widefat gb-prompts-table">
						<thead>
							<tr>
								<th style="width: 25%;"><?php esc_html_e( 'Block-ID', 'gutenblock-pro' ); ?></th>
								<th style="width: 25%;"><?php esc_html_e( 'Name', 'gutenblock-pro' ); ?></th>
								<th><?php esc_html_e( 'Prompt', 'gutenblock-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $prompts as $field_id => $prompt_data ) : ?>
								<tr>
									<td><code><?php echo esc_html( $field_id ); ?></code></td>
									<td><?php echo esc_html( $prompt_data['name'] ?? $field_id ); ?></td>
									<td class="gb-prompt-text"><?php echo esc_html( $prompt_data['prompt'] ?? '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="notice notice-info inline">
						<p><?php esc_html_e( 'Keine Prompts geladen. Klicke auf "Aktualisieren" um die Prompts von gutenblock.com zu laden.', 'gutenblock-pro' ); ?></p>
					</div>
				<?php endif; ?>
				<div id="gb-prompts-message" class="gb-message hidden"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Activate license
	 */
	public function ajax_activate_license() {
		check_ajax_referer( 'gutenblock_pro_ai', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'gutenblock-pro' ) ) );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

		if ( empty( $license_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte Lizenzschlüssel eingeben', 'gutenblock-pro' ) ) );
		}

		$result = $this->license->activate( $license_key );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Deactivate license
	 */
	public function ajax_deactivate_license() {
		check_ajax_referer( 'gutenblock_pro_ai', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'gutenblock-pro' ) ) );
		}

		$result = $this->license->deactivate();
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Refresh prompts from API
	 */
	public function ajax_refresh_prompts() {
		check_ajax_referer( 'gutenblock_pro_ai', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'gutenblock-pro' ) ) );
		}

		$prompts = $this->ai_generator->refresh_prompts();

		if ( ! empty( $prompts ) ) {
			wp_send_json_success( array(
				'message' => __( 'Prompts erfolgreich aktualisiert', 'gutenblock-pro' ),
				'count'   => count( $prompts ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Keine Prompts gefunden. Prüfe die Verbindung zu gutenblock.com', 'gutenblock-pro' ),
			) );
		}
	}
}
