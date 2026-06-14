<?php
/**
 * Consent Settings – admin page for the cookie/consent manager.
 *
 * A deliberately lean "Tracking & Consent" submenu. Configuration follows a
 * hybrid model: a single Google Tag Manager container is the recommended path
 * (tags are managed in the GTM UI), while direct IDs (GA4, Meta Pixel, Google
 * Ads, LinkedIn) are offered for sites that do not use GTM. When a GTM ID is
 * set, the frontend loads GTM only and ignores the direct IDs to avoid double
 * counting.
 *
 * Everything is stored in one option array (self::OPTION_NAME) consumed by
 * GutenBlock_Pro_Consent_Manager on the frontend.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Consent_Settings {

	const SETTINGS_GROUP = 'gutenblock_pro_consent';
	const OPTION_NAME    = 'gutenblock_pro_consent_settings';
	const PAGE_SLUG      = 'gutenblock-pro-consent';

	/**
	 * Default settings. Banner texts default to empty so the frontend can fall
	 * back to translated strings; only non-empty overrides are stored.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'             => false,
			'consent_mode'        => true,
			'backdrop'            => true,
			'reload_on_change'    => false,
			'gtm_id'              => '',
			'gtm_always'          => false,
			'ga4_id'              => '',
			'meta_pixel_id'       => '',
			'google_ads_id'       => '',
			'google_ads_label'    => '',
			'linkedin_partner_id' => '',
			'privacy_url'         => '',
			'banner_title'        => '',
			'banner_text'         => '',
		);
	}

	/**
	 * Read the merged settings (stored values over defaults).
	 *
	 * @return array
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Hook admin menu and settings registration.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the "Tracking & Consent" submenu page.
	 */
	public function add_submenu() {
		$label = __( 'Tracking & Consent', 'gutenblock-pro' );

		add_submenu_page(
			'gutenblock-pro',
			$label,
			$label,
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the single option array with a sanitizing callback.
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize the posted settings into the stored array shape.
	 *
	 * @param mixed $input Raw posted value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['enabled']          = ! empty( $input['enabled'] );
		$out['consent_mode']     = ! empty( $input['consent_mode'] );
		$out['backdrop']         = ! empty( $input['backdrop'] );
		$out['reload_on_change'] = ! empty( $input['reload_on_change'] );

		$out['gtm_id']              = isset( $input['gtm_id'] ) ? $this->clean_id( $input['gtm_id'] ) : '';
		$out['gtm_always']          = ! empty( $input['gtm_always'] );
		$out['ga4_id']              = isset( $input['ga4_id'] ) ? $this->clean_id( $input['ga4_id'] ) : '';
		$out['meta_pixel_id']       = isset( $input['meta_pixel_id'] ) ? $this->clean_id( $input['meta_pixel_id'] ) : '';
		$out['google_ads_id']       = isset( $input['google_ads_id'] ) ? $this->clean_id( $input['google_ads_id'] ) : '';
		$out['google_ads_label']    = isset( $input['google_ads_label'] ) ? $this->clean_id( $input['google_ads_label'] ) : '';
		$out['linkedin_partner_id'] = isset( $input['linkedin_partner_id'] ) ? $this->clean_id( $input['linkedin_partner_id'] ) : '';

		$out['privacy_url']  = isset( $input['privacy_url'] ) ? esc_url_raw( trim( (string) $input['privacy_url'] ) ) : '';
		$out['banner_title'] = isset( $input['banner_title'] ) ? sanitize_text_field( $input['banner_title'] ) : '';
		$out['banner_text']  = isset( $input['banner_text'] ) ? sanitize_textarea_field( $input['banner_text'] ) : '';

		return $out;
	}

	/**
	 * Restrict a tracking id to a safe character set (letters, digits, dash,
	 * underscore, slash). Tracking IDs never contain anything else.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_id( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_replace( '/[^A-Za-z0-9\-_\/]/', '', $value );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::get_settings();
		?>
		<?php $this->print_inline_assets(); ?>
		<div class="wrap gbp-consent-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description gbp-consent-intro">
				<?php esc_html_e( 'Blende ein schlankes Consent-Banner auf der Website ein und lade Tracking-Skripte erst nach Einwilligung. Empfohlen wird der Google Tag Manager; ohne GTM kannst du einzelne IDs direkt eintragen.', 'gutenblock-pro' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<div class="gbp-consent-cards">

					<!-- Card: Banner -->
					<section class="gbp-consent-card">
						<header class="gbp-consent-card__head">
							<span class="gbp-consent-card__icon dashicons dashicons-shield-alt" aria-hidden="true"></span>
							<div>
								<h2 class="gbp-consent-card__title"><?php esc_html_e( 'Banner & Darstellung', 'gutenblock-pro' ); ?></h2>
								<p class="gbp-consent-card__desc"><?php esc_html_e( 'Steuere, ob und wie das Consent-Banner auf der Website erscheint.', 'gutenblock-pro' ); ?></p>
							</div>
						</header>
						<div class="gbp-consent-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Consent-Banner', 'gutenblock-pro' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?> />
											<?php esc_html_e( 'Banner aktivieren und Tracking erst nach Einwilligung laden', 'gutenblock-pro' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Hintergrund', 'gutenblock-pro' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[backdrop]" value="1" <?php checked( $s['backdrop'] ); ?> />
											<?php esc_html_e( 'Seite leicht abdunkeln, bis der Nutzer eine Wahl trifft (hebt das Banner besser hervor)', 'gutenblock-pro' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="gbp_consent_privacy_url"><?php esc_html_e( 'Datenschutz-Link', 'gutenblock-pro' ); ?></label>
									</th>
									<td>
										<input type="url" class="regular-text" id="gbp_consent_privacy_url"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[privacy_url]"
											value="<?php echo esc_attr( $s['privacy_url'] ); ?>"
											placeholder="https://…/datenschutz" />
										<p class="description"><?php esc_html_e( 'URL zur Datenschutzerklärung. Wird im Banner verlinkt.', 'gutenblock-pro' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="gbp_consent_banner_title"><?php esc_html_e( 'Banner-Titel', 'gutenblock-pro' ); ?></label>
									</th>
									<td>
										<input type="text" class="regular-text" id="gbp_consent_banner_title"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[banner_title]"
											value="<?php echo esc_attr( $s['banner_title'] ); ?>"
											placeholder="<?php esc_attr_e( 'Wir respektieren deine Privatsphäre', 'gutenblock-pro' ); ?>" />
										<p class="description"><?php esc_html_e( 'Optional. Leer = Standardtext in der Seitensprache.', 'gutenblock-pro' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="gbp_consent_banner_text"><?php esc_html_e( 'Banner-Text', 'gutenblock-pro' ); ?></label>
									</th>
									<td>
										<textarea class="large-text" rows="3" id="gbp_consent_banner_text"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[banner_text]"
											placeholder="<?php esc_attr_e( 'Wir nutzen Cookies und ähnliche Technologien für Statistik und Marketing. Du kannst selbst entscheiden, welche Kategorien du zulässt.', 'gutenblock-pro' ); ?>"><?php echo esc_textarea( $s['banner_text'] ); ?></textarea>
										<p class="description"><?php esc_html_e( 'Optional. Leer = Standardtext in der Seitensprache.', 'gutenblock-pro' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</section>

					<!-- Card: Google Tag Manager -->
					<section class="gbp-consent-card">
						<header class="gbp-consent-card__head">
							<span class="gbp-consent-card__icon dashicons dashicons-tag" aria-hidden="true"></span>
							<div>
								<h2 class="gbp-consent-card__title">
									<?php esc_html_e( 'Google Tag Manager', 'gutenblock-pro' ); ?>
									<span class="gbp-consent-badge gbp-consent-badge--green"><?php esc_html_e( 'Empfohlen', 'gutenblock-pro' ); ?></span>
								</h2>
								<p class="gbp-consent-card__desc"><?php esc_html_e( 'Ein Container für alle Tags – verwalte Analytics, Ads, Meta und LinkedIn direkt im GTM.', 'gutenblock-pro' ); ?></p>
							</div>
						</header>
						<div class="gbp-consent-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="gbp_consent_gtm_id"><?php esc_html_e( 'GTM Container-ID', 'gutenblock-pro' ); ?></label>
									</th>
									<td>
										<input type="text" class="regular-text" id="gbp_consent_gtm_id"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[gtm_id]"
											value="<?php echo esc_attr( $s['gtm_id'] ); ?>"
											placeholder="GTM-XXXXXXX" />
										<p class="description">
											<?php esc_html_e( 'Wenn gesetzt, wird nur der Tag Manager geladen. Die direkten IDs unten werden dann ignoriert.', 'gutenblock-pro' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'GTM immer laden', 'gutenblock-pro' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[gtm_always]" value="1" <?php checked( $s['gtm_always'] ); ?> />
											<?php esc_html_e( 'Tag Manager bereits vor der Einwilligung laden', 'gutenblock-pro' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Der Tag Manager selbst setzt ohne Tags keine Cookies (cookieless). Tags im GTM respektieren weiterhin den Consent Mode und feuern erst nach Einwilligung. Nur sinnvoll, wenn eine GTM-Container-ID gesetzt ist.', 'gutenblock-pro' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</section>

					<!-- Card: Direct IDs -->
					<section class="gbp-consent-card">
						<header class="gbp-consent-card__head">
							<span class="gbp-consent-card__icon dashicons dashicons-admin-links" aria-hidden="true"></span>
							<div>
								<h2 class="gbp-consent-card__title">
									<?php esc_html_e( 'Direkte IDs', 'gutenblock-pro' ); ?>
									<span class="gbp-consent-badge"><?php esc_html_e( 'Ohne GTM', 'gutenblock-pro' ); ?></span>
								</h2>
								<p class="gbp-consent-card__desc"><?php esc_html_e( 'Nur nutzen, wenn du keinen Tag Manager verwendest.', 'gutenblock-pro' ); ?></p>
							</div>
						</header>
						<div class="gbp-consent-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<?php esc_html_e( 'Statistik', 'gutenblock-pro' ); ?>
										<span class="gbp-consent-cat gbp-consent-cat--analytics"></span>
									</th>
									<td>
										<label for="gbp_consent_ga4_id" class="gbp-consent-sublabel"><?php esc_html_e( 'GA4 Measurement-ID', 'gutenblock-pro' ); ?></label>
										<input type="text" class="regular-text" id="gbp_consent_ga4_id"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ga4_id]"
											value="<?php echo esc_attr( $s['ga4_id'] ); ?>"
											placeholder="G-XXXXXXXXXX" />
										<p class="description"><?php esc_html_e( 'Wird nach Einwilligung „Statistik“ geladen.', 'gutenblock-pro' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php esc_html_e( 'Marketing', 'gutenblock-pro' ); ?>
										<span class="gbp-consent-cat gbp-consent-cat--marketing"></span>
									</th>
									<td>
										<label for="gbp_consent_meta_pixel_id" class="gbp-consent-sublabel"><?php esc_html_e( 'Meta Pixel-ID', 'gutenblock-pro' ); ?></label>
										<input type="text" class="regular-text" id="gbp_consent_meta_pixel_id"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[meta_pixel_id]"
											value="<?php echo esc_attr( $s['meta_pixel_id'] ); ?>"
											placeholder="123456789012345" />

										<label for="gbp_consent_google_ads_id" class="gbp-consent-sublabel"><?php esc_html_e( 'Google Ads Conversion-ID', 'gutenblock-pro' ); ?></label>
										<input type="text" class="regular-text" id="gbp_consent_google_ads_id"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[google_ads_id]"
											value="<?php echo esc_attr( $s['google_ads_id'] ); ?>"
											placeholder="AW-XXXXXXXXX" />

										<label for="gbp_consent_google_ads_label" class="gbp-consent-sublabel"><?php esc_html_e( 'Google Ads Conversion-Label (optional)', 'gutenblock-pro' ); ?></label>
										<input type="text" class="regular-text" id="gbp_consent_google_ads_label"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[google_ads_label]"
											value="<?php echo esc_attr( $s['google_ads_label'] ); ?>"
											placeholder="abcDEF…" />

										<label for="gbp_consent_linkedin_partner_id" class="gbp-consent-sublabel"><?php esc_html_e( 'LinkedIn Partner-ID', 'gutenblock-pro' ); ?></label>
										<input type="text" class="regular-text" id="gbp_consent_linkedin_partner_id"
											name="<?php echo esc_attr( self::OPTION_NAME ); ?>[linkedin_partner_id]"
											value="<?php echo esc_attr( $s['linkedin_partner_id'] ); ?>"
											placeholder="1234567" />
										<p class="description"><?php esc_html_e( 'Wird nach Einwilligung „Marketing“ geladen.', 'gutenblock-pro' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</section>

					<!-- Card: Technical -->
					<section class="gbp-consent-card">
						<header class="gbp-consent-card__head">
							<span class="gbp-consent-card__icon dashicons dashicons-admin-settings" aria-hidden="true"></span>
							<div>
								<h2 class="gbp-consent-card__title"><?php esc_html_e( 'Technik', 'gutenblock-pro' ); ?></h2>
								<p class="gbp-consent-card__desc"><?php esc_html_e( 'Erweiterte Einstellungen für die Einwilligungssteuerung.', 'gutenblock-pro' ); ?></p>
							</div>
						</header>
						<div class="gbp-consent-card__body">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Google Consent Mode v2', 'gutenblock-pro' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[consent_mode]" value="1" <?php checked( $s['consent_mode'] ); ?> />
											<?php esc_html_e( 'Consent-Mode-Defaults setzen (analytics_storage und ad_storage standardmäßig „denied“, bis der Nutzer einwilligt)', 'gutenblock-pro' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Seite neu laden', 'gutenblock-pro' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[reload_on_change]" value="1" <?php checked( $s['reload_on_change'] ); ?> />
											<?php esc_html_e( 'Seite nach geänderter Einwilligung neu laden', 'gutenblock-pro' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Ohne Reload greifen entzogene Einwilligungen erst beim nächsten Seitenaufruf, da bereits geladene Skripte nicht entfernt werden können. Mit dieser Option wird die Seite direkt neu geladen, sobald der Nutzer seine Auswahl ändert.', 'gutenblock-pro' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</section>

					<!-- Card: Reopen settings -->
					<section class="gbp-consent-card">
						<header class="gbp-consent-card__head">
							<span class="gbp-consent-card__icon dashicons dashicons-update" aria-hidden="true"></span>
							<div>
								<h2 class="gbp-consent-card__title"><?php esc_html_e( 'Einstellungen erneut öffnen', 'gutenblock-pro' ); ?></h2>
								<p class="gbp-consent-card__desc"><?php esc_html_e( 'Biete Besuchern jederzeit die Möglichkeit, ihre Einwilligung zu ändern – z. B. im Footer oder in der Datenschutzerklärung.', 'gutenblock-pro' ); ?></p>
							</div>
						</header>
						<div class="gbp-consent-card__body">
							<p class="description gbp-consent-note">
								<?php
								printf(
									/* translators: %s: CSS class name. */
									esc_html__( 'Versieh einen beliebigen Link mit der CSS-Klasse %s. Ein Klick darauf öffnet das Consent-Banner direkt in der Einstellungsansicht.', 'gutenblock-pro' ),
									'<code>consent-settings</code>'
								);
								?>
							</p>
							<pre class="gbp-consent-code">&lt;a href="#" class="consent-settings"&gt;<?php esc_html_e( 'Cookie-Einstellungen', 'gutenblock-pro' ); ?>&lt;/a&gt;</pre>
						</div>
					</section>

				</div><!-- .gbp-consent-cards -->

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Minimal inline admin styles (a couple of spacing tweaks). Kept inline to
	 * avoid shipping a dedicated admin stylesheet for a few rules.
	 */
	private function print_inline_assets() {
		?>
		<style>
			.gbp-consent-settings .gbp-consent-intro { max-width: 46rem; }

			.gbp-consent-settings .gbp-consent-cards {
				display: flex;
				flex-direction: column;
				gap: 20px;
				max-width: 820px;
				margin-top: 16px;
			}

			.gbp-consent-settings .gbp-consent-card {
				background: #fff;
				border: 1px solid #dcdfe4;
				border-radius: 10px;
				box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
				overflow: hidden;
			}

			.gbp-consent-settings .gbp-consent-card__head {
				display: flex;
				align-items: flex-start;
				gap: 12px;
				padding: 16px 20px;
				background: #f6f7f9;
				border-bottom: 1px solid #e3e6ec;
			}

			.gbp-consent-settings .gbp-consent-card__icon {
				font-size: 22px;
				width: 22px;
				height: 22px;
				color: #5b2be0;
				flex: 0 0 auto;
				margin-top: 2px;
			}

			.gbp-consent-settings .gbp-consent-card__title {
				margin: 0;
				font-size: 14px;
				font-weight: 600;
				line-height: 1.3;
				display: flex;
				align-items: center;
				gap: 8px;
			}

			.gbp-consent-settings .gbp-consent-card__desc {
				margin: 3px 0 0;
				color: #646970;
				font-size: 13px;
			}

			.gbp-consent-settings .gbp-consent-card__body {
				padding: 4px 20px 8px;
			}

			.gbp-consent-settings .gbp-consent-card__body .form-table th {
				padding-top: 16px;
				padding-bottom: 16px;
			}

			.gbp-consent-settings .gbp-consent-badge {
				display: inline-block;
				font-size: 11px;
				font-weight: 600;
				line-height: 1.6;
				padding: 0 8px;
				border-radius: 999px;
				background: #eceef2;
				color: #50575e;
				text-transform: none;
			}

			.gbp-consent-settings .gbp-consent-badge--green {
				background: #e6f4ea;
				color: #1e7e44;
			}

			.gbp-consent-settings .gbp-consent-cat {
				display: inline-block;
				width: 8px;
				height: 8px;
				border-radius: 50%;
				margin-left: 8px;
				vertical-align: middle;
			}

			.gbp-consent-settings .gbp-consent-cat--analytics { background: #2271b1; }
			.gbp-consent-settings .gbp-consent-cat--marketing { background: #d63638; }

			.gbp-consent-settings .gbp-consent-sublabel {
				display: block;
				margin: 0.6rem 0 0.2rem;
				font-weight: 600;
			}
			.gbp-consent-settings .gbp-consent-sublabel:first-child { margin-top: 0; }

			.gbp-consent-settings .gbp-consent-note { margin: 12px 0 8px; }
			.gbp-consent-settings .gbp-consent-note code {
				background: #f0f0f1;
				padding: 1px 6px;
				border-radius: 4px;
			}

			.gbp-consent-settings .gbp-consent-code {
				margin: 0 0 8px;
				padding: 10px 12px;
				background: #1e1e1e;
				color: #e6e6e6;
				border-radius: 8px;
				font-size: 12px;
				overflow-x: auto;
			}
		</style>
		<?php
	}
}
