<?php
/**
 * Admin Page - Pattern Management Interface
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Admin_Page {

	/**
	 * Initialize the admin page
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_gutenblock_pro_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_gutenblock_pro_get_file_content', array( $this, 'ajax_get_file_content' ) );
		add_action( 'wp_ajax_gutenblock_pro_save_file', array( $this, 'ajax_save_file' ) );
		add_action( 'wp_ajax_gutenblock_pro_preview_pattern', array( $this, 'ajax_preview_pattern' ) );
		// nopriv nötig, wenn Gutenberg-Canvas-iframe die Session-Cookies nicht weitergibt
		add_action( 'wp_ajax_nopriv_gutenblock_pro_preview_pattern', array( $this, 'ajax_preview_pattern' ) );
		add_action( 'wp_ajax_gutenblock_pro_clear_preview_cache', array( $this, 'ajax_clear_preview_cache' ) );
		add_action( 'wp_ajax_gutenblock_pro_warm_previews', array( $this, 'ajax_warm_previews' ) );
		add_action( 'wp_ajax_nopriv_gutenblock_pro_warm_previews', array( $this, 'ajax_warm_previews' ) );
		add_action( 'wp_ajax_gutenblock_pro_delete_pattern', array( $this, 'ajax_delete_pattern' ) );
		add_action( 'wp_ajax_gutenblock_pro_reset_block_style', array( $this, 'ajax_reset_block_style' ) );
		add_action( 'wp_ajax_gutenblock_pro_reset_pattern_file', array( $this, 'ajax_reset_pattern_file' ) );
		add_action( 'wp_ajax_gutenblock_pro_adopt_as_original', array( $this, 'ajax_adopt_as_original' ) );
		add_action( 'wp_ajax_gutenblock_pro_update_group', array( $this, 'ajax_update_group' ) );
		add_action( 'wp_ajax_gutenblock_pro_update_premium', array( $this, 'ajax_update_premium' ) );
		add_action( 'wp_ajax_gutenblock_pro_save_pattern_meta', array( $this, 'ajax_save_pattern_meta' ) );
		add_action( 'wp_ajax_gutenblock_pro_reset_pattern_meta', array( $this, 'ajax_reset_pattern_meta' ) );
	}

	/**
	 * Add admin menu page
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'GutenBlock Pro', 'gutenblock-pro' ),
			__( 'GutenBlock Pro', 'gutenblock-pro' ),
			'manage_options',
			'gutenblock-pro',
			array( $this, 'render_admin_page' ),
			'dashicons-layout',
			59
		);

		add_submenu_page(
			'gutenblock-pro',
			__( 'Sections', 'gutenblock-pro' ),
			__( 'Sections', 'gutenblock-pro' ),
			'manage_options',
			'gutenblock-pro'
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_gutenblock-pro' !== $hook ) {
			return;
		}

		// CodeMirror for code editing
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		// Admin CSS
		wp_enqueue_style(
			'gutenblock-pro-admin',
			GUTENBLOCK_PRO_URL . 'assets/css/admin.css',
			array(),
			GUTENBLOCK_PRO_VERSION
		);

		// Admin JS
		wp_enqueue_script(
			'gutenblock-pro-admin',
			GUTENBLOCK_PRO_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-codemirror' ),
			GUTENBLOCK_PRO_VERSION,
			true
		);

		wp_localize_script( 'gutenblock-pro-admin', 'gutenblockProAdmin', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'gutenblock_pro_admin' ),
			'isDevMode' => GUTENBLOCK_PRO_DEV,
			'strings'  => array(
				'saved'          => __( 'Gespeichert!', 'gutenblock-pro' ),
				'error'          => __( 'Fehler beim Speichern', 'gutenblock-pro' ),
				'confirmReset'   => __( 'Datei wirklich zurücksetzen?', 'gutenblock-pro' ),
				'confirmResetMeta' => __( 'Meta-Anpassungen aus dem Uploads-Ordner entfernen und Plugin-Stand wiederherstellen?', 'gutenblock-pro' ),
				'confirmAdopt'   => __( 'Aktuellen Editor-Inhalt als neues Original in das Plugin übernehmen?', 'gutenblock-pro' ),
				'adopted'        => __( 'Als Original übernommen!', 'gutenblock-pro' ),
			),
		) );
	}

	/**
	 * Get all patterns with their assets
	 */
	private function get_patterns_data() {
		$patterns = array();
		$patterns_dir = GUTENBLOCK_PRO_PATTERNS_PATH;

		if ( ! is_dir( $patterns_dir ) ) {
			return $patterns;
		}

		$pattern_folders = glob( $patterns_dir . '*', GLOB_ONLYDIR );
		$disabled_patterns = get_option( 'gutenblock_pro_disabled_patterns', array() );

		foreach ( $pattern_folders as $folder ) {
			$slug = basename( $folder );
			$pattern_file = function_exists( 'gutenblock_pro_resolve_pattern_php_path' )
				? gutenblock_pro_resolve_pattern_php_path( $slug )
				: $folder . '/pattern.php';

			if ( ! file_exists( $pattern_file ) ) {
				continue;
			}

			$pattern_data = require $pattern_file;

			// Find all language versions
			$languages = $this->get_pattern_languages( $folder );

			$kw = isset( $pattern_data['keywords'] ) && is_array( $pattern_data['keywords'] )
				? implode( ', ', $pattern_data['keywords'] )
				: '';

			$custom_php = gutenblock_pro_custom_pattern_file( $slug, 'pattern.php' );
			$has_meta_custom = file_exists( $custom_php['path'] );

			$default_tones = array( 'neutral', 'dark', 'soft' );
			$tones = isset( $pattern_data['tones'] ) && is_array( $pattern_data['tones'] ) ? $pattern_data['tones'] : $default_tones;

			$patterns[ $slug ] = array(
				'slug'        => $slug,
				'title'       => isset( $pattern_data['title'] ) ? $pattern_data['title'] : $slug,
				'description' => isset( $pattern_data['description'] ) ? $pattern_data['description'] : '',
				'type'        => isset( $pattern_data['type'] ) ? $pattern_data['type'] : 'pattern',
				'group'       => isset( $pattern_data['group'] ) ? $pattern_data['group'] : '',
				'premium'     => isset( $pattern_data['premium'] ) ? (bool) $pattern_data['premium'] : false,
				'ai_hint'     => isset( $pattern_data['ai_hint'] ) ? $pattern_data['ai_hint'] : '',
				'keywords'    => $kw,
				'tones'       => $tones,
				'enabled'     => ! in_array( $slug, $disabled_patterns ),
				'has_style'   => file_exists( $folder . '/style.css' ),
				'has_editor'  => file_exists( $folder . '/editor.css' ),
				'has_script'  => file_exists( $folder . '/script.js' ),
				'has_content' => file_exists( $folder . '/content.html' ),
				'folder'      => $folder,
				'languages'   => $languages,
				'has_meta_custom' => $has_meta_custom,
			);
		}

		return $patterns;
	}

	/**
	 * Get available languages for a pattern
	 *
	 * @param string $folder Pattern folder path
	 * @return array Array of language codes
	 */
	private function get_pattern_languages( $folder ) {
		$languages = array();
		
		// Check for default content.html
		if ( file_exists( $folder . '/content.html' ) ) {
			$languages[] = 'default';
		}

		// Find all content-*.html files
		$content_files = glob( $folder . '/content-*.html' );
		
		foreach ( $content_files as $file ) {
			$filename = basename( $file );
			// Extract language code from content-de_DE.html or content-de.html
			if ( preg_match( '/^content-([a-z]{2}(?:_[A-Z]{2})?)\.html$/', $filename, $matches ) ) {
				$languages[] = $matches[1];
			}
		}

		return $languages;
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$patterns = $this->get_patterns_data();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'patterns';
		?>
		<div class="wrap gutenblock-pro-admin">
			<h1>
				<span class="dashicons dashicons-layout"></span>
				<?php _e( 'GutenBlock Pro', 'gutenblock-pro' ); ?>
			</h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=gutenblock-pro&tab=patterns" class="nav-tab <?php echo $active_tab === 'patterns' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Sections', 'gutenblock-pro' ); ?>
				</a>
			<a href="?page=gutenblock-pro&tab=editor" class="nav-tab <?php echo $active_tab === 'editor' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'CSS/JS Editor', 'gutenblock-pro' ); ?>
				</a>
				<a href="?page=gutenblock-pro&tab=info" class="nav-tab <?php echo $active_tab === 'info' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Info', 'gutenblock-pro' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'blocks':
						$this->render_blocks_tab();
						break;
					case 'editor':
						$this->render_editor_tab( $patterns );
						break;
					case 'info':
						$this->render_info_tab();
						break;
					default:
						$this->render_patterns_tab( $patterns );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render patterns tab
	 */
	private function render_patterns_tab( $patterns ) {
		// Separate patterns by type
		$pages = array_filter( $patterns, function( $p ) { return $p['type'] === 'page'; } );
		$sections = array_filter( $patterns, function( $p ) { return $p['type'] !== 'page'; } );

		// Sections nach Gruppen-Reihenfolge aus $groups sortieren.
		// Patterns ohne Gruppe oder mit unbekannter Gruppe kommen ans Ende.
		$group_order = array_keys( GutenBlock_Pro_Pattern_Loader::$groups );
		uasort( $sections, function( $a, $b ) use ( $group_order ) {
			$pos_a = array_search( $a['group'], $group_order );
			$pos_b = array_search( $b['group'], $group_order );
			// false (kein Treffer) → ans Ende
			$pos_a = $pos_a === false ? PHP_INT_MAX : $pos_a;
			$pos_b = $pos_b === false ? PHP_INT_MAX : $pos_b;
			if ( $pos_a !== $pos_b ) {
				return $pos_a - $pos_b;
			}
			// Innerhalb derselben Gruppe alphabetisch nach Titel
			return strcmp( $a['title'], $b['title'] );
		} );
		?>

		<?php if ( empty( $patterns ) ) : ?>
			<div class="notice notice-warning">
				<p><?php _e( 'Keine Sections gefunden.', 'gutenblock-pro' ); ?></p>
			</div>
		<?php else : ?>

			<?php if ( ! empty( $sections ) ) : ?>
			<h2 class="patterns-section-title"><?php _e( 'Sections', 'gutenblock-pro' ); ?></h2>
			<div class="gutenblock-pro-patterns-grid">
				<?php $this->render_pattern_cards( $sections ); ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $pages ) ) : ?>
			<h2 class="patterns-section-title"><?php _e( 'Seiten', 'gutenblock-pro' ); ?></h2>
			<div class="gutenblock-pro-patterns-grid">
				<?php $this->render_pattern_cards( $pages ); ?>
			</div>
			<?php endif; ?>

		<?php endif; ?>
		<?php
	}

	/**
	 * Render pattern cards
	 */
	private function render_pattern_cards( $patterns ) {
		$groups = GutenBlock_Pro_Pattern_Loader::$groups;
		$current_user = wp_get_current_user();
		$is_admin_user = $current_user->exists() && $current_user->user_login === 'hjherbst';
		
		$preview_nonce = wp_create_nonce( 'gutenblock_pro_modal' );
		$cache_dir     = $this->preview_cache_dir();
		$cache_url     = $this->preview_cache_url();
		$locale        = get_locale();
		// Warm the static cache for all visible patterns in a single bootstrap.
		// Idempotent: existing cache files are kept; only missing ones are
		// (re-)rendered. Drops first-time admin page load from ~5s to <1s and
		// makes every subsequent visit instant.
		$this->prewarm_preview_cache_for( array_keys( $patterns ) );
		foreach ( $patterns as $slug => $pattern ) :
			// Prefer the pre-warmed static cache file (served by the web server,
			// no WordPress bootstrap). If it doesn't exist yet, fall back to the
			// admin-ajax route which renders on demand and writes the cache file
			// for the next visit.
			$cache_file = trailingslashit( $cache_dir ) . $this->preview_cache_filename( $slug, $locale, 'neutral' );
			$static_url = trailingslashit( $cache_url ) . $this->preview_cache_filename( $slug, $locale, 'neutral' );
			$ajax_url   = admin_url( 'admin-ajax.php?action=gutenblock_pro_preview_pattern&pattern=' . $slug . '&_wpnonce=' . $preview_nonce );
			$preview_url = file_exists( $cache_file ) ? $static_url : $ajax_url;
			$edit_url = admin_url( 'admin.php?page=gutenblock-pro&tab=editor&pattern=' . $slug );
		?>
			<div class="pattern-card <?php echo $pattern['enabled'] ? 'enabled' : 'disabled'; ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
				<div class="pattern-card-header">
					<h3>
						<?php echo esc_html( $pattern['title'] ); ?>
						<?php if ( isset( $pattern['premium'] ) && $pattern['premium'] ) : ?>
							<span class="premium-badge" title="<?php esc_attr_e( 'Premium Pattern', 'gutenblock-pro' ); ?>">Pro Plus</span>
						<?php endif; ?>
					</h3>
					<div class="pattern-card-actions">
						<?php if ( $is_admin_user ) : ?>
							<label class="switch premium-toggle" title="<?php esc_attr_e( 'Premium/Free', 'gutenblock-pro' ); ?>">
								<input type="checkbox" class="premium-toggle-input" data-slug="<?php echo esc_attr( $slug ); ?>" <?php checked( isset( $pattern['premium'] ) && $pattern['premium'] ); ?>>
								<span class="slider premium-slider"></span>
							</label>
						<?php endif; ?>
						<label class="switch">
							<input type="checkbox" class="pattern-toggle" data-slug="<?php echo esc_attr( $slug ); ?>" <?php checked( $pattern['enabled'] ); ?>>
							<span class="slider"></span>
						</label>
						<button type="button" class="button-link delete-pattern" data-slug="<?php echo esc_attr( $slug ); ?>" data-name="<?php echo esc_attr( $pattern['title'] ); ?>" title="<?php esc_attr_e( 'Löschen', 'gutenblock-pro' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>

				<?php if ( $pattern['has_content'] ) : ?>
				<?php
				$card_tones_for_swatches = isset( $pattern['tones'] ) && is_array( $pattern['tones'] ) ? $pattern['tones'] : array( 'neutral' );
				$show_swatches = count( $card_tones_for_swatches ) > 1;
				?>
				<a href="<?php echo esc_url( $edit_url ); ?>" class="pattern-card-preview-link" data-pattern="<?php echo esc_attr( $slug ); ?>">
					<div class="pattern-card-preview">
						<iframe class="pattern-card-iframe" src="<?php echo esc_url( $preview_url ); ?>" data-base-url="<?php echo esc_url( $preview_url ); ?>" loading="lazy" sandbox="allow-same-origin allow-scripts allow-popups" tabindex="-1"></iframe>
						<div class="preview-overlay">
							<span class="dashicons dashicons-edit"></span>
						</div>
					</div>
				</a>
				<?php endif; ?>

				<div class="pattern-card-footer">
					<div class="pattern-group-select">
						<select class="group-dropdown" data-slug="<?php echo esc_attr( $slug ); ?>">
							<option value=""><?php _e( '— Keine Gruppe —', 'gutenblock-pro' ); ?></option>
							<?php foreach ( $groups as $group_slug => $group_label ) : ?>
								<option value="<?php echo esc_attr( $group_slug ); ?>" <?php selected( $pattern['group'], $group_slug ); ?>>
									<?php echo esc_html( $group_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php
					// Interaktive Tone-Swatches – Hover wechselt iframe, klick für persistent
					$card_tones = isset( $pattern['tones'] ) && is_array( $pattern['tones'] ) ? $pattern['tones'] : array( 'neutral' );
					if ( count( $card_tones ) > 1 ) :
						$labels = GutenBlock_Pro_Tone_Injector::tone_labels();
						?>
						<div class="gbp-tone-swatches" data-pattern="<?php echo esc_attr( $slug ); ?>">
							<?php foreach ( $card_tones as $ct ) : ?>
								<button
									type="button"
									class="gbp-tone-swatch gbp-tone-swatch--<?php echo esc_attr( $ct ); ?> <?php echo $ct === 'neutral' ? 'is-active' : ''; ?>"
									data-tone="<?php echo esc_attr( $ct ); ?>"
									aria-label="<?php echo esc_attr( isset( $labels[ $ct ] ) ? $labels[ $ct ] : $ct ); ?>"
									title="<?php echo esc_attr( isset( $labels[ $ct ] ) ? $labels[ $ct ] : $ct ); ?>"
								></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $pattern['languages'] ) && count( $pattern['languages'] ) > 1 ) : ?>
					<div class="pattern-languages">
						<span class="dashicons dashicons-translation"></span>
						<?php foreach ( $pattern['languages'] as $lang ) : ?>
							<span class="lang-badge <?php echo $lang === 'default' ? 'default' : ''; ?>">
								<?php echo $lang === 'default' ? 'DE' : strtoupper( substr( $lang, 0, 2 ) ); ?>
							</span>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach;
	}

	/**
	 * Render editor tab
	 */
	private function render_editor_tab( $patterns ) {
		$selected_type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'pattern';
		$selected_item = isset( $_GET['pattern'] ) ? sanitize_key( $_GET['pattern'] ) : ( isset( $_GET['block'] ) ? sanitize_key( $_GET['block'] ) : '' );
		$selected_file = isset( $_GET['file'] ) ? sanitize_key( $_GET['file'] ) : 'style';
		
		// Get block variants
		$block_registry = new GutenBlock_Pro_Block_Registry();
		$block_variants = $block_registry->get_block_variants();
		
		// Auto-select first item if none selected
		if ( empty( $selected_item ) ) {
			if ( $selected_type === 'block' && ! empty( $block_variants ) ) {
				$selected_item = $block_variants[0]['slug'];
			} elseif ( ! empty( $patterns ) ) {
				$selected_item = array_key_first( $patterns );
				$selected_type = 'pattern';
			}
		}
		?>
		<div class="gutenblock-pro-editor">
			<div class="editor-sidebar">
				<div class="editor-sidebar-tabs">
					<button type="button" class="sidebar-tab <?php echo $selected_type === 'pattern' ? 'active' : ''; ?>" data-type="pattern">
						<?php _e( 'Sections', 'gutenblock-pro' ); ?>
					</button>
					<button type="button" class="sidebar-tab <?php echo $selected_type === 'block' ? 'active' : ''; ?>" data-type="block">
						<?php _e( 'Stilvarianten', 'gutenblock-pro' ); ?>
					</button>
				</div>
				
				<?php if ( $selected_type === 'pattern' ) : ?>
					<h3><?php _e( 'Sections', 'gutenblock-pro' ); ?></h3>
					<ul class="pattern-list">
						<?php foreach ( $patterns as $slug => $pattern ) : ?>
							<li class="<?php echo $slug === $selected_item ? 'active' : ''; ?>">
								<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $pattern['title'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<h3><?php _e( 'Block-Varianten', 'gutenblock-pro' ); ?></h3>
					<ul class="pattern-list">
						<?php foreach ( $block_variants as $variant ) : ?>
							<li class="<?php echo $variant['slug'] === $selected_item ? 'active' : ''; ?>">
								<a href="?page=gutenblock-pro&tab=editor&type=block&block=<?php echo esc_attr( $variant['slug'] ); ?>">
									<?php echo esc_html( $variant['label'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="editor-main">
				<?php if ( $selected_type === 'pattern' && $selected_item && isset( $patterns[ $selected_item ] ) ) : 
					$pattern = $patterns[ $selected_item ];
				?>
					<div class="editor-header">
						<h2><?php echo esc_html( $pattern['title'] ); ?></h2>
						<div class="file-tabs">
							<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $selected_item ); ?>&file=style" 
							   class="file-tab <?php echo $selected_file === 'style' ? 'active' : ''; ?> <?php echo $pattern['has_style'] ? '' : 'no-file'; ?>">
								style.css
							</a>
							<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $selected_item ); ?>&file=editor" 
							   class="file-tab <?php echo $selected_file === 'editor' ? 'active' : ''; ?> <?php echo $pattern['has_editor'] ? '' : 'no-file'; ?>">
								editor.css
							</a>
							<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $selected_item ); ?>&file=script" 
							   class="file-tab <?php echo $selected_file === 'script' ? 'active' : ''; ?> <?php echo $pattern['has_script'] ? '' : 'no-file'; ?>">
								script.js
							</a>
							<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $selected_item ); ?>&file=content" 
							   class="file-tab <?php echo $selected_file === 'content' ? 'active' : ''; ?> <?php echo $pattern['has_content'] ? '' : 'no-file'; ?>">
								content.html
							</a>
							<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $selected_item ); ?>&file=meta" 
							   class="file-tab <?php echo $selected_file === 'meta' ? 'active' : ''; ?> file-tab-meta">
								<?php esc_html_e( 'Meta', 'gutenblock-pro' ); ?>
							</a>
							<?php 
							// Show language-specific content files
							foreach ( $pattern['languages'] as $lang ) :
								if ( $lang === 'default' ) continue;
								$lang_file = 'content_' . $lang;
							?>
							<a href="?page=gutenblock-pro&tab=editor&type=pattern&pattern=<?php echo esc_attr( $selected_item ); ?>&file=<?php echo esc_attr( $lang_file ); ?>" 
							   class="file-tab lang-file <?php echo $selected_file === $lang_file ? 'active' : ''; ?>">
								<?php echo strtoupper( $lang ); ?>
							</a>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="editor-content">
						<?php
					if ( $selected_file === 'meta' ) :
						$gbp_content_html_path = $this->get_pattern_resolved_content_html_path( $selected_item );
						$gbp_content_html       = file_exists( $gbp_content_html_path ) ? file_get_contents( $gbp_content_html_path ) : '';
						$gbp_detected_content_fields = $this->extract_content_field_ids_from_pattern_html( $gbp_content_html );
						$gbp_meta_preview_nonce = wp_create_nonce( 'gutenblock_pro_modal' );
						$gbp_meta_preview_url    = admin_url(
							'admin-ajax.php?action=gutenblock_pro_preview_pattern&pattern=' . rawurlencode( $selected_item ) . '&_wpnonce=' . rawurlencode( $gbp_meta_preview_nonce )
						);
						?>
						<div id="gutenblock-pro-pattern-meta-panel" class="gutenblock-pro-pattern-meta-panel" data-pattern="<?php echo esc_attr( $selected_item ); ?>">
							<p class="description"><?php esc_html_e( 'Änderungen werden in uploads/gutenblock-pro/patterns/…/pattern.php gespeichert und überschreiben nicht die Plugin-Datei.', 'gutenblock-pro' ); ?></p>
							<div class="gutenblock-pro-meta-layout">
							<div class="gutenblock-pro-meta-form-col">
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="gbp-meta-title"><?php esc_html_e( 'Name (Titel)', 'gutenblock-pro' ); ?></label></th>
									<td><input type="text" class="large-text" id="gbp-meta-title" name="title" value="<?php echo esc_attr( $pattern['title'] ); ?>" /></td>
								</tr>
								<tr>
									<th scope="row"><label for="gbp-meta-description"><?php esc_html_e( 'Beschreibung', 'gutenblock-pro' ); ?></label></th>
									<td>
										<textarea class="large-text gbp-meta-textarea-compact" rows="2" id="gbp-meta-description" name="description" placeholder="<?php esc_attr_e( 'Kurze, sichtbare Pattern-Beschreibung (Tooltip im Inserter).', 'gutenblock-pro' ); ?>"><?php echo esc_textarea( $pattern['description'] ); ?></textarea>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="gbp-meta-ai-hint"><?php esc_html_e( 'AI Hint', 'gutenblock-pro' ); ?></label></th>
									<td>
										<textarea class="large-text gbp-meta-textarea-compact" rows="3" id="gbp-meta-ai-hint" name="ai_hint" placeholder="<?php esc_attr_e( 'Strukturelle Beschreibung (Layout, Hintergrundtyp, Buttons, …)', 'gutenblock-pro' ); ?>"><?php echo esc_textarea( $pattern['ai_hint'] ); ?></textarea>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="gbp-meta-type"><?php esc_html_e( 'Typ', 'gutenblock-pro' ); ?></label></th>
									<td>
										<select id="gbp-meta-type" name="type">
											<option value="pattern" <?php selected( $pattern['type'], 'pattern' ); ?>><?php esc_html_e( 'Section', 'gutenblock-pro' ); ?></option>
											<option value="page" <?php selected( $pattern['type'], 'page' ); ?>><?php esc_html_e( 'Seite', 'gutenblock-pro' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="gbp-meta-group"><?php esc_html_e( 'Gruppe', 'gutenblock-pro' ); ?></label></th>
									<td>
										<select id="gbp-meta-group" name="group">
											<option value=""><?php esc_html_e( '— Keine Gruppe —', 'gutenblock-pro' ); ?></option>
											<?php foreach ( GutenBlock_Pro_Pattern_Loader::$groups as $g_slug => $g_label ) : ?>
												<option value="<?php echo esc_attr( $g_slug ); ?>" <?php selected( $pattern['group'], $g_slug ); ?>><?php echo esc_html( $g_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="gbp-meta-keywords"><?php esc_html_e( 'Keywords', 'gutenblock-pro' ); ?></label></th>
									<td><input type="text" class="large-text" id="gbp-meta-keywords" name="keywords" value="<?php echo esc_attr( $pattern['keywords'] ); ?>" placeholder="hero, cta" /></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Content-Felder', 'gutenblock-pro' ); ?></th>
									<td>
										<?php if ( empty( $gbp_detected_content_fields ) ) : ?>
											<span class="gbp-detected-content-fields-inline gbp-detected-content-fields-empty">—</span>
										<?php else : ?>
											<code class="gbp-detected-content-fields-inline"><?php echo esc_html( implode( ', ', $gbp_detected_content_fields ) ); ?></code>
										<?php endif; ?>
									</td>
								</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Tonalitäten', 'gutenblock-pro' ); ?></th>
								<td>
									<?php
									$gbp_active_tones = isset( $pattern['tones'] ) && is_array( $pattern['tones'] ) ? $pattern['tones'] : array( 'neutral', 'dark', 'soft' );
									foreach ( GutenBlock_Pro_Tone_Injector::tone_labels() as $t_key => $t_label ) :
										$t_checked = in_array( $t_key, $gbp_active_tones, true );
										?>
										<label style="margin-right:12px;">
											<input type="checkbox" class="gbp-meta-tone" name="tones[]" value="<?php echo esc_attr( $t_key ); ?>" <?php checked( $t_checked ); ?> />
											<?php echo esc_html( $t_label ); ?>
										</label>
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'Aktive Varianten werden im Inserter und in der KI-Auswahl berücksichtigt.', 'gutenblock-pro' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Premium', 'gutenblock-pro' ); ?></th>
								<td><label><input type="checkbox" id="gbp-meta-premium" name="premium" value="1" <?php checked( $pattern['premium'] ); ?> /> <?php echo esc_html( __( 'Paid Feature', 'gutenblock-pro' ) ); ?></label></td>
							</tr>
						</table>
							</div>
						<div class="gutenblock-pro-meta-preview-col">
							<?php if ( ! empty( $pattern['has_content'] ) ) : ?>
								<?php
								$gbp_meta_editor_link = admin_url(
									'admin.php?page=gutenblock-pro&tab=editor&type=pattern&pattern=' . rawurlencode( $selected_item ) . '&file=content'
								);
								$gbp_tone_labels = GutenBlock_Pro_Tone_Injector::tone_labels();
								$gbp_active_tones_preview = isset( $pattern['tones'] ) && is_array( $pattern['tones'] ) ? $pattern['tones'] : array( 'neutral' );
								foreach ( $gbp_active_tones_preview as $gbp_tone ) :
									$gbp_tone_preview_url = $gbp_meta_preview_url . '&tone=' . rawurlencode( $gbp_tone );
									?>
									<div class="gbp-tone-preview-wrap">
										<span class="gbp-tone-preview-label"><?php echo esc_html( isset( $gbp_tone_labels[ $gbp_tone ] ) ? $gbp_tone_labels[ $gbp_tone ] : $gbp_tone ); ?></span>
										<a href="<?php echo esc_url( $gbp_meta_editor_link ); ?>" class="pattern-card-preview-link">
											<div class="pattern-card-preview">
												<iframe
													src="<?php echo esc_url( $gbp_tone_preview_url ); ?>"
													title="<?php echo esc_attr( sprintf( __( 'Vorschau: %s (%s)', 'gutenblock-pro' ), $pattern['title'], $gbp_tone ) ); ?>"
													loading="lazy"
													sandbox="allow-same-origin allow-scripts allow-popups"
													tabindex="-1"
												></iframe>
												<div class="preview-overlay">
													<span class="dashicons dashicons-edit"></span>
												</div>
											</div>
										</a>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="pattern-card-preview pattern-card-preview-empty">
									<span class="description"><?php esc_html_e( 'Kein content.html — keine Vorschau.', 'gutenblock-pro' ); ?></span>
								</div>
							<?php endif; ?>
						</div>
							</div>
						</div>
						<div class="editor-actions gutenblock-pro-meta-actions">
							<button type="button" class="button button-primary" id="save-pattern-meta">
								<span class="dashicons dashicons-saved"></span>
								<?php esc_html_e( 'Meta speichern', 'gutenblock-pro' ); ?>
							</button>
							<button type="button" class="button" id="reset-pattern-meta" data-pattern="<?php echo esc_attr( $selected_item ); ?>" style="margin-left:8px;">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php esc_html_e( 'Meta auf Plugin-Stand zurücksetzen', 'gutenblock-pro' ); ?>
							</button>
							<span class="save-status"></span>
							<span class="custom-indicator gutenblock-pro-meta-custom" style="<?php echo ! empty( $pattern['has_meta_custom'] ) ? '' : 'display:none;'; ?> margin-left:12px; color:#d63638; font-style:italic;">
								<?php esc_html_e( 'Angepasst', 'gutenblock-pro' ); ?>
							</span>
						</div>
						<?php else : ?>
						<textarea id="gutenblock-pro-code-editor" 
						          data-type="pattern"
						          data-pattern="<?php echo esc_attr( $selected_item ); ?>" 
						          data-file="<?php echo esc_attr( $selected_file ); ?>"
						          data-file-type="<?php echo $selected_file === 'script' ? 'javascript' : ( $selected_file === 'content' ? 'html' : 'css' ); ?>"></textarea>
						
						<div class="editor-actions">
							<button type="button" class="button button-primary" id="save-file">
								<span class="dashicons dashicons-saved"></span>
								<?php _e( 'Speichern', 'gutenblock-pro' ); ?>
							</button>
							<button type="button" class="button" id="reset-pattern-file" data-pattern="<?php echo esc_attr( $selected_item ); ?>" data-file="<?php echo esc_attr( $selected_file ); ?>" style="margin-left:8px;">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php _e( 'Auf Original zurücksetzen', 'gutenblock-pro' ); ?>
							</button>
							<?php if ( GUTENBLOCK_PRO_DEV ) : ?>
							<button type="button" class="button button-link-delete" id="adopt-as-original" data-type="pattern" data-item="<?php echo esc_attr( $selected_item ); ?>" data-file="<?php echo esc_attr( $selected_file ); ?>" style="margin-left:8px;">
								<span class="dashicons dashicons-upload"></span>
								<?php _e( 'Als Original übernehmen', 'gutenblock-pro' ); ?>
							</button>
							<?php endif; ?>
							<span class="save-status"></span>
							<span class="custom-indicator" style="display:none; margin-left:12px; color:#d63638; font-style:italic;">
								<?php _e( 'Angepasst', 'gutenblock-pro' ); ?>
							</span>
						</div>
						<?php endif; ?>
					</div>
				<?php elseif ( $selected_type === 'block' && $selected_item ) : 
					$variant = null;
					foreach ( $block_variants as $v ) {
						if ( $v['slug'] === $selected_item ) {
							$variant = $v;
							break;
						}
					}
					if ( $variant ) :
				?>
					<div class="editor-header">
						<h2><?php echo esc_html( $variant['label'] ); ?></h2>
						<div class="file-tabs">
							<a href="?page=gutenblock-pro&tab=editor&type=block&block=<?php echo esc_attr( $selected_item ); ?>&file=style" 
							   class="file-tab <?php echo $selected_file === 'style' ? 'active' : ''; ?> <?php echo $variant['has_style'] ? '' : 'no-file'; ?>">
								style.css
							</a>
						</div>
					</div>

					<div class="editor-content">
						<textarea id="gutenblock-pro-code-editor" 
						          data-type="block"
						          data-block="<?php echo esc_attr( $selected_item ); ?>" 
						          data-file="<?php echo esc_attr( $selected_file ); ?>"
						          data-file-type="css"></textarea>
						
						<div class="editor-actions">
							<button type="button" class="button button-primary" id="save-file">
								<span class="dashicons dashicons-saved"></span>
								<?php _e( 'Speichern', 'gutenblock-pro' ); ?>
							</button>
							<button type="button" class="button" id="reset-block-style" data-block="<?php echo esc_attr( $selected_item ); ?>" style="margin-left:8px;">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php _e( 'Auf Original zurücksetzen', 'gutenblock-pro' ); ?>
							</button>
							<?php if ( GUTENBLOCK_PRO_DEV ) : ?>
							<button type="button" class="button button-link-delete" id="adopt-as-original" data-type="block" data-item="<?php echo esc_attr( $selected_item ); ?>" data-file="style" style="margin-left:8px;">
								<span class="dashicons dashicons-upload"></span>
								<?php _e( 'Als Original übernehmen', 'gutenblock-pro' ); ?>
							</button>
							<?php endif; ?>
							<span class="save-status"></span>
							<span class="custom-indicator" style="display:none; margin-left:12px; color:#d63638; font-style:italic;">
								<?php _e( 'Angepasst', 'gutenblock-pro' ); ?>
							</span>
						</div>
					</div>
				<?php endif; ?>
				<?php else : ?>
					<div class="no-pattern-selected">
						<p><?php _e( 'Wähle ein Pattern oder eine Block-Variante aus der Liste.', 'gutenblock-pro' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render blocks tab
	 */
	private function render_blocks_tab() {
		$block_registry = new GutenBlock_Pro_Block_Registry();
		$block_variants = $block_registry->get_block_variants();
		?>
		<div class="gutenblock-pro-blocks">
			<h2><?php _e( 'Block-Erweiterungen', 'gutenblock-pro' ); ?></h2>
			<p class="description">
				<?php _e( 'Übersicht aller registrierten Block-Varianten und Block-Erweiterungen von GutenBlock Pro.', 'gutenblock-pro' ); ?>
			</p>

			<?php if ( empty( $block_variants ) ) : ?>
				<div class="notice notice-info">
					<p><?php _e( 'Noch keine Block-Erweiterungen registriert.', 'gutenblock-pro' ); ?></p>
				</div>
			<?php else : ?>
				<div class="gutenblock-pro-blocks-grid">
					<?php foreach ( $block_variants as $variant ) : ?>
						<div class="block-card">
							<div class="block-card-header">
								<h3>
									<?php echo esc_html( $variant['label'] ); ?>
									<span class="block-type-badge"><?php echo esc_html( $variant['type'] ); ?></span>
								</h3>
							</div>
							<div class="block-card-body">
								<div class="block-info">
									<div class="block-info-row">
										<strong><?php _e( 'Block:', 'gutenblock-pro' ); ?></strong>
										<code><?php echo esc_html( $variant['block'] ); ?></code>
									</div>
									<div class="block-info-row">
										<strong><?php _e( 'Variante:', 'gutenblock-pro' ); ?></strong>
										<code><?php echo esc_html( $variant['name'] ); ?></code>
									</div>
									<?php if ( ! empty( $variant['description'] ) ) : ?>
									<div class="block-info-row">
										<p class="block-description"><?php echo esc_html( $variant['description'] ); ?></p>
									</div>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render info tab
	 */
	private function render_info_tab() {
		$patterns = $this->get_patterns_data();
		$total_css_size = 0;
		$total_js_size = 0;

		foreach ( $patterns as $pattern ) {
			if ( $pattern['has_style'] ) {
				$total_css_size += filesize( $pattern['folder'] . '/style.css' );
			}
			if ( $pattern['has_script'] ) {
				$total_js_size += filesize( $pattern['folder'] . '/script.js' );
			}
		}
		?>
		<div class="gutenblock-pro-info">
			<div class="info-card">
				<h3><?php _e( 'Statistiken', 'gutenblock-pro' ); ?></h3>
				<table class="widefat">
					<tr>
						<th><?php _e( 'Sections gesamt', 'gutenblock-pro' ); ?></th>
						<td><?php echo count( $patterns ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'CSS gesamt', 'gutenblock-pro' ); ?></th>
						<td><?php echo size_format( $total_css_size ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'JS gesamt', 'gutenblock-pro' ); ?></th>
						<td><?php echo size_format( $total_js_size ); ?></td>
					</tr>
					<tr>
						<th><?php _e( 'Plugin Version', 'gutenblock-pro' ); ?></th>
						<td><?php echo GUTENBLOCK_PRO_VERSION; ?></td>
					</tr>
				</table>
			</div>

			<div class="info-card">
				<h3><?php _e( 'Conditional Loading', 'gutenblock-pro' ); ?></h3>
				<p><?php _e( 'GutenBlock Pro lädt CSS und JS nur für Sections, die auf der aktuellen Seite verwendet werden.', 'gutenblock-pro' ); ?></p>
				<p><?php _e( 'Die Erkennung basiert auf der CSS-Klasse:', 'gutenblock-pro' ); ?> <code>gb-pattern-{slug}</code></p>
			</div>

			<div class="info-card">
				<h3><?php _e( 'Pfade', 'gutenblock-pro' ); ?></h3>
				<table class="widefat">
					<tr>
						<th><?php _e( 'Plugin-Verzeichnis', 'gutenblock-pro' ); ?></th>
						<td><code><?php echo GUTENBLOCK_PRO_PATH; ?></code></td>
					</tr>
					<tr>
						<th><?php _e( 'Sections-Verzeichnis', 'gutenblock-pro' ); ?></th>
						<td><code><?php echo GUTENBLOCK_PRO_PATTERNS_PATH; ?></code></td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Save settings (enable/disable patterns)
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$pattern = sanitize_key( $_POST['pattern'] );
		$enabled = filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN );

		$disabled_patterns = get_option( 'gutenblock_pro_disabled_patterns', array() );

		if ( $enabled ) {
			$disabled_patterns = array_diff( $disabled_patterns, array( $pattern ) );
		} else {
			$disabled_patterns[] = $pattern;
			$disabled_patterns = array_unique( $disabled_patterns );
		}

		update_option( 'gutenblock_pro_disabled_patterns', $disabled_patterns );

		wp_send_json_success();
	}

	/**
	 * AJAX: Get file content
	 */
	public function ajax_get_file_content() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'pattern';
		$item = isset( $_POST['pattern'] ) ? sanitize_key( $_POST['pattern'] ) : ( isset( $_POST['block'] ) ? sanitize_key( $_POST['block'] ) : '' );
		$file = sanitize_text_field( $_POST['file'] );

		if ( $type === 'block' ) {
			// Block variant: prefer user custom.css from uploads, fall back to plugin default
			if ( $file !== 'style' ) {
				wp_send_json_error( 'Invalid file type' );
			}

			$custom      = gutenblock_pro_custom_block_file( $item );
			$has_custom  = file_exists( $custom['path'] );
			$default_path = GUTENBLOCK_PRO_BLOCKS_PATH . $item . '/style.css';

			$file_path = $has_custom ? $custom['path'] : $default_path;

			$content = file_exists( $file_path ) ? file_get_contents( $file_path ) : '';
			wp_send_json_success( array(
				'content'    => $content,
				'has_custom' => $has_custom,
			) );
		} else {
			// Pattern file
			$file_map = array(
				'style'   => 'style.css',
				'editor'  => 'editor.css',
				'script'  => 'script.js',
				'content' => 'content.html',
			);

			// Handle language-specific content files (content_de_DE -> content-de_DE.html)
			if ( strpos( $file, 'content_' ) === 0 ) {
				$lang = str_replace( 'content_', '', $file );
				$file_map[ $file ] = 'content-' . $lang . '.html';
			}

			if ( ! isset( $file_map[ $file ] ) ) {
				wp_send_json_error( 'Invalid file type' );
			}

			$filename     = $file_map[ $file ];
			$custom       = gutenblock_pro_custom_pattern_file( $item, $filename );
			$has_custom   = file_exists( $custom['path'] );
			$default_path = GUTENBLOCK_PRO_PATTERNS_PATH . $item . '/' . $filename;

			$file_path = $has_custom ? $custom['path'] : $default_path;
			$content   = file_exists( $file_path ) ? file_get_contents( $file_path ) : '';

			wp_send_json_success( array(
				'content'    => $content,
				'has_custom' => $has_custom,
			) );
		}
	}

	/**
	 * AJAX: Save file
	 */
	public function ajax_save_file() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'pattern';
		$item = isset( $_POST['pattern'] ) ? sanitize_key( $_POST['pattern'] ) : ( isset( $_POST['block'] ) ? sanitize_key( $_POST['block'] ) : '' );
		$file = sanitize_text_field( $_POST['file'] );
		$content = wp_unslash( $_POST['content'] );

		if ( $type === 'block' ) {
			// Block variant: save user edits to uploads dir (survives plugin updates)
			if ( $file !== 'style' ) {
				wp_send_json_error( 'Invalid file type' );
			}

			$custom   = gutenblock_pro_custom_block_file( $item );
			$file_path = $custom['path'];
			$item_dir  = $custom['dir'];
		} else {
			// Pattern file: save user edits to uploads dir (survives plugin updates)
			$file_map = array(
				'style'   => 'style.css',
				'editor'  => 'editor.css',
				'script'  => 'script.js',
				'content' => 'content.html',
			);

			// Handle language-specific content files (content_de_DE -> content-de_DE.html)
			if ( strpos( $file, 'content_' ) === 0 ) {
				$lang = str_replace( 'content_', '', $file );
				$file_map[ $file ] = 'content-' . $lang . '.html';
			}

			if ( ! isset( $file_map[ $file ] ) ) {
				wp_send_json_error( 'Invalid file type' );
			}

			$custom    = gutenblock_pro_custom_pattern_file( $item, $file_map[ $file ] );
			$file_path = $custom['path'];
			$item_dir  = $custom['dir'];
		}

		// Create directory if it doesn't exist
		if ( ! is_dir( $item_dir ) ) {
			wp_mkdir_p( $item_dir );
		}

		$result = file_put_contents( $file_path, $content );

		if ( $result !== false ) {
			wp_send_json_success( array( 'size' => size_format( strlen( $content ) ) ) );
		} else {
			wp_send_json_error( 'Could not save file' );
		}
	}

	/**
	 * Pfad zu content.html (Uploads-Override zuerst).
	 *
	 * @param string $slug Pattern-Slug.
	 * @return string Absoluter Pfad.
	 */
	private function get_pattern_resolved_content_html_path( $slug ) {
		$slug = sanitize_key( $slug );
		$custom = gutenblock_pro_custom_pattern_file( $slug, 'content.html' );
		if ( file_exists( $custom['path'] ) ) {
			return $custom['path'];
		}
		return GUTENBLOCK_PRO_PATTERNS_PATH . $slug . '/content.html';
	}

	/**
	 * Content-Feld-IDs aus Block-Markup ableiten (ohne manuelle pattern.php-Liste).
	 *
	 * @param string $html Roher Block-Inhalt (content.html).
	 * @return string[] Reihenfolge: Traversierung parse_blocks, dann data-content-field, dann Button-IDs.
	 */
	private function extract_content_field_ids_from_pattern_html( $html ) {
		$ids = array();
		if ( ! is_string( $html ) || $html === '' ) {
			return $ids;
		}

		if ( function_exists( 'parse_blocks' ) ) {
			$this->collect_content_fields_from_parsed_blocks( parse_blocks( $html ), $ids );
		}

		if ( preg_match_all( '/\bdata-content-field\s*=\s*["\']([a-z0-9_-]+)["\']/i', $html, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}

		if ( preg_match_all( '/<div[^>]*class="[^"]*wp-block-button[^"]*"[^>]*\sid="([a-z0-9_-]+)"/i', $html, $m2 ) ) {
			$ids = array_merge( $ids, $m2[1] );
		}
		if ( preg_match_all( '/<div[^>]*\sid="([a-z0-9_-]+)"[^>]*class="[^"]*wp-block-button[^"]*"/i', $html, $m3 ) ) {
			$ids = array_merge( $ids, $m3[1] );
		}

		$seen    = array();
		$ordered = array();
		foreach ( $ids as $id ) {
			$id = is_string( $id ) ? trim( $id ) : '';
			if ( $id === '' || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$ordered[]  = $id;
		}

		return $ordered;
	}

	/**
	 * @param array $blocks parse_blocks()-Ausgabe.
	 * @param array $ids    Wird per Referenz befüllt.
	 */
	private function collect_content_fields_from_parsed_blocks( $blocks, array &$ids ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs']['metadata']['name'] ) && is_string( $block['attrs']['metadata']['name'] ) ) {
				$n = $block['attrs']['metadata']['name'];
				if ( preg_match( '/^[a-z0-9_-]+$/i', $n ) ) {
					$ids[] = $n;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collect_content_fields_from_parsed_blocks( $block['innerBlocks'], $ids );
			}
		}
	}

	/**
	 * Plugin-pattern.php + optionales Uploads-Override zusammenführen.
	 *
	 * @param string $slug Pattern-Slug.
	 * @return array
	 */
	private function get_merged_pattern_config( $slug ) {
		$plugin_path = GUTENBLOCK_PRO_PATTERNS_PATH . $slug . '/pattern.php';
		if ( ! file_exists( $plugin_path ) ) {
			return array();
		}
		$base = require $plugin_path;
		if ( ! is_array( $base ) ) {
			$base = array();
		}
		$custom = gutenblock_pro_custom_pattern_file( $slug, 'pattern.php' );
		if ( file_exists( $custom['path'] ) ) {
			$over = require $custom['path'];
			if ( is_array( $over ) ) {
				$base = array_merge( $base, $over );
			}
		}
		return $base;
	}

	/**
	 * pattern.php-Inhalt aus Array (für Uploads-Override).
	 *
	 * @param string $title   Anzeige-Titel (Docblock).
	 * @param array  $config  Pattern-Konfiguration.
	 * @return string PHP-Quelltext.
	 */
	private function build_pattern_php_export( $title, array $config ) {
		$title_safe = str_replace( array( "\r", "\n", '*' ), '', (string) $title );
		$export     = var_export( $config, true );
		return "<?php\n/**\n * Pattern: {$title_safe}\n */\n\nreturn {$export};\n";
	}

	/**
	 * AJAX: Pattern-Meta (pattern.php) in Uploads speichern
	 */
	public function ajax_save_pattern_meta() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$slug = isset( $_POST['pattern'] ) ? sanitize_key( $_POST['pattern'] ) : '';
		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'No pattern' ) );
		}

		$merged = $this->get_merged_pattern_config( $slug );
		if ( empty( $merged ) ) {
			wp_send_json_error( array( 'message' => 'Invalid pattern' ) );
		}

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$ai_hint     = isset( $_POST['ai_hint'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_hint'] ) ) : '';
		$type        = isset( $_POST['type'] ) && $_POST['type'] === 'page' ? 'page' : 'pattern';
		$group       = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : '';
		$premium     = ! empty( $_POST['premium'] );

		$keywords_raw = isset( $_POST['keywords'] ) ? wp_unslash( $_POST['keywords'] ) : '';
		$keywords_arr = array_filter( array_map( 'trim', explode( ',', $keywords_raw ) ) );

		// Tonalitäten: Array aus Checkboxen
		$tones_raw = isset( $_POST['tones'] ) && is_array( $_POST['tones'] ) ? $_POST['tones'] : array();
		$all_valid = GutenBlock_Pro_Tone_Injector::all_tones();
		$tones_arr = array_values( array_intersect( $all_valid, array_map( 'sanitize_key', $tones_raw ) ) );
		if ( empty( $tones_arr ) ) {
			$tones_arr = array( 'neutral' );
		}

		$html_path = $this->get_pattern_resolved_content_html_path( $slug );
		$html      = file_exists( $html_path ) ? file_get_contents( $html_path ) : '';
		$content_fields = $this->extract_content_field_ids_from_pattern_html( $html );

		$categories = isset( $merged['categories'] ) && is_array( $merged['categories'] ) ? $merged['categories'] : array( 'gutenblock-pro' );

		$out = array(
			'title'          => $title !== '' ? $title : $slug,
			'description'    => $description,
			'type'           => $type,
			'group'          => $group,
			'categories'     => $categories,
			'keywords'       => $keywords_arr,
			'content'        => '',
			'premium'        => $premium,
			'ai_hint'        => $ai_hint,
			'tones'          => $tones_arr,
			'content_fields' => $content_fields,
		);
		if ( ! empty( $merged['blockTypes'] ) && is_array( $merged['blockTypes'] ) ) {
			$out['blockTypes'] = $merged['blockTypes'];
		}
		if ( isset( $merged['inserter'] ) ) {
			$out['inserter'] = (bool) $merged['inserter'];
		}

		$custom = gutenblock_pro_custom_pattern_file( $slug, 'pattern.php' );
		if ( ! is_dir( $custom['dir'] ) ) {
			wp_mkdir_p( $custom['dir'] );
		}

		$php = $this->build_pattern_php_export( $out['title'], $out );
		if ( file_put_contents( $custom['path'], $php ) === false ) {
			wp_send_json_error( array( 'message' => 'Could not save pattern.php' ) );
		}

		wp_send_json_success(
			array(
				'size' => size_format( strlen( $php ) ),
			)
		);
	}

	/**
	 * AJAX: Uploads-pattern.php löschen (Meta wieder wie Plugin)
	 */
	public function ajax_reset_pattern_meta() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$slug = isset( $_POST['pattern'] ) ? sanitize_key( $_POST['pattern'] ) : '';
		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'No pattern' ) );
		}

		$custom = gutenblock_pro_custom_pattern_file( $slug, 'pattern.php' );
		if ( file_exists( $custom['path'] ) ) {
			unlink( $custom['path'] );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Preview pattern (renders HTML for iframe)
	 */
	public function ajax_preview_pattern() {
		// Kein Auth-Check nötig: rendert nur öffentliche Block-Patterns (read-only, keine sensiblen Daten).
		// Gutenberg-Plugin führt Preview-Requests über den Canvas-iframe ohne Session-Cookie aus (nopriv).

		// Cross-Origin-iframe-Embedding zulassen (SaaS-Editor → WP-Canvas).
		// WP/admin setzt standardmäßig X-Frame-Options: SAMEORIGIN, was das Embedding
		// in localhost:3000 (SaaS) verhindert. Für diesen rein lesenden Vorschau-Endpoint
		// ist das Embedding ausdrücklich gewollt.
		if ( ! headers_sent() ) {
			header_remove( 'X-Frame-Options' );
			header( 'Content-Security-Policy: frame-ancestors *' );
		}

		$pattern_slug = isset( $_GET['pattern'] ) ? sanitize_key( $_GET['pattern'] ) : '';

		if ( empty( $pattern_slug ) ) {
			wp_die( 'No pattern specified' );
		}

		// Tone aus URL-Parameter lesen (z.B. ?tone=dark)
		$tone_param = isset( $_GET['tone'] ) ? sanitize_key( $_GET['tone'] ) : 'neutral';
		if ( ! GutenBlock_Pro_Tone_Injector::is_valid_tone( $tone_param ) ) {
			$tone_param = 'neutral';
		}

		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern_slug;
		$pattern_file = function_exists( 'gutenblock_pro_resolve_pattern_php_path' )
			? gutenblock_pro_resolve_pattern_php_path( $pattern_slug )
			: $pattern_dir . '/pattern.php';
		$style_file = $pattern_dir . '/style.css';

		// Load content using same logic as load_localized_content()
		// Try files in order of specificity: content-{locale}.html -> content-{lang}.html -> content.html
		$locale = get_locale(); // e.g. de_DE
		$lang = substr( $locale, 0, 2 ); // e.g. de
		
		$files_to_try = array(
			$pattern_dir . '/content-' . $locale . '.html',  // content-de_DE.html
			$pattern_dir . '/content-' . $lang . '.html',    // content-de.html
			$pattern_dir . '/content.html',                   // content.html (fallback)
		);

		$content_file = null;
		foreach ( $files_to_try as $file ) {
			if ( file_exists( $file ) ) {
				$content_file = $file;
				break;
			}
		}

		if ( ! $content_file || ! file_exists( $content_file ) ) {
			wp_die( 'Pattern not found' );
		}

		// Transient-Cache: Key aus Slug, Locale, Tone, Datei-Änderungszeit und Plugin-
		// Version. Der Versions-Bestandteil sorgt dafür, dass jeder Plugin-Update den
		// Vorschau-Cache implizit invalidiert – nötig, wenn sich die Render-Pipeline
		// ändert (z. B. neue URL-Normalisierung) ohne dass die `content.html` selbst
		// touched wurde.
		$plugin_version = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';
		$cache_key = 'gbp_prev_' . substr( md5( $pattern_slug . $locale . $tone_param . filemtime( $content_file ) . $plugin_version ), 0, 20 );
		$cached_html = get_transient( $cache_key );
		if ( false !== $cached_html ) {
			echo $cached_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		ob_start();

		// Check if this is a "page" type pattern
		$is_page_type = false;
		if ( file_exists( $pattern_file ) ) {
			$pattern_data = require $pattern_file;
			$is_page_type = isset( $pattern_data['type'] ) && $pattern_data['type'] === 'page';
		}

		// Get content and render blocks
		$content = file_get_contents( $content_file );

		// Resolve `__PLUGIN_URL__` placeholders and any legacy absolute/root-relative
		// plugin-asset URLs to the current installation's plugin URL. Mirrors the
		// normalization that runs on `register_block_pattern()` so the standalone
		// iframe preview (admin page + Gutenberg sections modal) renders images
		// the exact same way as the FSE pattern inserter.
		if ( class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
			$content = GutenBlock_Pro_Pattern_Loader::normalize_plugin_asset_urls( $content );
		}

		// Ton-Variante injizieren
		if ( $tone_param !== 'neutral' ) {
			$content = GutenBlock_Pro_Tone_Injector::inject( $content, $tone_param );
		}

		// For page type, ensure content is wrapped in a group for proper rendering
		if ( $is_page_type && strpos( $content, '<!-- wp:group' ) === false ) {
			$content = '<!-- wp:group {"align":"full","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">' . $content . '</div><!-- /wp:group --></div><!-- /wp:group -->';
		}
		
		$rendered = do_blocks( $content );

		// Get pattern styles
		$pattern_styles = '';
		if ( file_exists( $style_file ) ) {
			$pattern_styles = file_get_contents( $style_file );
		}

		// Get global styles from theme.json
		$global_styles = '';
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			$global_styles = wp_get_global_stylesheet();
		}

		// Output standalone HTML page for iframe - simulates 1400px desktop viewport
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=1400">
			<?php
			// Reihenfolge wie WordPress: erst wp-block-library, dann Global Styles, dann Block-Varianten
			wp_enqueue_style( 'wp-block-library' );
			wp_enqueue_style( 'wp-block-library-theme' );
			if ( function_exists( 'wp_enqueue_global_styles' ) ) {
				wp_enqueue_global_styles();
			}
			wp_print_styles();
			?>
			<style>
				/* Global Styles aus theme.json (nach wp-block-library, vor Block-Varianten) */
				<?php echo $global_styles; ?>

				/* Force desktop layout - no responsive breakpoints */
				html, body {
					margin: 0;
					padding: 0;
					width: 1400px;
					min-width: 1400px;
					overflow: visible;
					background: #fff;
				}
				/* Override any max-width constraints */
				.wp-site-blocks,
				.wp-block-group.alignfull,
				.alignfull {
					max-width: 100% !important;
					width: 100% !important;
				}
				/*
				 * Hard image constraint fallback. The canonical rule
				 * `.wp-block-image img { max-width: 100%; height: auto }` lives in
				 * wp-block-library.css which is loaded via <link>. If that
				 * stylesheet is slow / blocked by the cache layer the browser may
				 * paint <img> at its intrinsic size before the rule applies,
				 * leading to the "image is bigger than the pattern" symptom that
				 * sometimes clears up on a second open. Inline copy guarantees
				 * the constraint is in effect from the first paint.
				 */
				img {
					max-width: 100%;
					height: auto;
				}
				.wp-block-image img,
				.wp-block-image figure img,
				figure.wp-block-image img {
					max-width: 100%;
					height: auto;
				}
				.wp-block-cover img.wp-block-cover__image-background,
				.wp-block-cover video.wp-block-cover__video-background {
					width: 100%;
					height: 100%;
					object-fit: cover;
				}
			</style>
		</head>
		<body <?php body_class( 'gutenblock-pro-preview' ); ?>>
			<?php echo $rendered; ?>
			<style>
				<?php
				/*
				 * Block-Varianten-CSS NACH dem gerenderten Content ausgeben.
				 * Entspricht dem WordPress-Verhalten, wo per-Block-CSS nach
				 * den Global Styles geladen wird und diese korrekt überschreibt.
				 */

				// Core-Button-Varianten (Kontur/Outline, Squared etc.): In der Preview kann
				// per-Block-CSS fehlen (separate assets, admin-ajax-Kontext). Explizit laden.
				$core_button_css = '';
				if ( function_exists( 'gutenberg_dir_path' ) ) {
					$gutenberg_button = gutenberg_dir_path() . 'build/styles/block-library/button/style.css';
					if ( file_exists( $gutenberg_button ) ) {
						$core_button_css = file_get_contents( $gutenberg_button );
					}
				}
				if ( empty( $core_button_css ) ) {
					$wp_button = ABSPATH . 'wp-includes/blocks/button/style.css';
					if ( file_exists( $wp_button ) ) {
						$core_button_css = file_get_contents( $wp_button );
					}
				}
				if ( ! empty( $core_button_css ) ) {
					echo "/* Core Button (Kontur, Squared etc.) */\n" . $core_button_css . "\n";
				}

				$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;
				if ( is_dir( $blocks_dir ) ) {
					foreach ( glob( $blocks_dir . '*/style.css' ) as $block_css ) {
						echo file_get_contents( $block_css ) . "\n";
					}
				}
				if ( GutenBlock_Pro_Features_Page::is_feature_enabled( 'horizontal-scroll' ) ) {
					echo "/* Horizontal Scroll */\n" . GutenBlock_Pro_Horizontal_Scroll::get_styles() . "\n";
				}
				echo "/* Media Text Stack (Stapel-Optionen + Linkbox) */\n" . GutenBlock_Pro_Media_Text_Stack::get_styles() . "\n";
				echo "/* Pattern specific styles */\n" . $pattern_styles . "\n";
				?>
			</style>
			<?php
			// Layout/Grid-Styles aus der Style-Engine holen (Core + Gutenberg-Plugin-Store)
			$block_support_styles = '';
			if ( function_exists( 'gutenberg_style_engine_get_stylesheet_from_context' ) ) {
				$block_support_styles .= gutenberg_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );
			}
			if ( function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
				$block_support_styles .= wp_style_engine_get_stylesheet_from_context( 'block-supports' );
			}
			if ( $block_support_styles ) {
				echo '<style>' . $block_support_styles . '</style>';
			}
			?>
		</body>
		</html>
		<?php
		$html = ob_get_clean();
		set_transient( $cache_key, $html, HOUR_IN_SECONDS );

		// Persist a static copy in uploads/ so subsequent iframe loads bypass the
		// WordPress bootstrap entirely (web server serves the file directly).
		// See {@see self::write_preview_to_disk()}.
		$this->write_preview_to_disk( $pattern_slug, $locale, $tone_param, $html );

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Absolute filesystem path of the static preview cache directory for the
	 * currently installed plugin version. Auto-creates the directory.
	 */
	private function preview_cache_dir(): string {
		$uploads = wp_upload_dir();
		$version = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';
		$dir = trailingslashit( $uploads['basedir'] ) . 'gutenblock-pro/preview-cache/v' . $version;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Public URL of the static preview cache directory for the current plugin
	 * version. Mirrors {@see self::preview_cache_dir()}.
	 */
	private function preview_cache_url(): string {
		$uploads = wp_upload_dir();
		$version = defined( 'GUTENBLOCK_PRO_VERSION' ) ? GUTENBLOCK_PRO_VERSION : '0';
		return trailingslashit( $uploads['baseurl'] ) . 'gutenblock-pro/preview-cache/v' . $version;
	}

	/**
	 * Build a filesystem-safe filename for a slug/locale/tone combination.
	 * Slug, locale and tone are all sanitised; unknown tone falls back to `neutral`.
	 */
	private function preview_cache_filename( string $slug, string $locale, string $tone ): string {
		$safe_slug   = preg_replace( '/[^a-z0-9_-]/i', '', $slug );
		$safe_locale = preg_replace( '/[^a-zA-Z0-9_-]/', '', $locale );
		$safe_tone   = GutenBlock_Pro_Tone_Injector::is_valid_tone( $tone ) ? $tone : 'neutral';
		return sprintf( '%s-%s-%s.html', $safe_slug, $safe_locale, $safe_tone );
	}

	/**
	 * Atomically write a rendered preview HTML to the static cache file. Best-
	 * effort; failures are silently ignored because the AJAX endpoint can always
	 * regenerate the file on demand.
	 */
	private function write_preview_to_disk( string $slug, string $locale, string $tone, string $html ): void {
		$dir = $this->preview_cache_dir();
		if ( ! is_writable( $dir ) ) {
			return;
		}
		$file = trailingslashit( $dir ) . $this->preview_cache_filename( $slug, $locale, $tone );
		$tmp  = $file . '.' . uniqid( '', true ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $tmp, $html, LOCK_EX ) === false ) {
			return;
		}
		@rename( $tmp, $file );
	}

	/**
	 * Render a single pattern preview HTML *without* sending headers/output.
	 * Used by {@see self::ajax_warm_previews()} to populate many cache files in
	 * one bootstrap. Returns the HTML string or empty on failure.
	 */
	private function render_preview_html( string $slug, string $tone ): string {
		$pattern_dir  = GUTENBLOCK_PRO_PATTERNS_PATH . $slug;
		$pattern_file = function_exists( 'gutenblock_pro_resolve_pattern_php_path' )
			? gutenblock_pro_resolve_pattern_php_path( $slug )
			: $pattern_dir . '/pattern.php';
		$style_file = $pattern_dir . '/style.css';

		$locale = get_locale();
		$lang   = substr( $locale, 0, 2 );
		$files_to_try = array(
			$pattern_dir . '/content-' . $locale . '.html',
			$pattern_dir . '/content-' . $lang . '.html',
			$pattern_dir . '/content.html',
		);
		$content_file = null;
		foreach ( $files_to_try as $file ) {
			if ( file_exists( $file ) ) {
				$content_file = $file;
				break;
			}
		}
		if ( ! $content_file ) {
			return '';
		}

		$is_page_type = false;
		if ( file_exists( $pattern_file ) ) {
			$pattern_data = require $pattern_file;
			$is_page_type = isset( $pattern_data['type'] ) && $pattern_data['type'] === 'page';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $content_file );
		if ( class_exists( 'GutenBlock_Pro_Pattern_Loader' ) ) {
			$content = GutenBlock_Pro_Pattern_Loader::normalize_plugin_asset_urls( $content );
		}
		if ( $tone !== 'neutral' ) {
			$content = GutenBlock_Pro_Tone_Injector::inject( $content, $tone );
		}
		if ( $is_page_type && strpos( $content, '<!-- wp:group' ) === false ) {
			$content = '<!-- wp:group {"align":"full","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">' . $content . '</div><!-- /wp:group --></div><!-- /wp:group -->';
		}

		$rendered = do_blocks( $content );

		$pattern_styles = '';
		if ( file_exists( $style_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$pattern_styles = file_get_contents( $style_file );
		}
		$global_styles = '';
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			$global_styles = wp_get_global_stylesheet();
		}

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=1400">
<?php
wp_enqueue_style( 'wp-block-library' );
wp_enqueue_style( 'wp-block-library-theme' );
if ( function_exists( 'wp_enqueue_global_styles' ) ) {
	wp_enqueue_global_styles();
}
wp_print_styles();
?>
<style><?php echo $global_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
<style><?php echo $pattern_styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
<style>
html,body{margin:0;padding:0}
body{overflow-x:hidden}
/* Inline image constraint — see ajax_preview_pattern() for the rationale. */
img{max-width:100%;height:auto}
.wp-block-image img,
.wp-block-image figure img,
figure.wp-block-image img{max-width:100%;height:auto}
.wp-block-cover img.wp-block-cover__image-background,
.wp-block-cover video.wp-block-cover__video-background{width:100%;height:100%;object-fit:cover}
</style>
</head>
<body>
<?php echo $rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php
$block_support_styles = '';
if ( function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
	$block_support_styles .= wp_style_engine_get_stylesheet_from_context( 'block-supports' );
}
if ( $block_support_styles ) {
	echo '<style>' . $block_support_styles . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>
</body>
</html>
<?php
		return (string) ob_get_clean();
	}

	/**
	 * Server-side warm-up entry point: generate any missing static preview
	 * cache files for the given pattern slugs (neutral tone only) in the
	 * current request. Used by the admin page to amortise the cost across the
	 * existing render cycle instead of paying it per iframe.
	 *
	 * @param string[] $slugs
	 */
	private function prewarm_preview_cache_for( array $slugs ): void {
		if ( empty( $slugs ) ) {
			return;
		}
		$cache_dir = $this->preview_cache_dir();
		$locale    = get_locale();
		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug ) {
				continue;
			}
			$path = trailingslashit( $cache_dir ) . $this->preview_cache_filename( $slug, $locale, 'neutral' );
			if ( file_exists( $path ) ) {
				continue;
			}
			$html = $this->render_preview_html( $slug, 'neutral' );
			if ( $html === '' ) {
				continue;
			}
			$this->write_preview_to_disk( $slug, $locale, 'neutral', $html );
		}
	}

	/**
	 * AJAX: warm the static preview cache for an arbitrary list of patterns and
	 * return a manifest mapping each `{slug}__{tone}` key to the public cache
	 * URL. Caller posts JSON-style:
	 *   POST action=gutenblock_pro_warm_previews
	 *        patterns[][slug]=hero-v1
	 *        patterns[][tone]=neutral
	 * Missing files are generated synchronously; existing files are reused. One
	 * single WordPress bootstrap is amortised across all entries.
	 */
	public function ajax_warm_previews() {
		// Same auth posture as ajax_preview_pattern: previews are read-only and
		// embedded in the editor canvas which strips cookies, so no nonce check.
		$patterns = isset( $_POST['patterns'] ) && is_array( $_POST['patterns'] )
			? wp_unslash( $_POST['patterns'] )
			: array();
		if ( empty( $patterns ) ) {
			wp_send_json_success( array( 'manifest' => array() ) );
		}

		$locale = get_locale();
		$cache_url = $this->preview_cache_url();
		$cache_dir = $this->preview_cache_dir();
		$manifest = array();
		$generated = 0;

		foreach ( $patterns as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['slug'] ) ) {
				continue;
			}
			$slug = sanitize_key( $entry['slug'] );
			$tone = isset( $entry['tone'] ) ? sanitize_key( $entry['tone'] ) : 'neutral';
			if ( ! GutenBlock_Pro_Tone_Injector::is_valid_tone( $tone ) ) {
				$tone = 'neutral';
			}

			$filename = $this->preview_cache_filename( $slug, $locale, $tone );
			$path     = trailingslashit( $cache_dir ) . $filename;

			if ( ! file_exists( $path ) ) {
				$html = $this->render_preview_html( $slug, $tone );
				if ( $html === '' ) {
					continue;
				}
				$this->write_preview_to_disk( $slug, $locale, $tone, $html );
				$generated++;
			}

			$manifest[ $slug . '__' . $tone ] = trailingslashit( $cache_url ) . $filename;
		}

		wp_send_json_success( array(
			'manifest'  => $manifest,
			'generated' => $generated,
			'cached'    => count( $manifest ) - $generated,
		) );
	}

	/**
	 * AJAX: Clear all pattern preview transients (admin only)
	 */
	public function ajax_clear_preview_cache() {
		check_ajax_referer( 'gbp_clear_preview_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_gbp_prev_%'
			   OR option_name LIKE '_transient_timeout_gbp_prev_%'"
		);

		// Also clear static preview cache files for the current version.
		$dir = $this->preview_cache_dir();
		if ( is_dir( $dir ) ) {
			foreach ( glob( trailingslashit( $dir ) . '*.html' ) ?: array() as $file ) {
				@unlink( $file );
			}
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Reset block variant style to plugin default (deletes custom.css from uploads)
	 */
	public function ajax_reset_block_style() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$item = isset( $_POST['block'] ) ? sanitize_key( $_POST['block'] ) : '';
		if ( empty( $item ) ) {
			wp_send_json_error( 'No block specified' );
		}

		$custom = gutenblock_pro_custom_block_file( $item );
		if ( file_exists( $custom['path'] ) ) {
			unlink( $custom['path'] );
		}

		$default_path = GUTENBLOCK_PRO_BLOCKS_PATH . $item . '/style.css';
		$content = file_exists( $default_path ) ? file_get_contents( $default_path ) : '';

		wp_send_json_success( array( 'content' => $content ) );
	}

	/**
	 * AJAX: Reset pattern file to plugin default (deletes custom file from uploads)
	 */
	public function ajax_reset_pattern_file() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$item = isset( $_POST['pattern'] ) ? sanitize_key( $_POST['pattern'] ) : '';
		$file = isset( $_POST['file'] ) ? sanitize_text_field( $_POST['file'] ) : '';

		if ( empty( $item ) || empty( $file ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		$file_map = array(
			'style'   => 'style.css',
			'editor'  => 'editor.css',
			'script'  => 'script.js',
			'content' => 'content.html',
		);

		if ( strpos( $file, 'content_' ) === 0 ) {
			$lang = str_replace( 'content_', '', $file );
			$file_map[ $file ] = 'content-' . $lang . '.html';
		}

		if ( ! isset( $file_map[ $file ] ) ) {
			wp_send_json_error( 'Invalid file type' );
		}

		$filename = $file_map[ $file ];
		$custom   = gutenblock_pro_custom_pattern_file( $item, $filename );

		if ( file_exists( $custom['path'] ) ) {
			unlink( $custom['path'] );
		}

		$default_path = GUTENBLOCK_PRO_PATTERNS_PATH . $item . '/' . $filename;
		$content = file_exists( $default_path ) ? file_get_contents( $default_path ) : '';

		wp_send_json_success( array( 'content' => $content ) );
	}

	/**
	 * AJAX: Adopt current editor content as plugin original (dev mode only).
	 * Writes directly into the plugin directory so the file ships with the next release.
	 */
	public function ajax_adopt_as_original() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! GUTENBLOCK_PRO_DEV ) {
			wp_send_json_error( 'Permission denied' );
		}

		$type    = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'pattern';
		$item    = isset( $_POST['item'] ) ? sanitize_key( $_POST['item'] ) : '';
		$file    = isset( $_POST['file'] ) ? sanitize_text_field( $_POST['file'] ) : '';
		$content = wp_unslash( $_POST['content'] );

		if ( empty( $item ) || empty( $file ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		if ( $type === 'block' ) {
			$target = GUTENBLOCK_PRO_BLOCKS_PATH . $item . '/style.css';
		} else {
			$file_map = array(
				'style'   => 'style.css',
				'editor'  => 'editor.css',
				'script'  => 'script.js',
				'content' => 'content.html',
			);

			if ( strpos( $file, 'content_' ) === 0 ) {
				$lang = str_replace( 'content_', '', $file );
				$file_map[ $file ] = 'content-' . $lang . '.html';
			}

			if ( ! isset( $file_map[ $file ] ) ) {
				wp_send_json_error( 'Invalid file type' );
			}

			$target = GUTENBLOCK_PRO_PATTERNS_PATH . $item . '/' . $file_map[ $file ];
		}

		$target_dir = dirname( $target );
		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$result = file_put_contents( $target, $content );

		if ( $result === false ) {
			wp_send_json_error( 'Could not write file' );
		}

		// Remove custom override so the editor shows the new original
		if ( $type === 'block' ) {
			$custom = gutenblock_pro_custom_block_file( $item );
		} else {
			$custom = gutenblock_pro_custom_pattern_file( $item, $file_map[ $file ] );
		}

		if ( file_exists( $custom['path'] ) ) {
			unlink( $custom['path'] );
		}

		wp_send_json_success( array( 'size' => size_format( strlen( $content ) ) ) );
	}

	/**
	 * AJAX: Delete pattern
	 */
	public function ajax_delete_pattern() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$pattern = sanitize_key( $_POST['pattern'] );

		if ( empty( $pattern ) ) {
			wp_send_json_error( array( 'message' => 'No pattern specified' ) );
		}

		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern;

		if ( ! is_dir( $pattern_dir ) ) {
			wp_send_json_error( array( 'message' => 'Pattern not found' ) );
		}

		// Delete all files in the pattern directory
		$files = glob( $pattern_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		// Remove the directory
		$result = rmdir( $pattern_dir );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Pattern deleted successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not delete pattern directory' ) );
		}
	}

	/**
	 * AJAX: Update pattern group
	 */
	public function ajax_update_group() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$pattern_slug = sanitize_key( $_POST['pattern'] );
		$group = sanitize_key( $_POST['group'] );

		if ( empty( $pattern_slug ) ) {
			wp_send_json_error( array( 'message' => 'No pattern specified' ) );
		}

		$pattern_file = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern_slug . '/pattern.php';

		if ( ! file_exists( $pattern_file ) ) {
			wp_send_json_error( array( 'message' => 'Pattern not found' ) );
		}

		// Read current pattern data
		$pattern_data = require $pattern_file;

		// Update group
		$pattern_data['group'] = $group;

		// Generate PHP file content
		$php_content = "<?php\n/**\n * Pattern: " . ( $pattern_data['title'] ?? $pattern_slug ) . "\n */\n\nreturn " . var_export( $pattern_data, true ) . ";\n";

		// Save file
		$result = file_put_contents( $pattern_file, $php_content );

		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => 'Group updated' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not save pattern file' ) );
		}
	}

	/**
	 * AJAX: Update pattern premium status
	 */
	public function ajax_update_premium() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$pattern_slug = sanitize_key( $_POST['pattern'] );
		$premium = filter_var( $_POST['premium'], FILTER_VALIDATE_BOOLEAN );

		if ( empty( $pattern_slug ) ) {
			wp_send_json_error( array( 'message' => 'No pattern specified' ) );
		}

		$pattern_file = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern_slug . '/pattern.php';

		if ( ! file_exists( $pattern_file ) ) {
			wp_send_json_error( array( 'message' => 'Pattern not found' ) );
		}

		// Read current pattern data
		$pattern_data = require $pattern_file;

		// Update premium status
		$pattern_data['premium'] = $premium;

		// Generate PHP file content
		$php_content = "<?php\n/**\n * Pattern: " . ( $pattern_data['title'] ?? $pattern_slug ) . "\n */\n\nreturn " . var_export( $pattern_data, true ) . ";\n";

		// Save file
		$result = file_put_contents( $pattern_file, $php_content );

		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => 'Premium status updated' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Could not save pattern file' ) );
		}
	}

}

