<?php
/**
 * Contact Form Mailer – wp_mail wrapper with optional SMTP transport.
 *
 * Centralises the option keys, the SMTP configuration (applied via the
 * phpmailer_init hook) and the actual send. Used by both the REST submit
 * handler and the "send test mail" admin action.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Contact_Form_Mailer {

	const OPT_RECIPIENT      = 'gutenblock_pro_cf_recipient';
	const OPT_SUBJECT        = 'gutenblock_pro_cf_subject';
	const OPT_MAIL_METHOD    = 'gutenblock_pro_cf_mail_method';
	const OPT_MAIL_PRESET    = 'gutenblock_pro_cf_mail_preset';
	const OPT_SMTP_ENABLED   = 'gutenblock_pro_cf_smtp_enabled';
	const OPT_SMTP_HOST      = 'gutenblock_pro_cf_smtp_host';
	const OPT_SMTP_PORT      = 'gutenblock_pro_cf_smtp_port';
	const OPT_SMTP_ENCRYPT   = 'gutenblock_pro_cf_smtp_encryption';
	const OPT_SMTP_USER      = 'gutenblock_pro_cf_smtp_user';
	const OPT_SMTP_PASS      = 'gutenblock_pro_cf_smtp_pass';
	const OPT_SMTP_FROM_MAIL = 'gutenblock_pro_cf_smtp_from_email';
	const OPT_SMTP_FROM_NAME = 'gutenblock_pro_cf_smtp_from_name';

	/**
	 * Register the SMTP transport hook.
	 */
	public function init() {
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
	}

	/**
	 * Whether the custom SMTP transport is enabled in the settings.
	 *
	 * @return bool
	 */
	public static function smtp_enabled() {
		return (bool) get_option( self::OPT_SMTP_ENABLED, false );
	}

	/**
	 * Resolve the recipient address (falls back to the site admin email).
	 *
	 * @return string
	 */
	public static function get_recipient() {
		$recipient = trim( (string) get_option( self::OPT_RECIPIENT, '' ) );
		if ( $recipient === '' || ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}
		return $recipient;
	}

	/**
	 * Resolve the subject line, replacing the {site_name} placeholder.
	 *
	 * @param string $fallback Default subject when the option is empty.
	 * @return string
	 */
	public static function get_subject( $fallback = '' ) {
		$subject = trim( (string) get_option( self::OPT_SUBJECT, '' ) );
		if ( $subject === '' ) {
			$subject = $fallback !== '' ? $fallback : __( 'Contact request from {site_name}', 'gutenblock-pro' );
		}
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return str_replace( '{site_name}', $site_name, $subject );
	}

	/**
	 * Apply the stored SMTP credentials to the PHPMailer instance.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (by ref).
	 */
	public function configure_smtp( $phpmailer ) {
		if ( ! self::smtp_enabled() ) {
			return;
		}

		$host = trim( (string) get_option( self::OPT_SMTP_HOST, '' ) );
		if ( $host === '' ) {
			return;
		}

		$port       = (int) get_option( self::OPT_SMTP_PORT, 587 );
		$encryption = (string) get_option( self::OPT_SMTP_ENCRYPT, 'tls' );
		$user       = (string) get_option( self::OPT_SMTP_USER, '' );
		$pass       = (string) get_option( self::OPT_SMTP_PASS, '' );

		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = $port > 0 ? $port : 587;

		if ( $user !== '' ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $user;
			$phpmailer->Password = $pass;
		} else {
			$phpmailer->SMTPAuth = false;
		}

		if ( $encryption === 'ssl' ) {
			$phpmailer->SMTPSecure = 'ssl';
		} elseif ( $encryption === 'tls' ) {
			$phpmailer->SMTPSecure = 'tls';
		} else {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		}

		$from_email = trim( (string) get_option( self::OPT_SMTP_FROM_MAIL, '' ) );
		$from_name  = trim( (string) get_option( self::OPT_SMTP_FROM_NAME, '' ) );

		if ( $from_email !== '' && is_email( $from_email ) ) {
			$phpmailer->setFrom(
				$from_email,
				$from_name !== '' ? $from_name : $phpmailer->FromName,
				false
			);
		}
	}

	/**
	 * Send a mail through wp_mail (SMTP applied transparently via the hook).
	 *
	 * @param string $to       Recipient.
	 * @param string $subject  Subject line.
	 * @param string $body     Plain text body.
	 * @param string $reply_to Optional reply-to address.
	 * @param string $reply_name Optional reply-to display name.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $reply_to = '', $reply_name = '' ) {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( $reply_to !== '' && is_email( $reply_to ) ) {
			$name     = $reply_name !== '' ? $reply_name : $reply_to;
			$headers[] = sprintf( 'Reply-To: %s <%s>', $name, $reply_to );
		}

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Resolve the selected sending method (none|brevo|mailbox|manual).
	 *
	 * Migrates legacy installs: if SMTP was enabled with a host but no method
	 * was stored yet, treat it as "manual".
	 *
	 * @return string
	 */
	public static function get_method() {
		$method = (string) get_option( self::OPT_MAIL_METHOD, '' );
		if ( $method === '' ) {
			$host = trim( (string) get_option( self::OPT_SMTP_HOST, '' ) );
			if ( self::smtp_enabled() && $host !== '' ) {
				return 'manual';
			}
			return 'none';
		}
		return in_array( $method, array( 'none', 'brevo', 'mailbox', 'manual' ), true ) ? $method : 'none';
	}

	/**
	 * Map a raw PHPMailer/wp_mail error string to a friendly, localised message.
	 *
	 * @param string $raw Raw error string (may be empty).
	 * @return string
	 */
	public static function friendly_error( $raw ) {
		$raw = strtolower( (string) $raw );

		if ( $raw !== '' ) {
			if ( strpos( $raw, 'authenticate' ) !== false || strpos( $raw, '535' ) !== false || strpos( $raw, 'username' ) !== false || strpos( $raw, 'password' ) !== false ) {
				return __( 'Die Anmeldung am Mailserver ist fehlgeschlagen. Bitte prüfe Benutzername und Passwort bzw. den SMTP-Schlüssel.', 'gutenblock-pro' );
			}
			if ( strpos( $raw, 'certificate' ) !== false || strpos( $raw, 'ssl' ) !== false || strpos( $raw, 'tls' ) !== false ) {
				return __( 'Die verschlüsselte Verbindung ist fehlgeschlagen. Bitte prüfe die Verschlüsselung (TLS oder SSL) und den Port.', 'gutenblock-pro' );
			}
			if ( strpos( $raw, 'connect' ) !== false || strpos( $raw, 'timed out' ) !== false || strpos( $raw, 'timeout' ) !== false || strpos( $raw, 'refused' ) !== false ) {
				return __( 'Die Verbindung zum Mailserver konnte nicht hergestellt werden. Bitte prüfe Host und Port.', 'gutenblock-pro' );
			}
		}

		return __( 'Die E-Mail konnte nicht gesendet werden. Bitte prüfe die Einstellungen oder kontaktiere den Support deines Anbieters.', 'gutenblock-pro' );
	}
}
