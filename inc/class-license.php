<?php
/**
 * License Management for GutenBlock Pro
 * 
 * Handles license activation, verification, and token tracking.
 * Lifetime licenses only - no expiration logic needed.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_License {

	/**
	 * License API URL
	 * Auto-detect local development
	 */
	private static function get_api_url() {
		// Check for constant first (useful for wp-config.php)
		if ( defined( 'GUTENBLOCK_PRO_API_URL' ) ) {
			return rtrim( GUTENBLOCK_PRO_API_URL, '/' ) . '/api/v1/license';
		}

		// Allow override via filter
		$url = apply_filters( 'gutenblock_pro_api_url', '' );
		if ( ! empty( $url ) ) {
			return rtrim( $url, '/' ) . '/api/v1/license';
		}

		// Auto-detect local development (LocalWP uses .local domains)
		$home_url = home_url();
		if ( strpos( $home_url, '.local' ) !== false || strpos( $home_url, 'localhost' ) !== false ) {
			// Local development - use localhost:3000
			return 'http://localhost:3000/api/v1/license';
		}

		// Production
		return 'https://app.gutenblock.com/api/v1/license';
	}

	/**
	 * Monthly token limit for free users
	 */
	const FREE_TOKEN_LIMIT = 10000;

	/**
	 * Option keys
	 */
	const OPTION_LICENSE_KEY = 'gutenblock_pro_license_key';
	const OPTION_LICENSE_STATUS = 'gutenblock_pro_license_status';
	const OPTION_LICENSE_PLAN = 'gutenblock_pro_license_plan';
	const OPTION_LICENSE_FEATURES = 'gutenblock_pro_license_features';
	const OPTION_LAST_VERIFIED = 'gutenblock_pro_last_verified';
	const OPTION_TOKEN_USAGE = 'gutenblock_pro_token_usage';
	const OPTION_TOKEN_RESET_DATE = 'gutenblock_pro_token_reset_date';

	/**
	 * Singleton instance
	 */
	private static $instance = null;

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
		// Schedule daily license verification
		add_action( 'gutenblock_pro_daily_license_check', array( $this, 'verify_license' ) );
		
		if ( ! wp_next_scheduled( 'gutenblock_pro_daily_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'gutenblock_pro_daily_license_check' );
		}
	}

	/**
	 * Get clean domain without protocol and www
	 */
	private function get_clean_domain() {
		$domain = home_url();
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = preg_replace( '#^www\.#', '', $domain );
		$domain = rtrim( $domain, '/' );
		return $domain;
	}

	/**
	 * Activate a license
	 *
	 * @param string $license_key The license key to activate
	 * @return array Result with success status and message
	 */
	public function activate( $license_key ) {
		$response = wp_remote_post( self::get_api_url() . '/activate', array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'license_key'    => $license_key,
				'domain'         => $this->get_clean_domain(),
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => GUTENBLOCK_PRO_VERSION,
			) ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['success'] ) ) {
			// Save license data
			update_option( self::OPTION_LICENSE_KEY, $license_key );
			update_option( self::OPTION_LICENSE_STATUS, $body['data']['status'] );
			update_option( self::OPTION_LICENSE_PLAN, $body['data']['plan'] );
			update_option( self::OPTION_LICENSE_FEATURES, $body['data']['features'] );
			update_option( self::OPTION_LAST_VERIFIED, time() );

			return array(
				'success' => true,
				'message' => __( 'Lizenz erfolgreich aktiviert!', 'gutenblock-pro' ),
				'data'    => $body['data'],
			);
		}

		return array(
			'success' => false,
			'message' => isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Aktivierung fehlgeschlagen', 'gutenblock-pro' ),
			'code'    => isset( $body['error']['code'] ) ? $body['error']['code'] : 'UNKNOWN',
		);
	}

	/**
	 * Verify license (called daily via cron)
	 *
	 * @return array|null Result or null if no license
	 */
	public function verify_license() {
		$license_key = get_option( self::OPTION_LICENSE_KEY );

		if ( empty( $license_key ) ) {
			return null;
		}

		$response = wp_remote_post( self::get_api_url() . '/verify', array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'license_key' => $license_key,
				'domain'      => $this->get_clean_domain(),
			) ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			// Network error - keep current status (grace period)
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['success'] ) ) {
			update_option( self::OPTION_LICENSE_STATUS, $body['data']['status'] );
			update_option( self::OPTION_LAST_VERIFIED, time() );
		} else {
			// License invalid - reset status
			update_option( self::OPTION_LICENSE_STATUS, 'invalid' );
		}

		return $body;
	}

	/**
	 * Deactivate license
	 *
	 * @return array Result
	 */
	public function deactivate() {
		$license_key = get_option( self::OPTION_LICENSE_KEY );

		if ( ! empty( $license_key ) ) {
			wp_remote_post( self::get_api_url() . '/deactivate', array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'license_key' => $license_key,
					'domain'      => $this->get_clean_domain(),
				) ),
				'timeout' => 15,
			) );
		}

		// Clear local data
		delete_option( self::OPTION_LICENSE_KEY );
		delete_option( self::OPTION_LICENSE_STATUS );
		delete_option( self::OPTION_LICENSE_PLAN );
		delete_option( self::OPTION_LICENSE_FEATURES );
		delete_option( self::OPTION_LAST_VERIFIED );

		return array(
			'success' => true,
			'message' => __( 'Lizenz deaktiviert', 'gutenblock-pro' ),
		);
	}

	/**
	 * Check if license is active (Pro user)
	 *
	 * @return bool
	 */
	public function is_pro() {
		$status = get_option( self::OPTION_LICENSE_STATUS );
		return $status === 'active';
	}

	/**
	 * Check if user has Premium access (Pro Plus or Lifetime)
	 * Premium access allows using premium patterns
	 *
	 * @return bool
	 */
	public function has_premium_access() {
		if ( ! $this->is_pro() ) {
			return false;
		}

		$plan = get_option( self::OPTION_LICENSE_PLAN, '' );
		$features = get_option( self::OPTION_LICENSE_FEATURES, array() );

		// Check if plan is "plus", "lifetime", or "agency"
		if ( in_array( $plan, array( 'plus', 'lifetime', 'agency' ), true ) ) {
			return true;
		}

		// Check if features include premium access
		if ( is_array( $features ) && in_array( 'premium-patterns', $features, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current license status
	 *
	 * @return array License info
	 */
	public function get_license_info() {
		return array(
			'has_license'   => ! empty( get_option( self::OPTION_LICENSE_KEY ) ),
			'status'        => get_option( self::OPTION_LICENSE_STATUS, 'none' ),
			'plan'          => get_option( self::OPTION_LICENSE_PLAN, '' ),
			'features'      => get_option( self::OPTION_LICENSE_FEATURES, array() ),
			'last_verified' => get_option( self::OPTION_LAST_VERIFIED, 0 ),
			'is_pro'        => $this->is_pro(),
		);
	}

	/**
	 * Get token usage for current month
	 *
	 * @return array Token usage info
	 */
	public function get_token_usage() {
		$this->maybe_reset_monthly_usage();

		$used = (int) get_option( self::OPTION_TOKEN_USAGE, 0 );
		$is_pro = $this->is_pro();

		return array(
			'used'      => $used,
			'limit'     => $is_pro ? -1 : self::FREE_TOKEN_LIMIT, // -1 = unlimited
			'remaining' => $is_pro ? -1 : max( 0, self::FREE_TOKEN_LIMIT - $used ),
			'is_pro'    => $is_pro,
			'reset_date' => get_option( self::OPTION_TOKEN_RESET_DATE, '' ),
		);
	}

	/**
	 * Check if user can generate (has tokens remaining)
	 *
	 * @param int $estimated_tokens Estimated tokens for the request
	 * @return bool
	 */
	public function can_generate( $estimated_tokens = 500 ) {
		if ( $this->is_pro() ) {
			return true;
		}

		$usage = $this->get_token_usage();
		return $usage['remaining'] >= $estimated_tokens;
	}

	/**
	 * Add tokens to usage counter
	 *
	 * @param int $tokens Number of tokens used
	 */
	public function add_token_usage( $tokens ) {
		$this->maybe_reset_monthly_usage();

		$current = (int) get_option( self::OPTION_TOKEN_USAGE, 0 );
		update_option( self::OPTION_TOKEN_USAGE, $current + $tokens );
	}

	/**
	 * Reset monthly usage if new month started
	 */
	private function maybe_reset_monthly_usage() {
		$current_month = gmdate( 'Y-m' );
		$reset_date = get_option( self::OPTION_TOKEN_RESET_DATE, '' );

		if ( $reset_date !== $current_month ) {
			update_option( self::OPTION_TOKEN_USAGE, 0 );
			update_option( self::OPTION_TOKEN_RESET_DATE, $current_month );
		}
	}

	/**
	 * Get masked license key for display
	 *
	 * @return string Masked key or empty
	 */
	public function get_masked_license_key() {
		$key = get_option( self::OPTION_LICENSE_KEY, '' );
		
		if ( empty( $key ) ) {
			return '';
		}

		// Show first and last 4 chars: GBPRO-XXXX-****-****
		$parts = explode( '-', $key );
		if ( count( $parts ) >= 4 ) {
			return $parts[0] . '-' . $parts[1] . '-****-****';
		}

		return substr( $key, 0, 8 ) . '****';
	}
}
