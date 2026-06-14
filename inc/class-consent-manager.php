<?php
/**
 * Consent Manager – frontend banner and consent-gated tracking.
 *
 * Renders a lean consent banner and loads tracking scripts only after the
 * visitor opts in. No third-party request is made before consent: the actual
 * GTM / GA4 / Meta / Google Ads / LinkedIn snippets are injected client-side
 * by assets/js/consent-manager.js once the matching category is granted.
 *
 * When a Google Tag Manager container is configured it is the only loader
 * (tags are managed inside GTM); the direct IDs are used only when GTM is
 * empty. Google Consent Mode v2 defaults are printed early (denied) so tags
 * fired through GTM respect consent from the first paint.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Consent_Manager {

	/** @var array Resolved settings. */
	private $settings = array();

	/**
	 * Hook frontend output when the banner is enabled.
	 */
	public function init() {
		if ( is_admin() ) {
			return;
		}
		$this->settings = GutenBlock_Pro_Consent_Settings::get_settings();
		if ( empty( $this->settings['enabled'] ) ) {
			return;
		}
		// Consent Mode defaults must run before any tag → very early in <head>.
		add_action( 'wp_head', array( $this, 'print_consent_mode_defaults' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Whether any tracking target is configured (GTM or any direct ID).
	 *
	 * @return bool
	 */
	private function has_any_target() {
		$s = $this->settings;
		return '' !== $s['gtm_id']
			|| '' !== $s['ga4_id']
			|| '' !== $s['meta_pixel_id']
			|| '' !== $s['google_ads_id']
			|| '' !== $s['linkedin_partner_id'];
	}

	/**
	 * Print the Google Consent Mode v2 defaults (all denied) plus the gtag
	 * stub so later consent updates have a target. Skipped when Consent Mode
	 * is disabled or nothing is configured.
	 */
	public function print_consent_mode_defaults() {
		if ( empty( $this->settings['consent_mode'] ) || ! $this->has_any_target() ) {
			return;
		}
		?>
<script id="gbp-consent-mode-default">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
	ad_storage: 'denied',
	ad_user_data: 'denied',
	ad_personalization: 'denied',
	analytics_storage: 'denied',
	wait_for_update: 500
});
</script>
		<?php
	}

	/**
	 * Enqueue the banner assets and hand the resolved config to the script.
	 */
	public function enqueue_assets() {
		if ( ! $this->has_any_target() ) {
			return;
		}

		$css_path = GUTENBLOCK_PRO_PATH . 'assets/css/consent-manager.css';
		$js_path  = GUTENBLOCK_PRO_PATH . 'assets/js/consent-manager.js';

		wp_enqueue_style(
			'gbp-consent-manager',
			GUTENBLOCK_PRO_URL . 'assets/css/consent-manager.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : GUTENBLOCK_PRO_VERSION
		);
		wp_enqueue_script(
			'gbp-consent-manager',
			GUTENBLOCK_PRO_URL . 'assets/js/consent-manager.js',
			array(),
			file_exists( $js_path ) ? filemtime( $js_path ) : GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_localize_script( 'gbp-consent-manager', 'gutenblockProConsent', $this->build_config() );
	}

	/**
	 * Build the config object passed to the frontend script. When a GTM
	 * container is set the direct IDs are intentionally omitted so the banner
	 * loads GTM only.
	 *
	 * @return array
	 */
	private function build_config() {
		$s        = $this->settings;
		$has_gtm  = '' !== $s['gtm_id'];
		$strings  = $this->get_strings();

		$config = array(
			'cookieName'  => 'gb_consent',
			'ttlDays'     => 180,
			'consentMode' => ! empty( $s['consent_mode'] ),
			'backdrop'    => ! empty( $s['backdrop'] ),
			// Reload the page when a returning visitor changes their choice, so
			// already-injected scripts are cleared (they cannot be unloaded).
			'reloadOnChange' => ! empty( $s['reload_on_change'] ),
			'privacyUrl'  => $s['privacy_url'],
			'useGtm'      => $has_gtm,
			'gtmId'       => $has_gtm ? $s['gtm_id'] : '',
			// Load GTM before consent (it is cookieless without tags; tags still
			// respect Consent Mode and only fire after opt-in).
			'gtmAlways'   => $has_gtm && ! empty( $s['gtm_always'] ),
			// Direct IDs only when GTM is not in use (avoids double counting).
			'ga4Id'              => $has_gtm ? '' : $s['ga4_id'],
			'metaPixelId'        => $has_gtm ? '' : $s['meta_pixel_id'],
			'googleAdsId'        => $has_gtm ? '' : $s['google_ads_id'],
			'googleAdsLabel'     => $has_gtm ? '' : $s['google_ads_label'],
			'linkedinPartnerId'  => $has_gtm ? '' : $s['linkedin_partner_id'],
			'strings'            => $strings,
		);

		return $config;
	}

	/**
	 * Banner strings. Admin overrides win for title/text; everything else uses
	 * locale-based defaults (German on de_* sites, English otherwise).
	 *
	 * @return array
	 */
	private function get_strings() {
		$is_de = ( 0 === strpos( (string) get_locale(), 'de' ) );

		if ( $is_de ) {
			$defaults = array(
				'title'        => __( 'Wir respektieren deine Privatsphäre', 'gutenblock-pro' ),
				'body'         => __( 'Wir nutzen Cookies und ähnliche Technologien für Statistik und Marketing. Du kannst selbst entscheiden, welche Kategorien du zulässt.', 'gutenblock-pro' ),
				'acceptAll'    => __( 'Alle akzeptieren', 'gutenblock-pro' ),
				'rejectAll'    => __( 'Nur notwendige', 'gutenblock-pro' ),
				'save'         => __( 'Auswahl speichern', 'gutenblock-pro' ),
				'customize'    => __( 'Einstellungen', 'gutenblock-pro' ),
				'analytics'    => __( 'Statistik', 'gutenblock-pro' ),
				'analyticsDsc' => __( 'Hilft uns zu verstehen, wie die Website genutzt wird (z. B. Google Analytics).', 'gutenblock-pro' ),
				'marketing'    => __( 'Marketing', 'gutenblock-pro' ),
				'marketingDsc' => __( 'Ermöglicht personalisierte Werbung und Conversion-Messung (z. B. Meta, Google Ads, LinkedIn).', 'gutenblock-pro' ),
				'privacy'      => __( 'Datenschutzerklärung', 'gutenblock-pro' ),
			);
		} else {
			$defaults = array(
				'title'        => __( 'We respect your privacy', 'gutenblock-pro' ),
				'body'         => __( 'We use cookies and similar technologies for statistics and marketing. You decide which categories to allow.', 'gutenblock-pro' ),
				'acceptAll'    => __( 'Accept all', 'gutenblock-pro' ),
				'rejectAll'    => __( 'Essential only', 'gutenblock-pro' ),
				'save'         => __( 'Save choice', 'gutenblock-pro' ),
				'customize'    => __( 'Settings', 'gutenblock-pro' ),
				'analytics'    => __( 'Statistics', 'gutenblock-pro' ),
				'analyticsDsc' => __( 'Helps us understand how the site is used (e.g. Google Analytics).', 'gutenblock-pro' ),
				'marketing'    => __( 'Marketing', 'gutenblock-pro' ),
				'marketingDsc' => __( 'Enables personalized advertising and conversion measurement (e.g. Meta, Google Ads, LinkedIn).', 'gutenblock-pro' ),
				'privacy'      => __( 'Privacy policy', 'gutenblock-pro' ),
			);
		}

		// Admin overrides for the two free-text fields.
		if ( '' !== $this->settings['banner_title'] ) {
			$defaults['title'] = $this->settings['banner_title'];
		}
		if ( '' !== $this->settings['banner_text'] ) {
			$defaults['body'] = $this->settings['banner_text'];
		}

		return $defaults;
	}
}
