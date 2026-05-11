<?php
/**
 * Pattern-Registry + Page-Assembly für GutenBlock KI-Builder (Canvas-WordPress).
 *
 * @package GutenBlock_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permission für assemble-page (API-Key wie Export, oder Admin).
 *
 * @return bool|WP_Error
 */
function gutenblock_bridge_assemble_permission_callback() {
	if ( function_exists( 'gutenblock_bridge_export_permission_check' ) ) {
		return gutenblock_bridge_export_permission_check();
	}
	$stored   = get_option( 'gutenblock_bridge_export_api_key' );
	$provided = isset( $_SERVER['HTTP_X_API_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) ) : '';
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	if ( $stored && $provided && hash_equals( (string) $stored, (string) $provided ) ) {
		return true;
	}
	return new WP_Error(
		'rest_forbidden',
		'Ungültiger API-Key',
		array( 'status' => 403 )
	);
}

/**
 * REST: Liste aller Section-Patterns mit KI-Metadaten (ohne Block-Markup).
 */
function gutenblock_bridge_rest_get_patterns() {
	if ( ! class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
		return new WP_REST_Response(
			array( 'error' => 'GutenBlock Pro Plugin ist nicht aktiv oder nicht geladen.' ),
			503
		);
	}

	$loader = new GutenBlock_Pro_Pattern_Loader();
	$loader->discover_patterns();
	$all    = $loader->get_patterns();
	$out    = array();

	foreach ( $all as $slug => $p ) {
		if ( isset( $p['type'] ) && $p['type'] === 'page' ) {
			continue;
		}
		$title = $p['title'];
		if ( is_array( $title ) ) {
			$title = isset( $title['rendered'] ) ? $title['rendered'] : '';
		}
		$desc = isset( $p['description'] ) ? $p['description'] : '';
		if ( is_array( $desc ) ) {
			$desc = isset( $desc['rendered'] ) ? $desc['rendered'] : '';
		}
		// Default: nur neutral. Opt-in-Varianten stehen explizit in pattern.php.
		$tones = isset( $p['tones'] ) && is_array( $p['tones'] ) && count( $p['tones'] ) > 0
			? $p['tones']
			: array( 'neutral' );

		$extracted_content_fields = gutenblock_bridge_extract_pattern_content_fields( (string) $slug );
		$content_fields           = ! empty( $extracted_content_fields )
			? $extracted_content_fields
			: ( isset( $p['content_fields'] ) && is_array( $p['content_fields'] ) ? $p['content_fields'] : array() );

		$out[] = array(
			'slug'           => $slug,
			'title'          => wp_strip_all_tags( (string) $title ),
			'group'          => isset( $p['group'] ) ? (string) $p['group'] : '',
			'description'    => wp_strip_all_tags( (string) $desc ),
			'ai_hint'        => isset( $p['ai_hint'] ) ? (string) $p['ai_hint'] : '',
			'content_fields' => $content_fields,
			'has_style'      => ! empty( $p['has_style'] ),
			'tones'          => $tones,
		);
	}

	return new WP_REST_Response( $out, 200 );
}

/**
 * Text-Slots direkt aus `content.html` lesen. `metadata.name` ist die
 * führende Quelle, damit veraltete `pattern.php`-Listen keine echten Slots
 * verdecken.
 *
 * @param string $slug Pattern-Slug.
 * @return string[]
 */
function gutenblock_bridge_extract_pattern_content_fields( string $slug ): array {
	if ( '' === $slug || ! defined( 'GUTENBLOCK_PRO_PATTERNS_PATH' ) ) {
		return array();
	}
	$html_path = trailingslashit( GUTENBLOCK_PRO_PATTERNS_PATH ) . sanitize_title( $slug ) . '/content.html';
	if ( ! is_file( $html_path ) ) {
		return array();
	}
	$html = (string) file_get_contents( $html_path );
	if ( '' === $html ) {
		return array();
	}

	$ids = array();
	if ( function_exists( 'parse_blocks' ) ) {
		gutenblock_bridge_collect_content_fields_from_blocks( parse_blocks( $html ), $ids );
	}
	if ( preg_match_all( '/\bdata-content-field\s*=\s*["\']([a-z0-9_-]+)["\']/i', $html, $m ) ) {
		$ids = array_merge( $ids, $m[1] );
	}

	$ids = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $id ) {
						return sanitize_key( (string) $id );
					},
					$ids
				)
			)
		)
	);

	return $ids;
}

/**
 * @param array $blocks parse_blocks()-Ausgabe.
 * @param array $ids Wird per Referenz befüllt.
 */
function gutenblock_bridge_collect_content_fields_from_blocks( array $blocks, array &$ids ): void {
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$text_blocks = array( 'core/heading', 'core/paragraph', 'core/button', 'core/list', 'core/list-item' );
		$block_name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$key         = gutenblock_bridge_get_text_slot_key_for_block( $block );
		if ( in_array( $block_name, $text_blocks, true ) && '' !== $key ) {
			$ids[] = $key;
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			gutenblock_bridge_collect_content_fields_from_blocks( $block['innerBlocks'], $ids );
		}
	}
}

/**
 * REST: Reihenfolge der Pattern-Gruppen (zur Sortierung im SaaS).
 */
function gutenblock_bridge_rest_get_group_order() {
	if ( ! class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
		return new WP_REST_Response( array(), 200 );
	}
	return new WP_REST_Response(
		array(
			'order'  => array_keys( GutenBlock_Pro_Pattern_Loader::$groups ),
			'labels' => GutenBlock_Pro_Pattern_Loader::$groups,
		),
		200
	);
}

/**
 * REST: Seite aus Pattern-Slugs zusammenbauen (serverseitig, geschützt per X-API-Key).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_assemble_page( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) {
		return $perm;
	}

	$body     = $request->get_json_params();
	$patterns = isset( $body['patterns'] ) && is_array( $body['patterns'] ) ? $body['patterns'] : array();
	if ( empty( $patterns ) ) {
		return new WP_Error( 'invalid_patterns', 'patterns (nicht-leeres Array) ist erforderlich.', array( 'status' => 400 ) );
	}

	$plugin_dir = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : WP_PLUGIN_DIR . '/gutenblock-pro/';
	if ( ! is_dir( $plugin_dir . 'patterns' ) ) {
		return new WP_Error( 'no_plugin', 'gutenblock-pro/patterns nicht gefunden.', array( 'status' => 500 ) );
	}

	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	if ( '' === $page_slug ) {
		$page_slug = 'gbp-' . strtolower( wp_generate_password( 10, false, false ) );
		$page_slug = sanitize_title( $page_slug );
	}

	$combined = '';
	foreach ( $patterns as $raw_slug ) {
		$raw = trim( (string) $raw_slug );
		if ( '' === $raw ) {
			continue;
		}

		// Ton-Suffix VOR sanitize_title auflösen!
		// sanitize_title() normalisiert '--' zu '-', was den Tone-Regex bricht.
		$tone      = 'neutral';
		$base_raw  = $raw;
		if ( preg_match( '/^(.+)--(neutral|dark|soft)$/', $raw, $tm ) ) {
			$base_raw = $tm[1];
			$tone     = $tm[2];
		}

		$base_slug = sanitize_title( $base_raw );
		if ( '' === $base_slug || 'impressum' === $base_slug ) {
			continue;
		}

		// Uploads-Override für content.html berücksichtigen
		$html_file = null;
		if ( function_exists( 'gutenblock_pro_custom_pattern_file' ) ) {
			$custom = gutenblock_pro_custom_pattern_file( $base_slug, 'content.html' );
			if ( is_readable( $custom['path'] ) ) {
				$html_file = $custom['path'];
			}
		}
		if ( null === $html_file ) {
			$html_file = $plugin_dir . 'patterns/' . $base_slug . '/content.html';
		}

		if ( ! is_readable( $html_file ) ) {
			return new WP_Error( 'unknown_pattern', 'Unbekanntes Pattern: ' . $base_slug, array( 'status' => 400 ) );
		}

		$chunk = file_get_contents( $html_file );

		// Ton injizieren
		if ( $tone !== 'neutral' && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
			$chunk = GutenBlock_Pro_Tone_Injector::inject( $chunk, $tone );
		}

		$combined .= $chunk . "\n\n";
	}

	$combined = trim( $combined );
	if ( '' === $combined ) {
		return new WP_Error( 'empty_content', 'Kein gültiger Pattern-Inhalt.', array( 'status' => 400 ) );
	}

	// Normalisierung via parse_blocks + serialize_blocks stellt sicher, dass alle
	// Block-Attribute (u.a. align:"full") korrekt im gespeicherten Content stehen –
	// identisch zum Toolbar-Insert-Pfad (appendSection/replaceSection).
	$parsed_blocks = parse_blocks( $combined );
	$combined      = serialize_blocks( $parsed_blocks );

	$existing = get_page_by_path( $page_slug, OBJECT, 'page' );
	$postarr  = array(
		'post_title'   => __( 'GutenBlock Builder Preview', 'gutenblock-bridge' ),
		'post_name'    => $page_slug,
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $combined,
	);

	if ( $existing && isset( $existing->ID ) ) {
		$postarr['ID'] = (int) $existing->ID;
		$pid           = wp_update_post( $postarr, true );
	} else {
		$pid = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $pid ) ) {
		return $pid;
	}

	$url = get_permalink( (int) $pid );
	if ( ! $url ) {
		return new WP_Error( 'no_url', 'Permalink konnte nicht ermittelt werden.', array( 'status' => 500 ) );
	}

	return new WP_REST_Response(
		array(
			'pageId'   => (int) $pid,
			'pageSlug' => $page_slug,
			'url'      => $url,
		),
		200
	);
}

// =============================================================================
// SECTION-OPERATIONEN: Move / Remove / Replace / Append / List
// =============================================================================

/**
 * Lädt das post_content einer Page (per Slug) und parst die Top-Level-Blöcke.
 * Nur Blöcke mit className enthält "gb-pattern-{slug}" gelten als Section.
 *
 * @return array{post:WP_Post, blocks:array, sections:array} Struktur oder WP_Error
 */
function gutenblock_bridge_load_page_sections( $page_slug ) {
	$page = get_page_by_path( $page_slug, OBJECT, 'page' );
	if ( ! $page ) {
		return new WP_Error( 'page_not_found', 'Page nicht gefunden: ' . $page_slug, array( 'status' => 404 ) );
	}

	$blocks   = parse_blocks( $page->post_content );
	$sections = array();
	foreach ( $blocks as $i => $block ) {
		$cls = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
		if ( '' === $cls && isset( $block['innerHTML'] ) ) {
			// Fallback: className aus HTML extrahieren, falls attrs leer
			if ( preg_match( '/class="([^"]*)"/', $block['innerHTML'], $m ) ) {
				$cls = $m[1];
			}
		}
		// Pattern-Slug aus className extrahieren (Outer-Wrapper: gb-section-{slug})
		$slug = '';
		if ( preg_match( '/gb-section-([a-z0-9-]+)/', $cls, $m ) ) {
			$slug = $m[1];
		} elseif ( preg_match( '/gb-pattern-([a-z0-9-]+)/', $cls, $m ) ) {
			$slug = $m[1];
		}
		// Tone-Suffix mit-erfassen, falls vorhanden
		$tone = 'neutral';
		if ( $slug && preg_match( '/^(.+)--(neutral|dark|soft)$/', $slug, $tm ) ) {
			$slug = $tm[1];
			$tone = $tm[2];
		}
		$sections[] = array(
			'index'      => $i,
			'block_idx'  => $i,
			'slug'       => $slug,
			'tone'       => $tone,
			'has_slug'   => $slug !== '',
		);
	}

	return array(
		'post'     => $page,
		'blocks'   => $blocks,
		'sections' => $sections,
	);
}

