<?php
/**
 * Contact Form Presets – SMTP server presets for the guided email setup.
 *
 * Central definition of the supported sending services and mailbox providers
 * so the settings UI and the sanitization logic share one source of truth.
 * Users pick a provider; host/port/encryption are filled in automatically.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Contact_Form_Presets {

	/**
	 * SMTP server presets keyed by slug.
	 *
	 * Each entry: host, port, encryption (tls|ssl|none).
	 *
	 * @return array
	 */
	public static function servers() {
		return array(
			'brevo'     => array( 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls' ),
			'ionos'     => array( 'host' => 'smtp.ionos.de',        'port' => 587, 'encryption' => 'tls' ),
			'strato'    => array( 'host' => 'smtp.strato.de',       'port' => 465, 'encryption' => 'ssl' ),
			'allinkl'   => array( 'host' => 'smtp.all-inkl.com',    'port' => 587, 'encryption' => 'tls' ),
			'hostinger' => array( 'host' => 'smtp.hostinger.com',   'port' => 587, 'encryption' => 'tls' ),
			'google'    => array( 'host' => 'smtp.gmail.com',       'port' => 587, 'encryption' => 'tls' ),
			'microsoft' => array( 'host' => 'smtp.office365.com',   'port' => 587, 'encryption' => 'tls' ),
			'other'     => array( 'host' => '',                     'port' => 587, 'encryption' => 'tls' ),
		);
	}

	/**
	 * Get a single server preset by slug, or null if unknown.
	 *
	 * @param string $slug Preset slug.
	 * @return array|null
	 */
	public static function get_server( $slug ) {
		$servers = self::servers();
		return isset( $servers[ $slug ] ) ? $servers[ $slug ] : null;
	}

	/**
	 * Mailbox provider options for the dropdown (label + optional hint).
	 *
	 * Returned with translated labels/hints, so call at render time.
	 *
	 * @return array slug => [ 'label' => string, 'hint' => string ]
	 */
	public static function mailbox_providers() {
		return array(
			'ionos'     => array(
				'label' => __( 'IONOS', 'gutenblock-pro' ),
				'hint'  => __( 'Benutzername ist die vollständige E-Mail-Adresse. Passwort = dein E-Mail-Postfach-Passwort.', 'gutenblock-pro' ),
			),
			'strato'    => array(
				'label' => __( 'Strato', 'gutenblock-pro' ),
				'hint'  => __( 'Benutzername ist die vollständige E-Mail-Adresse. Passwort = dein E-Mail-Postfach-Passwort.', 'gutenblock-pro' ),
			),
			'allinkl'   => array(
				'label' => __( 'All-Inkl', 'gutenblock-pro' ),
				'hint'  => __( 'Benutzername ist die vollständige E-Mail-Adresse. Passwort = dein E-Mail-Postfach-Passwort.', 'gutenblock-pro' ),
			),
			'hostinger' => array(
				'label' => __( 'Hostinger', 'gutenblock-pro' ),
				'hint'  => __( 'Benutzername ist die vollständige E-Mail-Adresse. Passwort = dein E-Mail-Postfach-Passwort.', 'gutenblock-pro' ),
			),
			'google'    => array(
				'label' => __( 'Google Workspace / Gmail', 'gutenblock-pro' ),
				'hint'  => __( 'Wichtig: Hier ist ein App-Passwort nötig, nicht dein normales Google-Passwort. Erstelle es in deinem Google-Konto unter "Sicherheit → App-Passwörter".', 'gutenblock-pro' ),
			),
			'microsoft' => array(
				'label' => __( 'Microsoft 365 / Outlook', 'gutenblock-pro' ),
				'hint'  => __( 'Benutzername ist die vollständige E-Mail-Adresse. Eventuell muss SMTP-AUTH im Microsoft-Admincenter aktiviert sein.', 'gutenblock-pro' ),
			),
			'other'     => array(
				'label' => __( 'Anderer Anbieter', 'gutenblock-pro' ),
				'hint'  => __( 'Für andere Anbieter nutze bitte die erweiterten SMTP-Einstellungen weiter unten.', 'gutenblock-pro' ),
			),
		);
	}

	/**
	 * Human-readable summary line for a server preset (host · port · encryption).
	 *
	 * @param string $slug Preset slug.
	 * @return string
	 */
	public static function summary( $slug ) {
		$server = self::get_server( $slug );
		if ( ! $server || $server['host'] === '' ) {
			return '';
		}
		$enc = strtoupper( $server['encryption'] === 'none' ? __( 'keine', 'gutenblock-pro' ) : $server['encryption'] );
		return sprintf( '%s · Port %d · %s', $server['host'], $server['port'], $enc );
	}
}
