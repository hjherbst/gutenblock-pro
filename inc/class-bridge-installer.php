<?php
/**
 * GutenBlock Pro - Bridge Installer
 * Installiert und aktualisiert das Bridge mu-plugin automatisch
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Bridge_Installer {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Bridge mu-plugin Version (muss bei Updates angepasst werden)
	 */
	const BRIDGE_VERSION = '2.0.3';

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
	 * Constructor
	 */
	private function __construct() {
		// Bei Plugin-Aktivierung installieren
		register_activation_hook( GUTENBLOCK_PRO_PATH . 'gutenblock-pro.php', array( $this, 'install_bridge' ) );

		// Bei jedem Admin-Load prüfen ob Update nötig
		add_action( 'admin_init', array( $this, 'maybe_update_bridge' ) );

		// Bei Plugin-Deaktivierung NICHT entfernen (Bridge soll bleiben für Live-Preview)
	}

	/**
	 * Installiere Bridge mu-plugin
	 */
	public function install_bridge() {
		$this->copy_bridge_to_mu_plugins();
	}

	/**
	 * Prüfe ob Bridge-Update nötig ist
	 */
	public function maybe_update_bridge() {
		$installed_version = get_option( 'gutenblock_bridge_version', '0' );

		if ( version_compare( $installed_version, self::BRIDGE_VERSION, '<' ) ) {
			$this->copy_bridge_to_mu_plugins();
		}
	}

	/**
	 * Kopiere Bridge nach mu-plugins
	 */
	private function copy_bridge_to_mu_plugins() {
		$source = GUTENBLOCK_PRO_PATH . 'includes/bridge/gutenblock-bridge.php';
		$mu_plugins_dir = WP_CONTENT_DIR . '/mu-plugins';
		$target = $mu_plugins_dir . '/gutenblock-bridge.php';

		// Prüfe ob Source existiert
		if ( ! file_exists( $source ) ) {
			error_log( '[GutenBlock Pro] Bridge source not found: ' . $source );
			return false;
		}

		// Erstelle mu-plugins Ordner falls nicht vorhanden
		if ( ! file_exists( $mu_plugins_dir ) ) {
			wp_mkdir_p( $mu_plugins_dir );
		}

		// Kopiere Bridge
		$result = copy( $source, $target );

		if ( $result ) {
			update_option( 'gutenblock_bridge_version', self::BRIDGE_VERSION );
			error_log( '[GutenBlock Pro] Bridge v' . self::BRIDGE_VERSION . ' installed successfully' );

			// Entferne altes gutenblock-headers.php falls vorhanden
			$old_headers = $mu_plugins_dir . '/gutenblock-headers.php';
			if ( file_exists( $old_headers ) ) {
				unlink( $old_headers );
				error_log( '[GutenBlock Pro] Removed old gutenblock-headers.php' );
			}

			return true;
		} else {
			error_log( '[GutenBlock Pro] Failed to copy Bridge to mu-plugins' );
			return false;
		}
	}

	/**
	 * Prüfe ob Bridge installiert ist
	 */
	public function is_bridge_installed() {
		return file_exists( WP_CONTENT_DIR . '/mu-plugins/gutenblock-bridge.php' );
	}

	/**
	 * Hole installierte Bridge-Version
	 */
	public function get_installed_version() {
		return get_option( 'gutenblock_bridge_version', '0' );
	}
}
