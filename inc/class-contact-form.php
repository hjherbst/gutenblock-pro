<?php
/**
 * Contact Form Block – native, configurable contact form.
 *
 * Dynamic block (render_callback) with a REST submit endpoint. UI strings are
 * bilingual: English by default, German when the page locale starts with "de"
 * (or the browser-reported document language does). Spam protection via a
 * honeypot field plus a per-IP rate limit.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Contact_Form {

	const BLOCK_NAME    = 'gutenblock-pro/contact-form';
	const REST_NS       = 'gutenblock-pro/v1';
	const RATE_MAX      = 3;    // Max submissions ...
	const RATE_WINDOW   = 600;  // ... per this many seconds (10 min).
	const MAX_NAME      = 120;
	const MAX_PHONE     = 40;
	const MAX_MESSAGE   = 5000;

	/**
	 * Whether the frontend assets have been enqueued already.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Whether the shared JS config was localized already.
	 *
	 * @var bool
	 */
	private $config_printed = false;

	/**
	 * Register block + REST route. Gated by the feature toggle.
	 */
	public function init() {
		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( 'contact-form' ) ) {
			return;
		}
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the dynamic block (editor side registered in JS).
	 */
	public function register_block() {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Localisation
	// -------------------------------------------------------------------------

	/**
	 * Resolve the active form language ("de" or "en").
	 *
	 * @param string $hint Optional language hint (e.g. document.documentElement.lang).
	 * @return string
	 */
	public static function resolve_lang( $hint = '' ) {
		$hint = strtolower( (string) $hint );
		if ( strpos( $hint, 'de' ) === 0 ) {
			return 'de';
		}
		$locale = strtolower( (string) get_locale() );
		if ( strpos( $locale, 'de' ) === 0 ) {
			return 'de';
		}
		return 'en';
	}

	/**
	 * Bilingual UI + validation strings.
	 *
	 * @param string $lang "de" or "en".
	 * @return array
	 */
	public static function strings( $lang ) {
		$de = array(
			'name'            => 'Name',
			'email'           => 'E-Mail-Adresse',
			'phone'           => 'Telefonnummer',
			'message'         => 'Nachricht',
			'submit'          => 'Absenden',
			'sending'         => 'Wird gesendet…',
			'success'         => 'Vielen Dank! Ihre Nachricht wurde gesendet.',
			'consent'         => 'Ich stimme zu, dass meine Angaben zur Bearbeitung meiner Anfrage verarbeitet werden.',
			'required'        => 'Pflichtfeld',
			'err_required'    => 'Bitte füllen Sie dieses Feld aus.',
			'err_email'       => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
			'err_consent'     => 'Bitte stimmen Sie der Verarbeitung zu.',
			'err_generic'     => 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.',
			'err_rate'        => 'Zu viele Anfragen. Bitte versuchen Sie es in einigen Minuten erneut.',
			'honeypot'        => 'Postleitzahl',
			'label_name'      => 'Name',
			'label_email'     => 'E-Mail',
			'label_phone'     => 'Telefon',
			'label_message'   => 'Nachricht',
		);
		$en = array(
			'name'            => 'Name',
			'email'           => 'Email address',
			'phone'           => 'Phone number',
			'message'         => 'Message',
			'submit'          => 'Send',
			'sending'         => 'Sending…',
			'success'         => 'Thank you! Your message has been sent.',
			'consent'         => 'I agree that my data may be processed to handle my request.',
			'required'        => 'Required',
			'err_required'    => 'Please fill out this field.',
			'err_email'       => 'Please enter a valid email address.',
			'err_consent'     => 'Please agree to the processing of your data.',
			'err_generic'     => 'The message could not be sent. Please try again later.',
			'err_rate'        => 'Too many requests. Please try again in a few minutes.',
			'honeypot'        => 'Postal code',
			'label_name'      => 'Name',
			'label_email'     => 'Email',
			'label_phone'     => 'Phone',
			'label_message'   => 'Message',
		);
		return $lang === 'de' ? $de : $en;
	}

	// -------------------------------------------------------------------------
	// Frontend rendering
	// -------------------------------------------------------------------------

	/**
	 * Render the contact form HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$lang = self::resolve_lang();
		$s    = self::strings( $lang );

		$show_name      = ! isset( $attributes['showName'] ) || (bool) $attributes['showName'];
		$name_required  = ! empty( $attributes['nameRequired'] );
		$show_phone     = ! isset( $attributes['showPhone'] ) || (bool) $attributes['showPhone'];
		$phone_required = ! empty( $attributes['phoneRequired'] );
		$show_consent   = ! isset( $attributes['showConsent'] ) || (bool) $attributes['showConsent'];

		$consent_html = isset( $attributes['consentHtml'] ) && $attributes['consentHtml'] !== ''
			? wp_kses_post( $attributes['consentHtml'] )
			: esc_html( $s['consent'] );

		$submit_label = isset( $attributes['submitLabel'] ) && trim( (string) $attributes['submitLabel'] ) !== ''
			? esc_html( $attributes['submitLabel'] )
			: esc_html( $s['submit'] );

		$success_message = isset( $attributes['successMessage'] ) && trim( (string) $attributes['successMessage'] ) !== ''
			? $attributes['successMessage']
			: $s['success'];

		$form_id = isset( $attributes['formId'] ) && $attributes['formId'] !== ''
			? sanitize_html_class( $attributes['formId'] )
			: 'gbpcf-' . wp_generate_password( 8, false, false );

		$this->enqueue_frontend_assets( $lang, $s );

		// Build the grid for row 1 based on which fields are active.
		$active_top = array();
		if ( $show_name ) {
			$active_top[] = 'name';
		}
		$active_top[] = 'email';
		if ( $show_phone ) {
			$active_top[] = 'phone';
		}
		$cols = count( $active_top );

		$req_mark = '<span class="gbp-cf-req" aria-hidden="true">*</span>';

		ob_start();
		?>
		<div class="gbp-contact-form-wrap"
			data-gbp-contact-form
			data-form-id="<?php echo esc_attr( $form_id ); ?>"
			data-success="<?php echo esc_attr( $success_message ); ?>">
			<form class="gbp-contact-form" novalidate>
				<div class="gbp-cf-feedback" role="alert" aria-live="assertive" hidden></div>

				<div class="gbp-cf-row gbp-cf-row--fields" data-cols="<?php echo esc_attr( $cols ); ?>">
					<?php if ( $show_name ) : ?>
						<p class="gbp-cf-field">
							<label for="<?php echo esc_attr( $form_id ); ?>-name">
								<?php echo esc_html( $s['name'] ); ?><?php echo $name_required ? ' ' . $req_mark : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</label>
							<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name"
								maxlength="<?php echo esc_attr( self::MAX_NAME ); ?>"
								autocomplete="name"
								<?php echo $name_required ? 'required aria-required="true"' : ''; ?> />
						</p>
					<?php endif; ?>

					<p class="gbp-cf-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-email">
							<?php echo esc_html( $s['email'] ); ?> <?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</label>
						<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email"
							autocomplete="email" required aria-required="true" />
					</p>

					<?php if ( $show_phone ) : ?>
						<p class="gbp-cf-field">
							<label for="<?php echo esc_attr( $form_id ); ?>-phone">
								<?php echo esc_html( $s['phone'] ); ?><?php echo $phone_required ? ' ' . $req_mark : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</label>
							<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone"
								maxlength="<?php echo esc_attr( self::MAX_PHONE ); ?>"
								autocomplete="tel"
								<?php echo $phone_required ? 'required aria-required="true"' : ''; ?> />
						</p>
					<?php endif; ?>
				</div>

				<div class="gbp-cf-row">
					<p class="gbp-cf-field">
						<label for="<?php echo esc_attr( $form_id ); ?>-message">
							<?php echo esc_html( $s['message'] ); ?> <?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</label>
						<textarea id="<?php echo esc_attr( $form_id ); ?>-message" name="message" rows="6"
							maxlength="<?php echo esc_attr( self::MAX_MESSAGE ); ?>"
							required aria-required="true"></textarea>
					</p>
				</div>

				<?php // Honeypot: visually hidden, ignored by humans, filled by bots. ?>
				<div class="gbp-cf-hp" aria-hidden="true">
					<label for="<?php echo esc_attr( $form_id ); ?>-postcode"><?php echo esc_html( $s['honeypot'] ); ?></label>
					<input type="text" id="<?php echo esc_attr( $form_id ); ?>-postcode" name="postcode"
						tabindex="-1" autocomplete="off" />
				</div>

				<?php if ( $show_consent ) : ?>
					<div class="gbp-cf-row gbp-cf-consent">
						<label class="gbp-cf-consent-label">
							<input type="checkbox" name="consent" value="1" required aria-required="true" />
							<span class="gbp-cf-consent-text"><?php echo $consent_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses_post. ?></span>
						</label>
					</div>
				<?php endif; ?>

				<div class="gbp-cf-row gbp-cf-submit-row">
					<button type="submit" class="gbp-cf-submit wp-element-button">
						<span class="gbp-cf-submit-label"><?php echo $submit_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html above. ?></span>
					</button>
				</div>

				<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>" />
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue (once) the frontend CSS/JS and localize the shared config.
	 *
	 * @param string $lang Active language.
	 * @param array  $s    Resolved strings.
	 */
	private function enqueue_frontend_assets( $lang, $s ) {
		if ( ! $this->assets_enqueued ) {
			$css_path = GUTENBLOCK_PRO_PATH . 'assets/css/contact-form.css';
			$js_path  = GUTENBLOCK_PRO_PATH . 'assets/js/contact-form.js';

			wp_enqueue_style(
				'gbp-contact-form',
				GUTENBLOCK_PRO_URL . 'assets/css/contact-form.css',
				array(),
				file_exists( $css_path ) ? filemtime( $css_path ) : GUTENBLOCK_PRO_VERSION
			);
			wp_enqueue_script(
				'gbp-contact-form',
				GUTENBLOCK_PRO_URL . 'assets/js/contact-form.js',
				array(),
				file_exists( $js_path ) ? filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
				true
			);
			$this->assets_enqueued = true;
		}

		if ( ! $this->config_printed ) {
			wp_localize_script( 'gbp-contact-form', 'gutenblockProContactForm', array(
				'restUrl' => esc_url_raw( rest_url( self::REST_NS . '/contact-form/submit' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'lang'    => $lang,
				'strings' => array(
					'sending'      => $s['sending'],
					'errRequired'  => $s['err_required'],
					'errEmail'     => $s['err_email'],
					'errConsent'   => $s['err_consent'],
					'errGeneric'   => $s['err_generic'],
				),
			) );
			$this->config_printed = true;
		}
	}

	// -------------------------------------------------------------------------
	// REST submit
	// -------------------------------------------------------------------------

	/**
	 * Register the public submit route.
	 */
	public function register_routes() {
		register_rest_route( self::REST_NS, '/contact-form/submit', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_submit' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle a form submission: validate, rate-limit, honeypot, mail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_submit( $request ) {
		$lang = self::resolve_lang( (string) $request->get_param( 'lang' ) );
		$s    = self::strings( $lang );

		// CSRF: standard WP REST nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'message' => $s['err_generic'] ), 403 );
		}

		// Honeypot: pretend success, send nothing.
		if ( trim( (string) $request->get_param( 'postcode' ) ) !== '' ) {
			return new WP_REST_Response( array( 'message' => $s['success'] ), 200 );
		}

		// Rate limit per IP.
		if ( $this->is_rate_limited() ) {
			return new WP_REST_Response( array( 'message' => $s['err_rate'] ), 429 );
		}

		// Collect + sanitize.
		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone   = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$consent = ! empty( $request->get_param( 'consent' ) );

		// Validate required fields (email + message always required).
		$errors = array();
		if ( $email === '' || ! is_email( $email ) ) {
			$errors['email'] = $s['err_email'];
		}
		if ( $message === '' ) {
			$errors['message'] = $s['err_required'];
		}
		// Consent is required only when the field was submitted (present in form).
		if ( null !== $request->get_param( 'consent' ) && ! $consent ) {
			$errors['consent'] = $s['err_consent'];
		}

		if ( ! empty( $errors ) ) {
			return new WP_REST_Response( array(
				'message' => $s['err_generic'],
				'errors'  => $errors,
			), 422 );
		}

		// Enforce length limits server-side.
		$name    = mb_substr( $name, 0, self::MAX_NAME );
		$phone   = mb_substr( $phone, 0, self::MAX_PHONE );
		$message = mb_substr( $message, 0, self::MAX_MESSAGE );

		// Build + send.
		$recipient = GutenBlock_Pro_Contact_Form_Mailer::get_recipient();
		$subject   = GutenBlock_Pro_Contact_Form_Mailer::get_subject();
		$body      = $this->build_body( $name, $email, $phone, $message, $s );

		$reply_name = $name !== '' ? $name : $email;
		$sent = GutenBlock_Pro_Contact_Form_Mailer::send( $recipient, $subject, $body, $email, $reply_name );

		if ( ! $sent ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[GutenBlock Pro] Contact form: wp_mail returned false.' );
			}
			return new WP_REST_Response( array( 'message' => $s['err_generic'] ), 500 );
		}

		$this->bump_rate_limit();

		return new WP_REST_Response( array( 'message' => $s['success'] ), 200 );
	}

	/**
	 * Assemble the plain-text mail body.
	 *
	 * @param string $name    Sanitized name.
	 * @param string $email   Sanitized email.
	 * @param string $phone   Sanitized phone.
	 * @param string $message Sanitized message.
	 * @param array  $s       Strings.
	 * @return string
	 */
	private function build_body( $name, $email, $phone, $message, $s ) {
		$lines = array();
		if ( $name !== '' ) {
			$lines[] = $s['label_name'] . ': ' . $name;
		}
		$lines[] = $s['label_email'] . ': ' . $email;
		if ( $phone !== '' ) {
			$lines[] = $s['label_phone'] . ': ' . $phone;
		}
		$lines[] = '';
		$lines[] = $s['label_message'] . ':';
		$lines[] = $message;
		return implode( "\n", $lines );
	}

	/**
	 * Build the rate-limit transient key for the current IP.
	 *
	 * @return string
	 */
	private function rate_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		return 'gbp_cf_' . md5( $ip );
	}

	/**
	 * Whether the current IP has reached the submission limit.
	 *
	 * @return bool
	 */
	private function is_rate_limited() {
		$count = (int) get_transient( $this->rate_key() );
		return $count >= self::RATE_MAX;
	}

	/**
	 * Increment the rate-limit counter for the current IP.
	 */
	private function bump_rate_limit() {
		$key   = $this->rate_key();
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::RATE_WINDOW );
	}
}
