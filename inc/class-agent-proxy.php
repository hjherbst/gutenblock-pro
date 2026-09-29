<?php
/**
 * FSE Agent REST proxy, pattern fill, and catalog.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Agent_Proxy {

	const OPTION_OPENAI_KEY     = 'gutenblock_pro_openai_api_key';
	const OPTION_ANTHROPIC_KEY  = 'gutenblock_pro_anthropic_api_key';
	const OPTION_AGENT_PROVIDER = 'gutenblock_pro_agent_provider';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_gutenblock_buy_credits', array( $this, 'handle_buy_credits' ) );
	}

	/**
	 * One-click credit purchase: ask the SaaS for a Stripe Checkout session bound to
	 * this site's wallet and redirect the admin there. No account, no license key.
	 */
	public function handle_buy_credits() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nur Administratoren können Credits kaufen.', 'gutenblock-pro' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'gutenblock_buy_credits' );

		$pack   = isset( $_GET['pack'] ) ? sanitize_title( wp_unslash( $_GET['pack'] ) ) : '';
		$return = isset( $_GET['return'] ) ? esc_url_raw( wp_unslash( $_GET['return'] ) ) : '';
		$shop   = admin_url( 'admin.php?page=gutenblock-pro-license' );
		// Only ever send the buyer back into this site's wp-admin.
		if ( '' === $return || 0 !== strpos( $return, admin_url() ) ) {
			$return = $shop;
		}

		$fail = function ( $message ) use ( $shop ) {
			wp_die(
				esc_html( $message ) . '<p><a href="' . esc_url( $shop ) . '">' . esc_html__( 'Zurück', 'gutenblock-pro' ) . '</a></p>',
				esc_html__( 'Kauf nicht möglich', 'gutenblock-pro' ),
				array( 'response' => 502 )
			);
		};

		if ( '' === $pack ) {
			$fail( __( 'Kein Paket ausgewählt.', 'gutenblock-pro' ) );
		}

		$response = wp_remote_post(
			self::saas_base_url() . '/api/v1/agent/checkout',
			array(
				'headers' => $this->saas_headers( true ),
				'body'    => wp_json_encode(
					array(
						'pack'      => $pack,
						'returnUrl' => $return,
					)
				),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $response ) ) {
			$fail( __( 'GutenBlock ist gerade nicht erreichbar. Bitte in ein paar Minuten erneut versuchen.', 'gutenblock-pro' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['url'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['error'] ) ? (string) $body['error'] : __( 'Checkout konnte nicht gestartet werden.', 'gutenblock-pro' );
			$fail( $msg );
		}

		// Stripe lives on another host, so wp_safe_redirect() would reject it.
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( esc_url_raw( (string) $body['url'] ) );
		exit;
	}

	/**
	 * Base URL of the GutenBlock SaaS.
	 *
	 * @return string
	 */
	public static function saas_base_url() {
		if ( defined( 'GUTENBLOCK_PRO_API_URL' ) && GUTENBLOCK_PRO_API_URL ) {
			return rtrim( (string) GUTENBLOCK_PRO_API_URL, '/' );
		}
		if ( defined( 'GUTENBLOCK_SAAS_PUBLIC_URL' ) && GUTENBLOCK_SAAS_PUBLIC_URL ) {
			return rtrim( (string) GUTENBLOCK_SAAS_PUBLIC_URL, '/' );
		}
		$filtered = apply_filters( 'gutenblock_pro_saas_url', '' );
		if ( ! empty( $filtered ) ) {
			return rtrim( (string) $filtered, '/' );
		}
		$home = home_url();
		if ( false !== strpos( $home, '.local' ) || false !== strpos( $home, 'localhost' ) ) {
			return 'http://localhost:3000';
		}
		return 'https://app.gutenblock.com';
	}

	/**
	 * Clean site domain (no protocol / www).
	 *
	 * @return string
	 */
	public static function site_domain() {
		$domain = home_url();
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = preg_replace( '#^www\.#', '', $domain );
		return rtrim( $domain, '/' );
	}

	/**
	 * Host only (no port) — must match SaaS normalizeDomain().
	 *
	 * @return string
	 */
	public static function normalized_site_domain() {
		$host = strtolower( self::site_domain() );
		$host = preg_replace( '/:\d+$/', '', $host );
		return preg_replace( '#^www\.#', '', $host );
	}

	/**
	 * Whether this WordPress site runs on a local/dev domain.
	 *
	 * @return bool
	 */
	public static function is_local_site() {
		$host = self::normalized_site_domain();
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return true;
		}
		foreach ( array( '.local', '.test', '.lndo.site', '.ddev.site', '.instawp.xyz' ) as $suffix ) {
			if ( $suffix === substr( $host, -strlen( $suffix ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Site language (WPLANG), not the admin user's profile language.
	 *
	 * @return string Locale such as de_DE or en_US.
	 */
	public static function site_language() {
		$locale = get_option( 'WPLANG' );
		if ( ! is_string( $locale ) || '' === $locale ) {
			return 'en_US';
		}
		return $locale;
	}

	/**
	 * Chat UI strings in the site language (DE vs everything else → EN).
	 *
	 * @param string $locale Site locale.
	 * @return array<string, string>
	 */
	public static function site_ui_strings( $locale ) {
		$de = ( 0 === strpos( (string) $locale, 'de' ) );
		if ( $de ) {
			return array(
				'confirm'   => 'Sieht gut aus',
				'revise'    => 'Anders beschreiben',
				'savedHint' => 'Gespeichert. Du kannst deine Angaben jederzeit unter GutenBlock → Prompts anpassen.',
			);
		}
		return array(
			'confirm'   => 'Looks good',
			'revise'    => 'Describe it differently',
			'savedHint' => 'Saved. You can update this anytime under GutenBlock → Prompts.',
		);
	}

	public function register_routes() {
		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_chat' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_status' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/patterns',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_patterns' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/fill-pattern',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_fill_pattern' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/context',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_save_context' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/page-context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_page_context' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					$post_id = absint( $request->get_param( 'postId' ) );
					return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/page-context',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_save_page_context' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					$payload = $request->get_json_params();
					if ( ! is_array( $payload ) ) {
						$payload = array();
					}
					$post_id = absint( $payload['postId'] ?? 0 );
					return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/generate-image',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_generate_image' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'gutenblock-pro/v1',
			'/agent/upload-image',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_upload_image' ),
				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},
			)
		);
	}

	/**
	 * Whether BYOK (own keys + annual license) should be used.
	 *
	 * @return bool
	 */
	public function should_use_byok() {
		$license = GutenBlock_Pro_License::get_instance();
		if ( ! $license->has_byok_access() ) {
			return false;
		}
		$provider = get_option( self::OPTION_AGENT_PROVIDER, 'openai' );
		if ( 'anthropic' === $provider ) {
			return (bool) get_option( self::OPTION_ANTHROPIC_KEY, '' );
		}
		return (bool) get_option( self::OPTION_OPENAI_KEY, '' );
	}

	/**
	 * Pull the SaaS trial wallet into local options (no license key).
	 */
	private function sync_trial_wallet_from_saas() {
		if ( (bool) get_option( GutenBlock_Pro_License::OPTION_LICENSE_KEY, '' ) ) {
			return;
		}

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$this->ensure_site_trial();
			$usage = $this->fetch_saas_usage();
			if (
				is_array( $usage )
				&& isset( $usage['balanceSource'] )
				&& 'trial' === $usage['balanceSource']
				&& array_key_exists( 'balance', $usage )
				&& null !== $usage['balance']
			) {
				GutenBlock_Pro_Agent_Credits::sync_from_saas(
					(int) $usage['balance'],
					isset( $usage['used'] ) ? (int) $usage['used'] : GutenBlock_Pro_Agent_Credits::get_used()
				);
				return;
			}

			// Stale trial token (e.g. after SaaS DB reset) — register once more.
			GutenBlock_Pro_Agent_Credits::clear_trial_token();
		}
	}

	public function api_status() {
		$license   = GutenBlock_Pro_License::get_instance();
		$byok      = $this->should_use_byok();
		$mode      = $byok ? 'byok' : 'saas';
		$local     = self::is_local_site();
		$credits   = GutenBlock_Pro_Agent_Credits::get_summary();
		$has_key   = (bool) get_option( GutenBlock_Pro_License::OPTION_LICENSE_KEY, '' );
		$saas_bal  = null;
		$saas_used = null;
		$source    = 'site';

		if ( ! $byok ) {
			if ( ! $has_key ) {
				$this->sync_trial_wallet_from_saas();
			}
			$credits = GutenBlock_Pro_Agent_Credits::get_summary();
			$usage   = $this->fetch_saas_usage();
			if ( is_array( $usage ) && array_key_exists( 'balance', $usage ) && null !== $usage['balance'] ) {
				$saas_bal = $usage['balance'];
				if ( isset( $usage['used'] ) ) {
					$saas_used = (int) $usage['used'];
				}
				if ( isset( $usage['balanceSource'] ) && 'trial' === $usage['balanceSource'] && is_int( $saas_bal ) ) {
					GutenBlock_Pro_Agent_Credits::sync_from_saas( (int) $saas_bal, (int) $saas_used );
					$credits = GutenBlock_Pro_Agent_Credits::get_summary();
					$source  = 'trial';
				} elseif ( $has_key ) {
					$source = 'account';
				}
			}
		}

		$can_chat = false;
		$reason   = '';
		$code     = '';
		if ( $byok ) {
			$can_chat = true;
		} elseif ( $has_key && is_int( $saas_bal ) ) {
			$can_chat = $saas_bal > 0;
			if ( ! $can_chat ) {
				$code   = 'INSUFFICIENT_CREDITS';
				$reason = __( 'Dein Credit-Guthaben ist aufgebraucht. Kaufe ein Paket — Bezahlung in wenigen Klicks, ohne Lizenzschlüssel.', 'gutenblock-pro' );
			}
		} elseif ( $has_key ) {
			$can_chat = true;
		} elseif ( $credits['balance'] > 0 ) {
			$can_chat = true;
		} else {
			$code   = 'INSUFFICIENT_CREDITS';
			$reason = __( 'Deine Test-Credits sind aufgebraucht. Kaufe ein Credit-Paket — Bezahlung in wenigen Klicks, ohne Lizenzschlüssel.', 'gutenblock-pro' );
		}

		if ( ! $can_chat && ! $reason && ! $byok && ! $has_key && $local ) {
			$reason = sprintf(
				/* translators: %s: SaaS dev URL */
				__( 'Starte die GutenBlock-Entwicklungs-API (%s) und setze OPENAI_API_KEY_PLUGIN.', 'gutenblock-pro' ),
				self::saas_base_url()
			);
			$code = 'SAAS_UNREACHABLE';
		}

		return rest_ensure_response(
			array(
				'mode'            => $mode,
				'byok'            => $byok,
				'hasLicense'      => $license->is_pro(),
				'hasLicenseKey'   => $has_key,
				'plan'            => get_option( GutenBlock_Pro_License::OPTION_LICENSE_PLAN, '' ),
				'provider'        => get_option( self::OPTION_AGENT_PROVIDER, 'openai' ),
				'hasOpenaiKey'    => (bool) get_option( self::OPTION_OPENAI_KEY, '' ),
				'hasAnthropicKey' => (bool) get_option( self::OPTION_ANTHROPIC_KEY, '' ),
				'isLocal'         => $local,
				'canChat'         => $can_chat,
				'blockReason'     => $reason,
				'blockReasonCode' => $code,
				'credits'         => $credits,
				'balance'         => is_int( $saas_bal ) ? $saas_bal : $credits['balance'],
				'balanceSource'   => $source,
				'upgradeUrl'      => 'https://app.gutenblock.com/licenses',
				// Pack picker on the license page; the actual Stripe redirect happens after choosing a pack.
				'creditsShopUrl'  => admin_url( 'admin.php?page=gutenblock-pro-license#gb-credit-packs' ),
				'settingsUrl'     => admin_url( 'admin.php?page=gutenblock-pro-ai' ),
				'siteLanguage'    => self::site_language(),
				'siteContext'     => (string) get_option( 'gutenblock_pro_ai_context', '' ),
				'stylePrompt'     => (string) get_option( 'gutenblock_pro_system_prompt', '' ),
				'hasContext'      => trim( (string) get_option( 'gutenblock_pro_ai_context', '' ) ) !== '',
				'ui'              => self::site_ui_strings( self::site_language() ),
			)
		);
	}

	public function api_patterns() {
		if ( ! function_exists( 'gutenblock_bridge_rest_get_patterns' ) ) {
			return new WP_Error( 'no_bridge', 'Pattern registry is not available.', array( 'status' => 503 ) );
		}
		return gutenblock_bridge_rest_get_patterns();
	}

	public function api_fill_pattern( WP_REST_Request $request ) {
		$slug  = sanitize_title( (string) $request->get_param( 'slug' ) );
		$texts = $request->get_param( 'texts' );
		if ( '' === $slug ) {
			return new WP_Error( 'invalid_slug', 'Pattern slug is required.', array( 'status' => 400 ) );
		}
		if ( ! is_array( $texts ) ) {
			$texts = array();
		}

		$path = GUTENBLOCK_PRO_PATTERNS_PATH . $slug . '/content.html';
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		$markup = (string) file_get_contents( $path );
		if ( class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
			$markup = GutenBlock_Pro_Pattern_Loader::normalize_plugin_asset_urls( $markup );
		}

		$fields_map = array();
		foreach ( $texts as $key => $value ) {
			$k = sanitize_key( (string) $key );
			if ( '' === $k || ! is_string( $value ) ) {
				continue;
			}
			$fields_map[ $k ] = $value;
		}

		$blocks   = parse_blocks( $markup );
		$replaced = 0;
		$applied  = array();
		if ( function_exists( 'gutenblock_bridge_walk_replace_block_texts' ) && ! empty( $fields_map ) ) {
			gutenblock_bridge_walk_replace_block_texts( $blocks, $fields_map, $replaced, $applied );
		}

		$expected = function_exists( 'gutenblock_bridge_extract_pattern_content_fields' )
			? gutenblock_bridge_extract_pattern_content_fields( $slug )
			: array();
		if ( empty( $expected ) && function_exists( 'gutenblock_bridge_rest_get_patterns' ) ) {
			// Fallback: pattern.php list when HTML extraction is empty.
			$pattern_file = GUTENBLOCK_PRO_PATTERNS_PATH . $slug . '/pattern.php';
			if ( is_readable( $pattern_file ) ) {
				$meta = include $pattern_file;
				if ( is_array( $meta ) && ! empty( $meta['content_fields'] ) && is_array( $meta['content_fields'] ) ) {
					$expected = $meta['content_fields'];
				}
			}
		}
		$missing = array();
		foreach ( $expected as $field_id ) {
			$key = sanitize_key( (string) $field_id );
			if ( '' === $key ) {
				continue;
			}
			if ( empty( $applied[ $key ] ) ) {
				$missing[] = $key;
			}
		}

		$out = serialize_blocks( $blocks );
		return rest_ensure_response(
			array(
				'slug'     => $slug,
				'markup'   => $out,
				'replaced' => $replaced,
				'expected' => array_values( $expected ),
				'applied'  => array_keys( $applied ),
				'missing'  => $missing,
			)
		);
	}

	public function api_save_context( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$context = isset( $payload['context'] ) ? sanitize_textarea_field( (string) $payload['context'] ) : '';
		$context = trim( $context );
		if ( '' === $context ) {
			return new WP_Error( 'empty_context', 'Context is required.', array( 'status' => 400 ) );
		}
		if ( function_exists( 'mb_substr' ) ) {
			$context = mb_substr( $context, 0, 4000 );
		} else {
			$context = substr( $context, 0, 4000 );
		}

		update_option( 'gutenblock_pro_ai_context', $context );
		update_option( 'gutenblock_pro_system_prompt_modified', time() );

		$locale = self::site_language();
		$ui     = self::site_ui_strings( $locale );

		return rest_ensure_response(
			array(
				'ok'           => true,
				'siteContext'  => $context,
				'hasContext'   => true,
				'siteLanguage' => $locale,
				'savedHint'    => $ui['savedHint'],
			)
		);
	}

	/**
	 * Optional per-page context (stored in post meta).
	 * Used to steer copy generation for the current page (contact / landing / etc.).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_get_page_context( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'postId' ) );
		if ( ! $post_id ) {
			return rest_ensure_response(
				array(
					'pageContext'    => '',
					'hasPageContext' => false,
				)
			);
		}

		$meta_key = '_gutenblock_pro_ai_page_context';
		$context  = get_post_meta( $post_id, $meta_key, true );
		$context  = is_string( $context ) ? trim( $context ) : '';

		return rest_ensure_response(
			array(
				'pageContext'    => $context,
				'hasPageContext' => '' !== $context,
			)
		);
	}

	/**
	 * Save optional per-page context (stored in post meta).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_save_page_context( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$post_id = absint( $payload['postId'] ?? 0 );
		$context = isset( $payload['context'] ) ? sanitize_textarea_field( (string) $payload['context'] ) : '';
		$context = trim( $context );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', 'postId is required.', array( 'status' => 400 ) );
		}
		if ( '' === $context ) {
			delete_post_meta( $post_id, '_gutenblock_pro_ai_page_context' );
			return rest_ensure_response(
				array(
					'ok'              => true,
					'pageContext'    => '',
					'hasPageContext' => false,
				)
			);
		}

		if ( function_exists( 'mb_substr' ) ) {
			$context = mb_substr( $context, 0, 4000 );
		} else {
			$context = substr( $context, 0, 4000 );
		}

		update_post_meta( $post_id, '_gutenblock_pro_ai_page_context', $context );

		return rest_ensure_response(
			array(
				'ok'              => true,
				'pageContext'    => $context,
				'hasPageContext' => true,
			)
		);
	}

	/**
	 * Generate an image via GutenBlock SaaS (Replicate via Flux).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_generate_image( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$prompt = isset( $payload['prompt'] ) ? sanitize_textarea_field( (string) $payload['prompt'] ) : '';
		$aspect = isset( $payload['aspectRatio'] ) ? sanitize_text_field( (string) $payload['aspectRatio'] ) : '';

		if ( '' === trim( $prompt ) ) {
			return new WP_Error( 'missing_prompt', 'Prompt is required.', array( 'status' => 400 ) );
		}

		$body = array(
			'prompt' => $prompt,
			'aspectRatio' => '' !== $aspect ? $aspect : '1:1',
		);

		$has_key = (bool) get_option( GutenBlock_Pro_License::OPTION_LICENSE_KEY, '' );

		$response = $this->proxy_saas_generate_image( $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_response', 'Invalid response from image generator.', array( 'status' => 502 ) );
		}

		// If the SaaS debited the trial wallet, mirror it back into local WP options.
		if ( ! $this->should_use_byok() && ! $has_key ) {
			$billing = isset( $data['billing'] ) && is_array( $data['billing'] ) ? $data['billing'] : array();
			$source  = isset( $billing['balanceSource'] ) ? (string) $billing['balanceSource'] : '';
			if ( 'trial' === $source && isset( $billing['balance'] ) ) {
				GutenBlock_Pro_Agent_Credits::sync_from_saas(
					(int) $billing['balance'],
					isset( $billing['used'] ) ? (int) $billing['used'] : GutenBlock_Pro_Agent_Credits::get_used()
				);
			}
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Sideload an external image URL into the WP media library.
	 * Called after a successful Replicate generation so the hotlink is replaced
	 * with a permanent Blob/WP-Upload before the user saves the post.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	/**
	 * Build a short, slug-safe filename from a prompt string.
	 * Max 5 words, separated by hyphens, no special characters.
	 *
	 * @param string $prompt Original image prompt.
	 * @return string e.g. "sunrise-mountain-forest-fog-light"
	 */
	private static function prompt_to_slug( $prompt ) {
		// Strip anything that isn't a letter, number or whitespace.
		$clean = preg_replace( '/[^a-zA-Z0-9\s]/u', ' ', (string) $prompt );
		$clean = preg_replace( '/\s+/', ' ', trim( $clean ) );
		$words = array_filter( explode( ' ', strtolower( $clean ) ) );

		// Common stop-words to skip so the slug stays meaningful.
		$stop = array(
			'a', 'an', 'the', 'and', 'or', 'but', 'of', 'in', 'on', 'at',
			'to', 'for', 'with', 'by', 'from', 'that', 'this', 'is', 'are',
			'was', 'be', 'as', 'it', 'its', 'im', 'ich', 'ein', 'eine',
			'der', 'die', 'das', 'und', 'oder', 'von', 'in', 'auf', 'mit',
		);

		$meaningful = array();
		foreach ( $words as $word ) {
			if ( ! in_array( $word, $stop, true ) && strlen( $word ) > 1 ) {
				$meaningful[] = $word;
			}
			if ( count( $meaningful ) >= 5 ) {
				break;
			}
		}

		if ( empty( $meaningful ) ) {
			return 'ai-generated-image';
		}

		return implode( '-', $meaningful );
	}

	/**
	 * Sideload an external image URL into the WP media library.
	 * Called after a successful Replicate generation so the hotlink is replaced
	 * with a permanent Blob/WP-Upload before the user saves the post.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_upload_image( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$url    = isset( $payload['url'] ) ? esc_url_raw( (string) $payload['url'] ) : '';
		$prompt = isset( $payload['prompt'] ) ? sanitize_textarea_field( (string) $payload['prompt'] ) : '';
		$alt    = isset( $payload['alt'] ) ? sanitize_text_field( (string) $payload['alt'] ) : '';
		// Prefer the pre-generated English slug from the SaaS response; fall back to local derivation.
		$raw_slug = isset( $payload['slug'] ) ? sanitize_title( (string) $payload['slug'] ) : '';

		if ( '' === $url ) {
			return new WP_Error( 'missing_url', 'url is required.', array( 'status' => 400 ) );
		}

		// media_sideload_image is only available in admin context; load the helpers.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Use the English slug from the SaaS (any-language safe), with a local fallback.
		$slug       = '' !== $raw_slug ? $raw_slug : self::prompt_to_slug( $prompt );
		$post_title = $prompt ? ucwords( str_replace( '-', ' ', $slug ) ) : __( 'KI-generiertes Bild', 'gutenblock-pro' );
		$alt_text   = $alt ? $alt : ( $prompt ? $prompt : '' );

		// Rename the downloaded file to the slug before sideloading.
		// WP uses the original URL filename; we override via a filter.
		$slug_ref = $slug; // capture for closure.
		$rename   = static function ( $file ) use ( $slug_ref ) {
			$ext               = pathinfo( $file['name'], PATHINFO_EXTENSION ) ?: 'webp';
			$file['name']      = $slug_ref . '.' . $ext;
			return $file;
		};
		add_filter( 'wp_handle_sideload_prefilter', $rename );

		// Returns a WP_Error on failure or the attachment ID (int) on success.
		$attachment_id = media_sideload_image( $url, 0, $post_title, 'id' );

		remove_filter( 'wp_handle_sideload_prefilter', $rename );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'sideload_failed',
				$attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$attachment_id = (int) $attachment_id;

		// Update alt text and source credit.
		if ( '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Override the "Quelle" (caption/source) meta with the AI credit.
		$ai_credit = 'AI-generated via GutenBlock';
		wp_update_post(
			array(
				'ID'           => $attachment_id,
				'post_excerpt' => $ai_credit, // shown as "Caption" in the media library.
			)
		);
		// Also update the attachment metadata description field used by some themes as "Quelle".
		update_post_meta( $attachment_id, '_source_url', $ai_credit );

		$src = wp_get_attachment_url( $attachment_id );

		if ( ! $src ) {
			return new WP_Error( 'attachment_url_missing', 'Attachment created but URL could not be resolved.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'ok'           => true,
				'attachmentId' => $attachment_id,
				'url'          => $src,
				'alt'          => $alt_text,
				'slug'         => $slug,
			)
		);
	}

	public function api_chat( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		if ( empty( $payload['language'] ) ) {
			$payload['language'] = self::site_language();
		}
		if ( ! isset( $payload['siteContext'] ) ) {
			$payload['siteContext'] = (string) get_option( 'gutenblock_pro_ai_context', '' );
		}
		if ( ! isset( $payload['stylePrompt'] ) ) {
			$payload['stylePrompt'] = (string) get_option( 'gutenblock_pro_system_prompt', '' );
		}

		if ( $this->should_use_byok() ) {
			return $this->proxy_byok( $payload );
		}

		$has_key     = (bool) get_option( GutenBlock_Pro_License::OPTION_LICENSE_KEY, '' );
		$refund_only = ! empty( $payload['refundOnly'] );
		$messages    = isset( $payload['messages'] ) && is_array( $payload['messages'] ) ? $payload['messages'] : array();
		$local_refund = GutenBlock_Pro_Agent_Credits::failed_credits_from_messages( $messages );
		if ( $local_refund > 0 && ! $has_key ) {
			GutenBlock_Pro_Agent_Credits::credit( $local_refund, 'refund' );
		}

		if ( ! $has_key && ! $refund_only ) {
			$this->sync_trial_wallet_from_saas();
		}

		$credits = GutenBlock_Pro_Agent_Credits::get_summary();

		if ( ! $has_key && ! $refund_only && $credits['balance'] <= 0 ) {
			return new WP_Error(
				'insufficient_credits',
				__( 'Keine Credits mehr. Kaufe ein Paket oder aktiviere eine Lizenz unter Einstellungen → GutenBlock → Lizenz.', 'gutenblock-pro' ),
				array(
					'status'  => 402,
					'code'    => 'INSUFFICIENT_CREDITS',
					'balance' => 0,
				)
			);
		}

		$response = $this->proxy_saas( $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}

		if ( $has_key ) {
			return $response;
		}

		$billing = isset( $data['billing'] ) && is_array( $data['billing'] ) ? $data['billing'] : array();
		$source  = isset( $billing['balanceSource'] ) ? (string) $billing['balanceSource'] : '';

		if ( 'trial' === $source && isset( $billing['balance'] ) ) {
			GutenBlock_Pro_Agent_Credits::sync_from_saas(
				(int) $billing['balance'],
				isset( $billing['used'] ) ? (int) $billing['used'] : GutenBlock_Pro_Agent_Credits::get_used()
			);
			return rest_ensure_response( $data );
		}

		if ( $refund_only ) {
			$data['billing'] = array(
				'billedCredits'   => 0,
				'refundedCredits' => $local_refund,
				'balance'         => GutenBlock_Pro_Agent_Credits::get_balance(),
				'used'            => GutenBlock_Pro_Agent_Credits::get_used(),
				'balanceSource'   => 'local',
				'costPerPattern'  => GutenBlock_Pro_Agent_Credits::CREDITS_PER_PATTERN,
			);
			return rest_ensure_response( $data );
		}

		$tool_calls = array();
		if ( isset( $data['message']['tool_calls'] ) && is_array( $data['message']['tool_calls'] ) ) {
			$tool_calls = $data['message']['tool_calls'];
		}
		$cost = GutenBlock_Pro_Agent_Credits::cost_from_tool_calls( $tool_calls );

		if ( $cost > 0 && ! GutenBlock_Pro_Agent_Credits::can_afford( $cost ) ) {
			return new WP_Error(
				'insufficient_credits',
				sprintf(
					/* translators: 1: needed credits, 2: current balance */
					__( 'Nicht genug Credits (%1$d benötigt, %2$d verfügbar). Pattern = %3$d Credits.', 'gutenblock-pro' ),
					$cost,
					GutenBlock_Pro_Agent_Credits::get_balance(),
					GutenBlock_Pro_Agent_Credits::CREDITS_PER_PATTERN
				),
				array(
					'status'  => 402,
					'code'    => 'INSUFFICIENT_CREDITS',
					'needed'  => $cost,
					'balance' => GutenBlock_Pro_Agent_Credits::get_balance(),
				)
			);
		}

		if ( $cost > 0 ) {
			$new_balance     = GutenBlock_Pro_Agent_Credits::debit( $cost );
			$data['billing'] = array(
				'billedCredits'   => $cost,
				'refundedCredits' => $local_refund,
				'balance'         => $new_balance,
				'used'            => GutenBlock_Pro_Agent_Credits::get_used(),
				'balanceSource'   => 'local',
				'costPerPattern'  => GutenBlock_Pro_Agent_Credits::CREDITS_PER_PATTERN,
			);
			return rest_ensure_response( $data );
		}

		if ( ! isset( $data['billing'] ) ) {
			$data['billing'] = array(
				'billedCredits'   => 0,
				'refundedCredits' => $local_refund,
				'balance'         => GutenBlock_Pro_Agent_Credits::get_balance(),
				'used'            => GutenBlock_Pro_Agent_Credits::get_used(),
				'balanceSource'   => 'local',
				'costPerPattern'  => GutenBlock_Pro_Agent_Credits::CREDITS_PER_PATTERN,
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * @param array $payload
	 * @return WP_REST_Response|WP_Error
	 */
	private function proxy_saas_generate_image( $payload ) {
		$url     = self::saas_base_url() . '/api/v1/agent/generate-image';
		$headers = $this->saas_headers( true );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => 150,
			)
		);

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( self::is_local_site() ) {
				$msg = sprintf(
					/* translators: 1: error, 2: SaaS URL */
					__( 'Verbindung zur GutenBlock-API fehlgeschlagen (%1$s). Läuft %2$s? OPENAI_API_KEY_PLUGIN gesetzt?', 'gutenblock-pro' ),
					$msg,
					self::saas_base_url()
				);
			}
			return new WP_Error( 'saas_error', $msg, array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'saas_bad_response', 'Invalid response from image generator.', array( 'status' => 502 ) );
		}

		if ( 401 === $code && isset( $body['code'] ) ) {
			$err_code = (string) $body['code'];
			$msg      = isset( $body['error'] ) ? (string) $body['error'] : __( 'Authentication failed.', 'gutenblock-pro' );

			return new WP_Error(
				$err_code,
				$msg,
				array( 'status' => 401, 'code' => $err_code )
			);
		}

		if ( $code >= 400 ) {
			$msg      = isset( $body['error'] ) ? (string) $body['error'] : __( 'Image generation failed.', 'gutenblock-pro' );
			$err_code = isset( $body['code'] ) ? (string) $body['code'] : 'saas_error';
			$needed   = isset( $body['needed'] ) ? $body['needed'] : null;
			$balance  = isset( $body['balance'] ) ? $body['balance'] : null;
			return new WP_Error(
				$err_code,
				$msg,
				array(
					'status' => $code,
					'code'   => $err_code,
					'error'  => $err_code,
					'needed' => $needed,
					'balance' => $balance,
				)
			);
		}

		return new WP_REST_Response( $body, $code ? $code : 200 );
	}

	private function proxy_saas( $payload ) {
		$url     = self::saas_base_url() . '/api/v1/agent/chat';
		$headers = $this->saas_headers( true );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => 90,
			)
		);

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( self::is_local_site() ) {
				$msg = sprintf(
					/* translators: 1: error, 2: SaaS URL */
					__( 'Verbindung zur GutenBlock-API fehlgeschlagen (%1$s). Läuft %2$s? OPENAI_API_KEY_PLUGIN gesetzt?', 'gutenblock-pro' ),
					$msg,
					self::saas_base_url()
				);
			}
			return new WP_Error( 'saas_error', $msg, array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'saas_bad_response', 'Invalid response from GutenBlock.', array( 'status' => 502 ) );
		}

		if ( 401 === $code && isset( $body['code'] ) && 'MISSING_AUTH' === $body['code'] ) {
			return new WP_Error(
				'missing_auth',
				sprintf(
					/* translators: %d: trial credit amount */
					__(
						'Keine Lizenz und kein API-Key hinterlegt. Du startest mit %d Test-Credits — aktiviere eine Lizenz oder hinterlege Keys unter Einstellungen → GutenBlock → Lizenz.',
						'gutenblock-pro'
					),
					GutenBlock_Pro_Agent_Credits::TRIAL_CREDITS
				),
				array( 'status' => 401, 'code' => 'MISSING_AUTH' )
			);
		}

		if ( $code >= 400 ) {
			$msg = isset( $body['error'] ) ? (string) $body['error'] : __( 'Verbindung zur GutenBlock-API fehlgeschlagen.', 'gutenblock-pro' );
			if ( ! empty( $body['details'] ) && is_string( $body['details'] ) ) {
				$msg .= ' ' . $body['details'];
			}
			$err_code = isset( $body['code'] ) ? (string) $body['code'] : 'saas_error';
			return new WP_Error(
				$err_code,
				$msg,
				array(
					'status' => $code,
					'code'   => $err_code,
				)
			);
		}

		return new WP_REST_Response( $body, $code ? $code : 200 );
	}

	/**
	 * Direct OpenAI / Anthropic call with the site-owner keys.
	 *
	 * @param array $payload
	 * @return WP_REST_Response|WP_Error
	 */
	private function proxy_byok( $payload ) {
		$provider = get_option( self::OPTION_AGENT_PROVIDER, 'openai' );
		if ( 'anthropic' === $provider ) {
			return $this->byok_anthropic( $payload );
		}
		return $this->byok_openai( $payload );
	}

	private function agent_tools_openai() {
		return array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'insert_pattern',
					'description' => 'Insert one curated GutenBlock pattern into the open editor document.',
					'parameters'  => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'slug'     => array( 'type' => 'string' ),
							'texts'    => array(
								'type'                 => 'object',
								'additionalProperties' => array( 'type' => 'string' ),
							),
							'position' => array( 'type' => 'string' ),
						),
						'required'             => array( 'slug', 'texts' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'build_page',
					'description' => 'Build the current page from several patterns in order.',
					'parameters'  => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'title'    => array( 'type' => 'string' ),
							'sections' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'slug'  => array( 'type' => 'string' ),
										'texts' => array(
											'type'                 => 'object',
											'additionalProperties' => array( 'type' => 'string' ),
										),
									),
									'required'   => array( 'slug', 'texts' ),
								),
							),
						),
						'required'             => array( 'sections' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'write_article',
					'description' => 'Write a complete blog post / article directly into the editor (title, subheadings, paragraphs). Use for any article or long-form text request — never patterns.',
					'parameters'  => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'title'    => array( 'type' => 'string' ),
							'sections' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'heading'    => array( 'type' => 'string' ),
										'level'      => array( 'type' => 'number' ),
										'paragraphs' => array(
											'type'  => 'array',
											'items' => array( 'type' => 'string' ),
										),
										'list'       => array(
											'type'  => 'array',
											'items' => array( 'type' => 'string' ),
										),
									),
									'required'   => array( 'paragraphs' ),
								),
							),
						),
						'required'             => array( 'title', 'sections' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'insert_image',
					'description' => 'Insert a single image block using one of the bundled plugin images. Use when the user just wants an image added.',
					'parameters'  => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'asset'    => array(
								'type' => 'string',
								'enum' => array( 'media-1', 'media-2', 'media-3', 'media-big-1', 'media-about-female', 'media-about-female-2', 'media-about-female-3' ),
							),
							'position' => array( 'type' => 'string' ),
							'alt'      => array( 'type' => 'string' ),
						),
						'required'             => array( 'asset' ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'insert_blocks',
					'description' => 'Last-resort fallback: insert core-block markup when no catalog pattern fits and neither write_article nor insert_image applies.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'markup'   => array( 'type' => 'string' ),
							'position' => array( 'type' => 'string' ),
						),
						'required'   => array( 'markup' ),
					),
				),
			),
		);
	}

	private function build_system_prompt( $payload ) {
		$mode = isset( $payload['mode'] ) ? (string) $payload['mode'] : 'chat';
		$lang = isset( $payload['language'] ) ? trim( (string) $payload['language'] ) : self::site_language();
		$language_block = $lang
			? "SITE LANGUAGE: {$lang}. Write EVERY chat message and ALL generated copy only in this language. Never switch, even if the user writes in another language.\n"
			: "Write generated copy in the same language as the user message.\n";
		$editor_post_type = isset( $payload['editorPostType'] ) ? trim( (string) $payload['editorPostType'] ) : '';
		$editor_post_type_block = $editor_post_type ? "EDITOR POST TYPE: {$editor_post_type}.\n" : '';
		$blog_post_rule = '';
		if ( $editor_post_type && preg_match( '/post|beitrag/i', $editor_post_type ) ) {
			$blog_post_rule = "This editor is a blog post (Beitrag): treat requests for content here as article requests → call write_article.\n";
		}

		if ( 'collect_context' === $mode ) {
			return "You are the GutenBlock FSE Agent.\n"
				. $language_block
				. $editor_post_type_block
				. "The user wants website copy, but there is no business context yet.\n"
				. "Ask ONE short, friendly question: they should briefly say what they do and what they offer, so later texts can match their site.\n"
				. "Do not mention WordPress, plugins, credits, tools, or settings. Do not insert anything. Do not call tools.\n";
		}

		if ( 'summarize_context' === $mode ) {
			return "You are the GutenBlock FSE Agent.\n"
				. $language_block
				. $editor_post_type_block
				. "The user described their business. Write a compact website context (3–6 sentences): who they are, what they offer, who it is for, and the tone if implied.\n"
				. "Output ONLY that summary — no intro, no questions, no markdown headings, no quotation marks around the whole text.\n";
		}

		if ( 'fill_missing_fields' === $mode ) {
			$context = isset( $payload['siteContext'] ) ? trim( (string) $payload['siteContext'] ) : '';
			if ( '' === $context ) {
				$context = trim( (string) get_option( 'gutenblock_pro_ai_context', '' ) );
			}
			$page_context = isset( $payload['pageContext'] ) ? trim( (string) $payload['pageContext'] ) : '';
			$style = isset( $payload['stylePrompt'] ) ? trim( (string) $payload['stylePrompt'] ) : '';
			if ( '' === $style ) {
				$style = trim( (string) get_option( 'gutenblock_pro_system_prompt', '' ) );
			}

			$context_block = $context
				? "\nWebsite context (global business identity; use this for every text; do not invent a different business):\n{$context}\n"
				: "\nNo website context was provided. Write generic but plausible copy for the requested section.\n";
			$page_context_block = $page_context
				? "\nPage context (purpose and focus for this specific page; use this to steer intent and wording):\n{$page_context}\n"
				: '';
			$style_block = $style ? "\nWriting style:\n{$style}\n" : '';

			return "You are the GutenBlock FSE Agent copywriter.\n"
				. $language_block
				. $editor_post_type_block
				. $context_block
				. $page_context_block
				. $style_block
				. "The user message is JSON: { \"slug\", \"missingFields\", \"existingTexts\", \"pattern\" }.\n"
				. "Write copy ONLY for the ids listed in missingFields. Use exact keys from missingFields.\n"
				. "Infer slot purpose from each field id and the pattern title, description, ai_hint, and group.\n"
				. "Ids containing \"sample\" are real slots — never skip them.\n"
				. "Match the tone of existingTexts when provided.\n"
				. "Output ONLY valid JSON: an object mapping each missing field id → string. No markdown fences, no commentary.\n";
		}

		$patterns = isset( $payload['patterns'] ) && is_array( $payload['patterns'] ) ? $payload['patterns'] : array();
		$lines    = array();
		foreach ( $patterns as $p ) {
			if ( ! is_array( $p ) || empty( $p['slug'] ) ) {
				continue;
			}
			$fields = isset( $p['content_fields'] ) && is_array( $p['content_fields'] ) ? implode( ',', $p['content_fields'] ) : '';
			$classes = isset( $p['classes'] ) && is_array( $p['classes'] ) ? implode( ',', $p['classes'] ) : '';
			$hint   = isset( $p['ai_hint'] ) ? $p['ai_hint'] : ( isset( $p['description'] ) ? $p['description'] : '' );
			$hint   = preg_replace( '/\s+/', ' ', (string) $hint );
			$hint   = function_exists( 'mb_substr' ) ? mb_substr( $hint, 0, 220 ) : substr( $hint, 0, 220 );
			$group  = isset( $p['group'] ) ? $p['group'] : 'other';
			$title  = isset( $p['title'] ) ? $p['title'] : '';
			$class_bit = $classes ? ' | classes: ' . $classes : '';
			$lines[] = '- ' . $p['slug'] . ' [' . $group . '] ' . $title . ' | fields: ' . $fields . $class_bit . ' | ' . $hint;
		}

		$context = isset( $payload['siteContext'] ) ? trim( (string) $payload['siteContext'] ) : '';
		if ( '' === $context ) {
			$context = trim( (string) get_option( 'gutenblock_pro_ai_context', '' ) );
		}
		$page_context = isset( $payload['pageContext'] ) ? trim( (string) $payload['pageContext'] ) : '';
		$style = isset( $payload['stylePrompt'] ) ? trim( (string) $payload['stylePrompt'] ) : '';
		if ( '' === $style ) {
			$style = trim( (string) get_option( 'gutenblock_pro_system_prompt', '' ) );
		}

		$context_block = $context
			? "\nWebsite context (global business identity; use this for every text; do not invent a different business):\n{$context}\n"
			: "\nNo website context was provided. Write generic but plausible copy for the requested section.\n";
		$page_context_block = $page_context
			? "\nPage context (purpose and focus for this specific page; may override generic intent):\n{$page_context}\n"
			: '';
		$style_block = $style ? "\nWriting style:\n{$style}\n" : '';

		return "You are the GutenBlock FSE Agent, a WordPress Full Site Editor assistant.\n"
			. "You help non-experts build the CURRENTLY OPEN page/post by choosing curated patterns and filling text slots.\n"
			. "You only affect the document open in the editor canvas — never another page or the whole site.\n"
			. $language_block
			. $editor_post_type_block
			. $context_block
			. $page_context_block
			. $style_block
			. "\nScope — you CAN:\n"
			. "- Insert/fill catalog patterns and assemble a page from patterns\n"
			. "- Write blog articles via write_article\n"
			. "- Insert a bundled plugin image via insert_image\n"
			. "\nScope — you CANNOT (do NOT call any tool; answer in chat only):\n"
			. "- Change styles or design of ANY element: colors, backgrounds, borders, radius, spacing, fonts, sizes, alignment\n"
			. "- Change global theme colors, fonts, or theme.json\n"
			. "- Clone or rebuild a site from a URL/screenshot\n"
			. "- Build custom sliders, booking widgets, or third-party plugin UIs\n"
			. "- Edit a different page than the one currently open\n"
			. "When refusing, briefly explain why, suggest a fitting alternative prompt if useful, and link https://gutenblock.com/plugin/agent\n"
			. "\nCopy rules:\n"
			. "- Infer wording from the pattern title, description, category (group), CSS classes, layout hint, and content_field ids. There are no per-field block prompts.\n"
			. "- Field ids describe the slot (headline, subline, CTA, FAQ item, …). Match length and purpose to that slot.\n"
			. "- You MUST set EVERY content_field id as a key in texts — exact spelling from the catalog (including ids with \"sample\"). Missing keys leave visible template placeholder text.\n"
			. "- Keep headlines short; body copy natural, not salesy. No emojis unless the user asked.\n"
			. ( $page_context ? "- Prefer Page context for intent/focus when it is present.\n" : '' )
			. "\nTool routing (pick ONE per request):\n"
			. "1. Article / blog post / long-form text requested → call write_article exactly ONCE with the full structured article (title + all sections). Never use patterns or insert_blocks for articles. Never just offer an outline in chat — write the article via the tool.\n"
			. "2. User wants just an image (no section, no text) → call insert_image with the best-fitting bundled asset. Respect any position the user gives.\n"
			. "3. ANY styling/design request (colors, backgrounds, borders, spacing, fonts, sizes — one element or the whole site) → NO tool call. Explain briefly in chat that the agent does not change styles, point to the WordPress Styles panel (click the element, settings sidebar top right, Block tab → Styles), and link https://gutenblock.com/plugin/agent\n"
			. "4. Page sections / landing pages → prefer catalog patterns: insert_pattern (one section) or build_page (whole page).\n"
			. "5. insert_blocks is a last resort when nothing above fits. Stick to core blocks.\n"
			. "\nRules:\n"
			. "- Fill every content_field listed for the chosen pattern — every id, exact key, non-empty string.\n"
			. "- Never invent pattern slugs.\n"
			. "- After calling tools, add one short confirmation in the site language.\n"
			. $blog_post_rule
			. "\nPattern catalog:\n"
			. ( $lines ? implode( "\n", $lines ) : '(empty — use insert_blocks)' );
	}

	private function payload_disables_tools( $payload ) {
		$mode = isset( $payload['mode'] ) ? (string) $payload['mode'] : 'chat';
		return in_array( $mode, array( 'collect_context', 'summarize_context', 'fill_missing_fields' ), true );
	}

	private function byok_openai( $payload ) {
		$api_key = get_option( self::OPTION_OPENAI_KEY, '' );
		if ( ! $api_key ) {
			return new WP_Error( 'no_key', 'OpenAI API key is not set.', array( 'status' => 400 ) );
		}
		$messages_in = isset( $payload['messages'] ) && is_array( $payload['messages'] ) ? $payload['messages'] : array();
		$openai_messages = array(
			array(
				'role'    => 'system',
				'content' => $this->build_system_prompt( $payload ),
			),
		);
		foreach ( $messages_in as $m ) {
			if ( ! is_array( $m ) || empty( $m['role'] ) ) {
				continue;
			}
			if ( 'notice' === $m['role'] ) {
				continue;
			}
			$openai_messages[] = $m;
		}

		$body = array(
			'model'       => 'gpt-4o-mini',
			'messages'    => $openai_messages,
			'max_tokens'  => 4096,
			'temperature' => 0.4,
		);
		if ( ! $this->payload_disables_tools( $payload ) ) {
			$body['tools']       = $this->agent_tools_openai();
			$body['tool_choice'] = 'auto';
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 90,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'openai_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 || ! is_array( $data ) ) {
			$detail = is_array( $data ) ? wp_json_encode( $data ) : (string) wp_remote_retrieve_body( $response );
			return new WP_Error( 'openai_error', 'OpenAI request failed.', array( 'status' => $code ?: 502, 'details' => $detail ) );
		}

		$choice = isset( $data['choices'][0]['message'] ) ? $data['choices'][0]['message'] : array();
		return rest_ensure_response(
			array(
				'message'  => array(
					'role'       => 'assistant',
					'content'    => isset( $choice['content'] ) ? $choice['content'] : '',
					'tool_calls' => isset( $choice['tool_calls'] ) ? $choice['tool_calls'] : array(),
				),
				'model'    => isset( $data['model'] ) ? $data['model'] : 'gpt-4o-mini',
				'provider' => 'openai',
				'usage'    => isset( $data['usage'] ) ? $data['usage'] : null,
				'billing'  => array(
					'billedPatterns' => 0,
					'balance'        => null,
				),
			)
		);
	}

	private function byok_anthropic( $payload ) {
		$api_key = get_option( self::OPTION_ANTHROPIC_KEY, '' );
		if ( ! $api_key ) {
			return new WP_Error( 'no_key', 'Anthropic API key is not set.', array( 'status' => 400 ) );
		}

		$tools = array();
		foreach ( $this->agent_tools_openai() as $t ) {
			$tools[] = array(
				'name'         => $t['function']['name'],
				'description'  => $t['function']['description'],
				'input_schema' => $t['function']['parameters'],
			);
		}

		$converted = array();
		$messages_in = isset( $payload['messages'] ) && is_array( $payload['messages'] ) ? $payload['messages'] : array();
		foreach ( $messages_in as $m ) {
			if ( ! is_array( $m ) || empty( $m['role'] ) ) {
				continue;
			}
			if ( 'notice' === $m['role'] ) {
				continue;
			}
			if ( 'tool' === $m['role'] ) {
				$block = array(
					'type'        => 'tool_result',
					'tool_use_id' => isset( $m['tool_call_id'] ) ? $m['tool_call_id'] : '',
					'content'     => isset( $m['content'] ) ? $m['content'] : '',
				);
				$idx = count( $converted ) - 1;
				if ( $idx >= 0 && 'user' === $converted[ $idx ]['role'] && is_array( $converted[ $idx ]['content'] ) ) {
					$converted[ $idx ]['content'][] = $block;
				} else {
					$converted[] = array(
						'role'    => 'user',
						'content' => array( $block ),
					);
				}
				continue;
			}
			if ( 'assistant' === $m['role'] ) {
				$blocks = array();
				if ( ! empty( $m['content'] ) ) {
					$blocks[] = array(
						'type' => 'text',
						'text' => $m['content'],
					);
				}
				if ( ! empty( $m['tool_calls'] ) && is_array( $m['tool_calls'] ) ) {
					foreach ( $m['tool_calls'] as $tc ) {
						$input = array();
						if ( ! empty( $tc['function']['arguments'] ) ) {
							$decoded = json_decode( $tc['function']['arguments'], true );
							if ( is_array( $decoded ) ) {
								$input = $decoded;
							}
						}
						$blocks[] = array(
							'type'  => 'tool_use',
							'id'    => isset( $tc['id'] ) ? $tc['id'] : '',
							'name'  => isset( $tc['function']['name'] ) ? $tc['function']['name'] : '',
							'input' => $input,
						);
					}
				}
				$converted[] = array(
					'role'    => 'assistant',
					'content' => $blocks ? $blocks : ( isset( $m['content'] ) ? $m['content'] : '' ),
				);
				continue;
			}
			if ( 'user' === $m['role'] ) {
				$converted[] = array(
					'role'    => 'user',
					'content' => isset( $m['content'] ) ? $m['content'] : '',
				);
			}
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode(
					array_filter(
						array(
							'model'      => 'claude-sonnet-4-20250514',
							'max_tokens' => 4096,
							'system'     => $this->build_system_prompt( $payload ),
							'tools'      => $this->payload_disables_tools( $payload ) ? null : $tools,
							'messages'   => $converted,
						)
					)
				),
				'timeout' => 90,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'anthropic_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 || ! is_array( $data ) ) {
			return new WP_Error( 'anthropic_error', 'Anthropic request failed.', array( 'status' => $code ?: 502 ) );
		}

		$text       = '';
		$tool_calls = array();
		if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}
				if ( 'text' === $block['type'] && ! empty( $block['text'] ) ) {
					$text .= $block['text'];
				}
				if ( 'tool_use' === $block['type'] ) {
					$tool_calls[] = array(
						'id'       => isset( $block['id'] ) ? $block['id'] : '',
						'type'     => 'function',
						'function' => array(
							'name'      => isset( $block['name'] ) ? $block['name'] : '',
							'arguments' => wp_json_encode( isset( $block['input'] ) ? $block['input'] : new stdClass() ),
						),
					);
				}
			}
		}

		return rest_ensure_response(
			array(
				'message'  => array(
					'role'       => 'assistant',
					'content'    => $text,
					'tool_calls' => $tool_calls,
				),
				'model'    => isset( $data['model'] ) ? $data['model'] : 'claude-sonnet-4-20250514',
				'provider' => 'anthropic',
				'usage'    => isset( $data['usage'] ) ? $data['usage'] : null,
				'billing'  => array(
					'billedPatterns' => 0,
					'balance'        => null,
				),
			)
		);
	}

	/**
	 * Headers for SaaS agent calls. OpenAI/Anthropic keys never leave the SaaS.
	 *
	 * @param bool $ensure_trial Register a site trial when no license is present.
	 * @return array<string,string>
	 */
	private function saas_headers( $ensure_trial = false ) {
		$headers = array(
			'Content-Type'        => 'application/json',
			'X-GutenBlock-Domain' => self::normalized_site_domain(),
			'X-GutenBlock-Site-Id' => GutenBlock_Pro_Agent_Credits::site_id(),
		);
		$key = get_option( GutenBlock_Pro_License::OPTION_LICENSE_KEY, '' );
		if ( $key ) {
			$headers['X-GutenBlock-License'] = $key;
			return $headers;
		}
		if ( $ensure_trial ) {
			$this->ensure_site_trial();
		}
		$token = GutenBlock_Pro_Agent_Credits::trial_token();
		if ( $token ) {
			$headers['X-GutenBlock-Trial-Token'] = $token;
		}
		return $headers;
	}

	/**
	 * Ask SaaS for a hashed trial wallet. Token is stored in WP options, never an LLM key.
	 *
	 * @return string
	 */
	private function ensure_site_trial() {
		$existing = GutenBlock_Pro_Agent_Credits::trial_token();
		if ( $existing ) {
			return $existing;
		}

		$response = wp_remote_post(
			self::saas_base_url() . '/api/v1/agent/trial',
			array(
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-GutenBlock-Domain'  => self::normalized_site_domain(),
					'X-GutenBlock-Site-Id' => GutenBlock_Pro_Agent_Credits::site_id(),
				),
				'body'    => '{}',
				'timeout' => 12,
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['token'] ) ) {
			return '';
		}
		GutenBlock_Pro_Agent_Credits::set_trial_token( (string) $body['token'] );
		if ( isset( $body['balance'] ) ) {
			GutenBlock_Pro_Agent_Credits::sync_from_saas(
				(int) $body['balance'],
				isset( $body['used'] ) ? (int) $body['used'] : 0
			);
		}
		return (string) $body['token'];
	}

	/**
	 * @return array|null
	 */
	private function fetch_saas_usage() {
		$headers = $this->saas_headers( true );
		unset( $headers['Content-Type'] );
		$response = wp_remote_get(
			self::saas_base_url() . '/api/v1/agent/usage',
			array(
				'headers' => $headers,
				'timeout' => 8,
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}
}
