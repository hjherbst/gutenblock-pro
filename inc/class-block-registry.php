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
	 * Used to auto-create missing block.json files on any installation.
	 */
	private static $default_blocks = array(
		'button-arrow-circle' => array(
			'block'       => 'core/button',
			'name'        => 'button-arrow-circle',
			'label'       => 'Arrow Circle',
			'type'        => 'variant',
			'description' => 'Pill-förmiger Button mit eingebettetem Kreis-Pfeil rechts und Hover-Animation',
		),
		'button-simple'       => array(
			'block'       => 'core/button',
			'name'        => 'button-simple',
			'label'       => 'Simple',
			'type'        => 'variant',
			'description' => 'Transparenter Button ohne Hintergrund – nur Text mit Pfeil-Icon und Hover-Animation',
		),
		'checkmark-list'      => array(
			'block'       => 'core/list',
			'name'        => 'checkmark-list',
			'label'       => 'Checkmark',
			'type'        => 'variant',
			'description' => 'Zeigt Checkmarks (✓) statt Bullets für alle Listenelemente',
		),
		'space-between'       => array(
			'block'       => 'core/group',
			'name'        => 'space-between',
			'label'       => 'Space Between',
			'type'        => 'variant',
			'description' => 'Vertikale Verteilung: Inhalte füllen die volle Höhe mit gleichmäßigem Abstand',
		),
		'step-circle'         => array(
			'block'       => 'core/paragraph',
			'name'        => 'step-circle',
			'label'       => 'Step Circle',
			'type'        => 'variant',
			'description' => 'Zeigt den Absatz als nummerierte Kreisfläche (z.B. für Schritte)',
		),
		'vertical-center'     => array(
			'block'       => 'core/group',
			'name'        => 'vertical-center',
			'label'       => 'Vertikal zentriert',
			'type'        => 'variant',
			'description' => 'Zentriert den Inhalt vertikal per Flexbox',
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
	 * Creates missing directories and block.json files so the plugin
	 * is self-contained regardless of which version was deployed.
	 */
	private function ensure_default_blocks() {
		$blocks_dir = GUTENBLOCK_PRO_BLOCKS_PATH;

		if ( ! is_dir( $blocks_dir ) ) {
			wp_mkdir_p( $blocks_dir );
		}

		foreach ( self::$default_blocks as $slug => $config ) {
			$folder     = $blocks_dir . $slug;
			$config_file = $folder . '/block.json';

			if ( file_exists( $config_file ) ) {
				continue;
			}

			if ( ! is_dir( $folder ) ) {
				wp_mkdir_p( $folder );
			}

			file_put_contents(
				$config_file,
				wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"
			);
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

		// Fallback: Use defaults based on folder name
		// For checkmark-list variant
		if ( $slug === 'checkmark-list' ) {
			return array(
				'block'       => 'core/list',
				'name'        => 'checkmark-list',
				'label'       => __( 'Checkmark', 'gutenblock-pro' ),
				'type'        => 'variant',
				'description' => __( 'Zeigt Checkmarks (✓) statt Bullets für alle Listenelemente', 'gutenblock-pro' ),
			);
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

			// Register for FSE iframe support
			wp_enqueue_block_style(
				$config['block'],
				array(
					'handle' => 'gutenblock-pro-block-' . $slug,
					'src'    => GUTENBLOCK_PRO_URL . 'blocks/' . $slug . '/style.css',
					'ver'    => filemtime( $style_file ),
					'path'   => $style_file,
				)
			);

			$custom = gutenblock_pro_custom_block_file( $slug );
			if ( file_exists( $custom['path'] ) ) {
				wp_enqueue_block_style(
					$config['block'],
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
