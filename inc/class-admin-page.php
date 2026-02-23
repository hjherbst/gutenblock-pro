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
		add_action( 'wp_ajax_gutenblock_pro_delete_pattern', array( $this, 'ajax_delete_pattern' ) );
		add_action( 'wp_ajax_gutenblock_pro_reset_block_style', array( $this, 'ajax_reset_block_style' ) );
		add_action( 'wp_ajax_gutenblock_pro_reset_pattern_file', array( $this, 'ajax_reset_pattern_file' ) );
		add_action( 'wp_ajax_gutenblock_pro_update_group', array( $this, 'ajax_update_group' ) );
		add_action( 'wp_ajax_gutenblock_pro_update_premium', array( $this, 'ajax_update_premium' ) );
		add_action( 'wp_ajax_gutenblock_pro_randomize_images', array( $this, 'ajax_randomize_images' ) );
		add_action( 'wp_ajax_gutenblock_pro_search_pexels_image', array( $this, 'ajax_search_pexels_image' ) );
		add_action( 'wp_ajax_gutenblock_pro_search_unsplash_image', array( $this, 'ajax_search_unsplash_image' ) );
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
			'strings'  => array(
				'saved'        => __( 'Gespeichert!', 'gutenblock-pro' ),
				'error'        => __( 'Fehler beim Speichern', 'gutenblock-pro' ),
				'confirmReset' => __( 'Datei wirklich zurücksetzen?', 'gutenblock-pro' ),
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
			$pattern_file = $folder . '/pattern.php';

			if ( ! file_exists( $pattern_file ) ) {
				continue;
			}

			$pattern_data = require $pattern_file;

			// Find all language versions
			$languages = $this->get_pattern_languages( $folder );

			$patterns[ $slug ] = array(
				'slug'        => $slug,
				'title'       => isset( $pattern_data['title'] ) ? $pattern_data['title'] : $slug,
				'description' => isset( $pattern_data['description'] ) ? $pattern_data['description'] : '',
				'type'        => isset( $pattern_data['type'] ) ? $pattern_data['type'] : 'pattern',
				'group'       => isset( $pattern_data['group'] ) ? $pattern_data['group'] : '',
				'premium'     => isset( $pattern_data['premium'] ) ? (bool) $pattern_data['premium'] : false,
				'enabled'     => ! in_array( $slug, $disabled_patterns ),
				'has_style'   => file_exists( $folder . '/style.css' ),
				'has_editor'  => file_exists( $folder . '/editor.css' ),
				'has_script'  => file_exists( $folder . '/script.js' ),
				'has_content' => file_exists( $folder . '/content.html' ),
				'folder'      => $folder,
				'languages'   => $languages,
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
					<?php _e( 'Patterns', 'gutenblock-pro' ); ?>
				</a>
				<a href="?page=gutenblock-pro&tab=blocks" class="nav-tab <?php echo $active_tab === 'blocks' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Blöcke', 'gutenblock-pro' ); ?>
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
		
		foreach ( $patterns as $slug => $pattern ) :
			$preview_url = admin_url( 'admin-ajax.php?action=gutenblock_pro_preview_pattern&pattern=' . $slug );
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
				<a href="<?php echo esc_url( $edit_url ); ?>" class="pattern-card-preview-link">
					<div class="pattern-card-preview">
						<iframe src="<?php echo esc_url( $preview_url ); ?>" loading="lazy" sandbox="allow-same-origin allow-scripts allow-popups" tabindex="-1"></iframe>
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
						<?php _e( 'Patterns', 'gutenblock-pro' ); ?>
					</button>
					<button type="button" class="sidebar-tab <?php echo $selected_type === 'block' ? 'active' : ''; ?>" data-type="block">
						<?php _e( 'Blöcke', 'gutenblock-pro' ); ?>
					</button>
				</div>
				
				<?php if ( $selected_type === 'pattern' ) : ?>
					<h3><?php _e( 'Patterns', 'gutenblock-pro' ); ?></h3>
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
							<span class="save-status"></span>
							<span class="custom-indicator" style="display:none; margin-left:12px; color:#d63638; font-style:italic;">
								<?php _e( 'Angepasst', 'gutenblock-pro' ); ?>
							</span>
						</div>
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
						<th><?php _e( 'Patterns gesamt', 'gutenblock-pro' ); ?></th>
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
				<p><?php _e( 'GutenBlock Pro lädt CSS und JS nur für Patterns, die auf der aktuellen Seite verwendet werden.', 'gutenblock-pro' ); ?></p>
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
						<th><?php _e( 'Patterns-Verzeichnis', 'gutenblock-pro' ); ?></th>
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
	 * AJAX: Preview pattern (renders HTML for iframe)
	 */
	public function ajax_preview_pattern() {
		// Allow without nonce for iframe src
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		$pattern_slug = isset( $_GET['pattern'] ) ? sanitize_key( $_GET['pattern'] ) : '';

		if ( empty( $pattern_slug ) ) {
			wp_die( 'No pattern specified' );
		}

		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern_slug;
		$pattern_file = $pattern_dir . '/pattern.php';
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

		// Check if this is a "page" type pattern
		$is_page_type = false;
		if ( file_exists( $pattern_file ) ) {
			$pattern_data = require $pattern_file;
			$is_page_type = isset( $pattern_data['type'] ) && $pattern_data['type'] === 'page';
		}

		// Get content and render blocks
		$content = file_get_contents( $content_file );
		
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
			// Enqueue block library styles
			wp_enqueue_style( 'wp-block-library' );
			wp_enqueue_style( 'wp-block-library-theme' );
			
			// Enqueue theme styles if available
			if ( function_exists( 'wp_enqueue_global_styles' ) ) {
				wp_enqueue_global_styles();
			}
			
			wp_print_styles();
			?>
			<style>
				/* Global Styles from theme.json */
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
				/* Pattern specific styles */
				<?php echo $pattern_styles; ?>
			</style>
		</head>
		<body <?php body_class( 'gutenblock-pro-preview' ); ?>>
			<?php echo $rendered; ?>
		</body>
		</html>
		<?php
		exit;
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

	/**
	 * AJAX: Randomize images in pattern
	 */
	public function ajax_randomize_images() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		// Only allow user "hjherbst"
		$current_user = wp_get_current_user();
		if ( $current_user->user_login !== 'hjherbst' ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$pattern_slug = sanitize_key( $_POST['pattern'] ?? '' );

		if ( empty( $pattern_slug ) ) {
			wp_send_json_error( array( 'message' => 'No pattern specified' ) );
		}

		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern_slug;
		
		if ( ! is_dir( $pattern_dir ) ) {
			wp_send_json_error( array( 'message' => 'Pattern not found' ) );
		}

		// Find all content files (default and language-specific)
		$content_files = glob( $pattern_dir . '/content*.html' );
		$updated_files = array();

		foreach ( $content_files as $content_file ) {
			$content = file_get_contents( $content_file );
			$original_content = $content;
			
			// Replace all picsum.photos URLs with new random seeds
			$image_counter = 0;
			
			// Replace in img src attributes (picsum.photos)
			$content = preg_replace_callback(
				'/(<img[^>]+src=["\'])(https:\/\/picsum\.photos\/seed\/[^\/]+\/(\d+)\/(\d+))(["\'])/i',
				function( $matches ) use ( $pattern_slug, &$image_counter ) {
					$width = $matches[3];
					$height = $matches[4];
					// Extract search query from seed if present
					$old_seed = $matches[2];
					$search_query = '';
					if (preg_match('/seed\/(query-[^\/]+)\//', $old_seed, $seed_matches)) {
						$query_part = str_replace('query-', '', $seed_matches[1]);
						$query_parts = explode('-', $query_part, 2);
						if (!empty($query_parts[0])) {
							$search_query = str_replace('-', '%', $query_parts[0]);
							$search_query = urldecode($search_query);
						}
					}
					
					// Generate new random seed (preserve search query if present)
					if ($search_query) {
						$encoded_query = urlencode($search_query);
						$new_seed = 'query-' . str_replace('%', '-', $encoded_query) . '-' . time() . '-' . wp_rand( 1000, 9999 );
					} else {
						$new_seed = $pattern_slug . '-' . $image_counter . '-' . time() . '-' . wp_rand( 1000, 9999 );
					}
					$image_counter++;
					$new_url = "https://picsum.photos/seed/{$new_seed}/{$width}/{$height}";
					return $matches[1] . $new_url . $matches[5];
				},
				$content
			);

			// Replace in wp:image block JSON attributes
			$content = preg_replace_callback(
				'/"url":"(https:\/\/picsum\.photos\/seed\/([^\/]+)\/(\d+)\/(\d+))"/',
				function( $matches ) use ( $pattern_slug, &$image_counter ) {
					$width = $matches[3];
					$height = $matches[4];
					$old_seed = $matches[2];
					
					// Extract search query from seed if present
					$search_query = '';
					if (strpos($old_seed, 'query-') === 0) {
						$query_part = str_replace('query-', '', $old_seed);
						$query_parts = explode('-', $query_part, 2);
						if (!empty($query_parts[0])) {
							$search_query = str_replace('-', '%', $query_parts[0]);
							$search_query = urldecode($search_query);
						}
					}
					
					// Generate new random seed (preserve search query if present)
					if ($search_query) {
						$encoded_query = urlencode($search_query);
						$new_seed = 'query-' . str_replace('%', '-', $encoded_query) . '-' . time() . '-' . wp_rand( 1000, 9999 );
					} else {
						$new_seed = $pattern_slug . '-' . $image_counter . '-' . time() . '-' . wp_rand( 1000, 9999 );
					}
					$image_counter++;
					$new_url = "https://picsum.photos/seed/{$new_seed}/{$width}/{$height}";
					return '"url":"' . $new_url . '"';
				},
				$content
			);

			// Only save if content changed
			if ( $content !== $original_content ) {
				file_put_contents( $content_file, $content );
				$updated_files[] = basename( $content_file );
			}
		}

		if ( ! empty( $updated_files ) ) {
			wp_send_json_success( array(
				'message' => __( 'Bilder erfolgreich aktualisiert', 'gutenblock-pro' ),
				'files'   => $updated_files,
				'count'   => count( $updated_files ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Keine picsum.photos Bilder gefunden', 'gutenblock-pro' ),
			) );
		}
	}

	/**
	 * AJAX: Search Pexels image by query
	 */
	public function ajax_search_pexels_image() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		// Only allow user "hjherbst"
		$current_user = wp_get_current_user();
		if ( $current_user->user_login !== 'hjherbst' ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$api_key = get_option( 'gutenblock_pro_pexels_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Pexels API Key nicht konfiguriert' ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		$width = isset( $_POST['width'] ) ? intval( $_POST['width'] ) : 800;
		$height = isset( $_POST['height'] ) ? intval( $_POST['height'] ) : 600;

		if ( empty( $query ) ) {
			wp_send_json_error( array( 'message' => 'Kein Suchbegriff angegeben' ) );
		}

		// Search Pexels API
		// Note: Pexels API only returns photos under Pexels License (free for commercial use)
		// All photos from the API are guaranteed to be free to use without restrictions
		// Improve search by adding more results and orientation for better contextual matching
		$api_url = 'https://api.pexels.com/v1/search?query=' . urlencode( $query ) . '&per_page=30&orientation=landscape&size=large';
		
		$response = wp_remote_get( $api_url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => $api_key,
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Fehler beim Abrufen von Pexels: ' . $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['photos'] ) || empty( $body['photos'] ) ) {
			wp_send_json_error( array( 'message' => 'Keine Bilder gefunden für: ' . $query ) );
		}

		// Filter photos - only use photos that are free to use
		// Pexels API returns photos under Pexels License (free for commercial use)
		// We only use photos from the API which are guaranteed to be free
		$available_photos = array();
		foreach ( $body['photos'] as $photo ) {
			// Pexels API only returns free photos, but we verify it's a valid photo object
			if ( isset( $photo['src'] ) && isset( $photo['src']['original'] ) ) {
				$available_photos[] = $photo;
			}
		}

		if ( empty( $available_photos ) ) {
			wp_send_json_error( array( 'message' => 'Keine verwendbaren Bilder gefunden für: ' . $query ) );
		}

		// Pick a random photo from available photos
		$random_photo = $available_photos[ array_rand( $available_photos ) ];
		
		// Get image URL - Pexels provides different sizes
		// Use large (2048px) or medium (1280px) for better quality
		$image_url = $random_photo['src']['original'];
		if ( isset( $random_photo['src']['large'] ) ) {
			$image_url = $random_photo['src']['large'];
		} elseif ( isset( $random_photo['src']['medium'] ) ) {
			$image_url = $random_photo['src']['medium'];
		}

		wp_send_json_success( array(
			'url'    => $image_url,
			'width'  => $width,
			'height' => $height,
			'photographer' => $random_photo['photographer'] ?? '',
			'photographer_url' => $random_photo['photographer_url'] ?? '',
		) );
	}

	/**
	 * AJAX: Search Unsplash image by query
	 */
	public function ajax_search_unsplash_image() {
		check_ajax_referer( 'gutenblock_pro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		// Only allow user "hjherbst"
		$current_user = wp_get_current_user();
		if ( $current_user->user_login !== 'hjherbst' ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$api_key = get_option( 'gutenblock_pro_unsplash_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'Unsplash API Key nicht konfiguriert' ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		$width = isset( $_POST['width'] ) ? intval( $_POST['width'] ) : 800;
		$height = isset( $_POST['height'] ) ? intval( $_POST['height'] ) : 600;

		if ( empty( $query ) ) {
			wp_send_json_error( array( 'message' => 'Kein Suchbegriff angegeben' ) );
		}

		// Search Unsplash API
		// Note: Unsplash API returns photos under Unsplash License (free for commercial use)
		// All photos from the API are guaranteed to be free to use
		$api_url = 'https://api.unsplash.com/search/photos?query=' . urlencode( $query ) . '&per_page=20&orientation=landscape';
		
		$response = wp_remote_get( $api_url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Client-ID ' . $api_key,
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Fehler beim Abrufen von Unsplash: ' . $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = isset( $body['errors'] ) ? implode( ', ', $body['errors'] ) : 'HTTP ' . $response_code;
			wp_send_json_error( array( 'message' => 'Unsplash API Fehler: ' . $error_message ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['results'] ) || empty( $body['results'] ) ) {
			wp_send_json_error( array( 'message' => 'Keine Bilder gefunden für: ' . $query ) );
		}

		// Filter photos - only use photos that are free to use
		// Unsplash API returns photos under Unsplash License (free for commercial use)
		$available_photos = array();
		foreach ( $body['results'] as $photo ) {
			// Unsplash API only returns free photos, but we verify it's a valid photo object
			if ( isset( $photo['urls'] ) && isset( $photo['urls']['regular'] ) ) {
				$available_photos[] = $photo;
			}
		}

		if ( empty( $available_photos ) ) {
			wp_send_json_error( array( 'message' => 'Keine verwendbaren Bilder gefunden für: ' . $query ) );
		}

		// Pick a random photo from available photos
		$random_photo = $available_photos[ array_rand( $available_photos ) ];
		
		// Get image URL - Unsplash provides different sizes
		// Use regular (1080px) for good quality, or full if available
		$image_url = $random_photo['urls']['regular'];
		if ( isset( $random_photo['urls']['full'] ) ) {
			$image_url = $random_photo['urls']['full'];
		}

		wp_send_json_success( array(
			'url'    => $image_url,
			'width'  => $width,
			'height' => $height,
			'photographer' => $random_photo['user']['name'] ?? '',
			'photographer_url' => $random_photo['user']['links']['html'] ?? '',
		) );
	}
}

