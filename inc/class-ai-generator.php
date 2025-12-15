<?php
/**
 * AI Text Generator for GutenBlock Pro
 * 
 * Handles text generation via OpenAI API with token tracking.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_AI_Generator {

	/**
	 * SaaS API URL für AI-Generierung (Key bleibt auf dem Server)
	 */
	const SAAS_AI_URL = 'https://app.gutenblock.com/api/v1/ai/generate';

	/**
	 * Default model
	 */
	const DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * License instance
	 */
	private $license;

	/**
	 * Cached prompts from API
	 */
	private $prompts = null;

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
		$this->license = GutenBlock_Pro_License::get_instance();
		
		// Register REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_api_endpoints' ) );

		// Enqueue editor assets
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets for AI functionality
	 */
	public function enqueue_editor_assets() {
		$asset_file = GUTENBLOCK_PRO_PATH . 'build/index.asset.php';
		
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		// Enqueue the main editor script
		wp_enqueue_script(
			'gutenblock-pro-ai-editor',
			GUTENBLOCK_PRO_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue editor styles
		wp_enqueue_style(
			'gutenblock-pro-ai-editor',
			GUTENBLOCK_PRO_URL . 'build/index.css',
			array(),
			$asset['version']
		);

		// Localize script with config
		wp_localize_script( 'gutenblock-pro-ai-editor', 'gutenblockProConfig', array(
			'restUrl'  => rest_url( 'gutenblock-pro/v1/' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'isPro'    => $this->license->is_pro(),
			'hasKey'   => $this->has_api_key(),
		) );
	}

	/**
	 * Get AI Generate API URL (with localhost detection for development)
	 *
	 * @return string
	 */
	private function get_ai_generate_url() {
		// Check for filter override
		$filtered = apply_filters( 'gutenblock_pro_saas_url', '' );
		if ( ! empty( $filtered ) ) {
			return rtrim( $filtered, '/' ) . '/api/v1/ai/generate';
		}

		// Localhost detection for development
		$site_url = get_site_url();
		if ( strpos( $site_url, 'localhost' ) !== false || strpos( $site_url, '.local' ) !== false ) {
			return 'http://localhost:3000/api/v1/ai/generate';
		}

		return self::SAAS_AI_URL;
	}

	/**
	 * AI is always available (key is on SaaS server)
	 *
	 * @return bool
	 */
	public function has_api_key() {
		return true; // Key is on SaaS server, always available
	}

	/**
	 * Register REST API endpoints
	 */
	public function register_api_endpoints() {
		// Generate text
		register_rest_route( 'gutenblock-pro/v1', '/ai/generate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'api_generate_text' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );

		// Get token usage
		register_rest_route( 'gutenblock-pro/v1', '/ai/usage', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'api_get_usage' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );

		// Get prompts
		register_rest_route( 'gutenblock-pro/v1', '/prompts', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'api_get_prompts' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );

		// Get system prompt
		register_rest_route( 'gutenblock-pro/v1', '/system-prompt', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'api_get_system_prompt' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	/**
	 * API: Generate text
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_generate_text( $request ) {
		$params = $request->get_json_params();
		$prompt = isset( $params['prompt'] ) ? sanitize_textarea_field( $params['prompt'] ) : '';
		$block_name = isset( $params['blockName'] ) ? sanitize_text_field( $params['blockName'] ) : '';
		$current_text = isset( $params['currentText'] ) ? sanitize_textarea_field( $params['currentText'] ) : '';
		$feedback = isset( $params['feedback'] ) ? sanitize_textarea_field( $params['feedback'] ) : '';

		if ( empty( $prompt ) && empty( $block_name ) ) {
			return new WP_Error( 'missing_prompt', __( 'Prompt ist erforderlich', 'gutenblock-pro' ), array( 'status' => 400 ) );
		}

		// Check token limit for free users
		if ( ! $this->license->can_generate() ) {
			return new WP_Error( 
				'token_limit_reached', 
				__( 'Monatliches Token-Limit erreicht. Upgrade auf Pro für unbegrenzte Generierung.', 'gutenblock-pro' ), 
				array( 'status' => 403 ) 
			);
		}

		// Check API key
		if ( ! $this->has_api_key() ) {
			return new WP_Error( 'no_api_key', __( 'API-Key nicht konfiguriert', 'gutenblock-pro' ), array( 'status' => 500 ) );
		}

		// Build the prompt
		$full_prompt = $prompt;

		// If we have a block name, try to get the specific prompt
		if ( ! empty( $block_name ) && empty( $prompt ) ) {
			$prompts = $this->get_prompts();
			if ( isset( $prompts[ $block_name ] ) ) {
				$full_prompt = $prompts[ $block_name ]['prompt'];
			} else {
				$full_prompt = sprintf( __( 'Schreibe einen passenden Text für das Element „%s".', 'gutenblock-pro' ), $block_name );
			}
		}

		// Add context if there's current text and feedback
		if ( ! empty( $current_text ) && ! empty( $feedback ) ) {
			$full_prompt .= "\n\nAktueller Text:\n" . $current_text . "\n\nFeedback: " . $feedback;
		}

		// Get system prompt
		$system_prompt = $this->get_system_prompt();

		// Call OpenAI
		$result = $this->call_openai( $full_prompt, $system_prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Track token usage
		if ( isset( $result['usage']['total_tokens'] ) ) {
			$this->license->add_token_usage( $result['usage']['total_tokens'] );
		}

		return rest_ensure_response( array(
			'success' => true,
			'text'    => $result['text'],
			'usage'   => $this->license->get_token_usage(),
		) );
	}

	/**
	 * API: Get token usage
	 *
	 * @return WP_REST_Response
	 */
	public function api_get_usage() {
		return rest_ensure_response( $this->license->get_token_usage() );
	}

	/**
	 * API: Get prompts
	 *
	 * @return WP_REST_Response
	 */
	public function api_get_prompts() {
		return rest_ensure_response( $this->get_prompts() );
	}

	/**
	 * API: Get system prompt
	 *
	 * @return WP_REST_Response
	 */
	public function api_get_system_prompt() {
		$system_prompt = $this->get_system_prompt();
		$last_modified = get_option( 'gutenblock_pro_system_prompt_modified', 0 );

		return rest_ensure_response( array(
			'systemPrompt'  => $system_prompt,
			'lastModified'  => $last_modified,
		) );
	}

	/**
	 * Call SaaS AI API (routes to OpenAI, key stays on server)
	 *
	 * @param string $prompt User prompt
	 * @param string $system_prompt System prompt
	 * @return array|WP_Error
	 */
	private function call_openai( $prompt, $system_prompt = '' ) {
		$messages = array();

		if ( ! empty( $system_prompt ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		$api_url = $this->get_ai_generate_url();

		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => self::DEFAULT_MODEL,
				'messages'   => $messages,
				'max_tokens' => 1000,
			) ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ai_error', $response->get_error_message(), array( 'status' => 500 ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code === 429 ) {
			return new WP_Error( 'rate_limit', __( 'Rate-Limit erreicht. Bitte warte einen Moment.', 'gutenblock-pro' ), array( 'status' => 429 ) );
		}

		if ( $status_code !== 200 || empty( $body['content'] ) ) {
			$error_message = isset( $body['error'] ) ? $body['error'] : __( 'AI-Generierung fehlgeschlagen', 'gutenblock-pro' );
			return new WP_Error( 'ai_error', $error_message, array( 'status' => 500 ) );
		}

		return array(
			'text'  => trim( $body['content'] ),
			'usage' => isset( $body['usage'] ) ? $body['usage'] : array(),
		);
	}

	/**
	 * Get prompts (from SaaS or local cache)
	 *
	 * @return array
	 */
	public function get_prompts() {
		if ( null !== $this->prompts ) {
			return $this->prompts;
		}

		// Try to get from cache
		$cached = get_transient( 'gutenblock_pro_prompts' );
		if ( false !== $cached ) {
			$this->prompts = $cached;
			return $this->prompts;
		}

		// Fetch from SaaS API
		$prompts = $this->fetch_prompts_from_api();

		if ( ! empty( $prompts ) ) {
			// Cache for 24 hours
			set_transient( 'gutenblock_pro_prompts', $prompts, DAY_IN_SECONDS );
			$this->prompts = $prompts;
		} else {
			// Use local fallback
			$this->prompts = $this->get_default_prompts();
		}

		return $this->prompts;
	}

	/**
	 * Get the SaaS API base URL
	 *
	 * @return string
	 */
	private function get_saas_api_url() {
		// Check for constant first (useful for wp-config.php)
		if ( defined( 'GUTENBLOCK_PRO_SAAS_URL' ) ) {
			return rtrim( GUTENBLOCK_PRO_SAAS_URL, '/' );
		}

		// Allow override via filter
		$url = apply_filters( 'gutenblock_pro_saas_api_url', '' );
		if ( ! empty( $url ) ) {
			return rtrim( $url, '/' );
		}

		// Auto-detect local development (LocalWP uses .local domains)
		$home_url = home_url();
		if ( strpos( $home_url, '.local' ) !== false || strpos( $home_url, 'localhost' ) !== false ) {
			// Local development - use localhost:3000
			return 'http://localhost:3000';
		}

		// Production
		return 'https://app.gutenblock.com';
	}

	/**
	 * Fetch prompts from SaaS Content API
	 *
	 * @return array
	 */
	private function fetch_prompts_from_api() {
		$api_url = $this->get_saas_api_url() . '/api/v1/plugin/prompts';
		
		$response = wp_remote_get( $api_url, array(
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['success'] ) || ! $body['success'] ) {
			return array();
		}

		// Transform to fieldId => { prompt, name } format
		$prompts = array();
		if ( isset( $body['data']['fields'] ) ) {
			foreach ( $body['data']['fields'] as $field ) {
				$prompts[ $field['fieldId'] ] = array(
					'prompt' => $field['prompt'],
					'name'   => $field['fieldName'],
				);
			}
		}

		return $prompts;
	}

	/**
	 * Get default prompts (fallback)
	 *
	 * @return array
	 */
	private function get_default_prompts() {
		return array(
			'h1-home' => array(
				'prompt' => 'Schreibe eine inspirierende Headline für die Startseite.',
				'name'   => 'Homepage Headline',
			),
			'p-intro' => array(
				'prompt' => 'Schreibe einen einleitenden Absatz für die Startseite.',
				'name'   => 'Intro Text',
			),
		);
	}

	/**
	 * Get system prompt
	 *
	 * @return string
	 */
	public function get_system_prompt() {
		// User-defined system prompt
		$user_prompt = get_option( 'gutenblock_pro_system_prompt', '' );

		if ( ! empty( $user_prompt ) ) {
			return $user_prompt;
		}

		// Default system prompt
		return 'Du bist ein professioneller Copywriter für Webseiten. ' .
		       'Schreibe prägnante, überzeugende Texte in deutscher Sprache. ' .
		       'Verwende keine Emojis oder Icons. ' .
		       'Antworte nur mit dem gewünschten Text, ohne Erklärungen oder Anführungszeichen.';
	}

	/**
	 * Refresh prompts from API (force reload)
	 */
	public function refresh_prompts() {
		delete_transient( 'gutenblock_pro_prompts' );
		$this->prompts = null;
		return $this->get_prompts();
	}

	/**
	 * Translate text
	 *
	 * @param string $text Text to translate
	 * @param string $target_language Target language (default: en)
	 * @return array|WP_Error
	 */
	public function translate( $text, $target_language = 'en' ) {
		if ( empty( $text ) ) {
			return new WP_Error( 'empty_text', __( 'Kein Text zum Übersetzen', 'gutenblock-pro' ) );
		}

		// Check token limit
		if ( ! $this->license->can_generate() ) {
			return new WP_Error( 'token_limit_reached', __( 'Token-Limit erreicht', 'gutenblock-pro' ) );
		}

		$language_names = array(
			'en' => 'Englisch',
			'de' => 'Deutsch',
			'fr' => 'Französisch',
			'es' => 'Spanisch',
			'it' => 'Italienisch',
		);

		$lang_name = isset( $language_names[ $target_language ] ) ? $language_names[ $target_language ] : $target_language;

		$prompt = sprintf(
			"Übersetze den folgenden Text ins %s. Antworte nur mit der Übersetzung, ohne Erklärungen.\n\nText:\n%s",
			$lang_name,
			$text
		);

		$result = $this->call_openai( $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Track usage
		if ( isset( $result['usage']['total_tokens'] ) ) {
			$this->license->add_token_usage( $result['usage']['total_tokens'] );
		}

		return array(
			'success' => true,
			'text'    => $result['text'],
		);
	}
}
