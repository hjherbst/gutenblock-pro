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
	 * @return array
	 */
	public static function get_available_languages() {
		return array(
			'de' => array( 'label' => 'Deutsch',         'promptLang' => 'ins Deutsche',        'translateAll' => 'Alles übersetzen' ),
			'en' => array( 'label' => 'Englisch',        'promptLang' => 'ins Englische',       'translateAll' => 'Translate all' ),
			'fr' => array( 'label' => 'Französisch',     'promptLang' => 'ins Französische',    'translateAll' => 'Traduire tout' ),
			'es' => array( 'label' => 'Spanisch',        'promptLang' => 'ins Spanische',       'translateAll' => 'Traducir todo' ),
			'it' => array( 'label' => 'Italienisch',     'promptLang' => 'ins Italienische',    'translateAll' => 'Traduci tutto' ),
			'pt' => array( 'label' => 'Portugiesisch',   'promptLang' => 'ins Portugiesische',  'translateAll' => 'Traduzir tudo' ),
			'nl' => array( 'label' => 'Niederländisch',  'promptLang' => 'ins Niederländische', 'translateAll' => 'Alles vertalen' ),
			'pl' => array( 'label' => 'Polnisch',        'promptLang' => 'ins Polnische',       'translateAll' => 'Przetłumacz wszystko' ),
			'cs' => array( 'label' => 'Tschechisch',     'promptLang' => 'ins Tschechische',    'translateAll' => 'Přeložit vše' ),
			'hu' => array( 'label' => 'Ungarisch',       'promptLang' => 'ins Ungarische',      'translateAll' => 'Minden fordítása' ),
			'ro' => array( 'label' => 'Rumänisch',       'promptLang' => 'ins Rumänische',      'translateAll' => 'Traduce tot' ),
			'da' => array( 'label' => 'Dänisch',         'promptLang' => 'ins Dänische',        'translateAll' => 'Oversæt alt' ),
			'sv' => array( 'label' => 'Schwedisch',      'promptLang' => 'ins Schwedische',     'translateAll' => 'Översätt allt' ),
			'no' => array( 'label' => 'Norwegisch',      'promptLang' => 'ins Norwegische',     'translateAll' => 'Oversett alt' ),
			'fi' => array( 'label' => 'Finnisch',        'promptLang' => 'ins Finnische',       'translateAll' => 'Käännä kaikki' ),
			'ru' => array( 'label' => 'Russisch',        'promptLang' => 'ins Russische',       'translateAll' => 'Перевести всё' ),
			'tr' => array( 'label' => 'Türkisch',        'promptLang' => 'ins Türkische',       'translateAll' => 'Tümünü çevir' ),
			'ja' => array( 'label' => 'Japanisch',       'promptLang' => 'ins Japanische',      'translateAll' => 'すべて翻訳' ),
			'zh' => array( 'label' => 'Chinesisch',      'promptLang' => 'ins Chinesische',     'translateAll' => '翻译全部' ),
		);
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