/**
 * Speichert geänderte Blocks zurück in die Page.
 */
function gutenblock_bridge_save_page_blocks( $post, array $blocks ) {
	$content = serialize_blocks( $blocks );
	$result  = wp_update_post(
		array(
			'ID'           => (int) $post->ID,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return true;
}

/**
 * Findet den Block-Index einer Section.
 *
 * Lookup-Reihenfolge:
 *   1. $index als section-relativer Index (0, 1, 2, … wie die Bridge es zählt)
 *      → übersetzt zu Block-Index (0, 2, 4, …)
 *   2. $index als direkter Block-Index (falls Bridge bereits den richtigen schickt)
 *   3. Slug-basiert (erste Section mit dem Slug)
 */
function gutenblock_bridge_find_section_index( array $sections, $slug, $index = null ) {
	// Nur Sections mit gültigem Slug in einer sortierten Liste
	$valid = array_values( array_filter( $sections, function ( $s ) {
		return $s['has_slug'];
	} ) );

	if ( $index !== null && $index !== '' ) {
		$idx = (int) $index;

		// 1) section-relativer Index (Bridge zählt 0,1,2... nur gb-section-Elemente)
		if ( isset( $valid[ $idx ] ) ) {
			return (int) $valid[ $idx ]['index'];
		}

		// 2) direkter Block-Index
		foreach ( $sections as $s ) {
			if ( (int) $s['index'] === $idx && $s['has_slug'] ) {
				return $idx;
			}
		}
	}

	// 3) Slug-basiert
	if ( $slug ) {
		foreach ( $valid as $s ) {
			if ( $s['slug'] === $slug ) {
				return (int) $s['index'];
			}
		}
	}

	return -1;
}

/**
 * REST: Section nach oben oder unten verschieben.
 */
function gutenblock_bridge_rest_section_move( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$slug      = isset( $body['slug'] ) ? sanitize_title( (string) $body['slug'] ) : '';
	$idx_param = isset( $body['index'] ) ? (int) $body['index'] : null;
	$direction = isset( $body['direction'] ) ? (string) $body['direction'] : 'down';

	if ( ! in_array( $direction, array( 'up', 'down' ), true ) ) {
		return new WP_Error( 'invalid_direction', 'direction muss up|down sein', array( 'status' => 400 ) );
	}

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$idx = gutenblock_bridge_find_section_index( $loaded['sections'], $slug, $idx_param );
	if ( $idx < 0 ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden', array( 'status' => 404 ) );
	}

	$blocks = $loaded['blocks'];
	$swap   = $direction === 'up' ? $idx - 1 : $idx + 1;

	// Nur mit anderer .gb-pattern-Section tauschen, sonst überspringen
	while ( $swap >= 0 && $swap < count( $blocks ) ) {
		$found = false;
		foreach ( $loaded['sections'] as $s ) {
			if ( (int) $s['index'] === $swap && $s['has_slug'] ) {
				$found = true;
				break;
			}
		}
		if ( $found ) break;
		$swap += $direction === 'up' ? -1 : 1;
	}

	if ( $swap < 0 || $swap >= count( $blocks ) ) {
		return new WP_REST_Response( array( 'changed' => false, 'reason' => 'edge' ), 200 );
	}

	$tmp             = $blocks[ $idx ];
	$blocks[ $idx ]  = $blocks[ $swap ];
	$blocks[ $swap ] = $tmp;

	$saved = gutenblock_bridge_save_page_blocks( $loaded['post'], $blocks );
	if ( is_wp_error( $saved ) ) return $saved;

	return new WP_REST_Response( array( 'changed' => true, 'from' => $idx, 'to' => $swap ), 200 );
}

/**
 * REST: Section per Drag-and-Drop an einen beliebigen Insert-Index (zwischen
 * den anderen Sections) verschieben.
 *
 * Body:
 *   - page_slug   : string
 *   - from_slug   : string  (Slug der gezogenen Section, für robusten Lookup)
 *   - from_index  : int     (optional — section-relativer Index als Tiebreaker)
 *   - to_index    : int     (Insert-Position in der Liste der Sections, 0..N)
 */
function gutenblock_bridge_rest_section_reorder( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	$body       = $request->get_json_params();
	$page_slug  = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$from_slug  = isset( $body['from_slug'] ) ? sanitize_title( (string) $body['from_slug'] ) : '';
	$from_idx   = array_key_exists( 'from_index', $body ) ? (int) $body['from_index'] : null;
	$to_index   = array_key_exists( 'to_index', $body ) ? (int) $body['to_index'] : -1;

	if ( ! $from_slug || $to_index < 0 ) {
		return new WP_Error( 'bad_request', 'from_slug & to_index erforderlich', array( 'status' => 400 ) );
	}

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$src_block_idx = gutenblock_bridge_find_section_index( $loaded['sections'], $from_slug, $from_idx );
	if ( $src_block_idx < 0 ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden', array( 'status' => 404 ) );
	}

	// Sortierte Liste der gb-section-Block-Indizes (in Page-Reihenfolge)
	$section_block_indices = array();
	foreach ( $loaded['sections'] as $s ) {
		if ( $s['has_slug'] ) $section_block_indices[] = (int) $s['index'];
	}
	$section_count = count( $section_block_indices );

	// Section-relativer Index der Quelle
	$src_section_idx = array_search( $src_block_idx, $section_block_indices, true );
	if ( $src_section_idx === false ) {
		return new WP_Error( 'section_not_found', 'Section nicht in Liste', array( 'status' => 404 ) );
	}

	$to_index = max( 0, min( $section_count, (int) $to_index ) );

	// Wenn das Ziel identisch zur Quelle ist — keine Änderung
	if ( $to_index === $src_section_idx || $to_index === $src_section_idx + 1 ) {
		return new WP_REST_Response( array( 'changed' => false, 'reason' => 'same' ), 200 );
	}

	// Block-Index des Ziels: vor section[to_index] einfügen.
	// Wenn to_index === count → ans Ende anhängen.
	$dst_block_idx = $to_index >= $section_count
		? count( $loaded['blocks'] )
		: $section_block_indices[ $to_index ];

	// Block extrahieren und an neuer Position einfügen.
	$blocks    = $loaded['blocks'];
	$src_block = $blocks[ $src_block_idx ];
	array_splice( $blocks, $src_block_idx, 1 );
	// Wenn wir nach hinten verschieben, rutscht der dst-Index nach Entfernen um 1.
	$insert_at = $dst_block_idx > $src_block_idx ? $dst_block_idx - 1 : $dst_block_idx;
	array_splice( $blocks, $insert_at, 0, array( $src_block ) );

	$saved = gutenblock_bridge_save_page_blocks( $loaded['post'], $blocks );
	if ( is_wp_error( $saved ) ) return $saved;

	return new WP_REST_Response(
		array(
			'changed'    => true,
			'from_index' => $src_section_idx,
			'to_index'   => $to_index,
		),
		200
	);
}

/**
 * REST: Section entfernen (nur aus Page; Texte/Content-DB unangetastet).
 */
function gutenblock_bridge_rest_section_remove( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$slug      = isset( $body['slug'] ) ? sanitize_title( (string) $body['slug'] ) : '';
	$idx_param = isset( $body['index'] ) ? (int) $body['index'] : null;

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$idx = gutenblock_bridge_find_section_index( $loaded['sections'], $slug, $idx_param );
	if ( $idx < 0 ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden', array( 'status' => 404 ) );
	}

	$blocks = $loaded['blocks'];
	array_splice( $blocks, $idx, 1 );

	$saved = gutenblock_bridge_save_page_blocks( $loaded['post'], $blocks );
	if ( is_wp_error( $saved ) ) return $saved;

	return new WP_REST_Response( array( 'changed' => true, 'removed_index' => $idx ), 200 );
}

/**
 * REST: Liefert das serialisierte Block-HTML einer Section aus post_content.
 *
 * Ziel: User kann den aktuellen Stand einer Section (inkl. seiner KI-Texte und
 * Tonalität) als Gutenberg-Block-Code kopieren und in seinen eigenen WP-Editor
 * einfügen.
 *
 * Body:
 *   - page_slug : string  Slug der Page in der die Section liegt
 *   - slug      : string  Section-Slug (gb-section-{slug})
 *   - index?    : int     optional Section-relativer Index zur Disambiguierung
 *
 * Antwort:
 *   - html  : string  Block-HTML-Markup, direkt in WP-Editor einfügbar
 *   - title : string  Pattern-Titel (für UI-Anzeige)
 */
function gutenblock_bridge_rest_section_get_html( WP_REST_Request $request ) {
	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$slug      = isset( $body['slug'] ) ? sanitize_title( (string) $body['slug'] ) : '';
	$idx_param = isset( $body['index'] ) ? (int) $body['index'] : null;

	if ( '' === $page_slug || '' === $slug ) {
		return new WP_Error( 'bad_request', 'page_slug & slug erforderlich', array( 'status' => 400 ) );
	}

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) {
		return $loaded;
	}

	$idx = gutenblock_bridge_find_section_index( $loaded['sections'], $slug, $idx_param );
	if ( $idx < 0 ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden', array( 'status' => 404 ) );
	}

	$blocks = $loaded['blocks'];
	if ( ! isset( $blocks[ $idx ] ) ) {
		return new WP_Error( 'section_not_found', 'Section-Block nicht gefunden', array( 'status' => 404 ) );
	}

	$html = serialize_block( $blocks[ $idx ] );

	// Pattern-Titel aus Registry holen (für UI-Hinweise)
	$title = $slug;
	if ( class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
		$loader = new GutenBlock_Pro_Pattern_Loader();
		$loader->discover_patterns();
		$registry = $loader->get_patterns();
		if ( isset( $registry[ $slug ]['title'] ) && is_string( $registry[ $slug ]['title'] ) ) {
			$title = (string) $registry[ $slug ]['title'];
		}
	}

	return new WP_REST_Response(
		array(
			'html'  => $html,
			'title' => $title,
			'slug'  => $slug,
		),
		200
	);
}

/**
 * Helper: lädt content.html (mit Uploads-Override) und injiziert ggf. Tone.
 *
 * @return string|WP_Error
 */
function gutenblock_bridge_load_pattern_html( $base_slug, $tone = 'neutral' ) {
	$plugin_dir = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : WP_PLUGIN_DIR . '/gutenblock-pro/';

	$html_file = null;
	if ( function_exists( 'gutenblock_pro_custom_pattern_file' ) ) {
		$custom = gutenblock_pro_custom_pattern_file( $base_slug, 'content.html' );
		if ( is_readable( $custom['path'] ) ) {
			$html_file = $custom['path'];
		}
	}
	if ( null === $html_file ) {
		$html_file = $plugin_dir . 'patterns/' . $base_slug . '/content.html';
	}
	if ( ! is_readable( $html_file ) ) {
		return new WP_Error( 'unknown_pattern', 'Unbekanntes Pattern: ' . $base_slug, array( 'status' => 400 ) );
	}

	$chunk = file_get_contents( $html_file );
	if ( $tone !== 'neutral' && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
		$chunk = GutenBlock_Pro_Tone_Injector::inject( $chunk, $tone );
	}
	return $chunk;
}

/**
 * REST: Section ersetzen — alte Section per slug/index, neue per new_slug.
 * Optional: content_html (bereits content-injizierter Markup mit echtem Text).
 */
function gutenblock_bridge_rest_section_replace( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	$body         = $request->get_json_params();
	$page_slug    = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$old_slug     = isset( $body['slug'] ) ? sanitize_title( (string) $body['slug'] ) : '';
	$idx_param    = isset( $body['index'] ) ? (int) $body['index'] : null;
	$new_raw      = isset( $body['new_slug'] ) ? sanitize_title( (string) $body['new_slug'] ) : '';
	$content_html = isset( $body['content_html'] ) ? (string) $body['content_html'] : '';

	if ( '' === $new_raw ) {
		return new WP_Error( 'invalid_new_slug', 'new_slug fehlt', array( 'status' => 400 ) );
	}

	// Tone-Suffix auflösen
	$new_base = $new_raw;
	$new_tone = 'neutral';
	if ( preg_match( '/^(.+)--(neutral|dark|soft)$/', $new_raw, $tm ) ) {
		$new_base = $tm[1];
		$new_tone = $tm[2];
	}

	if ( '' === $content_html ) {
		$loaded_html = gutenblock_bridge_load_pattern_html( $new_base, $new_tone );
		if ( is_wp_error( $loaded_html ) ) return $loaded_html;
		$content_html = $loaded_html;
	} elseif ( $new_tone !== 'neutral' && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
		$content_html = GutenBlock_Pro_Tone_Injector::inject( $content_html, $new_tone );
	}

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$idx = gutenblock_bridge_find_section_index( $loaded['sections'], $old_slug, $idx_param );
	if ( $idx < 0 ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden', array( 'status' => 404 ) );
	}

	$new_blocks = parse_blocks( $content_html );
	if ( empty( $new_blocks ) ) {
		return new WP_Error( 'parse_failed', 'content_html konnte nicht geparst werden', array( 'status' => 400 ) );
	}

	$blocks = $loaded['blocks'];
	array_splice( $blocks, $idx, 1, $new_blocks );

	$saved = gutenblock_bridge_save_page_blocks( $loaded['post'], $blocks );
	if ( is_wp_error( $saved ) ) return $saved;

	return new WP_REST_Response(
		array(
			'changed'     => true,
			'replaced_at' => $idx,
			'new_slug'    => $new_raw,
		),
		200
	);
}

/**
 * REST: Section anhängen (am Ende der Page einfügen).
 */
function gutenblock_bridge_rest_section_append( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	$body         = $request->get_json_params();
	$page_slug    = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$new_raw      = isset( $body['slug'] ) ? sanitize_title( (string) $body['slug'] ) : '';
	$content_html = isset( $body['content_html'] ) ? (string) $body['content_html'] : '';

	if ( '' === $new_raw ) {
		return new WP_Error( 'invalid_slug', 'slug fehlt', array( 'status' => 400 ) );
	}

	$new_base = $new_raw;
	$new_tone = 'neutral';
	if ( preg_match( '/^(.+)--(neutral|dark|soft)$/', $new_raw, $tm ) ) {
		$new_base = $tm[1];
		$new_tone = $tm[2];
	}

	if ( '' === $content_html ) {
		$loaded_html = gutenblock_bridge_load_pattern_html( $new_base, $new_tone );
		if ( is_wp_error( $loaded_html ) ) return $loaded_html;
		$content_html = $loaded_html;
	} elseif ( $new_tone !== 'neutral' && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
		$content_html = GutenBlock_Pro_Tone_Injector::inject( $content_html, $new_tone );
	}

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$new_blocks = parse_blocks( $content_html );
	if ( empty( $new_blocks ) ) {
		return new WP_Error( 'parse_failed', 'content_html konnte nicht geparst werden', array( 'status' => 400 ) );
	}

	$blocks    = $loaded['blocks'];
	$insert_at = -1;

	// Optionale Parameter: nach einer bestimmten Section einfügen.
	// `after_slug` (+ optional `after_section_index`) ist robuster als der
	// reine section-relative Index, weil der DOM-Index im Browser-Bridge
	// nicht zwingend mit dem PHP-`has_slug`-gefilterten Index übereinstimmt
	// (z.B. wenn Sections mit `gb-pattern-*` aber ohne `gb-section-*`
	// existieren). Wir suchen also primär nach Slug.
	$after_slug          = isset( $body['after_slug'] ) ? sanitize_title( (string) $body['after_slug'] ) : '';
	$after_section_index = isset( $body['after_section_index'] ) ? (int) $body['after_section_index'] : -1;

	if ( $after_slug !== '' ) {
		// Slug-basiert (robust): Section direkt suchen.
		// Wenn der Slug mehrfach vorkommt (selten), nutze `after_section_index`
		// in der gefilterten valid-Liste als Tiebreaker.
		$matches = array();
		foreach ( $loaded['sections'] as $s ) {
			if ( $s['has_slug'] && $s['slug'] === $after_slug ) {
				$matches[] = $s;
			}
		}
		if ( count( $matches ) === 1 ) {
			$insert_at = (int) $matches[0]['index'] + 1;
		} elseif ( count( $matches ) > 1 ) {
			// Disambiguieren über after_section_index (in der valid-Liste)
			$valid = array_values( array_filter( $loaded['sections'], function( $s ) {
				return $s['has_slug'];
			} ) );
			$pos = 0;
			foreach ( $valid as $i => $s ) {
				if ( $s['slug'] === $after_slug ) {
					if ( $i === $after_section_index ) { $pos = $i; break; }
					$pos = $i;
				}
			}
			$insert_at = (int) $valid[ $pos ]['index'] + 1;
		}
	} elseif ( $after_section_index >= 0 ) {
		// Fallback: section-relativer Index → Block-Index ermitteln
		$valid = array_values( array_filter( $loaded['sections'], function( $s ) {
			return $s['has_slug'];
		} ) );
		if ( isset( $valid[ $after_section_index ] ) ) {
			$insert_at = (int) $valid[ $after_section_index ]['index'] + 1;
		}
	}

	// Fallback: vor Footer oder ganz ans Ende
	if ( $insert_at < 0 ) {
		$footer_idx = -1;
		foreach ( $loaded['sections'] as $s ) {
			if ( $s['has_slug'] && false !== strpos( $s['slug'], 'footer' ) ) {
				$footer_idx = (int) $s['index'];
				break;
			}
		}
		$insert_at = $footer_idx >= 0 ? $footer_idx : count( $blocks );
	}

	array_splice( $blocks, $insert_at, 0, $new_blocks );

	$saved = gutenblock_bridge_save_page_blocks( $loaded['post'], $blocks );
	if ( is_wp_error( $saved ) ) return $saved;

	return new WP_REST_Response(
		array(
			'changed'     => true,
			'inserted_at' => $insert_at,
			'slug'        => $new_raw,
		),
		200
	);
}

/**
 * REST: Liste der Sections einer Page (für Editor-State).
 */
function gutenblock_bridge_rest_section_list( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	$page_slug = $request->get_param( 'page_slug' );
	$page_slug = sanitize_title( (string) $page_slug );

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$out = array();
	foreach ( $loaded['sections'] as $s ) {
		if ( ! $s['has_slug'] ) continue;
		$out[] = array(
			'index' => (int) $s['index'],
			'slug'  => $s['slug'],
			'tone'  => $s['tone'],
		);
	}
	return new WP_REST_Response( array( 'sections' => $out ), 200 );
}

/**
 * Liefert alle Page-Vorlagen aus dem Pattern-Loader, gefiltert nach page_type.
 *
 * Eine Page-Vorlage ist ein Eintrag mit type='page' und passendem
 * page_type-Feld in der pattern.php.
 *
 * @param string $page_type 'services'|'about'|'blog'|'legal'|'' (= alle Pages).
 * @return array<string,array> map slug => pattern_data
 */
function gutenblock_bridge_collect_page_templates( $page_type = '' ) {
	$loader = new GutenBlock_Pro_Pattern_Loader();
	$loader->discover_patterns();
	$out = array();
	foreach ( $loader->get_patterns() as $slug => $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		if ( ( isset( $p['type'] ) ? $p['type'] : '' ) !== 'page' ) {
			continue;
		}
		$pt = isset( $p['page_type'] ) ? (string) $p['page_type'] : '';
		// Impressum / Legal-Page wird nicht von page/create angeboten.
		if ( $pt === 'legal' || $slug === 'impressum' ) {
			continue;
		}
		if ( $page_type !== '' && $pt !== $page_type ) {
			continue;
		}
		$out[ $slug ] = $p;
	}
	return $out;
}

/**
 * Wählt eine Page-Vorlage aus den Kandidaten anhand des User-Prompts.
 *
 * Stub-Implementation: nimmt aktuell den ersten Kandidaten. Sobald das
 * SaaS einen KI-gestützten Selector liefert (analog zur Pattern-Auswahl
 * für die Startseite), kann hier der OpenAI-Call eingebaut werden, der
 * `ai_hint` der Vorlagen + den User-Prompt als Eingabe nutzt.
 *
 * @param array<string,array> $candidates  map slug => pattern_data
 * @param string              $prompt      Frei-Text vom User
 * @return string slug der ausgewählten Vorlage (leer bei Misserfolg)
 */
function gutenblock_bridge_choose_page_template( $candidates, $prompt ) {
	if ( empty( $candidates ) ) {
		return '';
	}
	// Aktuell: random — sorgt für Varianz, falls mehrere Vorlagen vorhanden.
	$slugs = array_keys( $candidates );
	if ( count( $slugs ) === 1 ) {
		return $slugs[0];
	}
	$idx = function_exists( 'wp_rand' ) ? wp_rand( 0, count( $slugs ) - 1 ) : array_rand( $slugs );
	return $slugs[ $idx ];
}

/**
 * Baut den HTML-Content einer Page aus deren Vorlage zusammen.
 *
 * Zwei mögliche Varianten in pattern.php:
 *   1. `page_patterns => array(...slugs)` — die Page setzt sich aus
 *      mehreren Section-Patterns zusammen (analog zu /assemble-page).
 *   2. content.html — der Page-Content wird direkt eingefügt.
 *
 * @param string $slug
 * @param array  $pattern_data
 * @return string|WP_Error
 */
function gutenblock_bridge_build_page_content( $slug, $pattern_data ) {
	$plugin_dir = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : WP_PLUGIN_DIR . '/gutenblock-pro/';

	// Variante 1: Pattern-Liste
	if ( ! empty( $pattern_data['page_patterns'] ) && is_array( $pattern_data['page_patterns'] ) ) {
		$combined = '';
		foreach ( $pattern_data['page_patterns'] as $raw_slug ) {
			$raw = trim( (string) $raw_slug );
			if ( '' === $raw ) {
				continue;
			}
			$tone     = 'neutral';
			$base_raw = $raw;
			if ( preg_match( '/^(.+)--(neutral|dark|soft)$/', $raw, $tm ) ) {
				$base_raw = $tm[1];
				$tone     = $tm[2];
			}
			$base_slug = sanitize_title( $base_raw );
			$html_file = $plugin_dir . 'patterns/' . $base_slug . '/content.html';
			if ( ! is_readable( $html_file ) ) {
				return new WP_Error( 'unknown_pattern', 'Unbekanntes Sub-Pattern: ' . $base_slug, array( 'status' => 400 ) );
			}
			$chunk = file_get_contents( $html_file );
			if ( $tone !== 'neutral' && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
				$chunk = GutenBlock_Pro_Tone_Injector::inject( $chunk, $tone );
			}
			$combined .= $chunk . "\n\n";
		}
		return trim( $combined );
	}

	// Variante 2: content.html der Vorlage selbst
	$html_file = $plugin_dir . 'patterns/' . $slug . '/content.html';
	if ( is_readable( $html_file ) ) {
		return trim( file_get_contents( $html_file ) );
	}
	return new WP_Error( 'no_content', 'Vorlage hat weder page_patterns noch content.html: ' . $slug, array( 'status' => 500 ) );
}

/**
 * Sammelt metadata.name aller Text-Blöcke (für SaaS: fehlende Keys in contentJson lazy füllen).
 *
 * @param array<int,array<string,mixed>> $blocks parse_blocks()-Baum.
 * @return array<int,string>
 */
function gutenblock_bridge_collect_content_field_ids_from_blocks( $blocks ) {
	if ( ! is_array( $blocks ) ) {
		return array();
	}
	$names       = array();
	$text_blocks = array( 'core/paragraph', 'core/heading', 'core/button', 'core/list-item' );
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$bn = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( $bn !== '' && in_array( $bn, $text_blocks, true ) ) {
			$nm = gutenblock_bridge_get_text_slot_key_for_block( $block );
			if ( $nm !== '' ) {
				$names[] = $nm;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$names = array_merge( $names, gutenblock_bridge_collect_content_field_ids_from_blocks( $block['innerBlocks'] ) );
		}
	}
	return $names;
}

/**
 * Text-Key für einen Block. `metadata.name` bleibt die führende Quelle.
 * Buttons dürfen als Fallback ihre HTML-ID nutzen, damit CTA-Texte auch ohne
 * manuelle Textfeld-Zuordnung im SaaS gefüllt und bearbeitet werden können.
 *
 * @param array<string,mixed> $block parse_blocks()-Block.
 * @return string
 */
function gutenblock_bridge_get_text_slot_key_for_block( $block ) {
	if ( ! is_array( $block ) ) {
		return '';
	}
	$name = isset( $block['attrs']['metadata']['name'] ) ? trim( (string) $block['attrs']['metadata']['name'] ) : '';
	if ( '' !== $name ) {
		return sanitize_key( $name );
	}
	$bn = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
	if ( 'core/button' !== $bn ) {
		return '';
	}
	$html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
	if ( preg_match( '/<div\b[^>]*\bid=["\']([a-zA-Z0-9_-]+)["\']/i', $html, $m ) ) {
		return sanitize_key( $m[1] );
	}
	return '';
}

/**
 * Ersetzt den Text-Inhalt eines einfachen HTML-Snippets mit genau einem Root-Element.
 *
 * @param string $inner_html Block-innerHTML.
 * @param string $new_text   Neuer Plaintext (wird mit wp_kses_post gesäubert).
 * @return string|null
 */
function gutenblock_bridge_replace_root_element_text( $inner_html, $new_text ) {
	$inner_html = (string) $inner_html;
	if ( $inner_html === '' ) {
		return null;
	}
	$trim = trim( $inner_html );
	if ( ! preg_match( '/^(<[a-zA-Z][^>]*>)([\s\S]*)(<\/[a-zA-Z][a-zA-Z0-9:-]*\s*>)$/u', $trim, $m ) ) {
		return null;
	}
	return $m[1] . wp_kses_post( $new_text ) . $m[3];
}

/**
 * Ersetzt den sichtbaren Text im ersten Button-Link (core/button).
 *
 * @param string $inner_html Block-innerHTML.
 * @param string $new_text   Neuer Linktext.
 * @return string|null
 */
function gutenblock_bridge_replace_button_anchor_text( $inner_html, $new_text ) {
	$inner_html = (string) $inner_html;
	if ( ! preg_match( '/^(.*?)(<a\s[^>]*wp-block-button__link[^>]*>)([\s\S]*?)(<\/a>)(.*)$/u', $inner_html, $m ) ) {
		return null;
	}
	return $m[1] . $m[2] . wp_kses_post( $new_text ) . $m[4] . $m[5];
}

/**
 * Rekursiv: schreibt generierte Feldtexte in Block-innerHTML (metadata.name).
 *
 * @param array<int,array<string,mixed>> $blocks     Referenz auf parse_blocks-Baum.
 * @param array<string,string>           $fields_map field_id => text.
 * @param int                            $replaced   Zähler.
 * @param array<string,bool>             $applied    Welche Keys wurden ersetzt.
 */
function gutenblock_bridge_walk_replace_block_texts( &$blocks, $fields_map, &$replaced, &$applied ) {
	if ( ! is_array( $blocks ) ) {
		return;
	}
	foreach ( $blocks as &$block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$name = gutenblock_bridge_get_text_slot_key_for_block( $block );
		if ( $name !== '' && isset( $fields_map[ $name ] ) && ! empty( $block['innerHTML'] ) ) {
			$new   = $fields_map[ $name ];
			$bn    = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$html  = $block['innerHTML'];
			$repl  = null;
			if ( in_array( $bn, array( 'core/paragraph', 'core/heading', 'core/list-item' ), true ) ) {
				$repl = gutenblock_bridge_replace_root_element_text( $html, $new );
			} elseif ( 'core/button' === $bn ) {
				$repl = gutenblock_bridge_replace_button_anchor_text( $html, $new );
			}
			if ( null !== $repl ) {
				$block['innerHTML']    = $repl;
				// WordPress' serialize_block() iteriert über innerContent (nicht innerHTML).
				// Für Text-Blocks ohne nested children ist innerContent ein 1-elementiges
				// Array mit dem HTML-String — wir setzen es konsistent neu, damit der
				// Block beim Speichern nicht leer serialisiert wird.
				$block['innerContent'] = array( $repl );
				++$replaced;
				$applied[ $name ] = true;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			gutenblock_bridge_walk_replace_block_texts( $block['innerBlocks'], $fields_map, $replaced, $applied );
		}
	}
}

/**
 * Injiziert ein HTML-Attribut in das ROOT-Tag eines einfachen innerHTML-
 * Snippets (paragraph / heading / list-item). Liefert null, wenn kein
 * Root-Tag erkannt oder das Attribut bereits vorhanden ist.
 *
 * @param string $inner_html
 * @param string $attr_name
 * @param string $attr_value
 * @return string|null
 */
function gutenblock_bridge_inject_root_attribute( $inner_html, $attr_name, $attr_value ) {
	$inner_html = (string) $inner_html;
	if ( '' === $inner_html ) {
		return null;
	}
	$trim = trim( $inner_html );
	if ( ! preg_match( '/^(<[a-zA-Z][^>]*?)(\/?>)/u', $trim, $m ) ) {
		return null;
	}
	$tag_open = $m[1];
	if ( stripos( $tag_open, ' ' . $attr_name . '=' ) !== false ) {
		return null;
	}
	$injected_open = $tag_open . ' ' . $attr_name . '="' . esc_attr( $attr_value ) . '"' . $m[2];
	return $injected_open . substr( $trim, strlen( $m[1] . $m[2] ) );
}

/**
 * Injiziert ein HTML-Attribut in das `<a class="…wp-block-button__link…">` eines
 * core/button-Blocks. Liefert null, wenn der Button-Anker nicht gefunden wird.
 *
 * @param string $inner_html
 * @param string $attr_name
 * @param string $attr_value
 * @return string|null
 */
function gutenblock_bridge_inject_button_attribute( $inner_html, $attr_name, $attr_value ) {
	$inner_html = (string) $inner_html;
	if ( ! preg_match( '/^(.*?)(<a\s)([^>]*?)(>)([\s\S]*?)(<\/a>)(.*)$/u', $inner_html, $m ) ) {
		return null;
	}
	if ( strpos( $m[3], 'wp-block-button__link' ) === false ) {
		return null;
	}
	if ( stripos( $m[3], ' ' . $attr_name . '=' ) !== false ) {
		return null;
	}
	$new_attrs = $m[3] . ' ' . $attr_name . '="' . esc_attr( $attr_value ) . '"';
	return $m[1] . $m[2] . $new_attrs . $m[4] . $m[5] . $m[6] . $m[7];
}

/**
 * Rekursiv: setzt `data-gb-text-key="<metadata.name>"` an das Root-Element jedes
 * Text-Blocks (paragraph/heading/list-item) bzw. an den `<a>` des Buttons.
 * Für Buttons ohne `metadata.name` wird die HTML-ID als Fallback-Key genutzt.
 * Sammelt parallel die Slot-Liste, damit der SaaS-Renderer weiß, welche Keys
 * im Template als Marker vorhanden sind.
 *
 * @param array<int,array<string,mixed>>           $blocks  Referenz auf parse_blocks-Baum.
 * @param array<int,array{key:string,kind:string}> $slots   Out-Parameter.
 */
function gutenblock_bridge_walk_inject_text_slot_markers( &$blocks, &$slots ) {
	if ( ! is_array( $blocks ) ) {
		return;
	}
	foreach ( $blocks as &$block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$name = gutenblock_bridge_get_text_slot_key_for_block( $block );
		if ( $name !== '' && ! empty( $block['innerHTML'] ) ) {
			$bn   = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$html = $block['innerHTML'];
			$marked = null;
			$kind   = '';
			if ( in_array( $bn, array( 'core/paragraph', 'core/heading', 'core/list-item' ), true ) ) {
				$marked = gutenblock_bridge_inject_root_attribute( $html, 'data-gb-text-key', $name );
				$kind   = 'text';
			} elseif ( 'core/button' === $bn ) {
				$marked = gutenblock_bridge_inject_button_attribute( $html, 'data-gb-text-key', $name );
				$kind   = 'button';
			}
			if ( null !== $marked ) {
				$block['innerHTML']    = $marked;
				$block['innerContent'] = array( $marked );
				$slots[] = array(
					'key'  => $name,
					'kind' => $kind,
				);
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			gutenblock_bridge_walk_inject_text_slot_markers( $block['innerBlocks'], $slots );
		}
	}
}

/**
 * REST: Schreibt generierte Texte aus dem SaaS (field_id → string) in post_content.
 *
 * Body:
 *   - page_slug string
 *   - fields    object { "field-id": "text", ... }
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_page_replace_block_texts( WP_REST_Request $request ) {
	$body       = $request->get_json_params();
	$page_slug  = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$fields_raw = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : array();

	if ( '' === $page_slug ) {
		return new WP_Error( 'missing_page_slug', 'page_slug ist erforderlich.', array( 'status' => 400 ) );
	}

	$fields_map = array();
	foreach ( $fields_raw as $k => $v ) {
		$fid = (string) $k;
		if ( '' === $fid ) {
			continue;
		}
		$fields_map[ $fid ] = is_string( $v ) ? $v : (string) $v;
	}

	if ( empty( $fields_map ) ) {
		return new WP_REST_Response(
			array(
				'replaced' => 0,
				'missing'  => array(),
			),
			200
		);
	}

	$post = get_page_by_path( $page_slug, OBJECT, 'page' );
	if ( ! $post ) {
		return new WP_Error( 'not_found', 'Seite nicht gefunden: ' . $page_slug, array( 'status' => 404 ) );
	}

	$blocks   = parse_blocks( $post->post_content );
	$replaced = 0;
	$applied  = array();

	gutenblock_bridge_walk_replace_block_texts( $blocks, $fields_map, $replaced, $applied );

	$new_content = serialize_blocks( $blocks );
	$upd         = wp_update_post(
		array(
			'ID'           => (int) $post->ID,
			'post_content' => $new_content,
		),
		true
	);
	if ( is_wp_error( $upd ) ) {
		return $upd;
	}

	$requested_ids = array_keys( $fields_map );
	$missing       = array_values( array_diff( $requested_ids, array_keys( $applied ) ) );

	return new WP_REST_Response(
		array(
			'replaced' => $replaced,
			'missing'  => $missing,
		),
		200
	);
}

/**
 * REST: Erstellt eine neue Sub-Page (Services/About/Blog) basierend auf einer
 * Plugin-Vorlage. Die KI wählt eine passende Variante anhand des User-Prompts.
 *
 * Body:
 *   - contentId   string  (für späteren Content-Fill, aktuell informativ)
 *   - templateId  string
 *   - page_type   'services'|'about'|'blog'
 *   - prompt      string  Freitext vom User
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_page_create( WP_REST_Request $request ) {
	$body       = $request->get_json_params();
	$page_type  = isset( $body['page_type'] ) ? sanitize_key( $body['page_type'] ) : '';
	$prompt     = isset( $body['prompt'] ) ? trim( (string) $body['prompt'] ) : '';
	$contentId  = isset( $body['contentId'] ) ? (string) $body['contentId'] : '';
	$templateId = isset( $body['templateId'] ) ? (string) $body['templateId'] : '';

	$valid_types = array( 'services', 'about', 'blog' );
	if ( ! in_array( $page_type, $valid_types, true ) ) {
		return new WP_Error( 'invalid_page_type', 'page_type muss services|about|blog sein.', array( 'status' => 400 ) );
	}
	if ( '' === $prompt ) {
		return new WP_Error( 'missing_prompt', 'prompt ist erforderlich.', array( 'status' => 400 ) );
	}
	if ( ! class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
		return new WP_Error( 'no_loader', 'Pattern-Loader nicht verfügbar.', array( 'status' => 500 ) );
	}

	$candidates = gutenblock_bridge_collect_page_templates( $page_type );
	if ( empty( $candidates ) ) {
		return new WP_Error(
			'no_template',
			sprintf(
				'Keine Page-Vorlage für "%s" vorhanden. Lege im Plugin ein Pattern mit type="page" und page_type="%s" an.',
				$page_type,
				$page_type
			),
			array( 'status' => 404 )
		);
	}

	$chosen_slug = gutenblock_bridge_choose_page_template( $candidates, $prompt );
	if ( '' === $chosen_slug || ! isset( $candidates[ $chosen_slug ] ) ) {
		return new WP_Error( 'no_choice', 'Keine Vorlage gewählt.', array( 'status' => 500 ) );
	}
	$template = $candidates[ $chosen_slug ];

	$content = gutenblock_bridge_build_page_content( $chosen_slug, $template );
	if ( is_wp_error( $content ) ) {
		return $content;
	}
	if ( '' === $content ) {
		return new WP_Error( 'empty_content', 'Vorlage hat leeren Content.', array( 'status' => 500 ) );
	}

	// Normalisierung wie in assemble-page.
	$parsed_blocks = parse_blocks( $content );

	// Page-Vorlagen sind oft als ein einziger gb-pattern-{slug}-Wrapper mit
	// verschachtelten gb-section-*-Blöcken aufgebaut. Damit die Bridge die
	// einzelnen Sections für move/replace/retone findet (sie scannt nur
	// Top-Level-Blocks), entpacken wir den Outer-Wrapper hier.
	if ( count( $parsed_blocks ) === 1 ) {
		$top = $parsed_blocks[0];
		$cls = isset( $top['attrs']['className'] ) ? (string) $top['attrs']['className'] : '';
		if ( $cls !== '' && preg_match( '/(?:^|\s)gb-pattern-[a-z0-9-]+/', $cls )
			&& ! empty( $top['innerBlocks'] ) && is_array( $top['innerBlocks'] ) ) {
			$parsed_blocks = $top['innerBlocks'];
		}
	}

	$field_ids = array_values( array_unique( gutenblock_bridge_collect_content_field_ids_from_blocks( $parsed_blocks ) ) );
	$content   = serialize_blocks( $parsed_blocks );

	// Frischer Page-Slug für jede Erzeugung — User kann später umbenennen.
	$page_title = '' !== ( isset( $template['title'] ) ? $template['title'] : '' )
		? (string) $template['title']
		: ucfirst( $page_type ) . ' Page';
	$page_slug  = sanitize_title( 'gbp-' . $page_type . '-' . wp_generate_password( 6, false, false ) );

	$postarr = array(
		'post_title'   => $page_title,
		'post_name'    => $page_slug,
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	);
	$pid = wp_insert_post( $postarr, true );
	if ( is_wp_error( $pid ) ) {
		return $pid;
	}
	$url = get_permalink( (int) $pid );

	unset( $contentId, $templateId );

	return new WP_REST_Response(
		array(
			'pageId'        => (int) $pid,
			'pageSlug'      => $page_slug,
			'pageTitle'     => $page_title,
			'url'           => $url,
			'used_template' => $chosen_slug,
			'field_ids'     => $field_ids,
		),
		200
	);
}

/**
 * Registriert REST-Routen für Pattern-Builder.
 */
function gutenblock_bridge_register_pattern_builder_api() {
	register_rest_route(
		'gutenblock/v1',
		'/patterns',
		array(
			'methods'             => 'GET',
			'callback'            => 'gutenblock_bridge_rest_get_patterns',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/group-order',
		array(
			'methods'             => 'GET',
			'callback'            => 'gutenblock_bridge_rest_get_group_order',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/patterns/bundle',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'gutenblock_bridge_rest_patterns_bundle',
				'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'gutenblock_bridge_rest_patterns_bundle_rebuild',
				'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
			),
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/assemble-page',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_assemble_page',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/list',
		array(
			'methods'             => 'GET',
			'callback'            => 'gutenblock_bridge_rest_section_list',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
			'args'                => array( 'page_slug' => array( 'required' => true ) ),
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/move',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_move',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/remove',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_remove',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/replace',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_replace',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/append',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_append',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/retone',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_retone',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/reorder',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_reorder',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page-section/get-html',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_section_get_html',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page/create',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_page_create',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page/replace-block-texts',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_page_replace_block_texts',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page/delete',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_page_delete',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page/clean-revisions',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_page_clean_revisions',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page/block-markup',
		array(
			'methods'             => 'GET',
			'callback'            => 'gutenblock_bridge_rest_page_block_markup',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
			'args'                => array( 'page_slug' => array( 'required' => true ) ),
		)
	);

	register_rest_route(
		'gutenblock/v1',
		'/page/render-sections',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_page_render_sections',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	// Alias gemäß Headless-Roadmap (POST Body gleich wie /page/render-sections).
	register_rest_route(
		'gutenblock/v1',
		'/render',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_page_render_sections',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	// Single-Section-Render (für punktuelles DOM-Patching im SaaS-Editor).
	register_rest_route(
		'gutenblock/v1',
		'/render-section',
		array(
			'methods'             => 'POST',
			'callback'            => 'gutenblock_bridge_rest_render_single_section',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
		)
	);

	// Pattern-Template (Headless-Optimierung): liefert das Pattern-HTML mit
	// `data-gb-text-key`-Markern + Slot-Map, damit der SaaS-Client Texte
	// clientseitig injizieren kann — kein WP-Roundtrip pro Text-Edit.
	register_rest_route(
		'gutenblock/v1',
		'/pattern/(?P<slug>[a-zA-Z0-9_-]+)/template',
		array(
			'methods'             => 'GET',
			'callback'            => 'gutenblock_bridge_rest_pattern_template',
			'permission_callback' => 'gutenblock_bridge_assemble_permission_callback',
			'args'                => array(
				'slug' => array( 'required' => true ),
				'tone' => array( 'required' => false ),
			),
		)
	);
}

/**
 * REST: Eine einzelne Section gerendert + ihre Pattern-Assets +
 * aktualisiertes globalInlineCss (Layout-Hashes für die ganze Page, damit
 * der Editor den Stylesheet-Snippet komplett tauschen kann).
 *
 * Body: { "page_slug": "…", "index": 0 }  (alternativ "slug": "hero-v1")
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_render_single_section( WP_REST_Request $request ) {
	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	if ( '' === $page_slug ) {
		return new WP_Error( 'missing_page_slug', 'page_slug ist erforderlich.', array( 'status' => 400 ) );
	}
	$want_index = isset( $body['index'] ) ? (int) $body['index'] : -1;
	$want_slug  = isset( $body['slug'] ) ? (string) $body['slug'] : '';

	$page = get_page_by_path( $page_slug, OBJECT, 'page' );
	if ( ! $page || ! isset( $page->ID ) ) {
		return new WP_Error( 'not_found', 'Seite nicht gefunden: ' . $page_slug, array( 'status' => 404 ) );
	}

	gutenblock_bridge_prime_frontend_styles();

	$all       = parse_blocks( $page->post_content );
	$ver       = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';
	$base_path = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : '';
	$base_url  = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : '';

	// Marker für clientseitiges Text-Inject im SaaS (gleicher Walker wie im
	// /page/render-sections-Endpoint und im /pattern/{slug}/template-Endpoint).
	$marker_slots = array();
	gutenblock_bridge_walk_inject_text_slot_markers( $all, $marker_slots );

	// Alle Blöcke einmal rendern, damit `wp_styles()->get_data('core-block-supports','after')`
	// sämtliche Layout-Klassen der Page enthält (Diff-Update im Editor sicher).
	$found_section = null;
	$running_index = 0;
	foreach ( $all as $block ) {
		if ( empty( $block['blockName'] ) ) {
			continue;
		}
		$html = render_block( $block );
		$slug = gutenblock_bridge_guess_pattern_slug_from_block( $block );
		if (
			null === $found_section &&
			(
				( $want_index >= 0 && $running_index === $want_index ) ||
				( '' !== $want_slug && $slug === $want_slug )
			)
		) {
			$css = array();
			$js  = array();
			if ( $slug && $base_path && is_file( $base_path . 'patterns/' . $slug . '/style.css' ) ) {
				$css[] = $base_url . 'patterns/' . $slug . '/style.css?ver=' . rawurlencode( (string) $ver );
			}
			if ( $slug && $base_path && is_file( $base_path . 'patterns/' . $slug . '/script.js' ) ) {
				$js[] = $base_url . 'patterns/' . $slug . '/script.js?ver=' . rawurlencode( (string) $ver );
			}
			$found_section = array(
				'index'    => $running_index,
				'slug'     => $slug,
				'html'     => $html,
				'cssHrefs' => $css,
				'jsHrefs'  => $js,
			);
		}
		++$running_index;
	}

	if ( null === $found_section ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden.', array( 'status' => 404 ) );
	}

	$globals          = gutenblock_bridge_collect_global_assets();
	$globals['inline'] .= "\n" . gutenblock_bridge_collect_inline_styles_after_render();

	return new WP_REST_Response(
		array(
			'pageSlug'        => $page_slug,
			'section'         => $found_section,
			'globalCssHrefs'  => $globals['hrefs'],
			'globalInlineCss' => $globals['inline'],
		),
		200
	);
}

/**
 * REST: Liefert ein Pattern als reines Template.
 *
 * Pfadparam:
 *   - slug       Pattern-Slug (z.B. "about-v2"); optional Tone-Suffix
 *                "--neutral"|"--dark"|"--soft" für die jeweilige Variante.
 *
 * Querystring:
 *   - tone       (optional) Alternative zum Suffix.
 *
 * Response:
 *   - slug             string           Aufgelöster Pattern-Slug (inkl. Tone).
 *   - baseSlug         string           Pattern-Slug OHNE Tone-Suffix.
 *   - tone             string           "neutral"|"dark"|"soft".
 *   - html             string           Gerendertes Pattern-HTML mit
 *                                       `data-gb-text-key`-Attributen an den
 *                                       Text-Slots.
 *   - slots            array            { key, kind: "text"|"button" }[].
 *   - cssHrefs         string[]         Pattern-spezifische Stylesheets.
 *   - jsHrefs          string[]         Pattern-spezifische Scripts.
 *   - globalCssHrefs   string[]         WP-Core/Theme-Stylesheets.
 *   - globalInlineCss  string           WP-Inline-Theme/Block-CSS.
 *   - pluginVersion    string
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_pattern_template( WP_REST_Request $request ) {
	$raw_slug = isset( $request['slug'] ) ? trim( (string) $request['slug'] ) : '';
	$tone_q   = $request->get_param( 'tone' );
	if ( '' === $raw_slug ) {
		return new WP_Error( 'missing_slug', 'slug ist erforderlich.', array( 'status' => 400 ) );
	}

	$tone      = 'neutral';
	$base_slug = $raw_slug;
	if ( preg_match( '/^(.+)--(neutral|dark|soft)$/', $raw_slug, $tm ) ) {
		$base_slug = $tm[1];
		$tone      = $tm[2];
	}
	if ( is_string( $tone_q ) && in_array( $tone_q, array( 'neutral', 'dark', 'soft' ), true ) ) {
		$tone = $tone_q;
	}
	$base_slug = sanitize_title( $base_slug );
	if ( '' === $base_slug ) {
		return new WP_Error( 'invalid_slug', 'slug ist ungültig.', array( 'status' => 400 ) );
	}

	$plugin_dir = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : WP_PLUGIN_DIR . '/gutenblock-pro/';
	$plugin_url = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : plugins_url( '/', __FILE__ );
	$ver        = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';

	$html_file = $plugin_dir . 'patterns/' . $base_slug . '/content.html';
	if ( ! is_readable( $html_file ) ) {
		return new WP_Error( 'unknown_pattern', 'Pattern nicht gefunden: ' . $base_slug, array( 'status' => 404 ) );
	}
	$markup = file_get_contents( $html_file );
	if ( $tone !== 'neutral' && class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
		$markup = GutenBlock_Pro_Tone_Injector::inject( $markup, $tone );
	}

	gutenblock_bridge_prime_frontend_styles();

	$blocks = parse_blocks( (string) $markup );
	$slots  = array();
	gutenblock_bridge_walk_inject_text_slot_markers( $blocks, $slots );

	$html = '';
	foreach ( $blocks as $block ) {
		if ( empty( $block['blockName'] ) && trim( (string) ( $block['innerHTML'] ?? '' ) ) === '' ) {
			continue;
		}
		$html .= render_block( $block );
	}

	$css = array();
	$js  = array();
	if ( is_file( $plugin_dir . 'patterns/' . $base_slug . '/style.css' ) ) {
		$css[] = $plugin_url . 'patterns/' . $base_slug . '/style.css?ver=' . rawurlencode( (string) $ver );
	}
	if ( is_file( $plugin_dir . 'patterns/' . $base_slug . '/script.js' ) ) {
		$js[] = $plugin_url . 'patterns/' . $base_slug . '/script.js?ver=' . rawurlencode( (string) $ver );
	}

	$globals           = gutenblock_bridge_collect_global_assets();
	$globals['inline'] .= "\n" . gutenblock_bridge_collect_inline_styles_after_render();

	return new WP_REST_Response(
		array(
			'slug'            => $base_slug . ( 'neutral' === $tone ? '' : '--' . $tone ),
			'baseSlug'        => $base_slug,
			'tone'            => $tone,
			'html'            => $html,
			'slots'           => array_values( $slots ),
			'cssHrefs'        => $css,
			'jsHrefs'         => $js,
			'globalCssHrefs'  => $globals['hrefs'],
			'globalInlineCss' => $globals['inline'],
			'pluginVersion'   => (string) $ver,
		),
		200
	);
}

/**
 * REST: Statisches Pattern-Bundle für den SaaS.
 *
 * GET liefert das zuletzt gebaute Bundle aus `wp_options`; falls keines
 * existiert oder `?forceFresh=1` gesetzt ist, wird synchron neu gebaut.
 * POST erzwingt einen Rebuild. Das ist der "aktive Trigger" im Plugin, den
 * ein Admin-Button oder Deployment-Hook später aufrufen kann.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_patterns_bundle( WP_REST_Request $request ) {
	$force = '1' === (string) $request->get_param( 'forceFresh' );
	$bundle = $force ? null : get_option( 'gutenblock_bridge_pattern_bundle' );
	if ( ! is_array( $bundle ) || empty( $bundle['patterns'] ) ) {
		$bundle = gutenblock_bridge_build_patterns_bundle();
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}
		update_option( 'gutenblock_bridge_pattern_bundle', $bundle, false );
	}
	return new WP_REST_Response( $bundle, 200 );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_patterns_bundle_rebuild( WP_REST_Request $request ) {
	unset( $request );
	$bundle = gutenblock_bridge_build_patterns_bundle();
	if ( is_wp_error( $bundle ) ) {
		return $bundle;
	}
	update_option( 'gutenblock_bridge_pattern_bundle', $bundle, false );
	return new WP_REST_Response( $bundle, 200 );
}

/**
 * Liest aus dem gemergten theme.json (Global Styles) die fontSize-Vorgaben
 * für H1–H3 sowie die Root-Fließtextgröße (für Absatz / Body in der Sidebar).
 *
 * @return array{h1: ?string, h2: ?string, h3: ?string, p: ?string}
 */
function gutenblock_bridge_get_semantic_font_sizes_from_theme() {
	$out = array(
		'h1' => null,
		'h2' => null,
		'h3' => null,
		'p'  => null,
	);
	if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		return $out;
	}
	$merged = WP_Theme_JSON_Resolver::get_merged_data();
	if ( ! $merged || ! is_callable( array( $merged, 'get_raw_data' ) ) ) {
		return $out;
	}
	$data = $merged->get_raw_data();
	if ( ! is_array( $data ) || empty( $data['styles'] ) || ! is_array( $data['styles'] ) ) {
		return $out;
	}
	$styles   = $data['styles'];
	$elements = ( isset( $styles['elements'] ) && is_array( $styles['elements'] ) ) ? $styles['elements'] : array();
	$pick_fs  = static function ( $elements, $el ) {
		if ( empty( $elements[ $el ]['typography']['fontSize'] ) ) {
			return null;
		}
		$fs = $elements[ $el ]['typography']['fontSize'];
		return is_string( $fs ) && $fs !== '' ? $fs : null;
	};
	foreach ( array( 'h1', 'h2', 'h3' ) as $tag ) {
		$out[ $tag ] = $pick_fs( $elements, $tag );
	}
	if ( ! empty( $styles['typography']['fontSize'] ) && is_string( $styles['typography']['fontSize'] ) ) {
		$out['p'] = $styles['typography']['fontSize'];
	}
	return $out;
}

/**
 * Baut ein statisches Bundle aus Pattern-Metadaten und gerendertem Pattern-HTML.
 * Dynamische Patterns werden in diesem MVP nicht speziell behandelt.
 *
 * @return array|WP_Error
 */
function gutenblock_bridge_build_patterns_bundle() {
	if ( ! class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
		return new WP_Error( 'plugin_inactive', 'GutenBlock Pro Pattern Loader ist nicht verfügbar.', array( 'status' => 503 ) );
	}

	$plugin_dir = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : WP_PLUGIN_DIR . '/gutenblock-pro/';
	$plugin_url = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : plugins_url( '/', __FILE__ );
	$ver        = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';

	gutenblock_bridge_prime_frontend_styles();

	$loader = new GutenBlock_Pro_Pattern_Loader();
	$loader->discover_patterns();
	$all = $loader->get_patterns();

	$patterns = array();
	foreach ( $all as $slug => $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$base_slug = sanitize_title( (string) $slug );
		if ( '' === $base_slug ) {
			continue;
		}
		$html_file = trailingslashit( $plugin_dir ) . 'patterns/' . $base_slug . '/content.html';
		if ( ! is_readable( $html_file ) ) {
			continue;
		}

		$markup = (string) file_get_contents( $html_file );
		$blocks = parse_blocks( $markup );
		$slots = array();
		gutenblock_bridge_walk_inject_text_slot_markers( $blocks, $slots );

		$html = '';
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) && trim( (string) ( $block['innerHTML'] ?? '' ) ) === '' ) {
				continue;
			}
			$html .= render_block( $block );
		}

		$extracted_content_fields = gutenblock_bridge_extract_pattern_content_fields( $base_slug );
		$content_fields           = ! empty( $extracted_content_fields )
			? $extracted_content_fields
			: ( isset( $p['content_fields'] ) && is_array( $p['content_fields'] ) ? $p['content_fields'] : array() );

		$pattern_dir = trailingslashit( $plugin_dir ) . 'patterns/' . $base_slug . '/';
		$pattern_url = trailingslashit( $plugin_url ) . 'patterns/' . $base_slug . '/';
		$style_file = $pattern_dir . 'style.css';
		$script_file = $pattern_dir . 'script.js';
		$editor_file = $pattern_dir . 'editor.css';

		$title = isset( $p['title'] ) ? $p['title'] : $base_slug;
		if ( is_array( $title ) ) {
			$title = isset( $title['rendered'] ) ? $title['rendered'] : $base_slug;
		}
		$desc = isset( $p['description'] ) ? $p['description'] : '';
		if ( is_array( $desc ) ) {
			$desc = isset( $desc['rendered'] ) ? $desc['rendered'] : '';
		}

		$patterns[] = array(
			'slug'           => $base_slug,
			'baseSlug'       => $base_slug,
			'type'           => isset( $p['type'] ) ? (string) $p['type'] : 'pattern',
			'title'          => wp_strip_all_tags( (string) $title ),
			'group'          => isset( $p['group'] ) ? (string) $p['group'] : '',
			'description'    => wp_strip_all_tags( (string) $desc ),
			'ai_hint'        => isset( $p['ai_hint'] ) ? (string) $p['ai_hint'] : '',
			'content_fields' => array_values( $content_fields ),
			'premium'        => ! empty( $p['premium'] ),
			'tones'          => isset( $p['tones'] ) && is_array( $p['tones'] ) ? array_values( $p['tones'] ) : array( 'neutral' ),
			'html'           => $html,
			'blockMarkup'    => $markup,
			'slots'          => array_values( $slots ),
			'cssInline'      => is_readable( $style_file ) ? (string) file_get_contents( $style_file ) : '',
			'editorCssInline'=> is_readable( $editor_file ) ? (string) file_get_contents( $editor_file ) : '',
			'jsInline'       => is_readable( $script_file ) ? (string) file_get_contents( $script_file ) : '',
			'cssHrefs'       => is_readable( $style_file ) ? array( $pattern_url . 'style.css?ver=' . rawurlencode( (string) $ver ) ) : array(),
			'jsHrefs'        => is_readable( $script_file ) ? array( $pattern_url . 'script.js?ver=' . rawurlencode( (string) $ver ) ) : array(),
		);
	}

	$globals           = gutenblock_bridge_collect_global_assets();
	$globals['inline'] .= "\n" . gutenblock_bridge_collect_inline_styles_after_render();

	return array(
		'schemaVersion'     => 'pattern-bundle-v1',
		'pluginVersion'     => (string) $ver,
		'builtAt'           => gmdate( 'c' ),
		'baseUrl'           => home_url(),
		'pluginUrl'         => $plugin_url,
		'globalCssHrefs'    => $globals['hrefs'],
		'globalInlineCss'   => $globals['inline'],
		'semanticFontSizes' => gutenblock_bridge_get_semantic_font_sizes_from_theme(),
		'groupOrder'        => gutenblock_bridge_rest_get_group_order()->get_data(),
		'patterns'          => $patterns,
	);
}

/**
 * REST: Löscht eine via /page/create erzeugte Sub-Page.
 *
 * Sicherheitsleitplanken:
 *   - nur Pages mit Slug-Präfix `gbp-` (vom Builder erzeugt)
 *   - kein Trash, sondern echtes force_delete (Builder-Pages sind wegwerfbar)
 *
 * Body:
 *   - page_slug : string  (Slug der zu löschenden Page)
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_page_delete( WP_REST_Request $request ) {
	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';

	if ( '' === $page_slug ) {
		return new WP_Error( 'missing_page_slug', 'page_slug ist erforderlich.', array( 'status' => 400 ) );
	}
	// Sicherheits-Leitplanke: nur Builder-Pages löschen.
	if ( strpos( $page_slug, 'gbp-' ) !== 0 ) {
		return new WP_Error( 'forbidden_slug', 'Nur Builder-Pages (Präfix gbp-) sind löschbar.', array( 'status' => 403 ) );
	}

	$page = get_page_by_path( $page_slug, OBJECT, 'page' );
	if ( ! $page || ! isset( $page->ID ) ) {
		// Idempotent: nicht-existente Page = ok.
		return new WP_REST_Response( array( 'deleted' => false, 'reason' => 'not_found' ), 200 );
	}

	$result = wp_delete_post( (int) $page->ID, true );
	if ( ! $result ) {
		return new WP_Error( 'delete_failed', 'Page konnte nicht gelöscht werden.', array( 'status' => 500 ) );
	}

	return new WP_REST_Response(
		array(
			'deleted'  => true,
			'pageId'   => (int) $page->ID,
			'pageSlug' => $page_slug,
		),
		200
	);
}

/**
 * REST: Löscht alle WordPress-Revisionen der angegebenen Pages.
 *
 * Wird nach jedem SaaS-Save aufgerufen, um die wp_posts-Tabelle zu entlasten
 * (jede SaaS-Action erzeugt potenziell mehrere Revisionen via wp_update_post).
 *
 * Body:
 *   - page_slugs : array<string>  (Liste der Page-Slugs)
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_page_clean_revisions( WP_REST_Request $request ) {
	$body  = $request->get_json_params();
	$slugs = isset( $body['page_slugs'] ) && is_array( $body['page_slugs'] ) ? $body['page_slugs'] : array();

	if ( empty( $slugs ) ) {
		return new WP_Error( 'missing_page_slugs', 'page_slugs (Array) ist erforderlich.', array( 'status' => 400 ) );
	}

	$results = array();
	$total   = 0;

	foreach ( $slugs as $raw_slug ) {
		$slug = sanitize_title( (string) $raw_slug );
		if ( '' === $slug ) {
			continue;
		}

		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page || ! isset( $page->ID ) ) {
			$results[] = array( 'slug' => $slug, 'deleted' => 0, 'reason' => 'not_found' );
			continue;
		}

		$revisions = wp_get_post_revisions( (int) $page->ID, array( 'posts_per_page' => -1 ) );
		$deleted   = 0;
		foreach ( $revisions as $revision ) {
			if ( wp_delete_post_revision( (int) $revision->ID ) ) {
				$deleted++;
			}
		}

		$total    += $deleted;
		$results[] = array(
			'slug'    => $slug,
			'pageId'  => (int) $page->ID,
			'deleted' => $deleted,
		);
	}

	return new WP_REST_Response(
		array(
			'totalDeleted' => $total,
			'pages'        => $results,
		),
		200
	);
}

add_action( 'rest_api_init', 'gutenblock_bridge_register_pattern_builder_api', 15 );

/**
 * REST: Tonalität einer bestehenden Section ändern, ohne Texte/Inhalte
 * neu zu laden. Tauscht nur Hintergrund- und Textfarben (Block-Attrs +
 * has-*-color Klassen) — Inhalte, Spalten, Bilder bleiben unverändert.
 *
 * Body:
 *   - page_slug : string  (slug der Page)
 *   - slug      : string  (base-slug der Section, ohne Tone-Suffix)
 *   - index     : int     (section-relativer Index)
 *   - tone      : 'neutral'|'dark'|'soft'
 */
function gutenblock_bridge_rest_section_retone( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) return $perm;

	if ( ! class_exists( 'GutenBlock_Pro_Tone_Injector' ) ) {
		return new WP_Error( 'no_tone_injector', 'Tone-Injector nicht verfügbar', array( 'status' => 500 ) );
	}

	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	$slug      = isset( $body['slug'] ) ? sanitize_title( (string) $body['slug'] ) : '';
	$idx_param = array_key_exists( 'index', $body ) ? (int) $body['index'] : null;
	$tone      = isset( $body['tone'] ) ? (string) $body['tone'] : 'neutral';

	if ( ! GutenBlock_Pro_Tone_Injector::is_valid_tone( $tone ) ) {
		return new WP_Error( 'invalid_tone', 'Ungültige Tonalität: ' . $tone, array( 'status' => 400 ) );
	}

	$loaded = gutenblock_bridge_load_page_sections( $page_slug );
	if ( is_wp_error( $loaded ) ) return $loaded;

	$idx = gutenblock_bridge_find_section_index( $loaded['sections'], $slug, $idx_param );
	if ( $idx < 0 ) {
		return new WP_Error( 'section_not_found', 'Section nicht gefunden', array( 'status' => 404 ) );
	}

	$blocks         = $loaded['blocks'];
	$section_block  = $blocks[ $idx ];
	$section_html   = serialize_block( $section_block );

	$new_html = GutenBlock_Pro_Tone_Injector::apply( $section_html, $tone );

	$new_blocks = parse_blocks( $new_html );
	if ( empty( $new_blocks ) ) {
		return new WP_Error( 'parse_failed', 'Section konnte nach Re-Tone nicht geparst werden', array( 'status' => 500 ) );
	}

	array_splice( $blocks, $idx, 1, $new_blocks );

	$saved = gutenblock_bridge_save_page_blocks( $loaded['post'], $blocks );
	if ( is_wp_error( $saved ) ) return $saved;

	return new WP_REST_Response(
		array(
			'changed' => true,
			'index'   => $idx,
			'tone'    => $tone,
		),
		200
	);
}

/**
 * Errät den Pattern-Slug (z. B. hero-v1) aus einem Block (className / metadata).
 *
 * @param array $block Parsed block.
 * @return string Leerstring wenn nicht ermittelbar.
 */
function gutenblock_bridge_guess_pattern_slug_from_block( array $block ): string {
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	if ( preg_match( '/gb-pattern-([a-z0-9-]+)/', $class, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/gb-section-([a-z0-9-]+)/', $class, $m ) ) {
		return $m[1];
	}
	if ( ! empty( $block['attrs']['metadata']['patternSlug'] ) ) {
		return sanitize_title( (string) $block['attrs']['metadata']['patternSlug'] );
	}
	return '';
}

/**
 * REST: Roh-Markup (serialisierte Blocks) einer Page für SaaS-Manifest / Wizard.
 *
 * GET …/page/block-markup?page_slug=...
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_page_block_markup( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) {
		return $perm;
	}

	$page_slug = $request->get_param( 'page_slug' );
	$page_slug = sanitize_title( (string) $page_slug );
	if ( '' === $page_slug ) {
		return new WP_Error( 'missing_page_slug', 'page_slug ist erforderlich.', array( 'status' => 400 ) );
	}

	$page = get_page_by_path( $page_slug, OBJECT, 'page' );
	if ( ! $page || ! isset( $page->ID ) ) {
		return new WP_Error( 'not_found', 'Seite nicht gefunden: ' . $page_slug, array( 'status' => 404 ) );
	}

	return new WP_REST_Response(
		array(
			'blockMarkup' => $page->post_content,
			'title'       => get_the_title( $page ),
			'slug'        => $page->post_name,
		),
		200
	);
}

/**
 * REST: Top-Level-Blocks als gerendertes HTML + Pattern-Asset-URLs (Headless-Preview).
 *
 * Body: { "page_slug": "…" }
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function gutenblock_bridge_rest_page_render_sections( WP_REST_Request $request ) {
	$perm = gutenblock_bridge_assemble_permission_callback();
	if ( is_wp_error( $perm ) ) {
		return $perm;
	}

	$body      = $request->get_json_params();
	$page_slug = isset( $body['page_slug'] ) ? sanitize_title( (string) $body['page_slug'] ) : '';
	if ( '' === $page_slug ) {
		return new WP_Error( 'missing_page_slug', 'page_slug ist erforderlich.', array( 'status' => 400 ) );
	}

	$page = get_page_by_path( $page_slug, OBJECT, 'page' );
	if ( ! $page || ! isset( $page->ID ) ) {
		return new WP_Error( 'not_found', 'Seite nicht gefunden: ' . $page_slug, array( 'status' => 404 ) );
	}

	gutenblock_bridge_prime_frontend_styles();

	$raw       = $page->post_content;
	$all       = parse_blocks( $raw );
	$sections  = array();
	$ver       = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';
	$base_path = defined( 'GUTENBLOCK_PRO_PATH' ) ? GUTENBLOCK_PRO_PATH : '';
	$base_url  = defined( 'GUTENBLOCK_PRO_URL' ) ? GUTENBLOCK_PRO_URL : '';

	// `data-gb-text-key`-Marker an alle Text-Slots heften, damit der SaaS
	// Texte clientseitig per Marker-Selector austauschen kann (kein
	// WP-Roundtrip pro Edit). `slots` bleibt hier ungenutzt — die Section
	// liefert sie nicht zurück, das passiert über /pattern/{slug}/template.
	$marker_slots = array();
	gutenblock_bridge_walk_inject_text_slot_markers( $all, $marker_slots );

	$index = 0;
	foreach ( $all as $block ) {
		if ( empty( $block['blockName'] ) ) {
			continue;
		}
		$html = render_block( $block );
		$slug = gutenblock_bridge_guess_pattern_slug_from_block( $block );
		$css  = array();
		$js   = array();
		if ( $slug && $base_path && is_file( $base_path . 'patterns/' . $slug . '/style.css' ) ) {
			$css[] = $base_url . 'patterns/' . $slug . '/style.css?ver=' . rawurlencode( (string) $ver );
		}
		if ( $slug && $base_path && is_file( $base_path . 'patterns/' . $slug . '/script.js' ) ) {
			$js[] = $base_url . 'patterns/' . $slug . '/script.js?ver=' . rawurlencode( (string) $ver );
		}
		$sections[] = array(
			'index'    => $index,
			'slug'     => $slug,
			'html'     => $html,
			'cssHrefs' => $css,
			'jsHrefs'  => $js,
		);
		++$index;
	}

	$globals = gutenblock_bridge_collect_global_assets();
	$globals['inline'] .= "\n" . gutenblock_bridge_collect_inline_styles_after_render();

	return new WP_REST_Response(
		array(
			'pageSlug'        => $page_slug,
			'title'           => get_the_title( $page ),
			'sections'        => $sections,
			'globalCssHrefs'  => $globals['hrefs'],
			'globalInlineCss' => $globals['inline'],
		),
		200
	);
}

/**
 * Sammelt während `render_block()` per `wp_add_inline_style()` registrierte
 * Layout-/Block-Supports-Styles (z.B. `.wp-container-…` für Spalten/Grids).
 *
 * @return string
 */
function gutenblock_bridge_collect_inline_styles_after_render(): string {
	if ( ! function_exists( 'wp_styles' ) ) {
		return '';
	}
	$wp_styles = wp_styles();
	$css       = '';
	$handles   = array(
		'core-block-supports',
		'wp-block-library',
		'wp-block-library-theme',
		'global-styles',
	);
	foreach ( $handles as $handle ) {
		$after = $wp_styles->get_data( $handle, 'after' );
		if ( is_array( $after ) && ! empty( $after ) ) {
			$css .= "\n/* inline-after:" . $handle . " */\n" . implode( "\n", array_filter( $after, 'is_string' ) );
		}
	}

	// WP-Style-Engine: pro-Instanz-Layout-CSS für `is-layout-grid` / -flex /
	// -constrained — z.B. `.wp-container-core-group-is-layout-XXX {
	// grid-template-columns: repeat(4, minmax(0,1fr)); }`. Diese Regeln
	// werden während `render_block` registriert, aber im REST-Kontext NICHT
	// automatisch via `wp_styles()->after` ausgespielt — ohne sie hat das
	// Grid keine Spaltenanzahl und kollabiert auf 1 Spalte.
	if ( function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
		// Kontexte, in denen WP/Gutenberg ihre dynamisch erzeugten Block-Styles
		// ablegen. `block-supports` ist der Layout-Engine-Speicher; weitere
		// Kontexte (z.B. einzelne Blöcke) ergänzen wir defensiv.
		$contexts = array( 'block-supports' );
		foreach ( $contexts as $context ) {
			$engine_css = wp_style_engine_get_stylesheet_from_context(
				$context,
				array( 'prettify' => false )
			);
			if ( is_string( $engine_css ) && '' !== trim( $engine_css ) ) {
				$css .= "\n/* style-engine:" . $context . " */\n" . $engine_css;
			}
		}
	}

	return $css;
}

/**
 * Stellt sicher, dass alle Frontend-Styles registriert/enqueued sind, damit
 * sie nach dem Render im SaaS gesammelt werden können (REST-Kontext fired
 * `wp_enqueue_scripts` nicht automatisch).
 */
function gutenblock_bridge_prime_frontend_styles(): void {
	static $primed = false;
	if ( $primed ) {
		return;
	}
	$primed = true;

	if ( ! did_action( 'wp_enqueue_scripts' ) ) {
		do_action( 'wp_enqueue_scripts' );
	}
	if ( function_exists( 'wp_enqueue_global_styles' ) ) {
		wp_enqueue_global_styles();
	}
	// Fallback für ältere WP-Versionen.
	if ( function_exists( 'wp_common_block_scripts_and_styles' ) ) {
		wp_common_block_scripts_and_styles();
	}

	// WICHTIG: In REST-API-Requests (z.B. Pattern Bundle Build) ist
	// wp_is_json_request() true. WP überspringt dann in wp_render_layout_support_flag
	// oft das Erzeugen der wp-container-* Layout-Klassen für Block-Gaps!
	// Dies erzwingt, dass die Layout-Klassen dennoch generiert werden.
	add_filter( 'should_load_separate_core_block_assets', '__return_true' );

	// Hack für WP < 6.6: Manchmal reicht der Filter nicht, da wp_is_json_request() hart prüft.
	// Wir maskieren kurzzeitig die JSON-Header, damit wp_is_json_request() false liefert
	// während die Blöcke gerendert werden. Wir können das am Ende des Requests wiederherstellen,
	// oder einfach für den Rest der PHP-Ausführung maskiert lassen, da WP-REST den Request
	// schon fertig geparst hat.
	if ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'application/json' ) !== false ) {
		$_SERVER['HTTP_ACCEPT'] = '*/*';
	}
	if ( isset( $_SERVER['CONTENT_TYPE'] ) && strpos( $_SERVER['CONTENT_TYPE'], 'application/json' ) !== false ) {
		$_SERVER['CONTENT_TYPE'] = 'text/html';
	}

	// Lazy-Loading abschalten: Beim 2-Finger-Scroll im pan/zoom-Canvas
	// triggert die Browser-Heuristik "Bild kommt jetzt in den Viewport" →
	// Bild lädt nach → Section wächst um die echte Bildhöhe → Page-Layout
	// shiftet sichtbar. Ohne Lazy laden alle Bilder einmalig beim Mount.
	add_filter( 'wp_lazy_loading_enabled', '__return_false' );
	add_filter( 'wp_img_tag_add_loading_attr', '__return_empty_string' );
	add_filter( 'wp_omit_loading_attr_threshold', '__return_zero' );
}

/**
 * Sammelt für die Headless-Vorschau die WP-Core/Theme/Theme-JSON-Stylesheets.
 *
 * @return array{hrefs:string[], inline:string}
 */
function gutenblock_bridge_collect_global_assets(): array {
	$hrefs  = array();
	$inline = '';

	if ( function_exists( 'includes_url' ) ) {
		$hrefs[] = includes_url( 'css/dist/block-library/style.min.css' );
		$hrefs[] = includes_url( 'css/dist/block-library/theme.min.css' );
	}

	if ( function_exists( 'wp_get_global_stylesheet' ) ) {
		$inline .= "\n/* wp_get_global_stylesheet */\n" . wp_get_global_stylesheet();
	}
	if ( function_exists( 'wp_get_global_styles_svg_filters' ) ) {
		$inline .= "\n" . wp_get_global_styles_svg_filters();
	}

	if ( function_exists( 'get_stylesheet_directory_uri' ) ) {
		$theme_dir  = get_stylesheet_directory();
		$theme_uri  = get_stylesheet_directory_uri();
		$candidates = array( 'style.css', 'assets/style.css', 'build/style.css' );
		foreach ( $candidates as $rel ) {
			if ( is_file( trailingslashit( $theme_dir ) . $rel ) ) {
				$hrefs[] = trailingslashit( $theme_uri ) . $rel;
				break;
			}
		}
	}

	// Alle vom Plugin/Theme registrierten Style-Handles aufsammeln (Block-
	// Variant-CSS via `wp_enqueue_block_style`, Mobile-Align-Inline, Pattern-
	// Files via Asset-Loader, Custom-Block-CSS …). Damit landet auch
	// `is-style-vertical-center` und `gbp-mobile-left` im SaaS-Render.
	$wp_styles = function_exists( 'wp_styles' ) ? wp_styles() : null;
	if ( $wp_styles && ! empty( $wp_styles->registered ) ) {
		foreach ( $wp_styles->registered as $handle => $style ) {
			$src = isset( $style->src ) ? (string) $style->src : '';
			if ( '' !== $src && gutenblock_bridge_should_inline_style_src( $src, (string) $handle ) ) {
				$ver = isset( $style->ver ) && $style->ver ? (string) $style->ver : '';
				$abs = gutenblock_bridge_resolve_style_src( $src );
				if ( '' !== $abs ) {
					$hrefs[] = $ver ? add_query_arg( 'ver', $ver, $abs ) : $abs;
				}
			}

			$extra_after  = $wp_styles->get_data( $handle, 'after' );
			$extra_before = $wp_styles->get_data( $handle, 'before' );
			if ( is_array( $extra_before ) && ! empty( $extra_before ) ) {
				$inline .= "\n/* inline-before:" . $handle . " */\n" . implode( "\n", array_filter( $extra_before, 'is_string' ) );
			}
			if ( is_array( $extra_after ) && ! empty( $extra_after ) ) {
				$inline .= "\n/* inline-after:" . $handle . " */\n" . implode( "\n", array_filter( $extra_after, 'is_string' ) );
			}

			// Inline-CSS, das per `wp_enqueue_block_style` mit `path` registriert ist:
			if ( ! empty( $style->extra['path'] ) && is_string( $style->extra['path'] ) && is_file( $style->extra['path'] ) ) {
				// Kleine Files (<200 KB) inlinen, größere als URL-Link verlinken.
				$size = (int) @filesize( $style->extra['path'] );
				if ( $size > 0 && $size <= 204800 ) {
					$css = (string) @file_get_contents( $style->extra['path'] );
					if ( '' !== $css ) {
						$inline .= "\n/* file:" . $handle . " */\n" . $css;
					}
				} elseif ( '' !== $src ) {
					$abs = gutenblock_bridge_resolve_style_src( $src );
					if ( '' !== $abs ) {
						$hrefs[] = $abs;
					}
				}
			}
		}
	}

	return array(
		'hrefs'  => array_values( array_unique( array_filter( $hrefs ) ) ),
		'inline' => $inline,
	);
}

/**
 * Filter: nur frontend-relevante Style-Handles in den SaaS-Render mitnehmen.
 *
 * @param string $src    URL.
 * @param string $handle Handle-Name.
 * @return bool
 */
function gutenblock_bridge_should_inline_style_src( string $src, string $handle ): bool {
	if ( '' === $src ) {
		return false;
	}
	$skip_handles = array(
		'admin-bar',
		'admin-menu',
		'wp-admin',
		'login',
		'wp-edit-post',
		'wp-edit-blocks',
		'wp-block-editor',
		'wp-components',
		'dashicons',
		'wp-pointer',
		'jquery-ui',
	);
	foreach ( $skip_handles as $h ) {
		if ( $handle === $h || 0 === strpos( $handle, $h . '-' ) ) {
			return false;
		}
	}
	if ( false !== strpos( $src, '/wp-admin/' ) ) {
		return false;
	}
	return true;
}

/**
 * Macht aus einem ggf. relativen Style-`src` eine absolute URL.
 */
function gutenblock_bridge_resolve_style_src( string $src ): string {
	if ( '' === $src ) {
		return '';
	}
	if ( 0 === strpos( $src, '//' ) ) {
		return ( is_ssl() ? 'https:' : 'http:' ) . $src;
	}
	if ( 0 === strpos( $src, 'http://' ) || 0 === strpos( $src, 'https://' ) ) {
		return $src;
	}
	if ( 0 === strpos( $src, '/' ) ) {
		return site_url( $src );
	}
	return $src;
}
