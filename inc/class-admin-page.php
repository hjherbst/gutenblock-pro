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
		add_action( 'wp_ajax_gutenblock_pro_update_group', array( $this, 'ajax_update_group' ) );
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
		$single_patterns = array_filter( $patterns, function( $p ) { return $p['type'] !== 'page'; } );
		?>

		<?php if ( empty( $patterns ) ) : ?>
			<div class="notice notice-warning">
				<p><?php _e( 'Keine Patterns gefunden.', 'gutenblock-pro' ); ?></p>
			</div>
		<?php else : ?>

			<?php if ( ! empty( $pages ) ) : ?>
			<h2 class="patterns-section-title"><?php _e( 'Seiten', 'gutenblock-pro' ); ?></h2>
			<div class="gutenblock-pro-patterns-grid">
				<?php $this->render_pattern_cards( $pages ); ?>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $single_patterns ) ) : ?>
			<h2 class="patterns-section-title"><?php _e( 'Patterns', 'gutenblock-pro' ); ?></h2>
			<div class="gutenblock-pro-patterns-grid">
				<?php $this->render_pattern_cards( $single_patterns ); ?>
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
		
		foreach ( $patterns as $slug => $pattern ) :
			$preview_url = admin_url( 'admin-ajax.php?action=gutenblock_pro_preview_pattern&pattern=' . $slug );
			$edit_url = admin_url( 'admin.php?page=gutenblock-pro&tab=editor&pattern=' . $slug );
		?>
			<div class="pattern-card <?php echo $pattern['enabled'] ? 'enabled' : 'disabled'; ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
				<div class="pattern-card-header">
					<h3><?php echo esc_html( $pattern['title'] ); ?></h3>
					<div class="pattern-card-actions">
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
						<iframe src="<?php echo esc_url( $preview_url ); ?>" loading="lazy" sandbox="allow-same-origin" tabindex="-1"></iframe>
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
		$selected_pattern = isset( $_GET['pattern'] ) ? sanitize_key( $_GET['pattern'] ) : '';
		$selected_file = isset( $_GET['file'] ) ? sanitize_key( $_GET['file'] ) : 'style';
		
		if ( empty( $selected_pattern ) && ! empty( $patterns ) ) {
			$selected_pattern = array_key_first( $patterns );
		}
		?>
		<div class="gutenblock-pro-editor">
			<div class="editor-sidebar">
				<h3><?php _e( 'Patterns', 'gutenblock-pro' ); ?></h3>
				<ul class="pattern-list">
					<?php foreach ( $patterns as $slug => $pattern ) : ?>
						<li class="<?php echo $slug === $selected_pattern ? 'active' : ''; ?>">
							<a href="?page=gutenblock-pro&tab=editor&pattern=<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $pattern['title'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="editor-main">
				<?php if ( $selected_pattern && isset( $patterns[ $selected_pattern ] ) ) : 
					$pattern = $patterns[ $selected_pattern ];
				?>
					<div class="editor-header">
						<h2><?php echo esc_html( $pattern['title'] ); ?></h2>
						<div class="file-tabs">
							<a href="?page=gutenblock-pro&tab=editor&pattern=<?php echo esc_attr( $selected_pattern ); ?>&file=style" 
							   class="file-tab <?php echo $selected_file === 'style' ? 'active' : ''; ?> <?php echo $pattern['has_style'] ? '' : 'no-file'; ?>">
								style.css
							</a>
							<a href="?page=gutenblock-pro&tab=editor&pattern=<?php echo esc_attr( $selected_pattern ); ?>&file=editor" 
							   class="file-tab <?php echo $selected_file === 'editor' ? 'active' : ''; ?> <?php echo $pattern['has_editor'] ? '' : 'no-file'; ?>">
								editor.css
							</a>
							<a href="?page=gutenblock-pro&tab=editor&pattern=<?php echo esc_attr( $selected_pattern ); ?>&file=script" 
							   class="file-tab <?php echo $selected_file === 'script' ? 'active' : ''; ?> <?php echo $pattern['has_script'] ? '' : 'no-file'; ?>">
								script.js
							</a>
							<a href="?page=gutenblock-pro&tab=editor&pattern=<?php echo esc_attr( $selected_pattern ); ?>&file=content" 
							   class="file-tab <?php echo $selected_file === 'content' ? 'active' : ''; ?> <?php echo $pattern['has_content'] ? '' : 'no-file'; ?>">
								content.html
							</a>
							<?php 
							// Show language-specific content files
							foreach ( $pattern['languages'] as $lang ) :
								if ( $lang === 'default' ) continue;
								$lang_file = 'content_' . $lang;
							?>
							<a href="?page=gutenblock-pro&tab=editor&pattern=<?php echo esc_attr( $selected_pattern ); ?>&file=<?php echo esc_attr( $lang_file ); ?>" 
							   class="file-tab lang-file <?php echo $selected_file === $lang_file ? 'active' : ''; ?>">
								<?php echo strtoupper( $lang ); ?>
							</a>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="editor-content">
						<textarea id="gutenblock-pro-code-editor" 
						          data-pattern="<?php echo esc_attr( $selected_pattern ); ?>" 
						          data-file="<?php echo esc_attr( $selected_file ); ?>"
						          data-type="<?php echo $selected_file === 'script' ? 'javascript' : ( $selected_file === 'content' ? 'html' : 'css' ); ?>"></textarea>
						
						<div class="editor-actions">
							<button type="button" class="button button-primary" id="save-file">
								<span class="dashicons dashicons-saved"></span>
								<?php _e( 'Speichern', 'gutenblock-pro' ); ?>
							</button>
							<span class="save-status"></span>
						</div>
					</div>
				<?php else : ?>
					<div class="no-pattern-selected">
						<p><?php _e( 'Wähle ein Pattern aus der Liste.', 'gutenblock-pro' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
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

		$pattern = sanitize_key( $_POST['pattern'] );
		$file = sanitize_text_field( $_POST['file'] );

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

		$file_path = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern . '/' . $file_map[ $file ];

		if ( file_exists( $file_path ) ) {
			$content = file_get_contents( $file_path );
			wp_send_json_success( array( 'content' => $content ) );
		} else {
			wp_send_json_success( array( 'content' => '' ) );
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

		$pattern = sanitize_key( $_POST['pattern'] );
		$file = sanitize_text_field( $_POST['file'] );
		$content = wp_unslash( $_POST['content'] );

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

		$file_path = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern . '/' . $file_map[ $file ];
		$pattern_dir = GUTENBLOCK_PRO_PATTERNS_PATH . $pattern;

		// Create directory if it doesn't exist
		if ( ! is_dir( $pattern_dir ) ) {
			wp_mkdir_p( $pattern_dir );
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
		$content_file = $pattern_dir . '/content.html';
		$style_file = $pattern_dir . '/style.css';

		if ( ! file_exists( $content_file ) ) {
			wp_die( 'Pattern not found' );
		}

		// Get content and render blocks
		$content = file_get_contents( $content_file );
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
}

