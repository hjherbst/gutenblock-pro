<?php
/**
 * Translation Settings Page – Toggle target languages for AI translation.
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Translation_Settings {

	const OPTION_NAME = 'gutenblock_pro_translate_languages';

	/**
	 * All available languages: code => array( label, promptLang ).
	 *
	 * `label` is the language name in the admin UI and follows the WordPress
	 * site language: German label on de_*, English label otherwise. `promptLang`
	 * stays German because the editor instruction is composed in German for the
	 * OpenAI call. `translateAll` is the button label shown in the editor
	 * sidebar and is always written in the TARGET language so users immediately
	 * recognise it in context.
	 *
	 * @return array
	 */
	public static function get_available_languages() {
		$site_is_german = self::site_is_german();
		$labels         = $site_is_german
			? array(
				'de' => 'Deutsch',     'en' => 'Englisch',      'fr' => 'Französisch',
				'es' => 'Spanisch',    'it' => 'Italienisch',   'pt' => 'Portugiesisch',
				'nl' => 'Niederländisch', 'pl' => 'Polnisch',  'cs' => 'Tschechisch',
				'hu' => 'Ungarisch',   'ro' => 'Rumänisch',     'da' => 'Dänisch',
				'sv' => 'Schwedisch',  'no' => 'Norwegisch',    'fi' => 'Finnisch',
				'ru' => 'Russisch',    'tr' => 'Türkisch',      'ja' => 'Japanisch',
				'zh' => 'Chinesisch',
			)
			: array(
				'de' => 'German',      'en' => 'English',       'fr' => 'French',
				'es' => 'Spanish',     'it' => 'Italian',       'pt' => 'Portuguese',
				'nl' => 'Dutch',       'pl' => 'Polish',        'cs' => 'Czech',
				'hu' => 'Hungarian',   'ro' => 'Romanian',      'da' => 'Danish',
				'sv' => 'Swedish',     'no' => 'Norwegian',     'fi' => 'Finnish',
				'ru' => 'Russian',     'tr' => 'Turkish',       'ja' => 'Japanese',
				'zh' => 'Chinese',
			);

		$meta = array(
			'de' => array( 'promptLang' => 'ins Deutsche',        'translateAll' => 'Alles übersetzen' ),
			'en' => array( 'promptLang' => 'ins Englische',       'translateAll' => 'Translate all' ),
			'fr' => array( 'promptLang' => 'ins Französische',    'translateAll' => 'Traduire tout' ),
			'es' => array( 'promptLang' => 'ins Spanische',       'translateAll' => 'Traducir todo' ),
			'it' => array( 'promptLang' => 'ins Italienische',    'translateAll' => 'Traduci tutto' ),
			'pt' => array( 'promptLang' => 'ins Portugiesische',  'translateAll' => 'Traduzir tudo' ),
			'nl' => array( 'promptLang' => 'ins Niederländische', 'translateAll' => 'Alles vertalen' ),
			'pl' => array( 'promptLang' => 'ins Polnische',       'translateAll' => 'Przetłumacz wszystko' ),
			'cs' => array( 'promptLang' => 'ins Tschechische',    'translateAll' => 'Přeložit vše' ),
			'hu' => array( 'promptLang' => 'ins Ungarische',      'translateAll' => 'Minden fordítása' ),
			'ro' => array( 'promptLang' => 'ins Rumänische',      'translateAll' => 'Traduce tot' ),
			'da' => array( 'promptLang' => 'ins Dänische',        'translateAll' => 'Oversæt alt' ),
			'sv' => array( 'promptLang' => 'ins Schwedische',     'translateAll' => 'Översätt allt' ),
			'no' => array( 'promptLang' => 'ins Norwegische',     'translateAll' => 'Oversett alt' ),
			'fi' => array( 'promptLang' => 'ins Finnische',       'translateAll' => 'Käännä kaikki' ),
			'ru' => array( 'promptLang' => 'ins Russische',       'translateAll' => 'Перевести всё' ),
			'tr' => array( 'promptLang' => 'ins Türkische',       'translateAll' => 'Tümünü çevir' ),
			'ja' => array( 'promptLang' => 'ins Japanische',      'translateAll' => 'すべて翻訳' ),
			'zh' => array( 'promptLang' => 'ins Chinesische',     'translateAll' => '翻译全部' ),
		);

		$out = array();
		foreach ( $meta as $code => $row ) {
			$out[ $code ] = array(
				'label'        => $labels[ $code ],
				'promptLang'   => $row['promptLang'],
				'translateAll' => $row['translateAll'],
			);
		}
		return $out;
	}

	/**
	 * Whether the WordPress site language is German.
	 *
	 * @return bool
	 */
	private static function site_is_german(): bool {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
		return 0 === strpos( $locale, 'de' );
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting(
			'gutenblock_pro_translations',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_languages' ),
			)
		);
	}

	/**
	 * Only allow known language codes.
	 *
	 * @param mixed $value Raw POST value.
	 * @return array
	 */
	public function sanitize_languages( $value ) {
		$allowed = array_keys( self::get_available_languages() );
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $allowed as $code ) {
			$out[ $code ] = ! empty( $value[ $code ] );
		}
		return $out;
	}

	/**
	 * Get enabled language codes with metadata for the editor.
	 *
	 * @return array Array of { code, label, promptLang }.
	 */
	public static function get_enabled_languages() {
		$saved     = get_option( self::OPTION_NAME, array() );
		$available = self::get_available_languages();
		$enabled   = array();
		foreach ( $available as $code => $meta ) {
			if ( ! empty( $saved[ $code ] ) ) {
				$enabled[] = array(
					'code'         => $code,
					'label'        => strtoupper( $code ),
					'promptLang'   => $meta['promptLang'],
					'translateAll' => $meta['translateAll'],
				);
			}
		}
		return $enabled;
	}
}
