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

		wp_set_script_translations( 'gutenblock-pro-ai-editor', 'gutenblock-pro', GUTENBLOCK_PRO_PATH . 'languages' );

		// Get license info
		$license = GutenBlock_Pro_License::get_instance();
		
		// Localize script with config
		wp_localize_script( 'gutenblock-pro-ai-editor', 'gutenblockProConfig', array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'restUrl'            => rest_url( 'gutenblock-pro/v1/' ),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'isPro'              => $license->is_pro(),
			'hasPremium'         => $license->has_premium_access(),
			'hasKey'             => $this->has_api_key(),
			'upgradeUrl'         => 'https://app.gutenblock.com/licenses',
			'aiSettingsUrl'      => admin_url( 'admin.php?page=gutenblock-pro-ai' ),
			'translateLanguages' => GutenBlock_Pro_Translation_Settings::get_enabled_languages(),
			'premiumPatterns'    => self::get_premium_pattern_slugs(),
			'hasTextFormats'     => GutenBlock_Pro_Features_Page::is_feature_enabled( 'text-formats' ),
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
	 * Get slugs of patterns marked as premium.
	 *
	 * @return array
	 */
	private static function get_premium_pattern_slugs() {
		$slugs   = array();
		$dir     = GUTENBLOCK_PRO_PATTERNS_PATH;
		if ( ! is_dir( $dir ) ) {
			return $slugs;
		}
		foreach ( glob( $dir . '*/pattern.php' ) as $file ) {
			$data = include $file;
			if ( is_array( $data ) && ! empty( $data['premium'] ) ) {
				$slugs[] = basename( dirname( $file ) );
			}
		}
		return $slugs;
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

		// Generate group content (multiple fields)
		register_rest_route( 'gutenblock-pro/v1', '/ai/generate-group', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'api_generate_group' ),
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

		// Suggest pattern meta (description + ai_hint, English)
		register_rest_route( 'gutenblock-pro/v1', '/ai/pattern-meta', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'api_suggest_pattern_meta' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	/**
	 * REST: Generate description + ai_hint (English) for a pattern from its block content.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_suggest_pattern_meta( $request ) {
		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$slug    = sanitize_title( (string) $request->get_param( 'slug' ) );
		$type    = (string) $request->get_param( 'type' );
		$type    = in_array( $type, array( 'pattern', 'page' ), true ) ? $type : 'pattern';
		$content = (string) $request->get_param( 'content' );

		$system = 'You analyse a WordPress block pattern by reading its block markup and produce two short English texts about its STRUCTURE ONLY.'
			. ' NEVER describe the actual copy, niche, target audience, tone, brand or use case.'
			. ' NEVER mention WordPress, plugins, page builders, themes or marketing claims.'
			. ' NEVER name specific colors.'
			. ' Always answer with strict JSON: {"description":"...","ai_hint":"..."} — no markdown, no preamble, no trailing text.'
			. "\n\n"
			. 'How to read the markup:'
			. ' - <!-- wp:columns --> with several <!-- wp:column --> children = MULTI-COLUMN layout;'
			. ' read each column\'s "width" attribute and report the ratio (e.g. 50/50, 60/40, 40/60).'
			. ' If "width" is missing or empty on all columns, treat as equal columns (e.g. 50/50 for two columns).'
			. ' - The column ORDER in the markup represents left-to-right placement.'
			. ' For each column, mention what it contains (e.g. "image left, heading + paragraph + CTA right").'
			. ' - <!-- wp:image --> placed inside the content is a CONTENT image, NOT a background.'
			. ' Background EXISTS only when:'
			. ' (a) the block is <!-- wp:cover --> (image / video / gradient overlay), OR'
			. ' (b) the top-level group has "backgroundColor", "gradient" or "style.background" / "style.backgroundImage" attributes.'
			. ' If none of these are present, write "no container background".'
			. ' - <!-- wp:heading --> level comes from the "level" attribute (default 2).'
			. ' - <!-- wp:buttons --> can contain multiple <!-- wp:button --> children. List EACH button separately and report its variant:'
			. ' "filled" (default), "outline" (className contains "is-style-outline"), or "custom" (any other className).'
			. ' - <!-- wp:gutenblock-pro/material-icon --> is an icon element. Mention "icon" if present.'
			. ' - Other media: gallery, list, quote, accordion, table — mention if present.'
			. "\n\n"
			. 'Field rules:'
			. ' "description": ≤140 chars, user-facing summary listing the visible structure (layout + main elements + button variants). Plain English, single sentence preferred.'
			. ' "ai_hint": ≤260 chars, structural analysis. Always include in this order: (1) layout (single column / two columns 50/50 / two columns 60/40 / grid Nxcols / hero with overlay / stacked image+content), (2) container background type or "no container background", (3) per-column or per-area contents, (4) heading level(s), (5) every CTA button with its variant, (6) any extra media or icon.';

		$shots = "Examples (input → JSON output):\n\n"
			. "INPUT 1: A wp:group containing wp:columns with two wp:column. Left column has H1. Right column (width 40%) has a paragraph and one wp:button (no className).\n"
			. "OUTPUT 1: {\"description\":\"Two-column hero (60/40) — H1 left, paragraph and filled CTA right.\",\"ai_hint\":\"Two columns 60/40 with vertical-bottom alignment; no container background; left column: H1 only; right column: paragraph and one filled CTA; no further media.\"}\n\n"
			. "INPUT 2: A wp:group with backgroundColor=base wrapping wp:columns with two equal wp:column. Left column has wp:image (square). Right column has icon block + paragraph (eyebrow), H1, paragraph, wp:buttons with two wp:button (second has is-style-outline).\n"
			. "OUTPUT 2: {\"description\":\"Two-column hero (50/50) — image left, eyebrow with icon, H1, paragraph and two CTAs (filled, outline) right.\",\"ai_hint\":\"Two columns 50/50 with vertical-center alignment; container background is a solid color; left column: square image; right column: icon, eyebrow paragraph, H1, paragraph, two CTAs (filled and outline); no further media.\"}\n\n"
			. "INPUT 3: A wp:group with wp:columns 50/50 inside, then below the columns a wp:image (full width).\n"
			. "OUTPUT 3: {\"description\":\"Stacked: two-column row (50/50) on top, full-width image below.\",\"ai_hint\":\"Stacked layout; no container background; row of two columns 50/50 (left H1, right paragraph and filled CTA), full-width content image below the columns.\"}";

		$user = "Pattern name: {$name}\n"
			. "Slug: {$slug}\n"
			. "Type: {$type}\n\n"
			. $shots
			. "\n\nNOW ANALYSE THIS PATTERN. Block markup (HTML + block comments — analyse the BLOCK STRUCTURE, ignore copy):\n"
			. ( strlen( $content ) > 8000 ? substr( $content, 0, 8000 ) : $content );

		$result = $this->call_openai( $user, $system );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$text = isset( $result['text'] ) ? trim( (string) $result['text'] ) : '';
		// Try to extract JSON object even if model added extra text
		if ( preg_match( '/\{[\s\S]*\}/', $text, $m ) ) {
			$text = $m[0];
		}
		$decoded = json_decode( $text, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'ai_parse_error', __( 'AI-Antwort konnte nicht geparst werden.', 'gutenblock-pro' ), array( 'status' => 502 ) );
		}

		$description = isset( $decoded['description'] ) ? sanitize_textarea_field( (string) $decoded['description'] ) : '';
		$ai_hint     = isset( $decoded['ai_hint'] ) ? sanitize_textarea_field( (string) $decoded['ai_hint'] ) : '';

		if ( $description === '' && $ai_hint === '' ) {
			return new WP_Error( 'ai_empty', __( 'AI-Antwort war leer.', 'gutenblock-pro' ), array( 'status' => 502 ) );
		}

		return rest_ensure_response( array(
			'description' => $description,
			'ai_hint'     => $ai_hint,
			'usage'       => isset( $result['usage'] ) ? $result['usage'] : array(),
		) );
	}

	/**
	 * API: Generate text
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_generate_text( $request ) {
		try {
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
		} catch ( Exception $e ) {
			error_log( '[GutenBlock Pro AI] Error in api_generate_text: ' . $e->getMessage() );
			return new WP_Error( 
				'ai_error', 
				__( 'Fehler bei der AI-Generierung: ', 'gutenblock-pro' ) . $e->getMessage(), 
				array( 'status' => 500 ) 
			);
		}
	}

	/**
	 * API: Generate content for multiple fields in a group
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function api_generate_group( $request ) {
		try {
			$params = $request->get_json_params();
			$group_prompt = isset( $params['groupPrompt'] ) ? sanitize_textarea_field( $params['groupPrompt'] ) : '';
			$fields = isset( $params['fields'] ) ? $params['fields'] : array();
			$feedback = isset( $params['feedback'] ) ? sanitize_textarea_field( $params['feedback'] ) : '';

			if ( empty( $fields ) || ! is_array( $fields ) ) {
				return new WP_Error( 'missing_fields', __( 'Keine Content-Felder angegeben', 'gutenblock-pro' ), array( 'status' => 400 ) );
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

			// Get system prompt
			$system_prompt = $this->get_system_prompt();

			// Build a comprehensive prompt for all fields
			$fields_description = '';
			foreach ( $fields as $index => $field ) {
				$field_name = isset( $field['fieldName'] ) ? sanitize_text_field( $field['fieldName'] ) : '';
				$field_prompt = isset( $field['prompt'] ) ? sanitize_textarea_field( $field['prompt'] ) : '';
				$current_text = isset( $field['currentText'] ) ? sanitize_textarea_field( $field['currentText'] ) : '';
				
				$fields_description .= "\n\nFeld " . ( $index + 1 ) . ": " . $field_name;
				if ( ! empty( $field_prompt ) ) {
					$fields_description .= "\nPrompt: " . $field_prompt;
				}
				if ( ! empty( $current_text ) ) {
					$fields_description .= "\nAktueller Text: " . $current_text;
				}
			}

			// Build the full prompt
			$full_prompt = $group_prompt;
			if ( ! empty( $fields_description ) ) {
				$full_prompt .= "\n\nFolgende Content-Felder müssen generiert werden:" . $fields_description;
			}
			
			if ( ! empty( $feedback ) ) {
				$full_prompt .= "\n\nFeedback: " . $feedback;
			}

			// Add instruction to return JSON
			$full_prompt .= "\n\nAntworte NUR mit einem JSON-Objekt im folgenden Format (ohne zusätzlichen Text):\n{\n  \"fields\": [\n    { \"fieldName\": \"feld-name-1\", \"text\": \"generierter Text\" },\n    { \"fieldName\": \"feld-name-2\", \"text\": \"generierter Text\" }\n  ]\n}";

			// Call OpenAI
			$result = $this->call_openai( $full_prompt, $system_prompt );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Track token usage
			if ( isset( $result['usage']['total_tokens'] ) ) {
				$this->license->add_token_usage( $result['usage']['total_tokens'] );
			}

			// Parse JSON response
			$response_text = $result['text'];
			
			// Try to extract JSON from response (might have markdown code blocks)
			if ( preg_match( '/```(?:json)?\s*(\{.*?\})\s*```/s', $response_text, $matches ) ) {
				$response_text = $matches[1];
			} elseif ( preg_match( '/\{.*\}/s', $response_text, $matches ) ) {
				$response_text = $matches[0];
			}

			$json_data = json_decode( $response_text, true );

			if ( ! $json_data || ! isset( $json_data['fields'] ) || ! is_array( $json_data['fields'] ) ) {
				return new WP_Error( 
					'invalid_response', 
					__( 'Ungültige JSON-Response von der API. Response: ' . substr( $response_text, 0, 200 ), 'gutenblock-pro' ), 
					array( 'status' => 500 ) 
				);
			}

			// Map field names to clientIds
			$field_map = array();
			foreach ( $fields as $field ) {
				$field_map[ $field['fieldName'] ] = $field['clientId'];
			}

			// Build response with clientIds
			$response_fields = array();
			foreach ( $json_data['fields'] as $field_data ) {
				$field_name = isset( $field_data['fieldName'] ) ? $field_data['fieldName'] : '';
				$field_text = isset( $field_data['text'] ) ? $field_data['text'] : '';
				
				if ( isset( $field_map[ $field_name ] ) ) {
					$response_fields[] = array(
						'clientId' => $field_map[ $field_name ],
						'fieldName' => $field_name,
						'text' => $field_text,
					);
				}
			}

			return rest_ensure_response( array(
				'success' => true,
				'fields'  => $response_fields,
				'usage'   => $this->license->get_token_usage(),
			) );
		} catch ( Exception $e ) {
			error_log( '[GutenBlock Pro AI] Error in api_generate_group: ' . $e->getMessage() );
			return new WP_Error( 
				'ai_error', 
				__( 'Fehler bei der AI-Generierung: ', 'gutenblock-pro' ) . $e->getMessage(), 
				array( 'status' => 500 ) 
			);
		}
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
	 * Call SaaS AI API with messages array (for session support)
	 *
	 * @param array $messages Messages array with role and content
	 * @return array|WP_Error
	 */
	private function call_openai_with_messages( $messages ) {

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
	 * Call SaaS AI API (routes to OpenAI, key stays on server)
	 * Legacy method for backwards compatibility
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

		return $this->call_openai_with_messages( $messages );
	}

	/**
	 * Get prompts (from SaaS or local cache)
	 *
	 * @return array
	 */
	public function get_prompts() {
		if ( null !== $this->prompts ) {
			return $this->apply_custom_prompts( $this->prompts );
		}

		// Try to get from cache
		$cached = get_transient( 'gutenblock_pro_prompts' );
		if ( false !== $cached ) {
			$this->prompts = $cached;
			return $this->apply_custom_prompts( $this->prompts );
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

		return $this->apply_custom_prompts( $this->prompts );
	}

	/**
	 * Apply custom prompts to API prompts
	 * Custom prompts override API prompts if they exist
	 *
	 * @param array $prompts API prompts
	 * @return array Prompts with custom overrides applied
	 */
	private function apply_custom_prompts( $prompts ) {
		$custom_prompts = get_option( 'gutenblock_pro_custom_prompts', array() );
		
		if ( empty( $custom_prompts ) ) {
			return $prompts;
		}

		// Apply custom prompts (only if they exist and are not empty)
		foreach ( $custom_prompts as $field_id => $custom_prompt ) {
			if ( isset( $prompts[ $field_id ] ) && ! empty( trim( $custom_prompt ) ) ) {
				$prompts[ $field_id ]['prompt'] = $custom_prompt;
			}
		}

		return $prompts;
	}

	/**
	 * Clear prompts cache
	 */
	public function clear_prompts_cache() {
		$this->prompts = null;
		delete_transient( 'gutenblock_pro_prompts' );
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
		// Get style prompt (technical/stylistic guidelines)
		$style_prompt = get_option( 'gutenblock_pro_system_prompt', '' );
		
		// Get context prompt (user's personal information)
		$context_prompt = get_option( 'gutenblock_pro_ai_context', '' );
		
		// Combine both prompts
		$combined_prompt = '';
		
		if ( ! empty( $style_prompt ) ) {
			$combined_prompt = $style_prompt;
		}
		
		if ( ! empty( $context_prompt ) ) {
			if ( ! empty( $combined_prompt ) ) {
				$combined_prompt .= "\n\n";
			}
			$combined_prompt .= $context_prompt;
		}
		
		// If both are empty, use default
		if ( empty( $combined_prompt ) ) {
			$combined_prompt = 'Du bist ein professioneller Copywriter für Webseiten. ' .
			       'Schreibe prägnante, überzeugende Texte in deutscher Sprache. ' .
			       'Verwende keine Emojis oder Icons. ' .
			       'Antworte nur mit dem gewünschten Text, ohne Erklärungen oder Anführungszeichen.';
		}
		
		return $combined_prompt;
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
