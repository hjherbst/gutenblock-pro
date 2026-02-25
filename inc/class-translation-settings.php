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
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_submenu() {
		add_submenu_page(
			'gutenblock-pro',
			__( 'KI Übersetzungen', 'gutenblock-pro' ),
			__( 'KI Übersetzungen', 'gutenblock-pro' ),
			'manage_options',
			'gutenblock-pro-translations',
			array( $this, 'render_page' )
		);
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

	public function enqueue_assets( $hook ) {
		if ( 'gutenblock-pro_page_gutenblock-pro-translations' !== $hook ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', $this->get_toggle_css() );
	}

	private function get_toggle_css() {
		return '
			.gbp-feature-row { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
			.gbp-feature-row .gbp-feature-toggle { flex-shrink: 0; }
			.gbp-feature-content h3 { margin: 0 0 0.25rem 0; }
			.gbp-feature-content p { margin: 0; color: #646970; }
			.gbp-features-form .submit { margin-top: 0; }
			.gbp-toggle { position: relative; display: inline-block; width: 44px; height: 24px; }
			.gbp-toggle input { opacity: 0; width: 0; height: 0; }
			.gbp-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #c3c4c7; border-radius: 24px; transition: 0.2s; }
			.gbp-toggle-slider::before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.2); }
			.gbp-toggle input:checked + .gbp-toggle-slider { background: #2271b1; }
			.gbp-toggle input:checked + .gbp-toggle-slider::before { transform: translateX(20px); }
			.gbp-toggle input:disabled + .gbp-toggle-slider { opacity: 0.6; cursor: not-allowed; }
		';
	}

	public function render_page() {
		$saved     = get_option( self::OPTION_NAME, array() );
		$available = self::get_available_languages();
		?>
		<div class="wrap gbp-features-page">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Aktiviere die Sprachen, in die im Editor übersetzt werden kann. Jede aktivierte Sprache erscheint als Button in der Sidebar.', 'gutenblock-pro' ); ?></p>

			<form method="post" action="options.php" class="gbp-features-form">
				<?php settings_fields( 'gutenblock_pro_translations' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $available as $code => $meta ) : ?>
						<?php $enabled = ! empty( $saved[ $code ] ); ?>
						<tr>
							<td>
								<div class="gbp-feature-row">
									<div class="gbp-feature-toggle">
										<label class="gbp-toggle">
											<input type="checkbox"
												name="<?php echo esc_attr( self::OPTION_NAME . '[' . $code . ']' ); ?>"
												value="1"
												<?php checked( $enabled ); ?>
											/>
											<span class="gbp-toggle-slider"></span>
										</label>
									</div>
									<div class="gbp-feature-content">
										<h3><?php echo esc_html( $meta['label'] ); ?> <small style="color:#646970;">(<?php echo esc_html( strtoupper( $code ) ); ?>)</small></h3>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Einstellungen speichern', 'gutenblock-pro' ) ); ?>
			</form>
		</div>
		<?php
	}
}
