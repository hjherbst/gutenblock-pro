<?php
/**
 * Block Registry - Register custom blocks and block variants
 *
 * @package GutenBlockPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GutenBlock_Pro_Block_Registry {

	/**
	 * Registered block variants
	 *
	 * @var array
	 */
	private $block_variants = array();

	/**
	 * Default block variant definitions bundled with the plugin.
	 * Used to auto-create missing block.json and style.css files on any installation.
	 */
	private static $default_blocks = array(
		'button-arrow-circle' => array(
			'config' => array(
				'block'       => 'core/button',
				'name'        => 'button-arrow-circle',
				'label'       => 'Arrow Circle',
				'type'        => 'variant',
				'description' => 'Pill-förmiger Button mit eingebettetem Kreis-Pfeil rechts und Hover-Animation',
			),
			'css' => '.wp-block-button.is-style-button-arrow-circle .wp-block-button__link {
	position: relative;
	display: inline-block;
	background-color: transparent;
	color: black;
	padding: 0.75em 5em 0.75em 1.25em;
	border: 1px solid #333;
	border-radius: 999px;
	text-decoration: none;
	transition: all 0.3s ease;
	overflow: hidden;
}
.wp-block-button.is-style-button-arrow-circle .wp-block-button__link::after {
	content: \'\';
	position: absolute;
	right: 0.4em;
	top: 50%;
	transform: translateY(-50%);
	width: 38px;
	height: 38px;
	background-image: url("data:image/svg+xml,%3Csvg width=\'44\' height=\'44\' viewBox=\'0 0 44 44\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Ccircle cx=\'22\' cy=\'22\' r=\'22\' fill=\'var(--wp--preset--color--contrast,%23333)\'/%3E%3Cpath d=\'M29.2431 22.7071C29.6336 22.3166 29.6336 21.6834 29.2431 21.2929L22.8791 14.9289C22.4886 14.5384 21.8554 14.5384 21.4649 14.9289C21.0744 15.3195 21.0744 15.9526 21.4649 16.3431L27.1217 22L21.4649 27.6569C21.0744 28.0474 21.0744 28.6805 21.4649 29.0711C21.8554 29.4616 22.4886 29.4616 22.8791 29.0711L29.2431 22.7071ZM16 22V23L28.536 23V22V21L16 21V22Z\' fill=\'white\'/%3E%3C/svg%3E");
	background-size: cover;
	background-repeat: no-repeat;
	transition: transform 0.3s ease;
}
.wp-block-button.is-style-button-arrow-circle .wp-block-button__link:hover::after {
	transform: translateY(-50%) translateX(-5px);
}
.wp-block-button.is-style-button-arrow-circle .wp-block-button__link.fly::after {
	transform: translateY(-50%) translateX(200%);
}',
		),
		'button-simple'       => array(
			'config' => array(
				'block'       => 'core/button',
				'name'        => 'button-simple',
				'label'       => 'Simple',
				'type'        => 'variant',
				'description' => 'Transparenter Button ohne Hintergrund – nur Text mit Pfeil-Icon und Hover-Animation',
			),
			'css' => '.wp-block-button.is-style-button-simple .wp-block-button__link {
	position: relative;
	display: inline-flex;
	align-items: center;
	gap: 0.5em;
	background-color: transparent;
	border: none;
	padding: 0 1.5em 0 0;
	text-decoration: none;
	overflow: hidden;
	transition: color 0.3s ease;
	color: var(--wp--preset--color--contrast, inherit);
}
.wp-block-button.is-style-button-simple .wp-block-button__link::after {
	content: \'\';
	position: absolute;
	right: 0;
	top: 50%;
	width: 1em;
	height: 1em;
	background-color: currentColor;
	-webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'white\' stroke-width=\'2.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'M9 6l6 6-6 6\'/%3E%3C/svg%3E");
	mask-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'white\' stroke-width=\'2.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'M9 6l6 6-6 6\'/%3E%3C/svg%3E");
	-webkit-mask-size: contain;
	mask-size: contain;
	-webkit-mask-repeat: no-repeat;
	mask-repeat: no-repeat;
	-webkit-mask-position: center;
	mask-position: center;
	transform: translateY(-50%);
	transition: transform 0.3s ease;
}
.wp-block-button.is-style-button-simple .wp-block-button__link:hover::after {
	transform: translateY(-50%) translateX(-5px);
}
.wp-block-button.is-style-button-simple .wp-block-button__link.fly::after {
	transform: translateY(-50%) translateX(200%);
}',
		),
		'checkmark-list'      => array(
			'config' => array(
				'block'       => 'core/list',
				'name'        => 'checkmark-list',
				'label'       => 'Checkmark',
				'type'        => 'variant',
				'description' => 'Zeigt Checkmarks (✓) statt Bullets für alle Listenelemente',
			),
			'css' => 'ul.is-style-checkmark-list {
	list-style-type: "\2713";
}
ul.is-style-checkmark-list li {
	padding-inline-start: 1ch;
}
.block-editor-block-list__layout ul.is-style-checkmark-list {
	list-style-type: "\2713";
}
.block-editor-block-list__layout ul.is-style-checkmark-list li {
	padding-inline-start: 1ch;
}',
		),
		'space-between'       => array(
			'config' => array(
				'block'       => 'core/group',
				'name'        => 'space-between',
				'label'       => 'Space Between',
				'type'        => 'variant',
				'description' => 'Vertikale Verteilung: Inhalte füllen die volle Höhe mit gleichmäßigem Abstand',
			),
			'css' => '.wp-block-group.is-style-space-between {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	height: 100%;
}
.wp-block-group.is-style-space-between > * {
	flex: 0 0 auto;
}
.wp-block-column:has(> .is-style-space-between) {
	align-self: stretch !important;
}',
		),
		'step-circle'         => array(
			'config' => array(
				'block'       => 'core/paragraph',
				'name'        => 'step-circle',
				'label'       => 'Step Circle',
				'type'        => 'variant',
				'description' => 'Zeigt den Absatz als nummerierte Kreisfläche (z.B. für Schritte)',
			),
			'css' => '.wp-block-paragraph.is-style-step-circle,
p.is-style-step-circle {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 2.5rem;
	height: 2.5rem;
	border-radius: 50%;
	background-color: #fff;
	color: #1e3a8a;
	font-weight: 700;
	line-height: 1;
	margin-bottom: 1rem;
	padding: 0;
	margin-left: 0 !important;
	margin-right: auto !important;
}
.wp-block-paragraph.is-style-step-circle.has-text-align-center,
p.is-style-step-circle.has-text-align-center {
	margin-left: auto !important;
	margin-right: auto !important;
}
.wp-block-paragraph.is-style-step-circle.has-text-align-right,
p.is-style-step-circle.has-text-align-right {
	margin-left: auto !important;
	margin-right: 0 !important;
}',
		),
		'vertical-center'     => array(
			'config' => array(
				'block'       => 'core/group',
				'name'        => 'vertical-center',
				'label'       => 'Vertikal zentriert',
				'type'        => 'variant',
				'description' => 'Zentriert den Inhalt vertikal per Flexbox',
			),
			'css' => '.wp-block-group.is-style-vertical-center {
	display: flex;
	flex-direction: column;
	justify-content: center;
}',
		),
	);

	/**
	 * Initialize the block registry
	 */
	public function init() {
		$this->ensure_default_blocks();
		add_action( 'init', array( $this, 'register_block_variants' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'init', array( $this, 'register_block_styles' ) );
	}

	/**
	 * Ensure all default block definitions exist on disk.
	 * Creates missing directories, block.json and style.css files so the plugin
	 * is self-contained regardless of which version was deployed.
	 */
	private function ensure_default_blocks() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			wp_mkdir_p( $blocks_dir );
		}

		foreach ( self::$default_blocks as $slug => $block ) {
			$folder      = $blocks_dir . $slug;
			$config_file = $folder . '/block.json';
			$style_file  = $folder . '/style.css';

			if ( ! is_dir( $folder ) ) {
				wp_mkdir_p( $folder );
			}

			if ( ! file_exists( $config_file ) ) {
				file_put_contents(
					$config_file,
					wp_json_encode( $block['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"
				);
			}

			if ( ! file_exists( $style_file ) && ! empty( $block['css'] ) ) {
				file_put_contents( $style_file, $block['css'] . "\n" );
			}
		}
	}

	/**
	 * Register block variants
	 */
	public function register_block_variants() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$config_file = $folder . '/block.json';
			$style_file = $folder . '/style.css';

			// Load block configuration
			$config = $this->load_block_config( $slug, $config_file );

			if ( ! $config ) {
				continue;
			}

			// Store for admin display (immer, unabhängig vom Toggle)
			$this->block_variants[] = array(
				'slug'        => $slug,
				'block'       => $config['block'],
				'name'        => $config['name'],
				'label'       => $config['label'],
				'type'        => $config['type'] ?? 'variant',
				'description' => $config['description'] ?? '',
				'folder'      => $folder,
				'has_style'   => file_exists( $style_file ),
			);

			// Nur registrieren/laden wenn Stilvariante aktiviert ist
			if ( ! GutenBlock_Pro_Features_Page::is_block_variant_enabled( $slug ) ) {
				continue;
			}

			register_block_style(
				$config['block'],
				array(
					'name'  => $config['name'],
					'label' => $config['label'],
				)
			);
		}
	}

	/**
	 * Load block configuration from JSON or use defaults
	 *
	 * @param string $slug Block variant slug
	 * @param string $config_file Path to block.json
	 * @return array|null
	 */
	private function load_block_config( $slug, $config_file ) {
		if ( file_exists( $config_file ) ) {
			$config = json_decode( file_get_contents( $config_file ), true );
			if ( $config ) {
				return $config;
			}
		}

		// Fallback: use inline defaults if available
		if ( isset( self::$default_blocks[ $slug ]['config'] ) ) {
			return self::$default_blocks[ $slug ]['config'];
		}

		return null;
	}

	/**
	 * Get all registered block variants
	 *
	 * @return array
	 */
	public function get_block_variants() {
		// If variants not yet populated, discover them from filesystem
		if ( empty( $this->block_variants ) ) {
			$this->discover_block_variants();
		}
		return $this->block_variants;
	}

	/**
	 * Discover block variants from filesystem
	 */
	private function discover_block_variants() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$config_file = $folder . '/block.json';
			$style_file = $folder . '/style.css';

			$config = $this->load_block_config( $slug, $config_file );

			if ( $config ) {
				$this->block_variants[] = array(
					'slug'        => $slug,
					'block'       => $config['block'],
					'name'        => $config['name'],
					'label'       => $config['label'],
					'type'        => $config['type'] ?? 'variant',
					'description' => $config['description'] ?? '',
					'folder'      => $folder,
					'has_style'   => file_exists( $style_file ),
				);
			}
		}
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_assets() {
		$this->enqueue_block_variant_styles( 'editor' );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		$this->enqueue_block_variant_styles( 'frontend' );
		if ( ! is_admin() ) {
			wp_enqueue_script(
				'gutenblock-pro-button-styles',
				GUTENBLOCK_PRO_URL . 'assets/js/button-styles.js',
				array(),
				GUTENBLOCK_PRO_VERSION,
				true
			);
		}
	}

	/**
	 * Register block styles for FSE iframe support
	 */
	public function register_block_styles() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$config_file = $folder . '/block.json';
			$style_file = $folder . '/style.css';

			if ( ! file_exists( $style_file ) ) {
				continue;
			}

			if ( ! GutenBlock_Pro_Features_Page::is_block_variant_enabled( $slug ) ) {
				continue;
			}

			$config = $this->load_block_config( $slug, $config_file );
			if ( ! $config ) {
				continue;
			}

			// Register for FSE iframe support (supports single block name or array of block names)
			$block_types = is_array( $config['block'] ) ? $config['block'] : array( $config['block'] );
			$custom      = gutenblock_pro_custom_block_file( $slug );

			foreach ( $block_types as $block_type ) {
				wp_enqueue_block_style(
					$block_type,
					array(
						'handle' => 'gutenblock-pro-block-' . $slug,
						'src'    => GUTENBLOCK_PRO_URL . 'blocks/' . $slug . '/style.css',
						'ver'    => filemtime( $style_file ),
						'path'   => $style_file,
					)
				);

				if ( file_exists( $custom['path'] ) ) {
					wp_enqueue_block_style(
						$block_type,
						array(
							'handle' => 'gutenblock-pro-block-' . $slug . '-custom',
							'src'    => $custom['url'],
							'ver'    => filemtime( $custom['path'] ),
							'path'   => $custom['path'],
						)
					);
				}
			}
		}
	}

	/**
	 * Enqueue styles for all block variants
	 *
	 * @param string $context 'editor' or 'frontend'
	 */
	private function enqueue_block_variant_styles( $context = 'frontend' ) {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

		foreach ( $block_folders as $folder ) {
			$slug = basename( $folder );
			$style_file = $folder . '/style.css';

			if ( ! GutenBlock_Pro_Features_Page::is_block_variant_enabled( $slug ) ) {
				continue;
			}

			if ( file_exists( $style_file ) ) {
				$handle = 'gutenblock-pro-block-' . $slug;
				wp_enqueue_style(
					$handle,
					GUTENBLOCK_PRO_URL . 'blocks/' . $slug . '/style.css',
					array(),
					filemtime( $style_file )
				);

				$custom = gutenblock_pro_custom_block_file( $slug );
				if ( file_exists( $custom['path'] ) ) {
					wp_enqueue_style(
						$handle . '-custom',
						$custom['url'],
						array( $handle ),
						filemtime( $custom['path'] )
					);
				}
			}
		}
	}
}
