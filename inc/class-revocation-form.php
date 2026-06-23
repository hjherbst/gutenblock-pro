<?php
/**
 * Revocation Form Block – native withdrawal / right-of-withdrawal form.
 *
 * A second native form block (sibling of the contact form) for the statutory
 * right of withdrawal ("Widerruf"). The wording is deliberately neutral so the
 * same form works for physical goods, digital products and digital service
 * contracts; an optional "contract type" setting tunes the item-field wording.
 *
 * Dynamic block (render_callback) with a public REST submit endpoint. UI strings
 * are bilingual: English by default, German when the page/site locale starts
 * with "de". Email delivery reuses the contact form's mailer (and therefore its
 * SMTP settings). Spam protection via a honeypot field plus a per-IP rate limit.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Revocation_Form {

	const BLOCK_NAME  = 'gutenblock-pro/revocation-form';
	const REST_NS     = 'gutenblock-pro/v1';
	const FEATURE     = 'revocation-form';
	const RATE_MAX    = 3;
	const RATE_WINDOW = 600;
	const MAX_NAME    = 120;
	const MAX_ORDER   = 80;
	const MAX_DATE    = 20;
	const MAX_ADDRESS = 300;
	const MAX_ITEMS   = 3000;
	const MAX_REASON  = 2000;

	/** @var bool */
	private $assets_enqueued = false;

	/** @var bool */
	private $config_printed = false;

	/**
	 * Register block + REST route. Gated by the feature toggle.
	 */
	public function init() {
		add_filter( 'allowed_block_types_all', array( $this, 'filter_allowed_blocks' ), 10, 2 );

		if ( ! GutenBlock_Pro_Features_Page::is_feature_enabled( self::FEATURE ) ) {
			return;
		}
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Hide the block from the inserter when the feature is disabled.
	 *
	 * @param array|bool $allowed Allowed block types.
	 * @param WP_Post    $post    Current post (unused).
	 * @return array|bool
	 */
	public function filter_allowed_blocks( $allowed, $post ) {
		if ( GutenBlock_Pro_Features_Page::is_feature_enabled( self::FEATURE ) ) {
			return $allowed;
		}
		if ( true === $allowed ) {
			$allowed = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
		}
		return array_values(
			array_filter(
				(array) $allowed,
				function ( $name ) {
					return self::BLOCK_NAME !== $name;
				}
			)
		);
	}

	/**
	 * Register the dynamic block (editor side registered in JS).
	 */
	public function register_block() {
		register_block_type(
			self::BLOCK_NAME,
			array(
				// Declare align support server-side too, so get_block_wrapper_attributes()
				// emits the alignwide/alignfull class chosen in the editor toolbar.
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
				),
				'attributes'      => array(
					'align' => array( 'type' => 'string' ),
				),
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
			'name'              => 'Name',
			'email'             => 'E-Mail-Adresse',
			'order_number'      => 'Bestell- oder Buchungsnummer',
			'order_date'        => 'Bestellt am',
			'receipt_date'      => 'Erhalten am',
			'address'           => 'Anschrift',
			'reason'            => 'Grund des Widerrufs (optional)',
			'submit'            => 'Widerruf absenden',
			'sending'           => 'Wird gesendet…',
			'success'           => 'Vielen Dank! Ihr Widerruf wurde übermittelt.',
			'consent'           => 'Ich stimme der Verarbeitung meiner Daten zur Bearbeitung meines Widerrufs zu. Es gelten die Datenschutzbestimmungen.',
			'required'          => 'Pflichtfeld',
			'err_required'      => 'Bitte füllen Sie dieses Feld aus.',
			'err_email'         => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
			'err_consent'       => 'Bitte stimmen Sie der Verarbeitung zu.',
			'err_generic'       => 'Der Widerruf konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.',
			'err_rate'          => 'Zu viele Anfragen. Bitte versuchen Sie es in einigen Minuten erneut.',
			'honeypot'          => 'Postleitzahl',
			// Mail labels.
			'label_name'        => 'Name',
			'label_email'       => 'E-Mail',
			'label_order'       => 'Bestell-/Buchungsnummer',
			'label_order_date'  => 'Bestellt am',
			'label_receipt'     => 'Erhalten am',
			'label_address'     => 'Anschrift',
			'label_items'       => 'Widerrufene Artikel/Leistungen',
			'label_reason'      => 'Grund',
			// Trader mail.
			'mail_subject'      => 'Neuer Widerruf über {site_name}',
			'mail_intro'        => 'Hiermit widerruft die unten genannte Person den abgeschlossenen Vertrag über die folgenden Artikel/Leistungen:',
			// Confirmation mail to the consumer.
			'confirm_subject'   => 'Eingangsbestätigung Ihres Widerrufs',
			'confirm_intro'     => 'Vielen Dank. Wir bestätigen den Eingang Ihres Widerrufs mit den folgenden Angaben:',
			'confirm_outro'     => 'Diese E-Mail dient als Eingangsbestätigung. Bei Rückfragen antworten Sie einfach auf diese Nachricht.',
		);
		$en = array(
			'name'              => 'Name',
			'email'             => 'Email address',
			'order_number'      => 'Order or booking number',
			'order_date'        => 'Ordered on',
			'receipt_date'      => 'Received on',
			'address'           => 'Address',
			'reason'            => 'Reason for withdrawal (optional)',
			'reason_help'       => 'Providing a reason is voluntary.',
			'submit'            => 'Submit withdrawal',
			'sending'           => 'Sending…',
			'success'           => 'Thank you! Your withdrawal has been submitted.',
			'consent'           => 'I agree to the processing of my data to handle my withdrawal. The privacy policy applies.',
			'required'          => 'Required',
			'err_required'      => 'Please fill out this field.',
			'err_email'         => 'Please enter a valid email address.',
			'err_consent'       => 'Please agree to the processing of your data.',
			'err_generic'       => 'The withdrawal could not be sent. Please try again later.',
			'err_rate'          => 'Too many requests. Please try again in a few minutes.',
			'honeypot'          => 'Postal code',
			'label_name'        => 'Name',
			'label_email'       => 'Email',
			'label_order'       => 'Order/booking number',
			'label_order_date'  => 'Ordered on',
			'label_receipt'     => 'Received on',
			'label_address'     => 'Address',
			'label_items'       => 'Withdrawn items/services',
			'label_reason'      => 'Reason',
			'mail_subject'      => 'New withdrawal via {site_name}',
			'mail_intro'        => 'The person named below hereby withdraws from the concluded contract for the following items/services:',
			'confirm_subject'   => 'Confirmation of your withdrawal',
			'confirm_intro'     => 'Thank you. We confirm receipt of your withdrawal with the following details:',
			'confirm_outro'     => 'This email serves as your confirmation of receipt. If you have any questions, simply reply to this message.',
		);
		return 'de' === $lang ? $de : $en;
	}

	/**
	 * The "which items?" field label, tuned by the contract type.
	 *
	 * @param string $lang          "de" or "en".
	 * @param string $contract_type neutral|goods|digital|service.
	 * @return string
	 */
	public static function items_label( $lang, $contract_type ) {
		$map = array(
			'de' => array(
				'neutral' => 'Welche Artikel oder Leistungen möchten Sie widerrufen?',
				'goods'   => 'Welche Waren möchten Sie widerrufen?',
				'digital' => 'Welche digitalen Inhalte möchten Sie widerrufen?',
				'service' => 'Welche Dienstleistung möchten Sie widerrufen?',
			),
			'en' => array(
				'neutral' => 'Which items or services would you like to withdraw from?',
				'goods'   => 'Which goods would you like to return?',
				'digital' => 'Which digital content would you like to withdraw from?',
				'service' => 'Which service would you like to withdraw from?',
			),
		);
		$lang = isset( $map[ $lang ] ) ? $lang : 'en';
		$type = isset( $map[ $lang ][ $contract_type ] ) ? $contract_type : 'neutral';
		return $map[ $lang ][ $type ];
	}

	/**
	 * Normalise the contract type attribute.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function normalize_contract_type( $value ) {
		$value = is_string( $value ) ? $value : 'neutral';
		return in_array( $value, array( 'neutral', 'goods', 'digital', 'service' ), true ) ? $value : 'neutral';
	}

	/**
	 * Editor UI strings (site locale, mirrors frontend bilingual logic).
	 *
	 * @return array<string, mixed>
	 */
	public static function editor_strings() {
		$lang = self::resolve_lang();
		$s    = self::strings( $lang );
		$de   = ( 'de' === $lang );

		return array(
			'lang'             => $lang,
			'blockTitle'       => $de ? 'Widerrufsformular' : 'Withdrawal Form',
			'blockDescription' => $de
				? 'Rechtssicheres Widerrufsformular für Waren, digitale Produkte und Verträge mit E-Mail-Versand und Eingangsbestätigung.'
				: 'Compliant withdrawal form for goods, digital products and contracts with email delivery and confirmation of receipt.',
			'panelFields'      => $de ? 'Felder' : 'Fields',
			'panelWording'     => $de ? 'Wortlaut' : 'Wording',
			'panelTexts'       => $de ? 'Texte' : 'Texts',
			'panelConsent'     => $de ? 'Einwilligung' : 'Consent',
			'panelDelivery'    => $de ? 'Versand' : 'Delivery',
			'showName'         => $de ? 'Name anzeigen' : 'Show name',
			'nameRequired'     => $de ? 'Name als Pflichtfeld' : 'Name required',
			'showOrderNumber'  => $de ? 'Bestell-/Buchungsnummer anzeigen' : 'Show order/booking number',
			'orderNumberRequired' => $de ? 'Als Pflichtfeld' : 'Required',
			'showDates'        => $de ? 'Datumsfelder anzeigen' : 'Show date fields',
			'showAddress'      => $de ? 'Anschrift anzeigen' : 'Show address',
			'showReason'       => $de ? 'Grund-Feld anzeigen' : 'Show reason field',
			'fieldsNote'       => $de
				? 'E-Mail und das Feld „Welche Artikel/Leistungen“ sind immer aktiv. E-Mail ist Pflichtfeld.'
				: 'Email and the "which items/services" field are always shown. Email is required.',
			'contractType'     => $de ? 'Vertragstyp (Wortlaut)' : 'Contract type (wording)',
			'contractTypeHelp' => $de
				? 'Passt die Beschriftung des Artikel-Feldes an. „Neutral“ passt für Waren, digitale Produkte und Verträge.'
				: 'Tunes the item-field label. "Neutral" fits goods, digital products and contracts.',
			'optNeutral'       => $de ? 'Neutral (alle)' : 'Neutral (all)',
			'optGoods'         => $de ? 'Waren (physisch)' : 'Goods (physical)',
			'optDigital'       => $de ? 'Digitale Produkte/Inhalte' : 'Digital products/content',
			'optService'       => $de ? 'Dienstleistung/Vertrag' : 'Service/contract',
			'submitLabel'      => $de ? 'Button-Text' : 'Button text',
			'submitHelp'       => $de ? 'Leer = automatisch je nach Sprache.' : 'Empty = automatic based on language.',
			'successLabel'     => $de ? 'Erfolgsmeldung' : 'Success message',
			'successHelp'      => $de ? 'Ersetzt das Formular nach dem Absenden. Leer = Standard.' : 'Replaces the form after submission. Empty = default.',
			'showConsent'      => $de ? 'Checkbox anzeigen' : 'Show checkbox',
			'consentEditHint'  => $de
				? 'Den Checkbox-Text direkt im Block bearbeiten. Links über die Werkzeugleiste setzen.'
				: 'Edit the checkbox text directly in the block. Add links via the toolbar.',
			'sendConfirmation' => $de ? 'Eingangsbestätigung an Absender senden' : 'Send confirmation to sender',
			'deliveryNote'     => $de
				? 'Empfänger und SMTP-Versand werden aus den Kontaktformular-Einstellungen übernommen.'
				: 'Recipient and SMTP delivery are taken from the contact form settings.',
			// Field labels for the editor preview.
			'name'             => $s['name'],
			'email'            => $s['email'],
			'orderNumber'      => $s['order_number'],
			'orderDate'        => $s['order_date'],
			'receiptDate'      => $s['receipt_date'],
			'address'          => $s['address'],
			'reason'           => $s['reason'],
			'consent'          => $s['consent'],
			'submit'           => $s['submit'],
			'success'          => $s['success'],
			'required'         => $s['required'],
			'itemsLabels'      => array(
				'neutral' => self::items_label( $lang, 'neutral' ),
				'goods'   => self::items_label( $lang, 'goods' ),
				'digital' => self::items_label( $lang, 'digital' ),
				'service' => self::items_label( $lang, 'service' ),
			),
		);
	}

	/**
	 * Enqueue the standalone editor block script + localise its strings.
	 */
	public function enqueue_editor_assets() {
		$js_path  = GUTENBLOCK_PRO_PATH . 'assets/js/revocation-form-editor.js';
		$css_path = GUTENBLOCK_PRO_PATH . 'assets/css/revocation-form.css';

		wp_enqueue_script(
			'gbp-revocation-form-editor',
			GUTENBLOCK_PRO_URL . 'assets/js/revocation-form-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);
		wp_localize_script( 'gbp-revocation-form-editor', 'gutenblockProRevocationFormEditor', self::editor_strings() );

		// Reuse the same stylesheet in the editor for an accurate preview.
		wp_enqueue_style(
			'gbp-revocation-form',
			GUTENBLOCK_PRO_URL . 'assets/css/revocation-form.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : GUTENBLOCK_PRO_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// Frontend rendering
	// -------------------------------------------------------------------------

	/**
	 * Render the withdrawal form HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$lang = self::resolve_lang();
		$s    = self::strings( $lang );

		$contract_type = self::normalize_contract_type( $attributes['contractType'] ?? 'neutral' );

		$show_name      = ! isset( $attributes['showName'] ) || (bool) $attributes['showName'];
		$name_required  = ! empty( $attributes['nameRequired'] );
		$show_order     = ! isset( $attributes['showOrderNumber'] ) || (bool) $attributes['showOrderNumber'];
		$order_required = ! isset( $attributes['orderNumberRequired'] ) || (bool) $attributes['orderNumberRequired'];
		$show_dates     = ! empty( $attributes['showDates'] );
		$show_address   = ! empty( $attributes['showAddress'] );
		$show_reason    = ! isset( $attributes['showReason'] ) || (bool) $attributes['showReason'];
		$show_consent   = ! isset( $attributes['showConsent'] ) || (bool) $attributes['showConsent'];

		$items_label = self::items_label( $lang, $contract_type );

		$consent_html = isset( $attributes['consentHtml'] ) && '' !== $attributes['consentHtml']
			? wp_kses_post( $attributes['consentHtml'] )
			: esc_html( $s['consent'] );

		$submit_label = isset( $attributes['submitLabel'] ) && '' !== trim( (string) $attributes['submitLabel'] )
			? esc_html( $attributes['submitLabel'] )
			: esc_html( $s['submit'] );

		$success_message = isset( $attributes['successMessage'] ) && '' !== trim( (string) $attributes['successMessage'] )
			? $attributes['successMessage']
			: $s['success'];

		$form_id = isset( $attributes['formId'] ) && '' !== $attributes['formId']
			? sanitize_html_class( $attributes['formId'] )
			: 'gbprf-' . wp_generate_password( 8, false, false );

		$this->enqueue_frontend_assets( $lang, $s );

		// Top row columns: name (optional), email, order number (optional).
		$cols = 1 + ( $show_name ? 1 : 0 ) + ( $show_order ? 1 : 0 );

		$req_mark = '<span class="gbp-rf-req" aria-hidden="true">*</span>';
		$fid      = esc_attr( $form_id );

		// Merge block-supports wrapper attributes (e.g. alignwide/alignfull) so the
		// content width chosen in the block toolbar is honoured on the frontend.
		$wrapper_attributes = get_block_wrapper_attributes( array(
			'class'                    => 'gbp-revocation-form-wrap',
			'data-gbp-revocation-form' => '',
			'data-form-id'             => $form_id,
			'data-success'             => $success_message,
		) );

		ob_start();
		?>
		<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes. ?>>

			<form class="gbp-revocation-form" novalidate>
				<div class="gbp-rf-feedback" role="alert" aria-live="assertive" hidden></div>

				<div class="gbp-rf-row gbp-rf-row--fields" data-cols="<?php echo esc_attr( $cols ); ?>">
					<?php if ( $show_name ) : ?>
						<p class="gbp-rf-field">
							<label for="<?php echo $fid; ?>-name">
								<?php echo esc_html( $s['name'] ); ?><?php echo $name_required ? ' ' . $req_mark : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</label>
							<input type="text" id="<?php echo $fid; ?>-name" name="name"
								maxlength="<?php echo esc_attr( self::MAX_NAME ); ?>" autocomplete="name"
								<?php echo $name_required ? 'required aria-required="true"' : ''; ?> />
						</p>
					<?php endif; ?>

					<p class="gbp-rf-field">
						<label for="<?php echo $fid; ?>-email">
							<?php echo esc_html( $s['email'] ); ?> <?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</label>
						<input type="email" id="<?php echo $fid; ?>-email" name="email"
							autocomplete="email" required aria-required="true" />
					</p>

					<?php if ( $show_order ) : ?>
						<p class="gbp-rf-field">
							<label for="<?php echo $fid; ?>-order">
								<?php echo esc_html( $s['order_number'] ); ?><?php echo $order_required ? ' ' . $req_mark : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</label>
							<input type="text" id="<?php echo $fid; ?>-order" name="order_number"
								maxlength="<?php echo esc_attr( self::MAX_ORDER ); ?>"
								<?php echo $order_required ? 'required aria-required="true"' : ''; ?> />
						</p>
					<?php endif; ?>
				</div>

				<?php if ( $show_dates ) : ?>
					<div class="gbp-rf-row gbp-rf-row--fields" data-cols="2">
						<p class="gbp-rf-field">
							<label for="<?php echo $fid; ?>-order-date"><?php echo esc_html( $s['order_date'] ); ?></label>
							<input type="date" id="<?php echo $fid; ?>-order-date" name="order_date" />
						</p>
						<p class="gbp-rf-field">
							<label for="<?php echo $fid; ?>-receipt-date"><?php echo esc_html( $s['receipt_date'] ); ?></label>
							<input type="date" id="<?php echo $fid; ?>-receipt-date" name="receipt_date" />
						</p>
					</div>
				<?php endif; ?>

				<div class="gbp-rf-row">
					<p class="gbp-rf-field">
						<label for="<?php echo $fid; ?>-items"><?php echo esc_html( $items_label ); ?></label>
						<textarea id="<?php echo $fid; ?>-items" name="items" rows="3"
							maxlength="<?php echo esc_attr( self::MAX_ITEMS ); ?>"></textarea>
					</p>
				</div>

				<?php if ( $show_address ) : ?>
					<div class="gbp-rf-row">
						<p class="gbp-rf-field">
							<label for="<?php echo $fid; ?>-address"><?php echo esc_html( $s['address'] ); ?></label>
							<textarea id="<?php echo $fid; ?>-address" name="address" rows="2"
								maxlength="<?php echo esc_attr( self::MAX_ADDRESS ); ?>" autocomplete="street-address"></textarea>
						</p>
					</div>
				<?php endif; ?>

				<?php if ( $show_reason ) : ?>
					<div class="gbp-rf-row">
						<p class="gbp-rf-field">
							<label for="<?php echo $fid; ?>-reason"><?php echo esc_html( $s['reason'] ); ?></label>
							<textarea id="<?php echo $fid; ?>-reason" name="reason" rows="4"
								maxlength="<?php echo esc_attr( self::MAX_REASON ); ?>"></textarea>
						</p>
					</div>
				<?php endif; ?>

				<?php // Honeypot: visually hidden, ignored by humans, filled by bots. ?>
				<div class="gbp-rf-hp" aria-hidden="true">
					<label for="<?php echo $fid; ?>-postcode"><?php echo esc_html( $s['honeypot'] ); ?></label>
					<input type="text" id="<?php echo $fid; ?>-postcode" name="postcode" tabindex="-1" autocomplete="off" />
				</div>

				<?php if ( $show_consent ) : ?>
					<div class="gbp-rf-row gbp-rf-consent">
						<label class="gbp-rf-consent-label">
							<input type="checkbox" name="consent" value="1" required aria-required="true" />
							<span class="gbp-rf-consent-text"><?php echo $consent_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized via wp_kses_post. ?></span>
						</label>
					</div>
				<?php endif; ?>

				<div class="gbp-rf-row gbp-rf-submit-row wp-block-buttons">
					<div class="wp-block-button">
						<button type="submit" class="gbp-rf-submit wp-block-button__link wp-element-button">
							<span class="gbp-rf-submit-label"><?php echo $submit_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html above. ?></span>
						</button>
					</div>
				</div>

				<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>" />
				<input type="hidden" name="contract_type" value="<?php echo esc_attr( $contract_type ); ?>" />
				<input type="hidden" name="send_confirmation" value="<?php echo ( ! isset( $attributes['sendConfirmation'] ) || $attributes['sendConfirmation'] ) ? '1' : '0'; ?>" />
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue (once) the frontend CSS/JS and localise the shared config.
	 *
	 * @param string $lang Active language.
	 * @param array  $s    Resolved strings.
	 */
	private function enqueue_frontend_assets( $lang, $s ) {
		if ( ! $this->assets_enqueued ) {
			$css_path = GUTENBLOCK_PRO_PATH . 'assets/css/revocation-form.css';
			$js_path  = GUTENBLOCK_PRO_PATH . 'assets/js/revocation-form.js';

			wp_enqueue_style(
				'gbp-revocation-form',
				GUTENBLOCK_PRO_URL . 'assets/css/revocation-form.css',
				array(),
				file_exists( $css_path ) ? filemtime( $css_path ) : GUTENBLOCK_PRO_VERSION
			);
			wp_enqueue_script(
				'gbp-revocation-form',
				GUTENBLOCK_PRO_URL . 'assets/js/revocation-form.js',
				array(),
				file_exists( $js_path ) ? filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
				true
			);
			$this->assets_enqueued = true;
		}

		if ( ! $this->config_printed ) {
			wp_localize_script( 'gbp-revocation-form', 'gutenblockProRevocationForm', array(
				'restUrl' => esc_url_raw( rest_url( self::REST_NS . '/revocation-form/submit' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'lang'    => $lang,
				'strings' => array(
					'sending'     => $s['sending'],
					'errRequired' => $s['err_required'],
					'errEmail'    => $s['err_email'],
					'errConsent'  => $s['err_consent'],
					'errGeneric'  => $s['err_generic'],
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
		register_rest_route( self::REST_NS, '/revocation-form/submit', array(
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

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( array( 'message' => $s['err_generic'] ), 403 );
		}

		// Honeypot: pretend success, send nothing.
		if ( '' !== trim( (string) $request->get_param( 'postcode' ) ) ) {
			return new WP_REST_Response( array( 'message' => $s['success'] ), 200 );
		}

		if ( $this->is_rate_limited() ) {
			return new WP_REST_Response( array( 'message' => $s['err_rate'] ), 429 );
		}

		$contract_type = self::normalize_contract_type( $request->get_param( 'contract_type' ) );

		$name         = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email        = sanitize_email( (string) $request->get_param( 'email' ) );
		$order_number = sanitize_text_field( (string) $request->get_param( 'order_number' ) );
		$order_date   = sanitize_text_field( (string) $request->get_param( 'order_date' ) );
		$receipt_date = sanitize_text_field( (string) $request->get_param( 'receipt_date' ) );
		$address      = sanitize_textarea_field( (string) $request->get_param( 'address' ) );
		$items        = sanitize_textarea_field( (string) $request->get_param( 'items' ) );
		$reason       = sanitize_textarea_field( (string) $request->get_param( 'reason' ) );
		$consent      = ! empty( $request->get_param( 'consent' ) );

		$errors = array();
		if ( '' === $email || ! is_email( $email ) ) {
			$errors['email'] = $s['err_email'];
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
		$name         = mb_substr( $name, 0, self::MAX_NAME );
		$order_number = mb_substr( $order_number, 0, self::MAX_ORDER );
		$order_date   = mb_substr( $order_date, 0, self::MAX_DATE );
		$receipt_date = mb_substr( $receipt_date, 0, self::MAX_DATE );
		$address      = mb_substr( $address, 0, self::MAX_ADDRESS );
		$items        = mb_substr( $items, 0, self::MAX_ITEMS );
		$reason       = mb_substr( $reason, 0, self::MAX_REASON );

		$data = array(
			'name'          => $name,
			'email'         => $email,
			'order_number'  => $order_number,
			'order_date'    => $order_date,
			'receipt_date'  => $receipt_date,
			'address'       => $address,
			'items'         => $items,
			'reason'        => $reason,
			'contract_type' => $contract_type,
			'lang'          => $lang,
		);

		// Recipient + SMTP transport are shared with the contact form settings.
		$recipient = GutenBlock_Pro_Contact_Form_Mailer::get_recipient();

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject   = str_replace( '{site_name}', $site_name, $s['mail_subject'] );
		$body      = $this->build_trader_body( $data, $s );

		$reply_name = '' !== $name ? $name : $email;
		$sent       = GutenBlock_Pro_Contact_Form_Mailer::send( $recipient, $subject, $body, $email, $reply_name );

		if ( ! $sent ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[GutenBlock Pro] Revocation form: wp_mail returned false.' );
			}
			return new WP_REST_Response( array( 'message' => $s['err_generic'] ), 500 );
		}

		// Confirmation of receipt to the consumer (best effort, never blocks success).
		$send_confirmation = null === $request->get_param( 'send_confirmation' )
			? true
			: ! empty( $request->get_param( 'send_confirmation' ) );
		if ( $send_confirmation ) {
			$confirm_subject = $s['confirm_subject'];
			$confirm_body    = $this->build_confirmation_body( $data, $s );
			GutenBlock_Pro_Contact_Form_Mailer::send( $email, $confirm_subject, $confirm_body );
		}

		$this->bump_rate_limit();

		return new WP_REST_Response( array( 'message' => $s['success'] ), 200 );
	}

	/**
	 * Assemble the plain-text body sent to the trader.
	 *
	 * @param array $d Sanitized data.
	 * @param array $s Strings.
	 * @return string
	 */
	private function build_trader_body( $d, $s ) {
		$lines   = array();
		$lines[] = $s['mail_intro'];
		$lines[] = '';

		$items_label = self::items_label( $d['lang'], $d['contract_type'] );
		$lines[]     = $items_label;
		$lines[]     = '' !== $d['items'] ? $d['items'] : '— (' . ( 'de' === $d['lang'] ? 'gesamter Vertrag' : 'entire contract' ) . ')';
		$lines[]     = '';

		if ( '' !== $d['order_number'] ) {
			$lines[] = $s['label_order'] . ': ' . $d['order_number'];
		}
		if ( '' !== $d['order_date'] ) {
			$lines[] = $s['label_order_date'] . ': ' . $d['order_date'];
		}
		if ( '' !== $d['receipt_date'] ) {
			$lines[] = $s['label_receipt'] . ': ' . $d['receipt_date'];
		}
		if ( '' !== $d['name'] ) {
			$lines[] = $s['label_name'] . ': ' . $d['name'];
		}
		$lines[] = $s['label_email'] . ': ' . $d['email'];
		if ( '' !== $d['address'] ) {
			$lines[] = $s['label_address'] . ': ' . $d['address'];
		}
		if ( '' !== $d['reason'] ) {
			$lines[] = '';
			$lines[] = $s['label_reason'] . ':';
			$lines[] = $d['reason'];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Assemble the confirmation body sent to the consumer.
	 *
	 * @param array $d Sanitized data.
	 * @param array $s Strings.
	 * @return string
	 */
	private function build_confirmation_body( $d, $s ) {
		$lines   = array();
		$lines[] = $s['confirm_intro'];
		$lines[] = '';
		$lines[] = $this->build_trader_body( $d, $s );
		$lines[] = '';
		$lines[] = $s['confirm_outro'];
		return implode( "\n", $lines );
	}

	/**
	 * Build the rate-limit transient key for the current IP.
	 *
	 * @return string
	 */
	private function rate_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		return 'gbp_rf_' . md5( $ip );
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
