<?php
/**
 * Site-level agent credits (trial + purchased top-ups stored in WP options).
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Agent_Credits {

	const TRIAL_CREDITS        = 250;
	const CREDITS_PER_PATTERN  = 10;
	const CREDITS_PER_PARAGRAPH = 1;
	const OPTION_BALANCE       = 'gutenblock_pro_agent_credits_balance';
	const OPTION_USED          = 'gutenblock_pro_agent_credits_used';
	const OPTION_TRIAL_GRANTED = 'gutenblock_pro_agent_trial_granted';
	const OPTION_SITE_ID       = 'gutenblock_pro_agent_site_id';
	const OPTION_TRIAL_TOKEN   = 'gutenblock_pro_agent_trial_token';

	/**
	 * Grant the one-time trial wallet on first agent use.
	 */
	public static function ensure_trial() {
		if ( get_option( self::OPTION_TRIAL_GRANTED ) ) {
			return;
		}
		update_option( self::OPTION_BALANCE, self::TRIAL_CREDITS, false );
		update_option( self::OPTION_USED, 0, false );
		update_option( self::OPTION_TRIAL_GRANTED, 1, false );
	}

	/**
	 * @return int
	 */
	public static function get_balance() {
		self::ensure_trial();
		return max( 0, (int) get_option( self::OPTION_BALANCE, 0 ) );
	}

	/**
	 * @return int
	 */
	public static function get_used() {
		return max( 0, (int) get_option( self::OPTION_USED, 0 ) );
	}

	/**
	 * @return bool
	 */
	public static function trial_granted() {
		return (bool) get_option( self::OPTION_TRIAL_GRANTED, false );
	}

	/**
	 * Summary for REST / UI.
	 *
	 * @return array{balance:int,used:int,trialGranted:bool,costPerPattern:int,trialTotal:int}
	 */
	public static function get_summary() {
		return array(
			'balance'        => self::get_balance(),
			'used'           => self::get_used(),
			'trialGranted'   => self::trial_granted(),
			'costPerPattern' => self::CREDITS_PER_PATTERN,
			'trialTotal'     => self::TRIAL_CREDITS,
			'hasTrialToken'  => (bool) get_option( self::OPTION_TRIAL_TOKEN, '' ),
		);
	}

	/**
	 * Stable per-site id (not a secret). Used to bind the SaaS trial wallet.
	 *
	 * @return string
	 */
	public static function site_id() {
		$id = (string) get_option( self::OPTION_SITE_ID, '' );
		if ( '' === $id ) {
			$id = wp_generate_uuid4();
			update_option( self::OPTION_SITE_ID, $id, false );
		}
		return $id;
	}

	/**
	 * @return string
	 */
	public static function trial_token() {
		return (string) get_option( self::OPTION_TRIAL_TOKEN, '' );
	}

	/**
	 * @param string $token
	 */
	public static function set_trial_token( $token ) {
		update_option( self::OPTION_TRIAL_TOKEN, (string) $token, false );
	}

	/**
	 * Drop the SaaS trial token so the next request re-registers the site wallet.
	 */
	public static function clear_trial_token() {
		delete_option( self::OPTION_TRIAL_TOKEN );
	}

	/**
	 * Keep the editor meter in sync with the SaaS trial wallet.
	 *
	 * @param int $balance
	 * @param int $used
	 */
	public static function sync_from_saas( $balance, $used ) {
		self::ensure_trial();
		update_option( self::OPTION_BALANCE, max( 0, (int) $balance ), false );
		update_option( self::OPTION_USED, max( 0, (int) $used ), false );
	}

	/**
	 * Credit packs shown on the license settings page (live from SaaS).
	 *
	 * @return array{packs:array<int,array<string,mixed>>,costPerPattern:int,trialCredits:int}
	 */
	public static function fetch_credit_catalog() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$cached = get_transient( 'gutenblock_pro_credit_packs_v3' );
		$empty  = array(
			'packs'          => array(),
			'costPerPattern' => self::CREDITS_PER_PATTERN,
			'trialCredits'   => self::TRIAL_CREDITS,
		);

		$base = class_exists( 'GutenBlock_Pro_Agent_Proxy' )
			? GutenBlock_Pro_Agent_Proxy::saas_base_url()
			: 'https://app.gutenblock.com';
		$url  = rtrim( $base, '/' ) . '/api/v1/plugin/credit-packs?locale=' . rawurlencode( (string) $locale );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return ( is_array( $cached ) && ! empty( $cached['packs'] ) ) ? $cached : $empty;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) || empty( $body['data']['packs'] ) || ! is_array( $body['data']['packs'] ) ) {
			return ( is_array( $cached ) && ! empty( $cached['packs'] ) ) ? $cached : $empty;
		}

		$packs = array();
		foreach ( $body['data']['packs'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$features = array();
			if ( ! empty( $raw['features'] ) && is_array( $raw['features'] ) ) {
				foreach ( $raw['features'] as $f ) {
					if ( is_string( $f ) && '' !== trim( $f ) ) {
						$features[] = $f;
					}
				}
			}
			$packs[] = array(
				'slug'               => isset( $raw['slug'] ) ? sanitize_title( (string) $raw['slug'] ) : '',
				'name'               => isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '',
				'description'        => isset( $raw['description'] ) ? sanitize_text_field( (string) $raw['description'] ) : '',
				'credits'            => isset( $raw['credits'] ) ? (int) $raw['credits'] : 0,
				'patterns'           => isset( $raw['patterns'] ) ? (int) $raw['patterns'] : 0,
				'pagesApprox'        => isset( $raw['pagesApprox'] ) ? (int) $raw['pagesApprox'] : 0,
				'priceLabel'         => isset( $raw['priceLabel'] ) ? sanitize_text_field( (string) $raw['priceLabel'] ) : '',
				'originalPriceLabel' => ! empty( $raw['originalPriceLabel'] ) ? sanitize_text_field( (string) $raw['originalPriceLabel'] ) : '',
				'features'           => $features,
				'buyable'            => ! empty( $raw['buyable'] ),
				'badge'              => isset( $raw['badge'] ) ? sanitize_text_field( (string) $raw['badge'] ) : '',
				'highlighted'        => ! empty( $raw['highlighted'] ),
			);
		}

		$catalog = array(
			'packs'          => $packs,
			'costPerPattern' => isset( $body['data']['costPerPattern'] ) ? (int) $body['data']['costPerPattern'] : self::CREDITS_PER_PATTERN,
			'trialCredits'   => isset( $body['data']['trialCredits'] ) ? (int) $body['data']['trialCredits'] : self::TRIAL_CREDITS,
		);
		set_transient( 'gutenblock_pro_credit_packs_v3', $catalog, 5 * MINUTE_IN_SECONDS );
		return $catalog;
	}

	/**
	 * One-click buy link for a pack: hits admin-post, which asks the SaaS for a
	 * Stripe Checkout URL bound to this site and redirects there.
	 *
	 * @param string $slug       Pack slug.
	 * @param string $return_url Where the "Back to WordPress" button on the thank-you page leads.
	 * @return string
	 */
	public static function buy_url( $slug, $return_url = '' ) {
		$args = array(
			'action' => 'gutenblock_buy_credits',
			'pack'   => sanitize_title( (string) $slug ),
		);
		if ( '' !== $return_url ) {
			$args['return'] = $return_url;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'gutenblock_buy_credits' );
	}

	/**
	 * The pack to push in the editor when credits run out (highlighted, else first buyable).
	 *
	 * @return string Pack slug or ''.
	 */
	public static function recommended_pack_slug() {
		$fallback = '';
		foreach ( self::get_credit_packs() as $pack ) {
			if ( empty( $pack['buyable'] ) || empty( $pack['slug'] ) ) {
				continue;
			}
			if ( ! empty( $pack['highlighted'] ) ) {
				return $pack['slug'];
			}
			if ( '' === $fallback ) {
				$fallback = $pack['slug'];
			}
		}
		return $fallback;
	}

	/**
	 * Credit packs shown on the license settings page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_credit_packs() {
		$catalog = self::fetch_credit_catalog();
		return $catalog['packs'];
	}

	/**
	 * @param int $amount
	 * @return bool
	 */
	public static function can_afford( $amount ) {
		return self::get_balance() >= max( 0, (int) $amount );
	}

	/**
	 * @param int    $amount
	 * @param string $reason
	 * @return int New balance.
	 */
	public static function credit( $amount, $reason = 'purchase' ) {
		$amount = max( 0, (int) $amount );
		if ( 0 === $amount ) {
			return self::get_balance();
		}
		self::ensure_trial();
		$balance = self::get_balance() + $amount;
		update_option( self::OPTION_BALANCE, $balance, false );
		if ( 'refund' === $reason ) {
			update_option( self::OPTION_USED, max( 0, self::get_used() - $amount ), false );
		}
		return $balance;
	}

	/**
	 * Credits for trailing tool results with ok=false (last agent round).
	 *
	 * @param array $messages Chat messages from the editor.
	 * @return int
	 */
	public static function failed_credits_from_messages( $messages ) {
		if ( ! is_array( $messages ) || empty( $messages ) ) {
			return 0;
		}
		$trailing = array();
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			$m = $messages[ $i ];
			if ( is_array( $m ) && isset( $m['role'] ) && 'tool' === $m['role'] ) {
				array_unshift( $trailing, $m );
				continue;
			}
			break;
		}
		if ( empty( $trailing ) ) {
			return 0;
		}

		$assistant = null;
		$end       = count( $messages ) - count( $trailing ) - 1;
		for ( $i = $end; $i >= 0; $i-- ) {
			$m = $messages[ $i ];
			if ( is_array( $m ) && isset( $m['role'] ) && 'assistant' === $m['role'] && ! empty( $m['tool_calls'] ) ) {
				$assistant = $m;
				break;
			}
		}
		if ( ! $assistant ) {
			return 0;
		}

		$failed_ids = array();
		foreach ( $trailing as $tool_msg ) {
			$body = json_decode( isset( $tool_msg['content'] ) ? (string) $tool_msg['content'] : '', true );
			if ( is_array( $body ) && isset( $body['ok'] ) && false === $body['ok'] ) {
				$id = isset( $tool_msg['tool_call_id'] ) ? (string) $tool_msg['tool_call_id'] : '';
				if ( '' !== $id ) {
					$failed_ids[ $id ] = true;
				}
			}
		}
		if ( empty( $failed_ids ) ) {
			return 0;
		}

		$failed_calls = array();
		foreach ( $assistant['tool_calls'] as $call ) {
			if ( ! is_array( $call ) ) {
				continue;
			}
			$cid = isset( $call['id'] ) ? (string) $call['id'] : '';
			if ( isset( $failed_ids[ $cid ] ) ) {
				$failed_calls[] = $call;
			}
		}
		return self::cost_from_tool_calls( $failed_calls );
	}

	/**
	 * @param int    $amount
	 * @param string $reason
	 * @return int New balance.
	 */
	public static function debit( $amount, $reason = 'pattern' ) {
		$amount = max( 0, (int) $amount );
		if ( 0 === $amount ) {
			return self::get_balance();
		}
		self::ensure_trial();
		$balance = max( 0, self::get_balance() - $amount );
		$used    = self::get_used() + $amount;
		update_option( self::OPTION_BALANCE, $balance, false );
		update_option( self::OPTION_USED, $used, false );
		return $balance;
	}

	/**
	 * Count credits from OpenAI-shaped tool_calls in an API response.
	 *
	 * @param array $tool_calls
	 * @return int
	 */
	public static function cost_from_tool_calls( $tool_calls ) {
		if ( ! is_array( $tool_calls ) ) {
			return 0;
		}
		$credits = 0;
		foreach ( $tool_calls as $call ) {
			if ( ! is_array( $call ) ) {
				continue;
			}
			$name = '';
			if ( isset( $call['function']['name'] ) ) {
				$name = (string) $call['function']['name'];
			} elseif ( isset( $call['name'] ) ) {
				$name = (string) $call['name'];
			}
			if ( 'insert_pattern' === $name ) {
				$credits += self::CREDITS_PER_PATTERN;
				continue;
			}

			if ( 'write_article' === $name ) {
				// Structured args: count billable paragraphs (>= 12 words) directly.
				$raw  = isset( $call['function']['arguments'] ) ? (string) $call['function']['arguments'] : '';
				$args = json_decode( $raw, true );
				$sections = is_array( $args ) && isset( $args['sections'] ) && is_array( $args['sections'] ) ? $args['sections'] : array();
				foreach ( $sections as $section ) {
					if ( ! is_array( $section ) || empty( $section['paragraphs'] ) || ! is_array( $section['paragraphs'] ) ) {
						continue;
					}
					foreach ( $section['paragraphs'] as $p ) {
						if ( ! is_string( $p ) ) {
							continue;
						}
						$words = preg_split( '/\s+/', trim( $p ), -1, PREG_SPLIT_NO_EMPTY );
						if ( is_array( $words ) && count( $words ) >= 12 ) {
							$credits += self::CREDITS_PER_PARAGRAPH;
						}
					}
				}
				continue;
			}

			if ( 'insert_image' === $name ) {
				$credits += self::CREDITS_PER_PARAGRAPH;
				continue;
			}

			if ( 'insert_blocks' === $name ) {
				$raw = isset( $call['function']['arguments'] ) ? (string) $call['function']['arguments'] : '';
				$args = json_decode( (string) $raw, true );
				$markup = is_array( $args ) && isset( $args['markup'] ) ? (string) $args['markup'] : '';
				$billableParagraphs = 0;
				if ( '' !== $markup ) {
					$re = '/<!--\s*wp:(?:core\/)?paragraph\b[^>]*-->(.*?)<!--\s*\/wp:(?:core\/)?paragraph\s*-->/s';
					if ( preg_match_all( $re, $markup, $matches ) ) {
						$inners = isset( $matches[1] ) && is_array( $matches[1] ) ? $matches[1] : array();
						foreach ( $inners as $inner ) {
							$text = trim( strip_tags( (string) $inner ) );
							$text = str_replace( '&nbsp;', ' ', $text );
							$text = preg_replace( '/&[a-zA-Z]+;/', ' ', (string) $text );
							$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
							$wc = is_array( $words ) ? count( $words ) : 0;
							if ( $wc >= 12 ) {
								$billableParagraphs += 1;
							}
						}
					}
				}

				if ( $billableParagraphs > 0 ) {
					$credits += (int) $billableParagraphs * self::CREDITS_PER_PARAGRAPH;
				} else {
					// Image-only inserts (no billable paragraph credits): charge per core/image block.
					$imageCount = 0;
					if ( preg_match_all( '/<!--\s*wp:(?:core\/)?image\b/', $markup, $im ) ) {
						$imageCount = isset( $im[0] ) && is_array( $im[0] ) ? count( $im[0] ) : 0;
					}
					$credits += (int) $imageCount * self::CREDITS_PER_PARAGRAPH;
				}
				continue;
			}
			if ( 'build_page' === $name ) {
				$raw = isset( $call['function']['arguments'] ) ? $call['function']['arguments'] : '';
				$args = json_decode( (string) $raw, true );
				if ( is_array( $args ) && isset( $args['sections'] ) && is_array( $args['sections'] ) ) {
					$credits += count( $args['sections'] ) * self::CREDITS_PER_PATTERN;
				} else {
					$credits += self::CREDITS_PER_PATTERN;
				}
			}
		}
		return $credits;
	}
}
