<?php
/**
 * Contact Form Settings – guided email setup for the contact form block.
 *
 * Instead of asking non-technical users for raw SMTP data, this page offers a
 * three-way wizard: Brevo (recommended), an existing mailbox (provider preset)
 * or advanced manual SMTP. Host/port/encryption are filled in automatically
 * from GutenBlock_Pro_Contact_Form_Presets; everything is stored in the same
 * options the mailer already consumes, so the send pipeline stays unchanged.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Contact_Form_Settings {

	const SETTINGS_GROUP = 'gutenblock_pro_contact_form';
	const OPT_TEST_STATUS  = 'gutenblock_pro_cf_test_status';
	const OPT_TEST_MESSAGE = 'gutenblock_pro_cf_test_message';

	/**
	 * Hook admin menu, settings, assets and the test-mail AJAX action.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_gutenblock_pro_cf_test_mail', array( $this, 'ajax_test_mail' ) );
	}

	/**
	 * Add the "Kontaktformular" submenu page.
	 */
	public function add_submenu() {
		$label = __( 'Kontaktformular', 'gutenblock-pro' );

		add_submenu_page(
			'gutenblock-pro',
			$label,
			$label,
			'manage_options',
			'gutenblock-pro-contact-form',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings. Only the recipient, subject and the two wizard
	 * controllers are registered; the canonical SMTP options are written by
	 * the controller in sanitize_mail_method() based on the chosen method.
	 */
	public function register_settings() {
		$mailer = 'GutenBlock_Pro_Contact_Form_Mailer';

		register_setting( self::SETTINGS_GROUP, $mailer::OPT_RECIPIENT, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => get_option( 'admin_email' ),
		) );
		register_setting( self::SETTINGS_GROUP, $mailer::OPT_SUBJECT, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( self::SETTINGS_GROUP, $mailer::OPT_MAIL_PRESET, array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_preset' ),
			'default'           => 'ionos',
		) );
		// Controller: validates the method AND derives the canonical SMTP
		// options from the wizard fields. Always posted (radio), so it runs.
		register_setting( self::SETTINGS_GROUP, $mailer::OPT_MAIL_METHOD, array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_mail_method' ),
			'default'           => 'none',
		) );
	}

	/**
	 * Restrict the mailbox preset to a known provider slug.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_preset( $value ) {
		$value   = is_string( $value ) ? $value : '';
		$servers = GutenBlock_Pro_Contact_Form_Presets::servers();
		return isset( $servers[ $value ] ) ? $value : 'ionos';
	}

	/**
	 * Controller: validate the chosen method and derive the canonical SMTP
	 * options. Reads the wizard fields from $_POST (nonce already verified by
	 * options.php for this settings group).
	 *
	 * @param mixed $value Raw method value.
	 * @return string Sanitized method.
	 */
	public function sanitize_mail_method( $value ) {
		$mailer = 'GutenBlock_Pro_Contact_Form_Mailer';
		$method = is_string( $value ) ? $value : 'none';
		if ( ! in_array( $method, array( 'none', 'brevo', 'mailbox', 'manual' ), true ) ) {
			$method = 'none';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- options.php verifies the settings-group nonce before sanitization runs.
		$post = wp_unslash( $_POST );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$keep_pass = (string) get_option( $mailer::OPT_SMTP_PASS, '' );

		if ( 'brevo' === $method ) {
			$server = GutenBlock_Pro_Contact_Form_Presets::get_server( 'brevo' );
			$pass   = isset( $post['gbp_brevo_pass'] ) ? trim( (string) $post['gbp_brevo_pass'] ) : '';

			update_option( $mailer::OPT_SMTP_ENABLED, true );
			update_option( $mailer::OPT_SMTP_HOST, $server['host'] );
			update_option( $mailer::OPT_SMTP_PORT, $server['port'] );
			update_option( $mailer::OPT_SMTP_ENCRYPT, $server['encryption'] );
			update_option( $mailer::OPT_SMTP_USER, isset( $post['gbp_brevo_user'] ) ? sanitize_text_field( $post['gbp_brevo_user'] ) : '' );
			update_option( $mailer::OPT_SMTP_PASS, $pass !== '' ? $pass : $keep_pass );
			update_option( $mailer::OPT_SMTP_FROM_MAIL, isset( $post['gbp_brevo_from_email'] ) ? sanitize_email( $post['gbp_brevo_from_email'] ) : '' );
			update_option( $mailer::OPT_SMTP_FROM_NAME, isset( $post['gbp_brevo_from_name'] ) ? sanitize_text_field( $post['gbp_brevo_from_name'] ) : '' );
		} elseif ( 'mailbox' === $method ) {
			$preset_slug = isset( $post[ $mailer::OPT_MAIL_PRESET ] ) ? $this->sanitize_preset( $post[ $mailer::OPT_MAIL_PRESET ] ) : 'ionos';
			$server      = GutenBlock_Pro_Contact_Form_Presets::get_server( $preset_slug );
			$email       = isset( $post['gbp_mailbox_email'] ) ? sanitize_email( $post['gbp_mailbox_email'] ) : '';
			$pass        = isset( $post['gbp_mailbox_pass'] ) ? trim( (string) $post['gbp_mailbox_pass'] ) : '';

			update_option( $mailer::OPT_SMTP_ENABLED, true );
			update_option( $mailer::OPT_SMTP_HOST, $server ? $server['host'] : '' );
			update_option( $mailer::OPT_SMTP_PORT, $server ? $server['port'] : 587 );
			update_option( $mailer::OPT_SMTP_ENCRYPT, $server ? $server['encryption'] : 'tls' );
			update_option( $mailer::OPT_SMTP_USER, $email );
			update_option( $mailer::OPT_SMTP_PASS, $pass !== '' ? $pass : $keep_pass );
			update_option( $mailer::OPT_SMTP_FROM_MAIL, $email );
			update_option( $mailer::OPT_SMTP_FROM_NAME, isset( $post['gbp_mailbox_from_name'] ) ? sanitize_text_field( $post['gbp_mailbox_from_name'] ) : '' );
		} elseif ( 'manual' === $method ) {
			$pass = isset( $post['gbp_manual_pass'] ) ? trim( (string) $post['gbp_manual_pass'] ) : '';

			update_option( $mailer::OPT_SMTP_ENABLED, true );
			update_option( $mailer::OPT_SMTP_HOST, isset( $post['gbp_manual_host'] ) ? sanitize_text_field( $post['gbp_manual_host'] ) : '' );
			update_option( $mailer::OPT_SMTP_PORT, isset( $post['gbp_manual_port'] ) ? absint( $post['gbp_manual_port'] ) : 587 );
			update_option( $mailer::OPT_SMTP_ENCRYPT, $this->normalise_encryption( isset( $post['gbp_manual_encryption'] ) ? $post['gbp_manual_encryption'] : 'tls' ) );
			update_option( $mailer::OPT_SMTP_USER, isset( $post['gbp_manual_user'] ) ? sanitize_text_field( $post['gbp_manual_user'] ) : '' );
			update_option( $mailer::OPT_SMTP_PASS, $pass !== '' ? $pass : $keep_pass );
			update_option( $mailer::OPT_SMTP_FROM_MAIL, isset( $post['gbp_manual_from_email'] ) ? sanitize_email( $post['gbp_manual_from_email'] ) : '' );
			update_option( $mailer::OPT_SMTP_FROM_NAME, isset( $post['gbp_manual_from_name'] ) ? sanitize_text_field( $post['gbp_manual_from_name'] ) : '' );
		} else {
			// none: disable SMTP, keep stored credentials untouched.
			update_option( $mailer::OPT_SMTP_ENABLED, false );
		}

		return $method;
	}

	/**
	 * Restrict the encryption value to a known set.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalise_encryption( $value ) {
		$value = is_string( $value ) ? $value : 'tls';
		return in_array( $value, array( 'tls', 'ssl', 'none' ), true ) ? $value : 'tls';
	}

	/**
	 * Send a test mail to the configured recipient (admin AJAX).
	 *
	 * Captures the raw PHPMailer error via the wp_mail_failed hook and maps it
	 * to a friendly message.
	 */
	public function ajax_test_mail() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'gutenblock-pro' ) ), 403 );
		}
		check_ajax_referer( 'gutenblock_pro_cf_test', 'nonce' );

		$captured = '';
		$capture  = function ( $wp_error ) use ( &$captured ) {
			if ( is_wp_error( $wp_error ) ) {
				$captured = $wp_error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $capture );

		$recipient = GutenBlock_Pro_Contact_Form_Mailer::get_recipient();
		$subject   = sprintf(
			/* translators: %s: site name */
			__( '[Test] Contact form – %s', 'gutenblock-pro' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = __( 'This is a test mail from the GutenBlock contact form settings. If you received it, sending works.', 'gutenblock-pro' );

		$sent = GutenBlock_Pro_Contact_Form_Mailer::send( $recipient, $subject, $body );

		remove_action( 'wp_mail_failed', $capture );

		if ( $sent ) {
			$message = sprintf(
				/* translators: %s: recipient email */
				__( 'Test-E-Mail an %s gesendet.', 'gutenblock-pro' ),
				$recipient
			);
			update_option( self::OPT_TEST_STATUS, 'ok' );
			update_option( self::OPT_TEST_MESSAGE, $message );
			wp_send_json_success( array( 'message' => $message ) );
		}

		$message = GutenBlock_Pro_Contact_Form_Mailer::friendly_error( $captured );
		update_option( self::OPT_TEST_STATUS, 'fail' );
		update_option( self::OPT_TEST_MESSAGE, $message );
		wp_send_json_error( array( 'message' => $message ) );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$mailer = 'GutenBlock_Pro_Contact_Form_Mailer';

		$recipient   = get_option( $mailer::OPT_RECIPIENT, get_option( 'admin_email' ) );
		$subject     = get_option( $mailer::OPT_SUBJECT, '' );
		$method      = GutenBlock_Pro_Contact_Form_Mailer::get_method();
		$preset      = get_option( $mailer::OPT_MAIL_PRESET, 'ionos' );
		$host        = get_option( $mailer::OPT_SMTP_HOST, '' );
		$port        = (int) get_option( $mailer::OPT_SMTP_PORT, 587 );
		$encryption  = get_option( $mailer::OPT_SMTP_ENCRYPT, 'tls' );
		$user        = get_option( $mailer::OPT_SMTP_USER, '' );
		$has_pass    = (string) get_option( $mailer::OPT_SMTP_PASS, '' ) !== '';
		$from_email  = get_option( $mailer::OPT_SMTP_FROM_MAIL, '' );
		$from_name   = get_option( $mailer::OPT_SMTP_FROM_NAME, '' );

		$pass_ph     = $has_pass
			? esc_attr__( '•••••••• (gespeichert – leer lassen zum Behalten)', 'gutenblock-pro' )
			: '';

		$providers   = GutenBlock_Pro_Contact_Form_Presets::mailbox_providers();
		$brevo_sum   = GutenBlock_Pro_Contact_Form_Presets::summary( 'brevo' );

		$test_status  = get_option( self::OPT_TEST_STATUS, '' );
		$test_message = get_option( self::OPT_TEST_MESSAGE, '' );

		// On a fresh install no method is chosen yet; preselect the recommended
		// Brevo path so exactly one panel is visible instead of all at once.
		$display_method = ( 'none' === $method ) ? 'brevo' : $method;
		$brevo_url      = 'https://get.brevo.com/3s6b626q5b6r';
		?>
		<?php $this->print_inline_assets(); ?>
		<div class="wrap gbp-cf-settings" data-method="<?php echo esc_attr( $display_method ); ?>">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<h2><?php esc_html_e( 'Empfänger', 'gutenblock-pro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="gbp_cf_recipient"><?php esc_html_e( 'Empfänger-E-Mail', 'gutenblock-pro' ); ?></label>
						</th>
						<td>
							<input type="email" class="regular-text" id="gbp_cf_recipient"
								name="<?php echo esc_attr( $mailer::OPT_RECIPIENT ); ?>"
								value="<?php echo esc_attr( $recipient ); ?>" />
							<p class="description"><?php esc_html_e( 'Eingehende Anfragen werden an diese Adresse gesendet. Standard: Website-Admin-E-Mail.', 'gutenblock-pro' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gbp_cf_subject"><?php esc_html_e( 'Betreff', 'gutenblock-pro' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="gbp_cf_subject"
								name="<?php echo esc_attr( $mailer::OPT_SUBJECT ); ?>"
								value="<?php echo esc_attr( $subject ); ?>"
								placeholder="<?php esc_attr_e( 'Contact request from {site_name}', 'gutenblock-pro' ); ?>" />
							<p class="description"><?php esc_html_e( 'Platzhalter {site_name} wird durch den Website-Namen ersetzt. Leer = Standard.', 'gutenblock-pro' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'E-Mail-Versand einrichten', 'gutenblock-pro' ); ?></h2>
				<p class="description gbp-cf-intro">
					<?php esc_html_e( 'Damit Kontaktformular-Anfragen zuverlässig zugestellt werden, sollte deine Website E-Mails nicht direkt über WordPress versenden. Verbinde stattdessen ein echtes E-Mail-Postfach oder einen Versanddienst wie Brevo.', 'gutenblock-pro' ); ?>
				</p>

				<div class="gbp-cf-methods">
					<?php
					$this->render_method_card(
						'brevo',
						$display_method,
						__( 'Empfohlen: Brevo', 'gutenblock-pro' ),
						__( 'Kostenloser Versanddienst – am einfachsten für die zuverlässige Zustellung.', 'gutenblock-pro' )
					);
					$this->render_method_card(
						'mailbox',
						$display_method,
						__( 'Vorhandenes E-Mail-Postfach', 'gutenblock-pro' ),
						__( 'Nutze dein bestehendes Postfach (z. B. IONOS, Strato, Google).', 'gutenblock-pro' )
					);
					$this->render_method_card(
						'manual',
						$display_method,
						__( 'Erweitert: Manuell', 'gutenblock-pro' ),
						__( 'Für Fortgeschrittene: SMTP-Daten selbst eintragen.', 'gutenblock-pro' )
					);
					?>
				</div>

				<?php // --- Brevo panel --- ?>
				<div class="gbp-cf-panel" data-panel="brevo">
					<ol class="gbp-cf-steps">
						<li>
							<?php
							printf(
								/* translators: %s: link to Brevo signup */
								esc_html__( 'Kostenloses Konto bei %s erstellen und Absender bestätigen.', 'gutenblock-pro' ),
								'<a href="' . esc_url( $brevo_url ) . '" target="_blank" rel="noopener noreferrer">Brevo</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Unter "SMTP & API" einen SMTP-Schlüssel erzeugen (nicht den API-Key).', 'gutenblock-pro' ); ?></li>
						<li><?php esc_html_e( 'SMTP-Login und SMTP-Schlüssel hier einfügen.', 'gutenblock-pro' ); ?></li>
					</ol>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gbp_brevo_user"><?php esc_html_e( 'SMTP-Login (Benutzername)', 'gutenblock-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gbp_brevo_user" name="gbp_brevo_user"
								value="<?php echo 'brevo' === $method ? esc_attr( $user ) : ''; ?>" autocomplete="off" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_brevo_pass"><?php esc_html_e( 'SMTP-Schlüssel', 'gutenblock-pro' ); ?></label></th>
							<td><input type="password" class="regular-text" id="gbp_brevo_pass" name="gbp_brevo_pass"
								value="" autocomplete="new-password"
								placeholder="<?php echo 'brevo' === $method ? esc_attr( $pass_ph ) : ''; ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_brevo_from_email"><?php esc_html_e( 'Absender-E-Mail', 'gutenblock-pro' ); ?></label></th>
							<td><input type="email" class="regular-text" id="gbp_brevo_from_email" name="gbp_brevo_from_email"
								value="<?php echo 'brevo' === $method ? esc_attr( $from_email ) : ''; ?>" />
								<p class="description"><?php esc_html_e( 'Die in Brevo bestätigte Absender-Adresse.', 'gutenblock-pro' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_brevo_from_name"><?php esc_html_e( 'Absender-Name', 'gutenblock-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gbp_brevo_from_name" name="gbp_brevo_from_name"
								value="<?php echo 'brevo' === $method ? esc_attr( $from_name ) : ''; ?>"
								placeholder="<?php esc_attr_e( 'optional', 'gutenblock-pro' ); ?>" /></td>
						</tr>
					</table>
					<p class="gbp-cf-autoinfo">
						<?php
						printf(
							/* translators: %s: server summary */
							esc_html__( 'Wird automatisch gesetzt: %s', 'gutenblock-pro' ),
							'<code>' . esc_html( $brevo_sum ) . '</code>'
						);
						?>
					</p>
				</div>

				<?php // --- Mailbox panel --- ?>
				<div class="gbp-cf-panel" data-panel="mailbox">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gbp_cf_preset"><?php esc_html_e( 'Anbieter', 'gutenblock-pro' ); ?></label></th>
							<td>
								<select id="gbp_cf_preset" name="<?php echo esc_attr( $mailer::OPT_MAIL_PRESET ); ?>">
									<?php foreach ( $providers as $slug => $prov ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"
											data-hint="<?php echo esc_attr( $prov['hint'] ); ?>"
											data-summary="<?php echo esc_attr( GutenBlock_Pro_Contact_Form_Presets::summary( $slug ) ); ?>"
											<?php selected( $preset, $slug ); ?>>
											<?php echo esc_html( $prov['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description gbp-cf-preset-hint"></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_mailbox_email"><?php esc_html_e( 'E-Mail-Adresse', 'gutenblock-pro' ); ?></label></th>
							<td><input type="email" class="regular-text" id="gbp_mailbox_email" name="gbp_mailbox_email"
								value="<?php echo 'mailbox' === $method ? esc_attr( $from_email ) : ''; ?>"
								placeholder="info@deine-domain.de" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_mailbox_pass"><?php esc_html_e( 'Passwort', 'gutenblock-pro' ); ?></label></th>
							<td><input type="password" class="regular-text" id="gbp_mailbox_pass" name="gbp_mailbox_pass"
								value="" autocomplete="new-password"
								placeholder="<?php echo 'mailbox' === $method ? esc_attr( $pass_ph ) : ''; ?>" />
								<p class="description"><?php esc_html_e( 'Das Passwort deines E-Mail-Postfachs.', 'gutenblock-pro' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_mailbox_from_name"><?php esc_html_e( 'Absender-Name', 'gutenblock-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gbp_mailbox_from_name" name="gbp_mailbox_from_name"
								value="<?php echo 'mailbox' === $method ? esc_attr( $from_name ) : ''; ?>"
								placeholder="<?php esc_attr_e( 'optional', 'gutenblock-pro' ); ?>" /></td>
						</tr>
					</table>
					<p class="gbp-cf-autoinfo gbp-cf-mailbox-summary"></p>
				</div>

				<?php // --- Manual panel --- ?>
				<div class="gbp-cf-panel" data-panel="manual">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gbp_manual_host"><?php esc_html_e( 'SMTP-Host', 'gutenblock-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gbp_manual_host" name="gbp_manual_host"
								value="<?php echo 'manual' === $method ? esc_attr( $host ) : ''; ?>" placeholder="smtp.example.com" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_manual_port"><?php esc_html_e( 'Port', 'gutenblock-pro' ); ?></label></th>
							<td><input type="number" class="small-text" id="gbp_manual_port" name="gbp_manual_port"
								value="<?php echo 'manual' === $method ? esc_attr( $port ) : '587'; ?>" min="1" max="65535" />
								<p class="description"><?php esc_html_e( '587 (TLS), 465 (SSL) oder 25 (unverschlüsselt).', 'gutenblock-pro' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_manual_encryption"><?php esc_html_e( 'Verschlüsselung', 'gutenblock-pro' ); ?></label></th>
							<td>
								<select id="gbp_manual_encryption" name="gbp_manual_encryption">
									<option value="tls" <?php selected( 'manual' === $method ? $encryption : 'tls', 'tls' ); ?>>TLS</option>
									<option value="ssl" <?php selected( 'manual' === $method ? $encryption : 'tls', 'ssl' ); ?>>SSL</option>
									<option value="none" <?php selected( 'manual' === $method ? $encryption : 'tls', 'none' ); ?>><?php esc_html_e( 'Keine', 'gutenblock-pro' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_manual_user"><?php esc_html_e( 'Benutzername', 'gutenblock-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gbp_manual_user" name="gbp_manual_user"
								value="<?php echo 'manual' === $method ? esc_attr( $user ) : ''; ?>" autocomplete="off" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_manual_pass"><?php esc_html_e( 'Passwort', 'gutenblock-pro' ); ?></label></th>
							<td><input type="password" class="regular-text" id="gbp_manual_pass" name="gbp_manual_pass"
								value="" autocomplete="new-password"
								placeholder="<?php echo 'manual' === $method ? esc_attr( $pass_ph ) : ''; ?>" />
								<p class="description"><?php esc_html_e( 'Leer lassen, um das gespeicherte Passwort beizubehalten.', 'gutenblock-pro' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_manual_from_email"><?php esc_html_e( 'Absender-Adresse', 'gutenblock-pro' ); ?></label></th>
							<td><input type="email" class="regular-text" id="gbp_manual_from_email" name="gbp_manual_from_email"
								value="<?php echo 'manual' === $method ? esc_attr( $from_email ) : ''; ?>"
								placeholder="<?php esc_attr_e( 'optional', 'gutenblock-pro' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="gbp_manual_from_name"><?php esc_html_e( 'Absender-Name', 'gutenblock-pro' ); ?></label></th>
							<td><input type="text" class="regular-text" id="gbp_manual_from_name" name="gbp_manual_from_name"
								value="<?php echo 'manual' === $method ? esc_attr( $from_name ) : ''; ?>"
								placeholder="<?php esc_attr_e( 'optional', 'gutenblock-pro' ); ?>" /></td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Test-E-Mail', 'gutenblock-pro' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Sendet eine Test-E-Mail an den oben gespeicherten Empfänger. Bitte zuerst speichern.', 'gutenblock-pro' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="gbp-cf-test-mail">
					<?php esc_html_e( 'Test-E-Mail senden', 'gutenblock-pro' ); ?>
				</button>
				<span id="gbp-cf-test-result" class="gbp-cf-test-result<?php echo $test_status ? ' is-' . esc_attr( $test_status ) : ''; ?>">
					<?php echo esc_html( $test_message ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Print the wizard CSS + JS inline.
	 *
	 * Inlined (instead of enqueued) so the panel switching is guaranteed to
	 * work on this settings screen regardless of admin asset-loading timing.
	 */
	private function print_inline_assets() {
		?>
		<style>
		.gbp-cf-intro { max-width: 640px; }
		.gbp-cf-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; max-width: 900px; margin: 1rem 0 1.5rem; }
		.gbp-cf-method-card { position: relative; display: flex; flex-direction: column; gap: 0.3rem; padding: 1rem 1rem 1rem 2.4rem; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
		.gbp-cf-method-card:hover { border-color: #2271b1; }
		.gbp-cf-method-card.is-selected { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
		.gbp-cf-method-card input[type="radio"] { position: absolute; top: 1.1rem; left: 0.9rem; margin: 0; }
		.gbp-cf-method-title { font-weight: 600; font-size: 14px; }
		.gbp-cf-method-desc { color: #646970; font-size: 12px; line-height: 1.4; }
		.gbp-cf-panel { display: none; max-width: 760px; margin: 0 0 1rem; padding: 0.5rem 1.25rem 1rem; background: #fff; border: 1px solid #dcdcde; border-radius: 6px; }
		.gbp-cf-settings[data-method="brevo"] .gbp-cf-panel[data-panel="brevo"],
		.gbp-cf-settings[data-method="mailbox"] .gbp-cf-panel[data-panel="mailbox"],
		.gbp-cf-settings[data-method="manual"] .gbp-cf-panel[data-panel="manual"] { display: block; }
		.gbp-cf-steps { max-width: 640px; margin: 1rem 0; padding-left: 1.4rem; color: #3c434a; line-height: 1.6; }
		.gbp-cf-steps li { margin-bottom: 0.3rem; }
		.gbp-cf-autoinfo { color: #646970; font-size: 12px; }
		.gbp-cf-autoinfo code { font-size: 12px; }
		.gbp-cf-test-result { margin-left: 10px; font-weight: 500; }
		.gbp-cf-test-result.is-ok { color: #008a20; }
		.gbp-cf-test-result.is-fail { color: #d63638; }
		</style>
		<script>
		( function () {
			var config = {
				ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
				nonce: <?php echo wp_json_encode( wp_create_nonce( 'gutenblock_pro_cf_test' ) ); ?>,
				autoPrefix: <?php echo wp_json_encode( __( 'Wird automatisch gesetzt: ', 'gutenblock-pro' ) ); ?>,
				strings: {
					sending: <?php echo wp_json_encode( __( 'Sende…', 'gutenblock-pro' ) ); ?>,
					error: <?php echo wp_json_encode( __( 'Fehler beim Senden.', 'gutenblock-pro' ) ); ?>
				}
			};

			function ready( fn ) {
				if ( document.readyState !== 'loading' ) { fn(); }
				else { document.addEventListener( 'DOMContentLoaded', fn ); }
			}

			ready( function () {
				var wrap = document.querySelector( '.gbp-cf-settings' );
				if ( ! wrap ) { return; }

				function selectMethod( method ) {
					wrap.dataset.method = method;
					wrap.querySelectorAll( '.gbp-cf-method-card' ).forEach( function ( card ) {
						var radio = card.querySelector( 'input[type="radio"]' );
						card.classList.toggle( 'is-selected', !! radio && radio.checked );
					} );
				}

				wrap.querySelectorAll( '.gbp-cf-method-card input[type="radio"]' ).forEach( function ( radio ) {
					radio.addEventListener( 'change', function () {
						if ( radio.checked ) { selectMethod( radio.value ); }
					} );
				} );

				var presetSelect = document.getElementById( 'gbp_cf_preset' );
				var hintEl = wrap.querySelector( '.gbp-cf-preset-hint' );
				var summaryEl = wrap.querySelector( '.gbp-cf-mailbox-summary' );

				function updatePresetHint() {
					if ( ! presetSelect ) { return; }
					var opt = presetSelect.options[ presetSelect.selectedIndex ];
					if ( ! opt ) { return; }
					if ( hintEl ) { hintEl.textContent = opt.getAttribute( 'data-hint' ) || ''; }
					if ( summaryEl ) {
						var summary = opt.getAttribute( 'data-summary' ) || '';
						summaryEl.textContent = summary ? ( config.autoPrefix + summary ) : '';
					}
					if ( opt.value === 'other' ) {
						var manualRadio = wrap.querySelector( '.gbp-cf-method-card input[value="manual"]' );
						if ( manualRadio ) { manualRadio.checked = true; selectMethod( 'manual' ); }
					}
				}

				if ( presetSelect ) {
					presetSelect.addEventListener( 'change', updatePresetHint );
					updatePresetHint();
				}

				var btn = document.getElementById( 'gbp-cf-test-mail' );
				var out = document.getElementById( 'gbp-cf-test-result' );
				if ( btn && out ) {
					btn.addEventListener( 'click', function () {
						btn.disabled = true;
						out.className = 'gbp-cf-test-result';
						out.textContent = config.strings.sending;
						var data = new FormData();
						data.append( 'action', 'gutenblock_pro_cf_test_mail' );
						data.append( 'nonce', config.nonce );
						fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
							.then( function ( r ) { return r.json(); } )
							.then( function ( res ) {
								var ok = res && res.success;
								out.textContent = ( res && res.data && res.data.message ) ? res.data.message : '';
								out.className = 'gbp-cf-test-result ' + ( ok ? 'is-ok' : 'is-fail' );
							} )
							.catch( function () {
								out.textContent = config.strings.error;
								out.className = 'gbp-cf-test-result is-fail';
							} )
							.finally( function () { btn.disabled = false; } );
					} );
				}
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Render a single method selection card with its radio input.
	 *
	 * @param string $key     Method key.
	 * @param string $current Currently selected method.
	 * @param string $title   Card title.
	 * @param string $desc    Card description.
	 */
	private function render_method_card( $key, $current, $title, $desc ) {
		$mailer  = 'GutenBlock_Pro_Contact_Form_Mailer';
		$checked = ( $current === $key );
		?>
		<label class="gbp-cf-method-card<?php echo $checked ? ' is-selected' : ''; ?>">
			<input type="radio" name="<?php echo esc_attr( $mailer::OPT_MAIL_METHOD ); ?>"
				value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?> />
			<span class="gbp-cf-method-title"><?php echo esc_html( $title ); ?></span>
			<span class="gbp-cf-method-desc"><?php echo esc_html( $desc ); ?></span>
		</label>
		<?php
	}
}
