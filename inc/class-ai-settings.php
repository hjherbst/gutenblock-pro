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
		add_action( 'admin_menu', array( $this, 'add_license_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gutenblock_pro_activate_license', array( $this, 'ajax_activate_license' ) );
		add_action( 'wp_ajax_gutenblock_pro_deactivate_license', array( $this, 'ajax_deactivate_license' ) );
		add_action( 'wp_ajax_gutenblock_pro_refresh_prompts', array( $this, 'ajax_refresh_prompts' ) );
		add_action( 'wp_ajax_gutenblock_pro_save_custom_prompts', array( $this, 'ajax_save_custom_prompts' ) );
	}

	/**
	 * Add Prompts submenu page
	 */
	public function add_submenu() {
		$title = __( 'Prompts', 'gutenblock-pro' );
		$context = get_option( 'gutenblock_pro_ai_context', '' );
		if ( trim( (string) $context ) === '' ) {
			$title .= ' <span class="awaiting-mod">1</span>';
		}

		add_submenu_page(
			'gutenblock-pro',
			__( 'Prompts', 'gutenblock-pro' ),
			$title,
			'manage_options',
			'gutenblock-pro-ai',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add Lizenz/Licence submenu page
	 */
	public function add_license_submenu() {
		$locale = get_locale();
		$label  = ( strpos( $locale, 'de' ) === 0 )
			? __( 'Lizenz', 'gutenblock-pro' )
			: __( 'Licence', 'gutenblock-pro' );

		add_submenu_page(
			'gutenblock-pro',
			$label,
			$label,
			'manage_options',
			'gutenblock-pro-license',
			array( $this, 'render_license_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'gutenblock_pro_ai_settings', 'gutenblock_pro_system_prompt', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_system_prompt' ),
			'default'           => 'Texte Elemente für eine Website.
Einfache Sätze bevorzugen, nicht zu gestelzt, hochtrabend oder zu förmlich. Formuliere für die Zielgruppe Branchentypisch per Sie (z.B. Dienstleistungsbranchen, Berater) und per du bei (Coaches, Vereinen und weniger förmliche Branchen.
Response niemals mit Icons, Trennlinien oder in Anführungszeichen außer es ist im prompt für das Feld gefordert.
Responses für Titel, CTA und Listen nicht mit Punkt am Ende.',
		) );
		register_setting( 'gutenblock_pro_ai_settings', 'gutenblock_pro_ai_context', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_ai_context' ),
			'default'           => '',
		) );
		register_setting( 'gutenblock_pro_ai_settings', 'gutenblock_pro_pexels_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'gutenblock_pro_ai_settings', 'gutenblock_pro_unsplash_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'gutenblock_pro_ai_settings', 'gutenblock_pro_image_api_provider', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'pexels',
		) );
	}

	/**
	 * Sanitize system prompt and update last modified timestamp
	 */
	public function sanitize_system_prompt( $value ) {
		update_option( 'gutenblock_pro_system_prompt_modified', time() );
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize AI context and update last modified timestamp
	 */
	public function sanitize_ai_context( $value ) {
		update_option( 'gutenblock_pro_system_prompt_modified', time() );
		return sanitize_textarea_field( $value );
	}

	/**
	 * Enqueue assets
	 */
	public function enqueue_assets( $hook ) {
		$allowed_hooks = array(
			'gutenblock-pro_page_gutenblock-pro-ai',
			'gutenblock-pro_page_gutenblock-pro-license',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
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

		// Get style prompt with default prefill
		$style_prompt = get_option( 'gutenblock_pro_system_prompt', '' );
		if ( empty( $style_prompt ) ) {
			$style_prompt = 'Texte Elemente für eine Website.
Einfache Sätze bevorzugen, nicht zu gestelzt, hochtrabend oder zu förmlich. Formuliere für die Zielgruppe Branchentypisch per Sie (z.B. Dienstleistungsbranchen, Berater) und per du bei (Coaches, Vereinen und weniger förmliche Branchen.
Response niemals mit Icons, Trennlinien oder in Anführungszeichen außer es ist im prompt für das Feld gefordert.
Responses für Titel, CTA und Listen nicht mit Punkt am Ende.';
		}
		
		$context_prompt = get_option( 'gutenblock_pro_ai_context', '' );
		$prompts = $this->ai_generator->get_prompts();
		$custom_prompts = get_option( 'gutenblock_pro_custom_prompts', array() );
		?>
		<div class="wrap gutenblock-pro-ai-settings">
			<h1>
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'GutenBlock Pro - Prompts', 'gutenblock-pro' ); ?>
			</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'gutenblock_pro_ai_settings' ); ?>

				<!-- Kontext – einziges Pflichtfeld -->
				<?php $context_filled = trim( (string) $context_prompt ) !== ''; ?>
				<div class="gb-settings-section gb-context-required">
					<h2>
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Kontext', 'gutenblock-pro' ); ?>
						<span class="gb-context-badge <?php echo $context_filled ? 'gb-context-filled' : 'gb-context-missing'; ?>">
							<?php if ( $context_filled ) : ?>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Ausgefüllt', 'gutenblock-pro' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Wird benötigt', 'gutenblock-pro' ); ?>
							<?php endif; ?>
						</span>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Beschreibe wer du bist und was du machst. Diese Information ist die Grundlage für alle KI-generierten Texte.', 'gutenblock-pro' ); ?>
					</p>
					<textarea name="gutenblock_pro_ai_context"
					          id="gutenblock_pro_ai_context"
					          rows="5"
					          class="large-text gb-context-input"
					          placeholder="<?php esc_attr_e( 'z.B. Ich bin ein Marketing-Berater und helfe Unternehmen bei der digitalen Transformation...', 'gutenblock-pro' ); ?>"
					><?php echo esc_textarea( $context_prompt ); ?></textarea>
					<p class="submit"><?php submit_button( __( 'Speichern', 'gutenblock-pro' ), 'primary', 'submit', false ); ?></p>
				</div>

				<!-- Optional für Feintuning -->
				<div class="gb-settings-section gb-optional-section">
					<h2 class="gb-optional-title">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Optional: Feintuning', 'gutenblock-pro' ); ?>
					</h2>
					<p class="description gb-optional-intro">
						<?php esc_html_e( 'Diese Einstellungen sind optional und dienen der Verfeinerung. Der Kontext oben reicht für den Start.', 'gutenblock-pro' ); ?>
					</p>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="gutenblock_pro_system_prompt"><?php esc_html_e( 'Stil', 'gutenblock-pro' ); ?></label>
							</th>
							<td>
								<textarea name="gutenblock_pro_system_prompt"
								          id="gutenblock_pro_system_prompt"
								          rows="6"
								          class="large-text code"
								><?php echo esc_textarea( $style_prompt ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Definiere den Schreibstil und technische Vorgaben für die Texte.', 'gutenblock-pro' ); ?>
								</p>
							</td>
						</tr>
						<?php
						// Only show image API settings for user "hjherbst"
						$current_user = wp_get_current_user();
						if ( $current_user->user_login === 'hjherbst' ) :
						?>
						<tr>
							<th scope="row">
								<label for="gutenblock_pro_image_api_provider"><?php esc_html_e( 'Bild-API Anbieter', 'gutenblock-pro' ); ?></label>
							</th>
							<td>
								<select name="gutenblock_pro_image_api_provider" id="gutenblock_pro_image_api_provider">
									<option value="pexels" <?php selected( get_option( 'gutenblock_pro_image_api_provider', 'pexels' ), 'pexels' ); ?>>
										<?php esc_html_e( 'Pexels', 'gutenblock-pro' ); ?>
									</option>
									<option value="unsplash" <?php selected( get_option( 'gutenblock_pro_image_api_provider', 'pexels' ), 'unsplash' ); ?>>
										<?php esc_html_e( 'Unsplash', 'gutenblock-pro' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Wähle den API-Anbieter für die Bildsuche. Ohne API Key wird picsum.photos verwendet.', 'gutenblock-pro' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="gutenblock_pro_pexels_api_key"><?php esc_html_e( 'Pexels API Key', 'gutenblock-pro' ); ?></label>
							</th>
							<td>
								<input type="text" 
								       name="gutenblock_pro_pexels_api_key" 
								       id="gutenblock_pro_pexels_api_key" 
								       value="<?php echo esc_attr( get_option( 'gutenblock_pro_pexels_api_key', '' ) ); ?>"
								       class="regular-text"
								       placeholder="Pexels API Key (optional)">
								<p class="description">
									<?php 
									printf(
										/* translators: %1$s: link to pexels.com/api, %2$s: link to pexels license */
										esc_html__( 'Optional: API Key von %1$s. Alle über die API bezogenen Bilder sind unter der Pexels-Lizenz frei verwendbar (kommerziell erlaubt, keine Attribution erforderlich). Siehe %2$s für Details.', 'gutenblock-pro' ),
										'<a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a>',
										'<a href="https://www.pexels.com/license/" target="_blank">Pexels-Lizenz</a>'
									); 
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="gutenblock_pro_unsplash_api_key"><?php esc_html_e( 'Unsplash API Key', 'gutenblock-pro' ); ?></label>
							</th>
							<td>
								<input type="text" 
								       name="gutenblock_pro_unsplash_api_key" 
								       id="gutenblock_pro_unsplash_api_key" 
								       value="<?php echo esc_attr( get_option( 'gutenblock_pro_unsplash_api_key', '' ) ); ?>"
								       class="regular-text"
								       placeholder="Unsplash Access Key (optional)">
								<p class="description">
									<?php 
									printf(
										/* translators: %1$s: link to unsplash.com/developers, %2$s: link to unsplash license */
										esc_html__( 'Optional: Access Key von %1$s. Alle über die API bezogenen Bilder sind unter der Unsplash-Lizenz frei verwendbar. Siehe %2$s für Details.', 'gutenblock-pro' ),
										'<a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a>',
										'<a href="https://unsplash.com/license" target="_blank">Unsplash-Lizenz</a>'
									); 
									?>
								</p>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>

				<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
			</form>

			<!-- Block Prompts Section (optional) -->
			<div class="gb-settings-section gb-optional-section">
				<h2>
					<?php esc_html_e( 'Block-Prompts', 'gutenblock-pro' ); ?>
					<button type="button" class="button button-secondary button-small" id="gb-refresh-prompts">
						<?php esc_html_e( 'Prompts mit API synchronisieren', 'gutenblock-pro' ); ?>
					</button>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Optional: Diese Prompts werden mit der API von gutenblock.com synchronisiert und definieren, welcher Text für welches Block-Element generiert wird. Custom Prompts überschreiben die API-Prompts.', 'gutenblock-pro' ); ?>
				</p>

				<?php if ( ! empty( $prompts ) ) : ?>
					<table class="widefat gb-prompts-table">
						<thead>
							<tr>
								<th style="width: 20%;"><?php esc_html_e( 'Block-ID', 'gutenblock-pro' ); ?></th>
								<th style="width: 40%;"><?php esc_html_e( 'Prompt (API)', 'gutenblock-pro' ); ?></th>
								<th style="width: 40%;"><?php esc_html_e( 'Custom Prompt', 'gutenblock-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $prompts as $field_id => $prompt_data ) : 
								$custom_prompt = isset( $custom_prompts[ $field_id ] ) ? $custom_prompts[ $field_id ] : '';
							?>
								<tr data-field-id="<?php echo esc_attr( $field_id ); ?>">
									<td><code><?php echo esc_html( $field_id ); ?></code></td>
									<td class="gb-prompt-text"><?php echo esc_html( $prompt_data['prompt'] ?? '' ); ?></td>
									<td>
										<textarea 
											class="gb-custom-prompt large-text" 
											rows="3" 
											data-field-id="<?php echo esc_attr( $field_id ); ?>"
											placeholder="<?php esc_attr_e( 'Optional: Eigener Prompt (überschreibt API-Prompt)', 'gutenblock-pro' ); ?>"
										><?php echo esc_textarea( $custom_prompt ); ?></textarea>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="button" class="button button-primary" id="gb-save-custom-prompts">
							<?php esc_html_e( 'Custom Prompts speichern', 'gutenblock-pro' ); ?>
						</button>
					</p>
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
	 * Render License page
	 */
	public function render_license_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$license_info = $this->license->get_license_info();
		$token_usage  = $this->license->get_token_usage();

		$locale = get_locale();
		$title  = ( strpos( $locale, 'de' ) === 0 )
			? __( 'GutenBlock Pro - Lizenz', 'gutenblock-pro' )
			: __( 'GutenBlock Pro - Licence', 'gutenblock-pro' );
		?>
		<div class="wrap gutenblock-pro-ai-settings">
			<h1>
				<span class="dashicons dashicons-admin-network"></span>
				<?php echo esc_html( $title ); ?>
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
							       maxlength="20"
							       class="regular-text">
							<button type="button" class="button button-primary" id="gb-activate-license">
								<?php esc_html_e( 'Lizenz aktivieren', 'gutenblock-pro' ); ?>
							</button>
						</div>
						<p class="description">
							<?php
							printf(
								esc_html__( 'Noch keine Lizenz? %s', 'gutenblock-pro' ),
								'<a href="https://app.gutenblock.com/gutenblock-pro" target="_blank">' . esc_html__( 'Jetzt kaufen', 'gutenblock-pro' ) . '</a>'
							);
							?>
						</p>
						<div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; font-size: 12px; color: #646970;">
							<span style="display: inline-flex; align-items: center; gap: 4px;">
								<span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php esc_html_e( '1 Mio. AI-Tokens monatlich', 'gutenblock-pro' ); ?>
							</span>
							<span style="display: inline-flex; align-items: center; gap: 4px;">
								<span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php esc_html_e( 'Alle Premium-Patterns freigeschalten', 'gutenblock-pro' ); ?>
							</span>
							<span style="display: inline-flex; align-items: center; gap: 4px;">
								<span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php esc_html_e( 'Einmalig zahlen, lebenslang nutzen', 'gutenblock-pro' ); ?>
							</span>
						</div>
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
								$bar_class  = $percentage > 95 ? 'critical' : ( $percentage > 80 ? 'warning' : '' );
								?>
								<div class="gb-token-progress <?php echo esc_attr( $bar_class ); ?>"
								     style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
							</div>
							<div class="gb-token-numbers">
								<span class="gb-token-used"><?php echo esc_html( number_format_i18n( $token_usage['used'] ) ); ?></span>
								<span class="gb-token-separator">/</span>
								<span class="gb-token-limit"><?php echo esc_html( number_format_i18n( $token_usage['limit'] ) ); ?></span>
								<span class="gb-token-label"><?php esc_html_e( 'Tokens', 'gutenblock-pro' ); ?></span>
							</div>
						</div>
						<p class="description">
							<?php
							printf(
								esc_html__( 'Verbleibend: %1$s Tokens. Reset: %2$s', 'gutenblock-pro' ),
								'<strong>' . number_format_i18n( $token_usage['remaining'] ) . '</strong>',
								'<strong>' . esc_html( date_i18n( 'j. F Y', strtotime( 'first day of next month' ) ) ) . '</strong>'
							);
							?>
						</p>
					<?php endif; ?>
				</div>
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

	/**
	 * AJAX: Save custom prompts
	 */
	public function ajax_save_custom_prompts() {
		check_ajax_referer( 'gutenblock_pro_ai', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'gutenblock-pro' ) ) );
		}

		$custom_prompts = array();
		
		if ( isset( $_POST['custom_prompts'] ) && is_array( $_POST['custom_prompts'] ) ) {
			foreach ( $_POST['custom_prompts'] as $field_id => $prompt ) {
				$field_id = sanitize_key( $field_id );
				$prompt = sanitize_textarea_field( $prompt );
				
				// Only save non-empty prompts
				if ( ! empty( trim( $prompt ) ) ) {
					$custom_prompts[ $field_id ] = $prompt;
				}
			}
		}

		// Save custom prompts (empty array removes all)
		update_option( 'gutenblock_pro_custom_prompts', $custom_prompts );

		// Clear prompts cache to force refresh
		$this->ai_generator->clear_prompts_cache();

		wp_send_json_success( array(
			'message' => __( 'Custom Prompts erfolgreich gespeichert', 'gutenblock-pro' ),
			'count'   => count( $custom_prompts ),
		) );
	}
}
