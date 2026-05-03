<?php
/**
 * Tone Injector – injiziert Hintergrundfarbe + Textfarbe in das Top-Level-Group-
 * oder Cover-Block eines Patterns, ohne content.html zu verändern.
 *
 * Drei Tonalitäten:
 *   neutral – kein Container-Hintergrund (Default-Theme)
 *   dark    – Hintergrund "contrast", Text "base"
 *   soft    – Hintergrund "tertiary", Text "contrast"
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Tone_Injector {

	/**
	 * Bekannte Tonalitäten und ihre Block-Attribut-Werte.
	 *
	 * @var array<string,array>
	 */
	const TONES = array(
		'neutral' => array(
			'label'           => 'Neutral',
			'backgroundColor' => null,
			'textColor'       => null,
		),
		'dark' => array(
			'label'           => 'Dark',
			'backgroundColor' => 'contrast',
			'textColor'       => 'base',
		),
		'soft' => array(
			'label'           => 'Soft',
			'backgroundColor' => 'tertiary',
			'textColor'       => 'contrast',
		),
	);

	/**
	 * Prüft, ob eine Tonalität gültig ist.
	 *
	 * @param string $tone
	 * @return bool
	 */
	public static function is_valid_tone( $tone ) {
		return isset( self::TONES[ $tone ] );
	}

	/**
	 * Gibt alle Tonalitäten zurück.
	 *
	 * @return array
	 */
	public static function all_tones() {
		return array_keys( self::TONES );
	}

	/**
	 * Gibt die Label-Map zurück (tone => label).
	 *
	 * @return array
	 */
	public static function tone_labels() {
		$out = array();
		foreach ( self::TONES as $key => $cfg ) {
			$out[ $key ] = $cfg['label'];
		}
		return $out;
	}

	/**
	 * Injiziert Ton-Hintergrund + Textfarbe in den ersten Top-Level-Block.
	 *
	 * Zwei Regex-Passes auf dem rohen Block-Markup:
	 * (1) Block-Kommentar-JSON aktualisieren (backgroundColor, textColor),
	 * (2) CSS-Klassen am ersten HTML-Container-Element ergänzen.
	 *
	 * @param string $content  Block-Markup (HTML + wp-Kommentare).
	 * @param string $tone     Tonalitäts-Schlüssel ('neutral'|'dark'|'soft').
	 * @return string
	 */
	public static function inject( $content, $tone ) {
		if ( ! self::is_valid_tone( $tone ) || $tone === 'neutral' ) {
			return $content;
		}

		$cfg = self::TONES[ $tone ];

		// Pass 1: Ersten Block-Kommentar auffinden und JSON-Attrs erweitern.
		// Greedy-Match (\{.+\}) findet das korrekte schließende } auch bei
		// verschachteltem JSON (z.B. "metadata":{"name":"…"}).
		$content = preg_replace_callback(
			'/(<!-- wp:\S+ )(\{.+\})(\s*-->)/',
			function ( $m ) use ( $cfg ) {
				$attrs = json_decode( $m[2], true );
				if ( ! is_array( $attrs ) ) {
					return $m[0];
				}
				if ( ! is_null( $cfg['backgroundColor'] ) ) {
					$attrs['backgroundColor'] = $cfg['backgroundColor'];
				}
				if ( ! is_null( $cfg['textColor'] ) ) {
					$attrs['textColor'] = $cfg['textColor'];
				}
				$json = function_exists( 'wp_json_encode' )
					? wp_json_encode( $attrs )
					: json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				return false !== $json ? $m[1] . $json . $m[3] : $m[0];
			},
			$content,
			1 // Nur ersten Kommentar
		);

		// Pass 2: CSS-Klassen am ersten HTML-Container-Element setzen.
		if ( ! is_null( $cfg['backgroundColor'] ) ) {
			$content = self::set_html_classes(
				$content,
				// Klassen entfernen: hat-*-background-color und has-background
				'has-[a-z0-9-]+-background-color',
				array( 'has-background' ),
				// Klassen hinzufügen
				array( 'has-' . $cfg['backgroundColor'] . '-background-color', 'has-background' )
			);
		}

		if ( ! is_null( $cfg['textColor'] ) ) {
			$content = self::set_html_classes(
				$content,
				// Klassen entfernen: has-*-color ABER NICHT has-*-background-color
				// Negativer Lookbehind verhindert, dass background-color-Klassen mitgelöscht werden.
				'has-[a-z0-9-]+(?<!background)-color',
				array( 'has-text-color' ),
				array( 'has-' . $cfg['textColor'] . '-color', 'has-text-color' )
			);
		}

		return $content;
	}

	/**
	 * Entfernt sämtliche Tone-relevanten Klassen + JSON-Attribute aus dem Block.
	 * Nützlich, um vor dem (Re-)inject einen sauberen Ausgangszustand herzustellen.
	 *
	 * @param string $content Block-Markup.
	 * @return string
	 */
	public static function clean( $content ) {
		// Pass 1: backgroundColor / textColor aus erstem Block-Kommentar entfernen.
		$content = preg_replace_callback(
			'/(<!-- wp:\S+ )(\{.+\})(\s*-->)/',
			function ( $m ) {
				$attrs = json_decode( $m[2], true );
				if ( ! is_array( $attrs ) ) {
					return $m[0];
				}
				unset( $attrs['backgroundColor'], $attrs['textColor'] );
				$json = function_exists( 'wp_json_encode' )
					? wp_json_encode( $attrs )
					: json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				return false !== $json ? $m[1] . $json . $m[3] : $m[0];
			},
			$content,
			1
		);

		// Pass 2: HTML-Klassen `has-*-background-color`, `has-*-color`,
		// `has-background`, `has-text-color` am ersten Container entfernen.
		$content = self::set_html_classes(
			$content,
			'(?:has-[a-z0-9-]+-background-color|has-[a-z0-9-]+(?<!background)-color)',
			array( 'has-background', 'has-text-color' ),
			array()
		);

		return $content;
	}

	/**
	 * Sauberes Setzen einer Tonalität: Erst clean(), dann inject() (außer neutral).
	 * Idempotent: kann beliebig oft auf bereits getontem Content angewendet werden.
	 *
	 * @param string $content Block-Markup.
	 * @param string $tone    'neutral' | 'dark' | 'soft'.
	 * @return string
	 */
	public static function apply( $content, $tone ) {
		if ( ! self::is_valid_tone( $tone ) ) {
			return $content;
		}
		$content = self::clean( $content );
		if ( $tone !== 'neutral' ) {
			$content = self::inject( $content, $tone );
		}
		return $content;
	}

	/**
	 * Entfernt und ergänzt CSS-Klassen am ersten HTML-Block-Container.
	 *
	 * @param string   $content           Block-Markup.
	 * @param string   $remove_pattern    Regex-Teilmuster für Klassen, die entfernt werden (kein Delimiter).
	 * @param string[] $remove_exact      Exakte Klassen-Strings, die ebenfalls entfernt werden.
	 * @param string[] $add_classes       Klassen, die hinzugefügt werden sollen.
	 * @return string
	 */
	private static function set_html_classes( $content, $remove_pattern, array $remove_exact, array $add_classes ) {
		return preg_replace_callback(
			'/(<(?:section|div|article|aside|header|footer)\b[^>]*\bclass=")([^"]*)"/',
			function ( $m ) use ( $remove_pattern, $remove_exact, $add_classes ) {
				$existing = array_filter( explode( ' ', $m[2] ) );
				$existing = array_values( array_filter( $existing, function ( $c ) use ( $remove_pattern, $remove_exact ) {
					if ( in_array( $c, $remove_exact, true ) ) {
						return false;
					}
					return ! preg_match( '/^(?:' . $remove_pattern . ')$/', $c );
				} ) );
				foreach ( $add_classes as $c ) {
					if ( ! in_array( $c, $existing, true ) ) {
						$existing[] = $c;
					}
				}
				return $m[1] . implode( ' ', $existing ) . '"';
			},
			$content,
			1
		);
	}

	/**
	 * Prüft, ob sinnvoll Tone-Varianten für diesen Pattern-Inhalt erzeugt werden können.
	 *
	 * Nicht möglich, wenn der Top-Level-Block:
	 *  - core/cover ist (Hintergrund ist Bild/Video, Tone-Farbe wirkungslos)
	 *  - eine backgroundImage/Url im JSON setzt
	 *  - einen Gradient als Hintergrund definiert
	 *
	 * @param string $content  Block-Markup.
	 * @return array { 'supported' => bool, 'reason' => string }
	 */
	public static function detect_tone_capability( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return array( 'supported' => false, 'reason' => 'empty' );
		}

		// Ersten Block-Kommentar finden
		if ( ! preg_match( '/<!-- wp:(\S+)\s+(\{.+\})\s*-->/', $content, $m ) ) {
			// Kein Block-Kommentar mit Attrs → mit nackter Group → ok
			if ( preg_match( '/<!-- wp:(group|columns|column)\s*-->/', $content ) ) {
				return array( 'supported' => true, 'reason' => 'simple-group' );
			}
			return array( 'supported' => true, 'reason' => 'no-attrs' );
		}

		$block_name = $m[1];
		$attrs      = json_decode( $m[2], true );

		if ( $block_name === 'core/cover' ) {
			return array( 'supported' => false, 'reason' => 'cover-block' );
		}

		if ( ! is_array( $attrs ) ) {
			return array( 'supported' => true, 'reason' => 'unparseable-attrs' );
		}

		// Background image / URL
		if ( ! empty( $attrs['backgroundImage'] ) || ! empty( $attrs['url'] ) ) {
			return array( 'supported' => false, 'reason' => 'background-image' );
		}

		// Style.background.backgroundImage (theme.json-Stil)
		if ( isset( $attrs['style']['background']['backgroundImage'] ) ) {
			return array( 'supported' => false, 'reason' => 'style-background-image' );
		}

		// Gradient
		if ( isset( $attrs['gradient'] ) || isset( $attrs['style']['color']['gradient'] ) ) {
			return array( 'supported' => false, 'reason' => 'gradient' );
		}

		return array( 'supported' => true, 'reason' => 'ok' );
	}

	/**
	 * Liefert das CSS, das Children eines tone-getonten Containers
	 * (`.has-contrast-background-color`, `.has-tertiary-background-color`)
	 * zwingt, kontextuell zur Container-Farbe zu rendern:
	 *
	 *   - Material-Icons (SVG-Pfade): fill: currentColor
	 *   - Trenner / hr: border + Hintergrund: currentColor
	 *   - Outline-Buttons: text + border = currentColor, BG transparent
	 *
	 * Filled-Buttons werden nicht angefasst (Theme-Default bleibt aktiv).
	 *
	 * @return string CSS (ohne <style>-Tags).
	 */
	public static function build_tone_inheritance_css() {
		return implode( "\n", array(
			'/* GutenBlock Pro: Tone-aware Inheritance für Material-Icons, Trenner, Outline-Buttons */',

			// Material-Icons → currentColor
			'.has-contrast-background-color .wp-block-gutenblock-pro-material-icon svg,',
			'.has-contrast-background-color .wp-block-gutenblock-pro-material-icon svg *,',
			'.has-tertiary-background-color .wp-block-gutenblock-pro-material-icon svg,',
			'.has-tertiary-background-color .wp-block-gutenblock-pro-material-icon svg * {',
			'  fill: currentColor !important;',
			'}',

			// Trenner / Separator
			'.has-contrast-background-color hr.wp-block-separator,',
			'.has-contrast-background-color .wp-block-separator,',
			'.has-tertiary-background-color hr.wp-block-separator,',
			'.has-tertiary-background-color .wp-block-separator {',
			'  border-color: currentColor !important;',
			'  background-color: currentColor !important;',
			'  color: currentColor !important;',
			'  opacity: 0.5;',
			'}',

			// Outline-Buttons
			'.has-contrast-background-color .wp-block-button.is-style-outline > .wp-block-button__link,',
			'.has-tertiary-background-color .wp-block-button.is-style-outline > .wp-block-button__link {',
			'  color: currentColor !important;',
			'  border-color: currentColor !important;',
			'  background-color: transparent !important;',
			'}',

			'/* Soft-Wrapper innerhalb eines Dark-Containers: Invertierung aufheben.',
			'   currentColor erbt sonst die helle (base) Farbe der übergeordneten Dark-Section. */',

			// Text-Farbe zurücksetzen
			'.has-contrast-background-color .has-tertiary-background-color {',
			'  color: var(--wp--preset--color--contrast) !important;',
			'}',

			// Material-Icons im Soft-Wrapper → contrast (nicht base)
			'.has-contrast-background-color .has-tertiary-background-color .wp-block-gutenblock-pro-material-icon svg,',
			'.has-contrast-background-color .has-tertiary-background-color .wp-block-gutenblock-pro-material-icon svg * {',
			'  fill: var(--wp--preset--color--contrast) !important;',
			'}',

			// Trenner im Soft-Wrapper → contrast
			'.has-contrast-background-color .has-tertiary-background-color hr.wp-block-separator,',
			'.has-contrast-background-color .has-tertiary-background-color .wp-block-separator {',
			'  border-color: var(--wp--preset--color--contrast) !important;',
			'  background-color: var(--wp--preset--color--contrast) !important;',
			'  color: var(--wp--preset--color--contrast) !important;',
			'}',

			// Outline-Buttons im Soft-Wrapper → contrast
			'.has-contrast-background-color .has-tertiary-background-color .wp-block-button.is-style-outline > .wp-block-button__link {',
			'  color: var(--wp--preset--color--contrast) !important;',
			'  border-color: var(--wp--preset--color--contrast) !important;',
			'}',
		) );
	}

	/**
	 * Registriert das Frontend-Stylesheet (lädt überall — Frontend, FSE, Editor-Iframe).
	 * Hooked an wp_enqueue_scripts und enqueue_block_assets.
	 */
	public static function enqueue_tone_styles() {
		$handle = 'gutenblock-pro-tone-inheritance';
		wp_register_style( $handle, false, array(), GUTENBLOCK_PRO_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, self::build_tone_inheritance_css() );
	}

	/**
	 * Erzeugt den virtuellen Pattern-Slug für eine Tonalität.
	 *
	 * @param string $base_slug
	 * @param string $tone
	 * @return string
	 */
	public static function tone_slug( $base_slug, $tone ) {
		return $tone === 'neutral' ? $base_slug : $base_slug . '--' . $tone;
	}

	/**
	 * Extrahiert den Base-Slug und den Tone aus einem virtuellen Slug.
	 *
	 * @param string $slug  Z. B. "hero-v1--dark"
	 * @return array{base: string, tone: string}
	 */
	public static function parse_slug( $slug ) {
		if ( preg_match( '/^(.+)--(neutral|dark|soft)$/', $slug, $m ) ) {
			return array( 'base' => $m[1], 'tone' => $m[2] );
		}
		return array( 'base' => $slug, 'tone' => 'neutral' );
	}
}

// Frontend (FSE-Frontend, Theme-Frontend, Editor-Preview-Iframe).
add_action( 'wp_enqueue_scripts', array( 'GutenBlock_Pro_Tone_Injector', 'enqueue_tone_styles' ) );
// Editor-Iframe (Block-Editor / FSE).
add_action( 'enqueue_block_assets', array( 'GutenBlock_Pro_Tone_Injector', 'enqueue_tone_styles' ) );
